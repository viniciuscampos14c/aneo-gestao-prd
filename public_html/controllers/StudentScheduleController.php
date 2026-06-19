<?php

class StudentScheduleController extends BaseController
{
    private StudentScheduleModel $schedules;
    private StudentPortalModel $portal;
    private EmailService $emails;

    public function __construct()
    {
        $this->schedules = new StudentScheduleModel();
        $this->portal = new StudentPortalModel();
        $this->emails = new EmailService();
    }

    public function index(): void
    {
        require_auth();
        require_permission('student_schedule');

        $filters = [
            'q' => trim((string) request('q', '')),
            'unit_id' => trim((string) request('unit_id', '')),
            'status' => trim((string) request('status', '')),
        ];
        $perPage = (int) request('per_page', config('app.default_pagination', 50));
        if (!in_array($perPage, config('app.pagination_options', [50, 100, 200]), true)) {
            $perPage = 50;
        }
        $page = max(1, (int) request('page', 1));

        $result = $this->schedules->featureAvailable()
            ? $this->schedules->listSchedules($filters, $perPage, $page)
            : ['rows' => [], 'meta' => pagination_meta(0, $perPage, $page)];

        $this->render('student_schedule/index', [
            'title' => 'Escala Aluno',
            'featureAvailable' => $this->schedules->featureAvailable(),
            'units' => $this->schedules->featureAvailable() ? $this->schedules->listUnits() : [],
            'rows' => $result['rows'],
            'meta' => $result['meta'],
            'filters' => $filters,
            'paginationOptions' => config('app.pagination_options', [50, 100, 200]),
        ]);
    }

    public function create(): void
    {
        $this->requireScheduleManageAccess();

        $this->renderForm('Nova Escala', null);
    }

    public function store(): void
    {
        $this->requireScheduleManageAccess();
        csrf_validate();

        $this->ensureFeatureAvailable('escala-aluno');
        $data = $this->collectScheduleData();
        $error = $this->validateScheduleData($data);
        if ($error !== null) {
            $this->error($error);
            $this->redirect('escala-aluno/create');
        }

        $id = $this->schedules->createSchedule($data, (int) current_user()['id']);
        $this->success('Escala criada. Agora gere as semanas e faca as alocacoes.');
        $this->redirect('escala-aluno/show&id=' . $id);
    }

    public function edit(): void
    {
        $this->requireScheduleManageAccess();

        $schedule = $this->findScheduleOrRedirect((int) request('id'));
        if (!$schedule) {
            return;
        }

        if ((string) ($schedule['status'] ?? '') === 'archived') {
            $this->error('Escala encerrada. Desarquive para editar.');
            $this->redirect('escala-aluno/show&id=' . (int) $schedule['id']);
            return;
        }

        $this->renderForm('Editar Escala', $schedule);
    }

    public function update(): void
    {
        $this->requireScheduleManageAccess();
        csrf_validate();

        $schedule = $this->findScheduleOrRedirect((int) request('id'));
        if (!$schedule) {
            return;
        }

        if ((string) ($schedule['status'] ?? '') === 'archived') {
            $this->error('Escala encerrada. Desarquive para editar.');
            $this->redirect('escala-aluno/show&id=' . (int) $schedule['id']);
            return;
        }

        $data = $this->collectScheduleData();
        $error = $this->validateScheduleData($data);
        if ($error !== null) {
            $this->error($error);
            $this->redirect('escala-aluno/edit&id=' . (int) $schedule['id']);
        }

        $this->schedules->updateSchedule((int) $schedule['id'], $data);
        $this->success('Escala atualizada.');
        $this->redirect('escala-aluno/show&id=' . (int) $schedule['id']);
    }

    public function show(): void
    {
        require_auth();
        require_permission('student_schedule');

        $schedule = $this->findScheduleOrRedirect((int) request('id'));
        if (!$schedule) {
            return;
        }

        $weeksByMonth = $this->schedules->groupedWeeksForSchedule((int) $schedule['id']);
        $availableMonths = array_keys($weeksByMonth);
        $selectedMonth = strtoupper(trim((string) request('month_ref', '')));
        if ($selectedMonth !== '' && isset($weeksByMonth[$selectedMonth])) {
            $weeksByMonth = [$selectedMonth => $weeksByMonth[$selectedMonth]];
        } else {
            $selectedMonth = '';
        }

        $this->render('student_schedule/show', [
            'title' => 'Escala Aluno',
            'schedule' => $schedule,
            'featureAvailable' => $this->schedules->featureAvailable(),
            'weeksByMonth' => $weeksByMonth,
            'availableMonths' => $availableMonths,
            'selectedMonth' => $selectedMonth,
            'eligibleByWeek' => $this->buildEligibleByWeek((int) $schedule['id']),
            'statuses' => [
                'draft' => 'Rascunho',
                'published' => 'Publicada',
                'archived' => 'Arquivada',
            ],
        ]);
    }

    public function publish(): void
    {
        $this->requireScheduleManageAccess();
        csrf_validate();

        $schedule = $this->findScheduleOrRedirect((int) post('id'));
        if (!$schedule) {
            return;
        }

        $wasPublished = (string) ($schedule['status'] ?? '') === 'published';
        $this->schedules->setScheduleStatus((int) $schedule['id'], 'published');
        $notificationSummary = $this->notifyPublishedSchedule((int) $schedule['id']);
        $message = $wasPublished ? 'Avisos da escala republicados.' : 'Escala publicada.';
        if ($notificationSummary['portal_created'] > 0 || $notificationSummary['email_sent'] > 0) {
            $message .= sprintf(
                ' Avisos enviados: %d no portal e %d por e-mail.',
                $notificationSummary['portal_created'],
                $notificationSummary['email_sent']
            );
            if ($notificationSummary['email_failed'] > 0) {
                $message .= ' Alguns e-mails não puderam ser enviados.';
            }
        }
        $this->success($message);
        $this->redirect('escala-aluno/show&id=' . (int) $schedule['id']);
    }

    public function archive(): void
    {
        $this->requireScheduleManageAccess();
        csrf_validate();

        $schedule = $this->findScheduleOrRedirect((int) post('id'));
        if (!$schedule) {
            return;
        }

        $this->schedules->setScheduleStatus((int) $schedule['id'], 'archived');
        $this->success('Escala encerrada e bloqueada para edicao.');
        $this->redirect('escala-aluno/show&id=' . (int) $schedule['id']);
    }

    public function unarchive(): void
    {
        $this->requireScheduleManageAccess();
        csrf_validate();

        $schedule = $this->findScheduleOrRedirect((int) post('id'));
        if (!$schedule) {
            return;
        }

        $this->schedules->setScheduleStatus((int) $schedule['id'], 'draft');
        $this->success('Escala reaberta para edicao.');
        $this->redirect('escala-aluno/show&id=' . (int) $schedule['id']);
    }

    public function storeUnit(): void
    {
        $this->requireScheduleManageAccess();
        csrf_validate();

        $this->ensureFeatureAvailable('escala-aluno');

        $name = trim((string) post('name'));
        if ($name === '') {
            $this->error('Informe o nome da unidade/hospital.');
            $this->redirect('escala-aluno');
        }

        $this->schedules->createUnit([
            'name' => $name,
            'city' => trim((string) post('city')),
            'state' => trim((string) post('state')),
            'is_active' => 1,
        ], (int) current_user()['id']);

        $this->success('Unidade cadastrada.');
        $this->redirect('escala-aluno');
    }

    public function toggleUnit(): void
    {
        $this->requireScheduleManageAccess();
        csrf_validate();

        $this->schedules->toggleUnit((int) post('id'), (int) post('active', 1));
        $this->success('Status da unidade atualizado.');
        $this->redirect('escala-aluno');
    }

    public function generateWeeks(): void
    {
        $this->requireScheduleManageAccess();
        csrf_validate();

        $schedule = $this->findScheduleOrRedirect((int) post('schedule_id'));
        if (!$schedule) {
            return;
        }

        if ((string) ($schedule['status'] ?? '') === 'archived') {
            $this->error('Escala encerrada. Desarquive para fazer manutencoes.');
            $this->redirect('escala-aluno/show&id=' . (int) $schedule['id']);
            return;
        }

        $r3Slots = max(0, (int) post('r3_slots', 1));
        $r2Slots = max(0, (int) post('r2_slots', 1));
        $r1Slots = max(0, (int) post('r1_slots', 2));

        if ($r3Slots + $r2Slots + $r1Slots <= 0) {
            $this->error('Defina ao menos uma vaga por semana.');
            $this->redirect('escala-aluno/show&id=' . (int) $schedule['id']);
            return;
        }

        $weeks = [];
        $cursor = new DateTimeImmutable((string) $schedule['start_date']);
        $endDate = new DateTimeImmutable((string) $schedule['end_date']);
        $weekOrder = 1;

        while ($cursor <= $endDate) {
            $weekEnd = $cursor->modify('+6 days');
            if ($weekEnd > $endDate) {
                $weekEnd = $endDate;
            }

            $weeks[] = [
                'month_ref' => $this->schedules->formatMonthRef($cursor->format('Y-m-d')),
                'week_order' => $weekOrder++,
                'start_date' => $cursor->format('Y-m-d'),
                'end_date' => $weekEnd->format('Y-m-d'),
                'r3_slots' => $r3Slots,
                'r2_slots' => $r2Slots,
                'r1_slots' => $r1Slots,
                'notes' => '',
            ];

            $cursor = $weekEnd->modify('+1 day');
        }

        try {
            $summary = $this->schedules->syncWeeksPreservingAssignments((int) $schedule['id'], $weeks);
            $message = sprintf(
                'Grade atualizada preservando alocacoes. Semanas criadas: %d. Semanas atualizadas: %d.',
                (int) ($summary['created'] ?? 0),
                (int) ($summary['updated'] ?? 0)
            );
            if ((int) ($summary['deleted_empty'] ?? 0) > 0) {
                $message .= ' Semanas vazias removidas: ' . (int) $summary['deleted_empty'] . '.';
            }
            if ((string) ($schedule['status'] ?? '') === 'published') {
                $message .= ' Use Republicar avisos se quiser reenviar comunicados.';
            }
            $this->success($message);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
        }
        $this->redirect('escala-aluno/show&id=' . (int) $schedule['id']);
    }

    public function updateWeek(): void
    {
        $this->requireScheduleManageAccess();
        csrf_validate();

        $weekId = (int) post('week_id');
        $week = $this->schedules->findWeek($weekId);
        if (!$week) {
            $this->error('Semana não encontrada.');
            $this->redirect('escala-aluno');
        }

        if ((string) ($week['status'] ?? '') === 'archived') {
            $this->error('Escala encerrada. Desarquive para editar semanas.');
            $this->redirect('escala-aluno/show&id=' . (int) $week['schedule_id']);
            return;
        }

        $data = [
            'r3_slots' => max(0, (int) post('r3_slots', 0)),
            'r2_slots' => max(0, (int) post('r2_slots', 0)),
            'r1_slots' => max(0, (int) post('r1_slots', 0)),
            'notes' => trim((string) post('notes')),
        ];

        $assigned = $this->schedules->assignmentCountsForWeek($weekId);
        foreach (['R3' => 'r3_slots', 'R2' => 'r2_slots', 'R1' => 'r1_slots'] as $group => $field) {
            if ($data[$field] < ($assigned[$group] ?? 0)) {
                $this->error(sprintf(
                    'Não é possível reduzir %s para %d vaga(s), pois já existem %d aluno(s) alocados. Remova alunos antes de reduzir.',
                    $group,
                    $data[$field],
                    (int) ($assigned[$group] ?? 0)
                ));
                $this->redirect('escala-aluno/show&id=' . (int) $week['schedule_id']);
                return;
            }
        }

        $this->schedules->updateWeek($weekId, $data);

        $message = 'Semana atualizada.';
        if ((string) ($week['status'] ?? '') === 'published') {
            $message .= ' A escala publicada já reflete a alteração no portal; use Republicar avisos se quiser reenviar comunicados.';
        }
        $this->success($message);
        $this->redirect('escala-aluno/show&id=' . (int) $week['schedule_id']);
    }

    public function storeAssignment(): void
    {
        $this->requireScheduleManageAccess();
        csrf_validate();

        $weekId = (int) post('week_id');
        $studentId = (int) post('student_id');
        $slotGroup = strtoupper(trim((string) post('slot_group')));

        $week = $this->schedules->findWeek($weekId);
        if (!$week) {
            $this->error('Semana da escala não encontrada.');
            $this->redirect('escala-aluno');
        }

        try {
            $this->schedules->createAssignment($weekId, $studentId, $slotGroup, (int) current_user()['id']);
            $message = 'Aluno escalado com sucesso.';
            if ((string) ($week['status'] ?? '') === 'published') {
                $notificationResult = $this->notifyScheduledStudent($weekId, $studentId, $slotGroup);
                if ($notificationResult['email_failed']) {
                    $message .= ' A notificação apareceu no portal, mas o e-mail não foi enviado.';
                } elseif ($notificationResult['email_sent']) {
                    $message .= ' Notificacao enviada no portal e por e-mail.';
                } elseif ($notificationResult['portal_created']) {
                    $message .= ' Notificacao criada no portal do aluno.';
                }
            } else {
                $message .= ' Como a escala ainda esta em rascunho, o aluno não foi notificado.';
            }
            $this->success($message);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
        }

        $this->redirect('escala-aluno/show&id=' . (int) $week['schedule_id']);
    }

    public function deleteAssignment(): void
    {
        $this->requireScheduleManageAccess();
        csrf_validate();

        $week = $this->schedules->findWeek((int) post('week_id'));
        if (!$week) {
            $this->error('Semana da escala não encontrada.');
            $this->redirect('escala-aluno');
        }

        if ((string) ($week['status'] ?? '') === 'archived') {
            $this->error('Escala encerrada. Desarquive para remover alocacoes.');
            $this->redirect('escala-aluno/show&id=' . (int) $week['schedule_id']);
            return;
        }

        $this->schedules->deleteAssignment((int) post('assignment_id'));
        $message = 'Aluno removido da semana.';
        if ((string) ($week['status'] ?? '') === 'published') {
            $message .= ' O portal do aluno já foi atualizado; use Republicar avisos se quiser reenviar comunicados da escala.';
        }
        $this->success($message);
        $this->redirect('escala-aluno/show&id=' . (int) $week['schedule_id']);
    }

    public function export(): void
    {
        require_auth();
        require_permission('student_schedule.export');

        $schedule = $this->findScheduleOrRedirect((int) request('id'));
        if (!$schedule) {
            return;
        }

        $this->render('student_schedule/export', [
            'title' => 'Exportar Escala',
            'schedule' => $schedule,
            'weeksByMonth' => $this->schedules->groupedWeeksForSchedule((int) $schedule['id']),
            'isPrintView' => true,
        ], 'layouts/print');
    }

    private function requireScheduleManageAccess(): void
    {
        require_auth();
        require_permission('student_schedule.manage');

        if (is_professor()) {
            $this->error('Perfil professor possui acesso somente de visualizacao nesta area.');
            $this->redirect('escala-aluno');
        }
    }

    private function renderForm(string $title, ?array $schedule): void
    {
        $this->render('student_schedule/form', [
            'title' => $title,
            'schedule' => $schedule,
            'featureAvailable' => $this->schedules->featureAvailable(),
            'units' => $this->schedules->featureAvailable() ? $this->schedules->listUnits() : [],
            'action' => $schedule
                ? route('escala-aluno/update&id=' . (int) $schedule['id'])
                : route('escala-aluno/store'),
        ]);
    }

    private function collectScheduleData(): array
    {
        return [
            'unit_id' => (int) post('unit_id'),
            'title' => trim((string) post('title')),
            'start_date' => trim((string) post('start_date')),
            'end_date' => trim((string) post('end_date')),
            'status' => trim((string) post('status', 'draft')),
            'notes' => trim((string) post('notes')),
        ];
    }

    private function validateScheduleData(array $data): ?string
    {
        if ($data['unit_id'] <= 0) {
            return 'Selecione a unidade/hospital da escala.';
        }

        if ($data['title'] === '') {
            return 'Informe o título da escala.';
        }

        if ($data['start_date'] === '' || $data['end_date'] === '') {
            return 'Informe a data inicial e final da escala.';
        }

        if ($data['end_date'] < $data['start_date']) {
            return 'A data final não pode ser menor que a data inicial.';
        }

        return null;
    }

    private function ensureFeatureAvailable(string $redirectRoute): void
    {
        if ($this->schedules->featureAvailable()) {
            return;
        }

        $this->error('Módulo Escala Aluno indisponivel no banco. Execute a migration 20260505_student_duty_schedule.sql.');
        $this->redirect($redirectRoute);
    }

    private function findScheduleOrRedirect(int $id): ?array
    {
        $this->ensureFeatureAvailable('escala-aluno');
        $schedule = $this->schedules->findSchedule($id);
        if (!$schedule) {
            $this->error('Escala não encontrada.');
            $this->redirect('escala-aluno');
            return null;
        }
        return $schedule;
    }

    private function buildEligibleByWeek(int $scheduleId): array
    {
        $eligibleByWeek = [];
        foreach ($this->schedules->listWeeks($scheduleId) as $week) {
            $week['unit_id'] = (int) ($this->schedules->findWeek((int) $week['id'])['unit_id'] ?? 0);
            $eligibleByWeek[(int) $week['id']] = $this->schedules->listEligibleStudentsForWeek($week);
        }
        return $eligibleByWeek;
    }

    private function notifyScheduledStudent(int $weekId, int $studentId, string $slotGroup): array
    {
        $context = $this->schedules->assignmentNotificationContext($weekId, $studentId);
        if (!$context || (string) ($context['schedule_status'] ?? '') !== 'published') {
            return [
                'portal_created' => false,
                'email_sent' => false,
                'email_failed' => false,
            ];
        }

        $slotGroup = strtoupper(trim($slotGroup));
        $weekRange = $this->formatWeekPeriod((string) ($context['week_start_date'] ?? ''), (string) ($context['week_end_date'] ?? ''));
        $unitName = trim((string) ($context['unit_name'] ?? 'Unidade'));
        $scheduleTitle = trim((string) ($context['schedule_title'] ?? 'Nova escala'));
        $studentName = trim((string) ($context['student_name'] ?? 'Aluno'));

        $portalCreated = false;
        if ($this->portal->studentPortalNotificationsFeatureAvailable()) {
            $portalCreated = $this->portal->createPortalNotification([
                'company_id' => (int) ($context['company_id'] ?? 0),
                'student_id' => (int) ($context['student_id'] ?? 0),
                'notification_type' => 'duty_schedule',
                'title' => 'Voce foi escalado(a) em ' . $unitName,
                'message' => sprintf(
                    'Sua alocacao foi registrada em %s para a semana %s, na coluna %s.',
                    $scheduleTitle,
                    $weekRange,
                    $slotGroup
                ),
                'link_url' => route('student/schedule'),
                'meta' => [
                    'schedule_id' => (int) ($context['schedule_id'] ?? 0),
                    'week_id' => (int) ($context['week_id'] ?? 0),
                    'slot_group' => $slotGroup,
                    'unit_name' => $unitName,
                ],
            ]) > 0;
        }

        $emailSent = false;
        $emailFailed = false;
        $studentEmail = trim((string) ($context['student_email'] ?? ''));
        if ($studentEmail !== '' && filter_var($studentEmail, FILTER_VALIDATE_EMAIL)) {
            $result = $this->emails->send(
                $studentEmail,
                'Nova escala publicada para você | ' . $unitName,
                $this->buildScheduleAssignmentEmailHtml(
                    $studentName,
                    $scheduleTitle,
                    $unitName,
                    $weekRange,
                    $slotGroup,
                    (string) ($context['week_notes'] ?? '')
                ),
                [
                    'company_id' => (int) ($context['company_id'] ?? 0),
                    'is_html' => true,
                ]
            );
            $emailSent = !empty($result['ok']);
            $emailFailed = !$emailSent;
        }

        return [
            'portal_created' => $portalCreated,
            'email_sent' => $emailSent,
            'email_failed' => $emailFailed,
        ];
    }

    private function notifyPublishedSchedule(int $scheduleId): array
    {
        $summary = [
            'portal_created' => 0,
            'email_sent' => 0,
            'email_failed' => 0,
        ];

        foreach ($this->schedules->listAssignmentNotificationRows($scheduleId) as $row) {
            $result = $this->notifyPublishedAssignmentRow($row);
            $summary['portal_created'] += $result['portal_created'] ? 1 : 0;
            $summary['email_sent'] += $result['email_sent'] ? 1 : 0;
            $summary['email_failed'] += $result['email_failed'] ? 1 : 0;
        }

        return $summary;
    }

    private function notifyPublishedAssignmentRow(array $row): array
    {
        $slotGroup = strtoupper(trim((string) ($row['slot_group'] ?? 'R1')));
        $weekRange = $this->formatWeekPeriod((string) ($row['week_start_date'] ?? ''), (string) ($row['week_end_date'] ?? ''));
        $unitName = trim((string) ($row['unit_name'] ?? 'Unidade'));
        $scheduleTitle = trim((string) ($row['schedule_title'] ?? 'Nova escala'));
        $studentName = trim((string) ($row['student_name'] ?? 'Aluno'));

        $portalCreated = false;
        if ($this->portal->studentPortalNotificationsFeatureAvailable()) {
            $portalCreated = $this->portal->createPortalNotification([
                'company_id' => (int) ($row['company_id'] ?? 0),
                'student_id' => (int) ($row['student_id'] ?? 0),
                'notification_type' => 'duty_schedule',
                'title' => 'Voce foi escalado(a) em ' . $unitName,
                'message' => sprintf(
                    'Sua alocacao foi registrada em %s para a semana %s, na coluna %s.',
                    $scheduleTitle,
                    $weekRange,
                    $slotGroup
                ),
                'link_url' => route('student/schedule'),
                'meta' => [
                    'schedule_id' => (int) ($row['schedule_id'] ?? 0),
                    'week_id' => (int) ($row['week_id'] ?? 0),
                    'slot_group' => $slotGroup,
                    'unit_name' => $unitName,
                ],
            ]) > 0;
        }

        $emailSent = false;
        $emailFailed = false;
        $studentEmail = trim((string) ($row['student_email'] ?? ''));
        if ($studentEmail !== '' && filter_var($studentEmail, FILTER_VALIDATE_EMAIL)) {
            $result = $this->emails->send(
                $studentEmail,
                'Nova escala publicada para você | ' . $unitName,
                $this->buildScheduleAssignmentEmailHtml(
                    $studentName,
                    $scheduleTitle,
                    $unitName,
                    $weekRange,
                    $slotGroup,
                    (string) ($row['week_notes'] ?? '')
                ),
                [
                    'company_id' => (int) ($row['company_id'] ?? 0),
                    'is_html' => true,
                ]
            );
            $emailSent = !empty($result['ok']);
            $emailFailed = !$emailSent;
        }

        return [
            'portal_created' => $portalCreated,
            'email_sent' => $emailSent,
            'email_failed' => $emailFailed,
        ];
    }

    private function buildScheduleAssignmentEmailHtml(
        string $studentName,
        string $scheduleTitle,
        string $unitName,
        string $weekRange,
        string $slotGroup,
        string $weekNotes
    ): string {
        $rawWeekNotes = trim($weekNotes);
        $studentName = e($studentName);
        $scheduleTitle = e($scheduleTitle);
        $unitName = e($unitName);
        $weekRange = e($weekRange);
        $slotGroup = e($slotGroup);
        $weekNotesHtml = $rawWeekNotes !== ''
            ? '<tr><td style="padding:14px 0 0 0;"><div style="border-left:4px solid #0ea5e9;background-color:#eff8ff;border-radius:10px;padding:12px 14px;color:#334155;font-size:14px;line-height:1.55;"><strong style="color:#0f2f4a;">Observacao da semana:</strong><br>' . nl2br(e($rawWeekNotes)) . '</div></td></tr>'
            : '';
        $scheduleUrl = e($this->absoluteUrl(route('student/schedule')));

        return <<<HTML
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova alocacao de escala</title>
</head>
<body style="margin:0;padding:0;background-color:#eef5fb;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <span style="display:none!important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;overflow:hidden;mso-hide:all;">Sua escala foi atualizada no portal do aluno.</span>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background-color:#eef5fb;margin:0;padding:0;">
        <tr>
            <td align="center" style="padding:28px 14px;">
                <table role="presentation" width="640" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:640px;border-collapse:separate;">
                    <tr>
                        <td style="background-color:#082f49;border-radius:18px 18px 0 0;padding:28px 30px 26px 30px;">
                            <p style="margin:0 0 8px 0;font-size:12px;line-height:1.2;letter-spacing:3px;text-transform:uppercase;color:#67e8f9;font-weight:700;">ANEO</p>
                            <h1 style="margin:0;font-size:26px;line-height:1.25;color:#ffffff;font-weight:700;">Voce foi escalado(a)</h1>
                            <p style="margin:14px 0 0 0;font-size:15px;line-height:1.6;color:#d9efff;">Olá, {$studentName}. Sua escala foi atualizada e já esta disponível no portal do aluno.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#ffffff;border-right:1px solid #d7e4ef;border-left:1px solid #d7e4ef;padding:26px 30px 22px 30px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td style="padding:0 0 18px 0;border-bottom:1px solid #e5eef6;">
                                        <p style="margin:0 0 6px 0;font-size:12px;line-height:1.2;letter-spacing:2px;text-transform:uppercase;color:#0284c7;font-weight:700;">Nova alocacao</p>
                                        <h2 style="margin:0;font-size:20px;line-height:1.3;color:#0f172a;font-weight:700;">{$scheduleTitle}</h2>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:18px 0 0 0;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td width="48%" valign="top" style="padding:0 10px 14px 0;">
                                                    <p style="margin:0 0 4px 0;font-size:11px;line-height:1.2;color:#64748b;text-transform:uppercase;letter-spacing:1px;font-weight:700;">Unidade/Hospital</p>
                                                    <p style="margin:0;font-size:15px;line-height:1.45;color:#0f172a;font-weight:700;">{$unitName}</p>
                                                </td>
                                                <td width="52%" valign="top" style="padding:0 0 14px 10px;">
                                                    <p style="margin:0 0 4px 0;font-size:11px;line-height:1.2;color:#64748b;text-transform:uppercase;letter-spacing:1px;font-weight:700;">Semana</p>
                                                    <p style="margin:0;font-size:15px;line-height:1.45;color:#0f172a;font-weight:700;">{$weekRange}</p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td valign="top" style="padding:0 10px 0 0;">
                                                    <p style="margin:0 0 4px 0;font-size:11px;line-height:1.2;color:#64748b;text-transform:uppercase;letter-spacing:1px;font-weight:700;">Coluna</p>
                                                    <p style="margin:0;"><span style="display:inline-block;background-color:#e0f2fe;border:1px solid #7dd3fc;border-radius:999px;padding:7px 13px;font-size:14px;line-height:1;color:#075985;font-weight:700;">{$slotGroup}</span></p>
                                                </td>
                                                <td valign="top" style="padding:0 0 0 10px;">
                                                    <p style="margin:0 0 4px 0;font-size:11px;line-height:1.2;color:#64748b;text-transform:uppercase;letter-spacing:1px;font-weight:700;">Status</p>
                                                    <p style="margin:0;"><span style="display:inline-block;background-color:#dcfce7;border:1px solid #86efac;border-radius:999px;padding:7px 13px;font-size:14px;line-height:1;color:#166534;font-weight:700;">Publicada</span></p>
                                                </td>
                                            </tr>
                                            {$weekNotesHtml}
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:24px 0 0 0;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td bgcolor="#0284c7" style="border-radius:12px;">
                                                    <a href="{$scheduleUrl}" target="_blank" rel="noopener" style="display:inline-block;padding:13px 20px;border-radius:12px;font-size:14px;line-height:1.2;color:#ffffff;text-decoration:none;font-weight:700;background-color:#0284c7;">Abrir Minha Escala</a>
                                                </td>
                                            </tr>
                                        </table>
                                        <p style="margin:14px 0 0 0;font-size:12px;line-height:1.5;color:#64748b;">Se o botão não abrir, copie e cole este endereço no navegador:<br><a href="{$scheduleUrl}" style="color:#0284c7;text-decoration:underline;word-break:break-all;">{$scheduleUrl}</a></p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#f8fbfe;border:1px solid #d7e4ef;border-top:0;border-radius:0 0 18px 18px;padding:18px 30px;">
                            <p style="margin:0;font-size:12px;line-height:1.5;color:#64748b;">Mensagem automática do sistema ANEO. Consulte o portal do aluno para confirmar todos os detalhes da sua escala.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    private function absoluteUrl(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            $path = route('');
        }

        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        $base = trim((string) config('app.public_url', ''));
        if ($base === '') {
            $base = trim((string) config('app.base_url', ''));
        }

        if ($base === '') {
            $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            $scheme = $isHttps ? 'https' : 'http';
            $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $scriptDir = dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
            $scriptDir = str_replace('\\', '/', $scriptDir);
            $scriptDir = ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '\\') ? '' : rtrim($scriptDir, '/');
            $base = $scheme . '://' . $host . $scriptDir;
        }

        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }

    private function formatWeekPeriod(string $startDate, string $endDate): string
    {
        if ($startDate === '' || $endDate === '') {
            return 'periodo informado na escala';
        }

        return date('d/m/Y', strtotime($startDate)) . ' a ' . date('d/m/Y', strtotime($endDate));
    }
}
