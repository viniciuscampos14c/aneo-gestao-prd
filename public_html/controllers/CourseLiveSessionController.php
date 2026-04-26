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
            'course_id' => (int) get('course_id'),
            'status'    => get('status') ?: '',
        ];
        $page    = max(1, (int) (get('page') ?: 1));
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

        $message = 'Reuniao criada com sucesso! Meeting ID: ' . $meeting['meeting_id'];
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
        $id        = (int) get('id');

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
                $body = '<p>Ola, ' . e($studentName) . '.</p>'
                    . '<p>Uma nova aula ao vivo foi agendada no seu curso.</p>'
                    . '<p><strong>Curso:</strong> ' . e($courseName) . '<br>'
                    . '<strong>Titulo:</strong> ' . e($sessionTitle) . '<br>'
                    . '<strong>Data/Hora:</strong> ' . e($scheduledLabel) . '<br>'
                    . '<strong>Duracao:</strong> ' . e((string) $durationMin) . ' min</p>'
                    . '<p><strong>URL:</strong> <a href="' . e($joinUrl) . '" target="_blank" rel="noopener">' . e($joinUrl) . '</a><br>'
                    . '<strong>Meeting ID:</strong> ' . e($meetingId !== '' ? $meetingId : '-') . '<br>'
                    . '<strong>Senha:</strong> ' . e($meetingPassword !== '' ? $meetingPassword : '-') . '<br>'
                    . '<strong>Login Portal:</strong> ' . e($portalLogin !== '' ? $portalLogin : '-') . '</p>'
                    . '<p>Voce tambem pode consultar em: <strong>Portal do Aluno > Aulas ao Vivo</strong>.</p>';

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
}
