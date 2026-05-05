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
        require_auth();
        require_permission('student_schedule.manage');

        $this->renderForm('Nova Escala', null);
    }

    public function store(): void
    {
        require_auth();
        require_permission('student_schedule.manage');
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
        require_auth();
        require_permission('student_schedule.manage');

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
        require_auth();
        require_permission('student_schedule.manage');
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
        require_auth();
        require_permission('student_schedule.manage');
        csrf_validate();

        $schedule = $this->findScheduleOrRedirect((int) post('id'));
        if (!$schedule) {
            return;
        }

        $this->schedules->setScheduleStatus((int) $schedule['id'], 'published');
        $notificationSummary = $this->notifyPublishedSchedule((int) $schedule['id']);
        $message = 'Escala publicada.';
        if ($notificationSummary['portal_created'] > 0 || $notificationSummary['email_sent'] > 0) {
            $message .= sprintf(
                ' Avisos enviados: %d no portal e %d por e-mail.',
                $notificationSummary['portal_created'],
                $notificationSummary['email_sent']
            );
            if ($notificationSummary['email_failed'] > 0) {
                $message .= ' Alguns e-mails nao puderam ser enviados.';
            }
        }
        $this->success($message);
        $this->redirect('escala-aluno/show&id=' . (int) $schedule['id']);
    }

    public function archive(): void
    {
        require_auth();
        require_permission('student_schedule.manage');
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
        require_auth();
        require_permission('student_schedule.manage');
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
        require_auth();
        require_permission('student_schedule.manage');
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
        require_auth();
        require_permission('student_schedule.manage');
        csrf_validate();

        $this->schedules->toggleUnit((int) post('id'), (int) post('active', 1));
        $this->success('Status da unidade atualizado.');
        $this->redirect('escala-aluno');
    }

    public function generateWeeks(): void
    {
        require_auth();
        require_permission('student_schedule.manage');
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

        $this->schedules->replaceWeeks((int) $schedule['id'], $weeks);
        $this->success('Semanas geradas com sucesso. Alocacoes anteriores foram substituidas.');
        $this->redirect('escala-aluno/show&id=' . (int) $schedule['id']);
    }

    public function updateWeek(): void
    {
        require_auth();
        require_permission('student_schedule.manage');
        csrf_validate();

        $weekId = (int) post('week_id');
        $week = $this->schedules->findWeek($weekId);
        if (!$week) {
            $this->error('Semana nao encontrada.');
            $this->redirect('escala-aluno');
        }

        if ((string) ($week['status'] ?? '') === 'archived') {
            $this->error('Escala encerrada. Desarquive para editar semanas.');
            $this->redirect('escala-aluno/show&id=' . (int) $week['schedule_id']);
            return;
        }

        $this->schedules->updateWeek($weekId, [
            'r3_slots' => max(0, (int) post('r3_slots', 0)),
            'r2_slots' => max(0, (int) post('r2_slots', 0)),
            'r1_slots' => max(0, (int) post('r1_slots', 0)),
            'notes' => trim((string) post('notes')),
        ]);

        $this->success('Semana atualizada.');
        $this->redirect('escala-aluno/show&id=' . (int) $week['schedule_id']);
    }

    public function storeAssignment(): void
    {
        require_auth();
        require_permission('student_schedule.manage');
        csrf_validate();

        $weekId = (int) post('week_id');
        $studentId = (int) post('student_id');
        $slotGroup = strtoupper(trim((string) post('slot_group')));

        $week = $this->schedules->findWeek($weekId);
        if (!$week) {
            $this->error('Semana da escala nao encontrada.');
            $this->redirect('escala-aluno');
        }

        try {
            $this->schedules->createAssignment($weekId, $studentId, $slotGroup, (int) current_user()['id']);
            $message = 'Aluno escalado com sucesso.';
            if ((string) ($week['status'] ?? '') === 'published') {
                $notificationResult = $this->notifyScheduledStudent($weekId, $studentId, $slotGroup);
                if ($notificationResult['email_failed']) {
                    $message .= ' A notificacao apareceu no portal, mas o e-mail nao foi enviado.';
                } elseif ($notificationResult['email_sent']) {
                    $message .= ' Notificacao enviada no portal e por e-mail.';
                } elseif ($notificationResult['portal_created']) {
                    $message .= ' Notificacao criada no portal do aluno.';
                }
            } else {
                $message .= ' Como a escala ainda esta em rascunho, o aluno nao foi notificado.';
            }
            $this->success($message);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
        }

        $this->redirect('escala-aluno/show&id=' . (int) $week['schedule_id']);
    }

    public function deleteAssignment(): void
    {
        require_auth();
        require_permission('student_schedule.manage');
        csrf_validate();

        $week = $this->schedules->findWeek((int) post('week_id'));
        if (!$week) {
            $this->error('Semana da escala nao encontrada.');
            $this->redirect('escala-aluno');
        }

        if ((string) ($week['status'] ?? '') === 'archived') {
            $this->error('Escala encerrada. Desarquive para remover alocacoes.');
            $this->redirect('escala-aluno/show&id=' . (int) $week['schedule_id']);
            return;
        }

        $this->schedules->deleteAssignment((int) post('assignment_id'));
        $this->success('Aluno removido da semana.');
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
            return 'Informe o titulo da escala.';
        }

        if ($data['start_date'] === '' || $data['end_date'] === '') {
            return 'Informe a data inicial e final da escala.';
        }

        if ($data['end_date'] < $data['start_date']) {
            return 'A data final nao pode ser menor que a data inicial.';
        }

        return null;
    }

    private function ensureFeatureAvailable(string $redirectRoute): void
    {
        if ($this->schedules->featureAvailable()) {
            return;
        }

        $this->error('Modulo Escala Aluno indisponivel no banco. Execute a migration 20260505_student_duty_schedule.sql.');
        $this->redirect($redirectRoute);
    }

    private function findScheduleOrRedirect(int $id): ?array
    {
        $this->ensureFeatureAvailable('escala-aluno');
        $schedule = $this->schedules->findSchedule($id);
        if (!$schedule) {
            $this->error('Escala nao encontrada.');
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
                'Nova escala publicada para voce | ' . $unitName,
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
                'Nova escala publicada para voce | ' . $unitName,
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
        $studentName = e($studentName);
        $scheduleTitle = e($scheduleTitle);
        $unitName = e($unitName);
        $weekRange = e($weekRange);
        $slotGroup = e($slotGroup);
        $weekNotesHtml = trim($weekNotes) !== ''
            ? '<p style="margin:16px 0 0;color:#475569;font-size:14px;"><strong>Observacao da semana:</strong> ' . nl2br(e($weekNotes)) . '</p>'
            : '';
        $scheduleUrl = e(route('student/schedule'));

        return <<<HTML
<!doctype html>
<html lang="pt-BR">
<body style="margin:0;background:#f8fafc;font-family:Arial,sans-serif;color:#0f172a;">
    <div style="max-width:640px;margin:0 auto;padding:24px;">
        <div style="background:linear-gradient(135deg,#0f172a,#0f766e);border-radius:18px;padding:28px;color:#fff;">
            <p style="margin:0 0 8px;font-size:12px;letter-spacing:.18em;text-transform:uppercase;color:#67e8f9;">ANEO</p>
            <h1 style="margin:0;font-size:26px;line-height:1.2;">Nova alocacao de escala</h1>
            <p style="margin:14px 0 0;font-size:15px;line-height:1.6;color:#dbeafe;">Ola, {$studentName}. Sua escala foi atualizada e ja esta disponivel no portal do aluno.</p>
        </div>
        <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:18px;padding:24px;margin-top:18px;">
            <p style="margin:0 0 12px;font-size:16px;font-weight:700;color:#0f172a;">{$scheduleTitle}</p>
            <p style="margin:0 0 8px;color:#334155;font-size:14px;"><strong>Unidade/Hospital:</strong> {$unitName}</p>
            <p style="margin:0 0 8px;color:#334155;font-size:14px;"><strong>Semana:</strong> {$weekRange}</p>
            <p style="margin:0 0 8px;color:#334155;font-size:14px;"><strong>Coluna:</strong> {$slotGroup}</p>
            {$weekNotesHtml}
            <div style="margin-top:24px;">
                <a href="{$scheduleUrl}" style="display:inline-block;background:#0284c7;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:12px;font-weight:700;">Abrir Minha Escala</a>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function formatWeekPeriod(string $startDate, string $endDate): string
    {
        if ($startDate === '' || $endDate === '') {
            return 'periodo informado na escala';
        }

        return date('d/m/Y', strtotime($startDate)) . ' a ' . date('d/m/Y', strtotime($endDate));
    }
}
