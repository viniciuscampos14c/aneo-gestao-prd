<?php

class CourseLiveSessionController extends BaseController
{
    private CourseLiveSessionModel $model;

    public function __construct()
    {
        $this->model = new CourseLiveSessionModel();
    }

    // -------------------------------------------------------------------------
    // Listagem
    // -------------------------------------------------------------------------

    public function index(): void
    {
        require_auth();
        require_permission('courses');

        $companyId = (int) current_company_id();
        $filters   = [
            'course_id' => (int) request('course_id'),
            'status'    => request('status') ?: '',
        ];
        $page    = max(1, (int) (request('page') ?: 1));
        $perPage = 20;

        $sessions = $this->model->list($companyId, $filters, $perPage, $page);
        $courses  = $this->model->listCourseOptions($companyId);

        $zoomConfigured = $this->model->getZoomCredentials($companyId) !== null;

        $this->render('courses/live_sessions/index', [
            'sessions'       => $sessions,
            'courses'        => $courses,
            'filters'        => $filters,
            'zoomConfigured' => $zoomConfigured,
        ]);
    }

    // -------------------------------------------------------------------------
    // Formulário criar
    // -------------------------------------------------------------------------

    public function create(): void
    {
        require_auth();
        require_permission('courses');

        $companyId      = (int) current_company_id();
        $courses        = $this->model->listCourseOptions($companyId);
        $zoomConfigured = $this->model->getZoomCredentials($companyId) !== null;

        $this->render('courses/live_sessions/form', [
            'session'        => null,
            'courses'        => $courses,
            'zoomConfigured' => $zoomConfigured,
        ]);
    }

    // -------------------------------------------------------------------------
    // Criar reunião (POST)
    // -------------------------------------------------------------------------

    public function store(): void
    {
        require_auth();
        require_permission('courses');
        csrf_validate();

        $companyId  = (int) current_company_id();
        $user       = current_user();
        $userId     = (int) ($user['id'] ?? 0);
        $redirectTo = trim((string) post('redirect_to')); // destino após criar (ex: courses/edit?id=5)

        $title       = trim((string) post('title'));
        $courseId    = (int) post('course_id');
        $scheduledAt = trim((string) post('scheduled_at'));
        $durationMin = max(15, min(480, (int) post('duration_minutes')));
        $notes       = trim((string) post('notes'));
        $isGlobal    = post('is_global') === '1';

        // Normaliza datetime-local (substitui T por espaço)
        $scheduledAt = str_replace('T', ' ', $scheduledAt);

        $fallback = $redirectTo !== '' ? $redirectTo : 'courses/live-sessions';

        // Validação básica
        $errors = [];
        if ($title === '')       $errors[] = 'Título é obrigatório.';
        if ($courseId === 0)     $errors[] = 'Selecione um curso.';
        if ($scheduledAt === '') $errors[] = 'Data e horário são obrigatórios.';

        if ($errors) {
            flash('error', implode(' ', $errors));
            $this->redirect($fallback);
            return;
        }

        // Busca credenciais Zoom
        $creds = $this->model->getZoomCredentials($companyId);
        if ($creds === null) {
            flash('error', 'Configure as credenciais Zoom nas configurações da empresa antes de criar uma aula.');
            $this->redirect($fallback);
            return;
        }

        // Chama a API do Zoom
        try {
            $zoom    = new ZoomService($creds['zoom_account_id'], $creds['zoom_client_id'], $creds['zoom_client_secret']);
            $meeting = $zoom->createMeeting($title, $scheduledAt, $durationMin);
        } catch (RuntimeException $e) {
            flash('error', 'Erro ao criar reunião no Zoom: ' . $e->getMessage());
            $this->redirect('courses/live-sessions/create');
            return;
        }

        if ($isGlobal) {
            $targets = $this->model->equivalentPublishedCoursesForGlobal($courseId, $companyId);
            if ($targets === []) {
                flash('error', 'Reunião criada no Zoom, mas não encontramos cursos equivalentes publicados para vincular a aula global.');
                $this->redirect($fallback);
                return;
            }

            try {
                $createdSessions = $this->model->createGlobalCopies($targets, [
                    'title' => $title,
                    'zoom_meeting_id' => $meeting['meeting_id'],
                    'zoom_password' => $meeting['password'],
                    'join_url' => $meeting['join_url'],
                    'start_url' => $meeting['start_url'],
                    'scheduled_at' => $scheduledAt,
                    'duration_minutes' => $durationMin,
                    'notes' => $notes !== '' ? $notes : null,
                    'zoom_raw_response' => $meeting['raw'],
                    'created_by' => $userId,
                    'global_session_uuid' => $this->generateGlobalSessionUuid(),
                ]);
            } catch (Throwable $e) {
                flash('error', 'Reunião criada no Zoom, mas não foi possível salvar a aula global no ERP: ' . $e->getMessage());
                $this->redirect($fallback);
                return;
            }

            $notifySummary = ['total' => 0, 'sent' => 0, 'failed' => 0];
            foreach ($createdSessions as $createdSession) {
                $summary = $this->notifyEnrolledStudents(
                    (int) $createdSession['company_id'],
                    (int) $createdSession['course_id'],
                    $title,
                    $scheduledAt,
                    $durationMin,
                    $meeting
                );

                $notifySummary['total'] += (int) ($summary['total'] ?? 0);
                $notifySummary['sent'] += (int) ($summary['sent'] ?? 0);
                $notifySummary['failed'] += (int) ($summary['failed'] ?? 0);
            }

            $message = 'Aula global criada com sucesso! Meeting ID: ' . $meeting['meeting_id'];
            $message .= ' | Unidades vinculadas: ' . count($createdSessions) . '.';
            if (($notifySummary['total'] ?? 0) > 0) {
                $message .= ' | Alertas enviados: ' . (int) ($notifySummary['sent'] ?? 0) . '/' . (int) ($notifySummary['total'] ?? 0) . '.';
            }
            flash('success', $message);

            if (($notifySummary['failed'] ?? 0) > 0) {
                flash('error', 'Alguns alertas por e-mail falharam (' . (int) $notifySummary['failed'] . '). Verifique SMTP para envio automatico.');
            }

            $newId = (int) ($createdSessions[0]['session_id'] ?? 0);
            $dest = $redirectTo !== '' ? $redirectTo : 'courses/live-sessions?new_id=' . $newId;
            $this->redirect($dest);
            return;
        }

        // Salva no banco
        $id = $this->model->create([
            'company_id'        => $companyId,
            'course_id'         => $courseId,
            'title'             => $title,
            'zoom_meeting_id'   => $meeting['meeting_id'],
            'zoom_password'     => $meeting['password'],
            'join_url'          => $meeting['join_url'],
            'start_url'         => $meeting['start_url'],
            'scheduled_at'      => $scheduledAt,
            'duration_minutes'  => $durationMin,
            'notes'             => $notes !== '' ? $notes : null,
            'zoom_raw_response' => $meeting['raw'],
            'created_by'        => $userId,
        ]);

        if ($id === false) {
            flash('error', 'Reunião criada no Zoom mas não foi possível salvar no banco. Contate o suporte.');
            $this->redirect($fallback);
            return;
        }
        $notifySummary = $this->notifyEnrolledStudents(
            $companyId,
            $courseId,
            $title,
            $scheduledAt,
            $durationMin,
            $meeting
        );

        $message = 'Reunião criada com sucesso! Meeting ID: ' . $meeting['meeting_id'];
        if (($notifySummary['total'] ?? 0) > 0) {
            $message .= ' | Alertas enviados: ' . (int) ($notifySummary['sent'] ?? 0) . '/' . (int) ($notifySummary['total'] ?? 0) . '.';
        }
        flash('success', $message);

        if (($notifySummary['failed'] ?? 0) > 0) {
            flash('error', 'Alguns alertas por e-mail falharam (' . (int) $notifySummary['failed'] . '). Verifique SMTP para envio automatico.');
        }

        $dest = $redirectTo !== '' ? $redirectTo : 'courses/live-sessions?new_id=' . $id;
        $this->redirect($dest);
    }

    // -------------------------------------------------------------------------
    // Formulário editar
    // -------------------------------------------------------------------------

    public function edit(): void
    {
        require_auth();
        require_permission('courses');

        $companyId = (int) current_company_id();
        $id        = (int) request('id');

        $session = $this->model->findById($id, $companyId);
        if ($session === null) {
            flash('error', 'Aula não encontrada.');
            $this->redirect('courses/live-sessions');
            return;
        }

        $courses = $this->model->listCourseOptions($companyId);

        $this->render('courses/live_sessions/form', [
            'session' => $session,
            'courses' => $courses,
            'zoomConfigured' => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // Atualizar (POST)
    // -------------------------------------------------------------------------

    public function update(): void
    {
        require_auth();
        require_permission('courses');
        csrf_validate();

        $companyId      = (int) current_company_id();
        $id             = (int) post('id');
        $title          = trim((string) post('title'));
        $courseId       = (int) post('course_id');
        $scheduledAt    = str_replace('T', ' ', trim((string) post('scheduled_at')));
        $durationMin    = max(15, min(480, (int) post('duration_minutes')));
        $notes          = trim((string) post('notes'));

        $errors = [];
        if ($title === '')   $errors[] = 'Título é obrigatório.';
        if ($courseId === 0) $errors[] = 'Selecione um curso.';

        if ($errors) {
            flash('error', implode(' ', $errors));
            $this->redirect('courses/live-sessions/edit?id=' . $id);
            return;
        }

        $ok = $this->model->update($id, [
            'course_id'        => $courseId,
            'title'            => $title,
            'scheduled_at'     => $scheduledAt,
            'duration_minutes' => $durationMin,
            'notes'            => $notes !== '' ? $notes : null,
        ], $companyId);

        if ($ok) {
            flash('success', 'Aula atualizada com sucesso.');
        } else {
            flash('error', 'Não foi possível atualizar a aula.');
        }

        $this->redirect('courses/live-sessions');
    }

    // -------------------------------------------------------------------------
    // Cancelar reunião (POST)
    // -------------------------------------------------------------------------

    public function cancel(): void
    {
        require_auth();
        require_permission('courses');
        csrf_validate();

        $companyId  = (int) current_company_id();
        $id         = (int) post('id');
        $redirectTo = trim((string) post('redirect_to'));

        $session = $this->model->findById($id, $companyId);
        if ($session === null) {
            flash('error', 'Aula não encontrada.');
            $this->redirect('courses/live-sessions');
            return;
        }

        if (!empty($session['is_global']) && !empty($session['global_session_uuid'])) {
            if (!empty($session['zoom_meeting_id'])) {
                $creds = $this->model->getZoomCredentials($companyId);
                if ($creds !== null) {
                    try {
                        $zoom = new ZoomService($creds['zoom_account_id'], $creds['zoom_client_id'], $creds['zoom_client_secret']);
                        $zoom->deleteMeeting($session['zoom_meeting_id']);
                    } catch (RuntimeException $e) {
                        // Nao bloqueia o cancelamento local se o Zoom falhar.
                    }
                }
            }

            $affected = $this->model->cancelGlobal((string) $session['global_session_uuid']);
            if ($affected > 0) {
                flash('success', 'Aula global cancelada com sucesso em ' . $affected . ' unidade(s).');
            } else {
                flash('error', 'Não foi possível cancelar a aula global.');
            }

            $dest = $redirectTo !== '' ? $redirectTo : 'courses/live-sessions';
            $this->redirect($dest);
            return;
        }

        // Tenta deletar no Zoom se tiver meeting_id
        if (!empty($session['zoom_meeting_id'])) {
            $creds = $this->model->getZoomCredentials($companyId);
            if ($creds !== null) {
                try {
                    $zoom = new ZoomService($creds['zoom_account_id'], $creds['zoom_client_id'], $creds['zoom_client_secret']);
                    $zoom->deleteMeeting($session['zoom_meeting_id']);
                } catch (RuntimeException $e) {
                    // Não bloqueia o cancelamento local se o Zoom falhar
                    // (reunião pode já ter sido deletada manualmente no Zoom)
                }
            }
        }

        $ok = $this->model->cancel($id, $companyId);

        if ($ok) {
            flash('success', 'Aula cancelada com sucesso.');
        } else {
            flash('error', 'Não foi possível cancelar a aula.');
        }

        $dest = $redirectTo !== '' ? $redirectTo : 'courses/live-sessions';
        $this->redirect($dest);
    }

    // -------------------------------------------------------------------------
    // Salvar credenciais Zoom (POST)
    // -------------------------------------------------------------------------

    public function saveZoomCredentials(): void
    {
        require_auth();
        require_permission('companies');
        csrf_validate();

        $companyId    = (int) current_company_id();
        $accountId    = trim((string) post('zoom_account_id'));
        $clientId     = trim((string) post('zoom_client_id'));
        $clientSecret = trim((string) post('zoom_client_secret'));

        $ok = $this->model->saveZoomCredentials($companyId, $accountId, $clientId, $clientSecret);

        if ($ok) {
            flash('success', 'Credenciais Zoom salvas com sucesso.');
        } else {
            flash('error', 'Não foi possível salvar as credenciais Zoom.');
        }

        $this->redirect('courses/live-sessions/zoom-settings');
    }

    // -------------------------------------------------------------------------
    // Tela de configurações Zoom
    // -------------------------------------------------------------------------

    public function zoomSettings(): void
    {
        require_auth();
        require_permission('companies');

        $companyId = (int) current_company_id();
        $creds     = $this->model->getZoomCredentials($companyId) ?? [
            'zoom_account_id'    => '',
            'zoom_client_id'     => '',
            'zoom_client_secret' => '',
        ];

        $this->render('courses/live_sessions/zoom_settings', [
            'creds' => $creds,
        ]);
    }

    private function generateGlobalSessionUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private function notifyEnrolledStudents(
        int $companyId,
        int $courseId,
        string $sessionTitle,
        string $scheduledAt,
        int $durationMin,
        array $meeting
    ): array {
        $summary = [
            'total' => 0,
            'sent' => 0,
            'failed' => 0,
        ];

        try {
            $students = $this->model->enrolledStudentsForCourse($courseId, $companyId);
            if (!is_array($students) || $students === []) {
                return $summary;
            }

            $summary['total'] = count($students);
            $courseName = $this->model->findCourseName($courseId, $companyId) ?? ('Curso #' . $courseId);
            $meetingId = trim((string) ($meeting['meeting_id'] ?? ''));
            $meetingPassword = trim((string) ($meeting['password'] ?? ''));
            $joinUrl = trim((string) ($meeting['join_url'] ?? ''));

            $timezone = (string) config('app.timezone', 'America/Sao_Paulo');
            $scheduledLabel = $scheduledAt;
            try {
                $dt = new DateTimeImmutable($scheduledAt, new DateTimeZone($timezone !== '' ? $timezone : 'America/Sao_Paulo'));
                $scheduledLabel = $dt->format('d/m/Y H:i');
            } catch (Throwable $e) {
                // Mantem o valor original em caso de formato inesperado.
            }

            $emailService = new EmailService();

            foreach ($students as $student) {
                $to = strtolower(trim((string) ($student['email_primary'] ?? '')));
                if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    $summary['failed']++;
                    continue;
                }

                $studentName = trim((string) ($student['full_name'] ?? 'Aluno'));
                $portalLogin = trim((string) ($student['portal_login'] ?? ''));

                $subject = 'Nova aula ao vivo agendada | ' . $courseName;
                $body = $this->buildLiveClassEmailHtml([
                    'student_name' => $studentName,
                    'course_name' => $courseName,
                    'session_title' => $sessionTitle,
                    'scheduled_label' => $scheduledLabel,
                    'duration_label' => $this->formatDurationLabel($durationMin),
                    'join_url' => $joinUrl,
                    'meeting_id' => $meetingId,
                    'meeting_password' => $meetingPassword,
                    'portal_login' => $portalLogin,
                ]);

                $send = $emailService->send($to, $subject, $body, [
                    'company_id' => $companyId,
                    'is_html' => true,
                ]);

                if (!empty($send['ok'])) {
                    $summary['sent']++;
                } else {
                    $summary['failed']++;
                }
            }
        } catch (Throwable $e) {
            // Nao interrompe o fluxo de criacao da aula em caso de falha de notificacao.
        }

        return $summary;
    }

    private function buildLiveClassEmailHtml(array $payload): string
    {
        $studentName = trim((string) ($payload['student_name'] ?? 'Aluno'));
        $courseName = trim((string) ($payload['course_name'] ?? '-'));
        $sessionTitle = trim((string) ($payload['session_title'] ?? '-'));
        $scheduledLabel = trim((string) ($payload['scheduled_label'] ?? '-'));
        $durationLabel = trim((string) ($payload['duration_label'] ?? '-'));
        $joinUrl = trim((string) ($payload['join_url'] ?? ''));
        $meetingId = trim((string) ($payload['meeting_id'] ?? ''));
        $meetingPassword = trim((string) ($payload['meeting_password'] ?? ''));
        $portalLogin = trim((string) ($payload['portal_login'] ?? ''));

        $safeStudentName = e($studentName);
        $safeCourseName = e($courseName !== '' ? $courseName : '-');
        $safeSessionTitle = e($sessionTitle !== '' ? $sessionTitle : '-');
        $safeScheduledLabel = e($scheduledLabel !== '' ? $scheduledLabel : '-');
        $safeDurationLabel = e($durationLabel !== '' ? $durationLabel : '-');
        $safeMeetingId = e($meetingId !== '' ? $meetingId : '-');
        $safeMeetingPassword = e($meetingPassword !== '' ? $meetingPassword : '-');
        $safePortalLogin = e($portalLogin !== '' ? $portalLogin : '-');
        $safeJoinUrl = e($joinUrl);
        $publicBase = rtrim(trim((string) config('app.public_url', '')), '/');
        if ($publicBase === '') {
            $publicBase = rtrim(trim((string) config('app.base_url', '')), '/');
        }
        $logoUrl = $publicBase !== '' ? $publicBase . '/assets/brand/aneo-wordmark-transparente-branco.png?v=20260512-brand-kit-v1' : '';
        $safeLogoUrl = e($logoUrl);

        $ctaButton = '';
        $ctaFallback = '<p style="margin:14px 0 0 0;font-size:13px;color:#64748b;">Link da aula indisponivel no momento. Consulte o Portal do Aluno em <strong>Aulas ao Vivo</strong>.</p>';
        if ($joinUrl !== '') {
            $ctaButton = '<p style="margin:18px 0 0 0;">'
                . '<a href="' . $safeJoinUrl . '" target="_blank" rel="noopener" style="display:inline-block;padding:12px 18px;border-radius:10px;background:#0ea5e9;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;">Entrar na aula ao vivo</a>'
                . '</p>';
            $ctaFallback = '<p style="margin:12px 0 0 0;font-size:12px;color:#64748b;">Se o botão não abrir, copie e cole no navegador:<br>'
                . '<a href="' . $safeJoinUrl . '" target="_blank" rel="noopener" style="color:#0284c7;word-break:break-all;">' . $safeJoinUrl . '</a></p>';
        }

        return implode("\n", [
            '<!doctype html>',
            '<html lang="pt-BR">',
            '<head>',
            '<meta charset="UTF-8">',
            '<meta name="viewport" content="width=device-width, initial-scale=1.0">',
            '<title>Nova aula ao vivo agendada</title>',
            '</head>',
            '<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">',
            '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;background:#f1f5f9;">',
            '<tr><td align="center" style="padding:24px 12px;">',
            '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;border-collapse:separate;background:#ffffff;border:1px solid #dbe7f3;border-radius:16px;overflow:hidden;">',
            '<tr><td bgcolor="#0b2443" style="padding:22px 24px;background:#0b2443;">',
            ($logoUrl !== ''
                ? '<img src="' . $safeLogoUrl . '" alt="ANEO" height="44" style="display:inline-block;height:44px;width:auto;vertical-align:middle;">'
                : '<p style="margin:0;font-size:12px;font-weight:700;letter-spacing:0.08em;color:#bae6fd;text-transform:uppercase;">ANEO | Aulas ao Vivo</p>'),
            '<p style="margin:' . ($logoUrl !== '' ? '14px' : '8px') . ' 0 0 0;font-size:12px;font-weight:700;letter-spacing:0.08em;color:#bae6fd;text-transform:uppercase;">ANEO | Aulas ao Vivo</p>',
            '<h1 style="margin:8px 0 0 0;font-size:24px;line-height:1.3;color:#ffffff;">Nova aula ao vivo agendada</h1>',
            '</td></tr>',
            '<tr><td style="padding:24px;">',
            '<p style="margin:0 0 10px 0;font-size:15px;line-height:1.6;color:#1e293b;">Olá, <strong>' . $safeStudentName . '</strong>.</p>',
            '<p style="margin:0 0 16px 0;font-size:14px;line-height:1.6;color:#334155;">Uma nova aula foi criada no seu curso. Confira os dados abaixo:</p>',
            '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:separate;border-spacing:0;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;">',
            '<tr>',
            '<td style="padding:10px 14px;font-size:12px;font-weight:700;color:#334155;width:38%;border-bottom:1px solid #e2e8f0;">Curso</td>',
            '<td style="padding:10px 14px;font-size:13px;color:#0f172a;border-bottom:1px solid #e2e8f0;">' . $safeCourseName . '</td>',
            '</tr>',
            '<tr>',
            '<td style="padding:10px 14px;font-size:12px;font-weight:700;color:#334155;width:38%;border-bottom:1px solid #e2e8f0;">Título da aula</td>',
            '<td style="padding:10px 14px;font-size:13px;color:#0f172a;border-bottom:1px solid #e2e8f0;">' . $safeSessionTitle . '</td>',
            '</tr>',
            '<tr>',
            '<td style="padding:10px 14px;font-size:12px;font-weight:700;color:#334155;width:38%;border-bottom:1px solid #e2e8f0;">Data e horário</td>',
            '<td style="padding:10px 14px;font-size:13px;color:#0f172a;border-bottom:1px solid #e2e8f0;">' . $safeScheduledLabel . ' (Brasilia)</td>',
            '</tr>',
            '<tr>',
            '<td style="padding:10px 14px;font-size:12px;font-weight:700;color:#334155;width:38%;border-bottom:1px solid #e2e8f0;">Duração</td>',
            '<td style="padding:10px 14px;font-size:13px;color:#0f172a;border-bottom:1px solid #e2e8f0;">' . $safeDurationLabel . '</td>',
            '</tr>',
            '<tr>',
            '<td style="padding:10px 14px;font-size:12px;font-weight:700;color:#334155;width:38%;border-bottom:1px solid #e2e8f0;">Meeting ID</td>',
            '<td style="padding:10px 14px;font-size:13px;color:#0f172a;border-bottom:1px solid #e2e8f0;font-family:Consolas,Monaco,monospace;">' . $safeMeetingId . '</td>',
            '</tr>',
            '<tr>',
            '<td style="padding:10px 14px;font-size:12px;font-weight:700;color:#334155;width:38%;border-bottom:1px solid #e2e8f0;">Senha</td>',
            '<td style="padding:10px 14px;font-size:13px;color:#0f172a;border-bottom:1px solid #e2e8f0;font-family:Consolas,Monaco,monospace;">' . $safeMeetingPassword . '</td>',
            '</tr>',
            '<tr>',
            '<td style="padding:10px 14px;font-size:12px;font-weight:700;color:#334155;width:38%;">Login no portal</td>',
            '<td style="padding:10px 14px;font-size:13px;color:#0f172a;font-family:Consolas,Monaco,monospace;">' . $safePortalLogin . '</td>',
            '</tr>',
            '</table>',
            $ctaButton,
            $ctaFallback,
            '<p style="margin:16px 0 0 0;font-size:13px;line-height:1.6;color:#475569;">Voce também pode consultar em <strong>Portal do Aluno > Aulas ao Vivo</strong>.</p>',
            '<p style="margin:18px 0 0 0;font-size:11px;color:#94a3b8;">Mensagem automática do sistema ANEO. Não responda este e-mail.</p>',
            '</td></tr>',
            '</table>',
            '</td></tr>',
            '</table>',
            '</body>',
            '</html>',
        ]);
    }

    private function formatDurationLabel(int $durationMin): string
    {
        if ($durationMin <= 0) {
            return '-';
        }

        if ($durationMin < 60) {
            return $durationMin . ' min';
        }

        $hours = intdiv($durationMin, 60);
        $minutes = $durationMin % 60;
        if ($minutes === 0) {
            return $hours . 'h';
        }

        return $hours . 'h ' . $minutes . 'min';
    }
}
