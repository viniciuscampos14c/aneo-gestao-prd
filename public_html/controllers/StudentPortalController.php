<?php

class StudentPortalController extends BaseController
{
    private StudentPortalModel $portal;
    private AcademicCalendarModel $calendar;
    private SupportTicketModel $tickets;
    private StudentExchangeModel $exchange;
    private ReenrollmentModel $reenrollment;
    private EmailService $emails;

    public function __construct()
    {
        $this->portal       = new StudentPortalModel();
        $this->calendar     = new AcademicCalendarModel();
        $this->tickets      = new SupportTicketModel();
        $this->exchange     = new StudentExchangeModel();
        $this->reenrollment = new ReenrollmentModel();
        $this->emails       = new EmailService();
    }

    // Gate de rematrícula com dois comportamentos:
    // 1. Aviso (30 dias antes): redireciona apenas o dashboard → rematrícula
    // 2. Bloqueio total (após vencimento): qualquer rota → rematrícula
    private function checkReenrollmentGate(array $student): void
    {
        $skip = [
            'student/reenrollment',
            'student/reenrollment/confirm',
            'student/login',
            'student/logout',
        ];

        $route     = parse_route();
        $studentId = (int) $student['id'];

        if (in_array($route, $skip, true)) {
            return;
        }

        // Bloqueio total: prazo vencido — bloqueia TODAS as rotas
        if ($this->reenrollment->isExpired($studentId)) {
            $this->redirect('student/reenrollment');
            return;
        }

        // Aviso: dentro dos 30 dias anteriores ao vencimento — redireciona só o dashboard
        if ($this->reenrollment->isDue($studentId) && $route === 'student/dashboard') {
            $this->redirect('student/reenrollment');
        }
    }

    public function home(): void
    {
        $this->redirect('student/dashboard');
    }

    public function dashboard(): void
    {
        require_student_auth();

        if (!$this->portal->portalFeatureAvailable()) {
            $this->error('Portal do aluno nao configurado no banco.');
            $this->redirect('student/login');
        }

        $student = current_student();
        $this->checkReenrollmentGate($student);
        $this->calendar->processAutomaticReminders(45, (int) ($student['company_id'] ?? 0));
        $summary = $this->portal->dashboardSummary((int) $student['id']);
        $examScheduleEnabled = $this->portal->examScheduleFeatureAvailable();

        $this->render('student_portal/dashboard', [
            'title' => 'Portal do Aluno',
            'student' => $student,
            'summary' => $summary,
            'examScheduleEnabled' => $examScheduleEnabled,
        ], 'layouts/student');
    }

    public function courses(): void
    {
        require_student_auth();

        $student = current_student();
        $this->checkReenrollmentGate($student);
        $rows = $this->portal->myCourses((int) $student['id']);

        $this->render('student_portal/courses', [
            'title' => 'Meus Cursos',
            'student' => $student,
            'rows' => $rows,
        ], 'layouts/student');
    }

    public function course(): void
    {
        require_student_auth();

        $student = current_student();
        $this->checkReenrollmentGate($student);

        if (!$this->portal->lmsFeatureAvailable()) {
            $this->error('Trilha de aulas ainda nao habilitada no banco. Execute a migracao LMS.');
            $this->redirect('student/courses');
        }
        $studentId = (int) ($student['id'] ?? 0);
        $courseId = (int) request('course_id', request('id', 0));
        $lessonId = (int) request('lesson_id', 0);

        if ($courseId <= 0) {
            $this->error('Curso invalido para abrir o player.');
            $this->redirect('student/courses');
        }

        $path = $this->portal->courseLearningPath($studentId, $courseId, $lessonId > 0 ? $lessonId : null);
        if (!$path) {
            $this->error('Curso nao encontrado na sua matricula.');
            $this->redirect('student/courses');
        }

        $courseCommentsFeatureAvailable = $this->portal->courseCommentsFeatureAvailable();
        $courseComments = $courseCommentsFeatureAvailable
            ? $this->portal->listCourseCommentsForStudent($studentId, $courseId, 12)
            : [];

        $this->render('student_portal/course_player', [
            'title' => 'Aulas do Curso',
            'student' => $student,
            'course' => $path['course'],
            'modules' => $path['modules'],
            'selectedLesson' => $path['selected_lesson'],
            'summary' => $path['summary'],
            'courseCommentsFeatureAvailable' => $courseCommentsFeatureAvailable,
            'courseComments' => $courseComments,
        ], 'layouts/student');
    }

    public function lessonProgress(): void
    {
        require_student_auth();
        csrf_validate();

        $student = current_student();
        $studentId = (int) ($student['id'] ?? 0);

        $result = $this->portal->recordLessonProgress(
            $studentId,
            (int) post('course_id'),
            (int) post('lesson_id'),
            (int) post('watched_seconds', 0),
            (int) post('duration_seconds', 0),
            (int) post('position_seconds', 0)
        );

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function confirmLessonCompletion(): void
    {
        require_student_auth();
        csrf_validate();

        $student = current_student();
        $studentId = (int) ($student['id'] ?? 0);

        $result = $this->portal->confirmLessonCompletion(
            $studentId,
            (int) post('course_id'),
            (int) post('lesson_id')
        );

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function markAlertsRead(): void
    {
        require_student_auth();
        csrf_validate();

        $student = current_student();
        $studentId = (int) ($student['id'] ?? 0);
        $companyId = (int) ($student['company_id'] ?? 0);
        $studentEmail = trim((string) ($student['email'] ?? ''));

        $this->portal->markAllPortalNotificationsAsRead($studentId);
        $ticketCount = 0;
        $liveCount = 0;
        $portalCount = $this->portal->countUnreadPortalNotifications($studentId);

        $this->json([
            'ok' => true,
            'studentPortalAlertCount' => $portalCount,
            'studentTicketAlertCount' => $ticketCount,
            'studentLiveAlertCount' => $liveCount,
            'studentAlertCount' => $portalCount + $ticketCount + $liveCount,
        ]);
    }

    public function live(): void
    {
        require_student_auth();

        $student = current_student();
        $this->checkReenrollmentGate($student);
        $rows = $this->portal->upcomingLiveClasses((int) $student['id']);

        $this->render('student_portal/live', [
            'title' => 'Proximas Aulas ao Vivo',
            'student' => $student,
            'rows' => $rows,
        ], 'layouts/student');
    }

    public function materials(): void
    {
        require_student_auth();

        $student = current_student();
        $this->checkReenrollmentGate($student);
        $data = $this->portal->materials((int) $student['id']);

        $this->render('student_portal/materials', [
            'title' => 'Materiais',
            'student' => $student,
            'courses' => $data['courses'],
            'uploads' => $data['uploads'],
        ], 'layouts/student');
    }

    public function schedule(): void
    {
        require_student_auth();

        $student = current_student();
        $this->checkReenrollmentGate($student);
        $studentId = (int) ($student['id'] ?? 0);
        $featureAvailable = $this->portal->studentDutyScheduleFeatureAvailable();
        $rows = $featureAvailable ? $this->portal->myDutySchedule($studentId, true) : [];
        $grouped = [];

        foreach ($rows as $row) {
            $scheduleId = (int) ($row['schedule_id'] ?? 0);
            $monthRef = (string) ($row['month_ref'] ?? '');
            $weekId = (int) ($row['week_id'] ?? 0);

            if (!isset($grouped[$scheduleId])) {
                $grouped[$scheduleId] = [
                    'schedule_id' => $scheduleId,
                    'schedule_title' => (string) ($row['schedule_title'] ?? ''),
                    'schedule_status' => (string) ($row['schedule_status'] ?? ''),
                    'schedule_start_date' => (string) ($row['schedule_start_date'] ?? ''),
                    'schedule_end_date' => (string) ($row['schedule_end_date'] ?? ''),
                    'unit_name' => (string) ($row['unit_name'] ?? ''),
                    'unit_city' => (string) ($row['unit_city'] ?? ''),
                    'unit_state' => (string) ($row['unit_state'] ?? ''),
                    'months' => [],
                ];
            }

            if (!isset($grouped[$scheduleId]['months'][$monthRef])) {
                $grouped[$scheduleId]['months'][$monthRef] = [];
            }

            if (!isset($grouped[$scheduleId]['months'][$monthRef][$weekId])) {
                $grouped[$scheduleId]['months'][$monthRef][$weekId] = [
                    'week_id' => $weekId,
                    'week_order' => (int) ($row['week_order'] ?? 0),
                    'week_start_date' => (string) ($row['week_start_date'] ?? ''),
                    'week_end_date' => (string) ($row['week_end_date'] ?? ''),
                    'week_notes' => (string) ($row['week_notes'] ?? ''),
                    'slot_group' => (string) ($row['slot_group'] ?? ''),
                ];
            }
        }

        $schedules = array_values(array_map(function (array $schedule): array {
            $months = [];
            foreach ($schedule['months'] as $monthRef => $weeks) {
                $monthWeeks = array_values($weeks);
                usort($monthWeeks, fn (array $a, array $b): int => strcmp((string) $a['week_start_date'], (string) $b['week_start_date']));
                $months[$monthRef] = $monthWeeks;
            }
            $schedule['months'] = $months;
            return $schedule;
        }, $grouped));

        $this->render('student_portal/schedule', [
            'title' => 'Minha Escala',
            'student' => $student,
            'featureAvailable' => $featureAvailable,
            'schedules' => $schedules,
        ], 'layouts/student');
    }

    public function arsenal(): void
    {
        require_student_auth();

        $student = current_student();
        $this->checkReenrollmentGate($student);
        $studentId = (int) ($student['id'] ?? 0);

        if (!$this->portal->arsenalFeatureAvailable()) {
            $this->error('Arsenal Digital nao habilitado no banco.');
            $this->redirect('student/dashboard');
        }

        $filters = [
            'q' => trim((string) request('q', '')),
            'material_type' => trim((string) request('material_type', '')),
            'category_id' => (int) request('category_id', 0),
        ];

        $rows = $this->portal->arsenal($studentId, $filters);
        $categories = [];
        foreach ($rows as $row) {
            $categoryId = (int) ($row['category_id'] ?? 0);
            $categoryName = trim((string) ($row['category_name'] ?? ''));
            if ($categoryId > 0 && $categoryName !== '') {
                $categories[$categoryId] = $categoryName;
            }
        }
        asort($categories);

        $this->render('student_portal/arsenal', [
            'title' => 'Arsenal do Aluno',
            'student' => $student,
            'rows' => $rows,
            'filters' => $filters,
            'categories' => $categories,
        ], 'layouts/student');
    }

    public function arsenalOpen(): void
    {
        require_student_auth();

        $student = current_student();
        $studentId = (int) ($student['id'] ?? 0);
        $itemId = (int) request('id');
        if ($itemId <= 0) {
            $this->error('Item do Arsenal invalido.');
            $this->redirect('student/arsenal');
        }

        $item = $this->portal->findAccessibleArsenalItem($studentId, $itemId);
        if (!$item) {
            $this->error('Item nao encontrado ou sem permissao de acesso.');
            $this->redirect('student/arsenal');
        }

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        if ((string) ($item['material_type'] ?? '') === 'link') {
            $url = trim((string) ($item['external_url'] ?? ''));
            if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
                $this->error('Link externo invalido.');
                $this->redirect('student/arsenal');
            }

            $this->portal->logArsenalAccess($studentId, $itemId, 'open_link', $ipAddress, $userAgent);
            header('Location: ' . $url);
            exit;
        }

        $relativePath = trim((string) ($item['file_path'] ?? ''));
        $fullPath = $this->resolveArsenalFilePath($relativePath);
        if ($fullPath === null || !is_file($fullPath)) {
            $this->error('Arquivo nao encontrado para este item.');
            $this->redirect('student/arsenal');
        }

        $downloadName = trim((string) ($item['file_name'] ?? ''));
        if ($downloadName === '') {
            $downloadName = basename($fullPath);
        }

        $mimeType = $this->detectMimeType($fullPath);
        $disposition = str_starts_with($mimeType, 'application/pdf')
            || str_starts_with($mimeType, 'image/')
            || str_starts_with($mimeType, 'text/')
            ? 'inline'
            : 'attachment';

        $this->portal->logArsenalAccess($studentId, $itemId, 'open_file', $ipAddress, $userAgent);

        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: ' . $disposition . '; filename="' . addslashes($downloadName) . '"');
        header('Content-Length: ' . (string) filesize($fullPath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: public');
        readfile($fullPath);
        exit;
    }

    public function progress(): void
    {
        require_student_auth();

        $student = current_student();
        $this->checkReenrollmentGate($student);
        $data = $this->portal->progress((int) $student['id']);

        $this->render('student_portal/progress', [
            'title' => 'Progresso',
            'student' => $student,
            'summary' => $data['summary'],
            'courses' => $data['courses'],
        ], 'layouts/student');
    }

    public function requests(): void
    {
        require_student_auth();

        $student = current_student();
        $this->checkReenrollmentGate($student);
        $studentId = (int) ($student['id'] ?? 0);
        $companyId = (int) ($student['company_id'] ?? 0);
        $studentEmail = trim((string) ($student['email'] ?? ''));
        if ($studentId <= 0 || $companyId <= 0) {
            $this->error('Aluno ou empresa nao identificado para chamados.');
            $this->redirect('student/dashboard');
        }

        $featureAvailable = $this->tickets->featureAvailable();
        $filters = [
            'q' => trim((string) request('q', '')),
            'status' => $this->normalizeTicketStatus((string) request('status', '')),
        ];

        $perPage = (int) request('per_page', 20);
        if (!in_array($perPage, [10, 20, 50], true)) {
            $perPage = 20;
        }
        $page = max(1, (int) request('page', 1));

        if ($featureAvailable) {
            $result = $this->tickets->listStudentTickets($companyId, $studentId, $studentEmail, $filters, $perPage, $page);
            $stats = $this->tickets->studentStats($companyId, $studentId, $studentEmail);
        } else {
            $result = [
                'rows' => [],
                'meta' => pagination_meta(0, $perPage, $page),
            ];
            $stats = [
                'total' => 0,
                'open' => 0,
                'in_progress' => 0,
                'resolved' => 0,
                'closed' => 0,
            ];
        }

        $ticketIds = array_map(fn ($row) => (int) ($row['id'] ?? 0), $result['rows']);

        $this->render('student_portal/requests', [
            'title' => 'Meus Chamados',
            'student' => $student,
            'rows' => $result['rows'],
            'meta' => $result['meta'],
            'filters' => $filters,
            'stats' => $stats,
            'featureAvailable' => $featureAvailable,
            'attachmentsByTicket' => $this->tickets->attachmentsByTicketIdsAny($ticketIds),
            'commentsByTicket' => $this->tickets->commentsByTicketIdsAny($ticketIds),
            'paginationOptions' => [10, 20, 50],
        ], 'layouts/student');
    }

    public function storeRequest(): void
    {
        require_student_auth();
        csrf_validate();

        if (!$this->tickets->featureAvailable()) {
            $this->error('Estrutura de chamados indisponivel no banco.');
            $this->redirect('student/requests');
        }

        $student = current_student();
        $studentId = (int) ($student['id'] ?? 0);
        $companyId = (int) ($student['company_id'] ?? 0);
        if ($studentId <= 0 || $companyId <= 0) {
            $this->error('Aluno ou empresa nao identificado para abrir chamado.');
            $this->redirect('student/requests');
        }

        $subject = trim((string) post('subject'));
        $description = trim((string) post('description'));
        $priority = $this->normalizeTicketPriority((string) post('priority', 'medium'));

        if ($subject === '' || $description === '') {
            $this->error('Assunto e descricao sao obrigatorios para abrir o chamado.');
            $this->redirect('student/requests');
        }

        $ticketId = $this->tickets->createTicketForCompany($companyId, [
            'subject' => $subject,
            'description' => $description,
            'priority' => $priority,
            'requester_name' => (string) ($student['name'] ?? ''),
            'requester_email' => (string) ($student['email'] ?? ''),
            'external_reference' => 'student:' . $studentId,
        ], null, 'student_portal');

        if ($ticketId <= 0) {
            $this->error('Nao foi possivel abrir o chamado no momento.');
            $this->redirect('student/requests');
        }

        $ticket = $this->tickets->findTicketForCompany($companyId, $ticketId);
        $ticketCode = trim((string) ($ticket['ticket_code'] ?? ''));
        if ($ticketCode === '') {
            $ticketCode = 'ANEO' . str_pad((string) $ticketId, 3, '0', STR_PAD_LEFT);
        }

        $this->success('Chamado ' . $ticketCode . ' aberto com sucesso.');
        $this->redirect('student/requests');
    }

    public function exams(): void
    {
        require_student_auth();

        $student = current_student();
        $this->checkReenrollmentGate($student);
        $availableExams = $this->portal->listAvailableExams((int) $student['id']);
        $history = $this->portal->examHistory((int) $student['id']);
        $pending = $this->portal->pendingExamSubmissions((int) $student['id']);
        $examScheduleEnabled = $this->portal->examScheduleFeatureAvailable();
        $examCalendar = $this->portal->upcomingExamCalendar((int) $student['id'], 12);

        $this->render('student_portal/exams', [
            'title' => 'Avaliacoes',
            'student' => $student,
            'availableExams' => $availableExams,
            'pendingSubmissions' => $pending,
            'examScheduleEnabled' => $examScheduleEnabled,
            'examCalendar' => $examCalendar,
            'rows' => $history,
        ], 'layouts/student');
    }

    public function academicHistory(): void
    {
        require_student_auth();

        $student = current_student();
        $this->checkReenrollmentGate($student);
        $studentId = (int) ($student['id'] ?? 0);
        if ($studentId <= 0) {
            $this->error('Aluno invalido para emitir historico academico.');
            $this->redirect('student/dashboard');
        }

        $profile = $this->portal->studentAcademicProfile($studentId) ?? [];
        $history = $this->portal->academicHistoryRecords($studentId);
        $courses = $this->portal->myCourses($studentId);

        $courseWorkload = [];
        $totalWorkload = 0.0;
        foreach ($courses as $course) {
            $courseName = trim((string) ($course['name'] ?? ''));
            $workload = (float) ($course['workload_hours'] ?? 0);
            if ($courseName !== '' && $workload > 0) {
                $courseWorkload[strtolower($courseName)] = $workload;
                $totalWorkload += $workload;
            }
        }

        $terms = [];
        foreach ($history as $row) {
            $submittedAt = trim((string) ($row['submitted_at'] ?? ''));
            $timestamp = $submittedAt !== '' ? strtotime($submittedAt) : false;
            $year = $timestamp !== false ? (int) date('Y', $timestamp) : (int) date('Y');
            $semester = $timestamp !== false ? ((int) date('n', $timestamp) <= 6 ? 1 : 2) : 1;
            $termKey = $year . '-' . $semester;

            if (!isset($terms[$termKey])) {
                $terms[$termKey] = [
                    'sort_key' => sprintf('%04d-%d', $year, $semester),
                    'term_label' => $semester . ' Semestre / ' . $year,
                    'rows' => [],
                ];
            }

            $courseName = trim((string) ($row['course_name'] ?? ''));
            $workload = 0.0;
            if ($courseName !== '') {
                $workload = (float) ($courseWorkload[strtolower($courseName)] ?? 0);
            }

            $terms[$termKey]['rows'][] = [
                'date' => $submittedAt,
                'course_name' => $courseName,
                'exam_title' => trim((string) ($row['exam_title'] ?? '')),
                'passing_score' => (float) ($row['passing_score'] ?? 0),
                'score' => (float) ($row['score'] ?? 0),
                'status' => (string) ($row['status'] ?? 'failed'),
                'workload' => $workload,
                'absences' => 0,
            ];
        }

        $termList = array_values($terms);
        usort($termList, function (array $a, array $b): int {
            return strcmp((string) ($b['sort_key'] ?? ''), (string) ($a['sort_key'] ?? ''));
        });

        $ra = trim((string) ($profile['ra'] ?? ''));
        if ($ra === '') {
            $ra = str_pad((string) $studentId, 9, '0', STR_PAD_LEFT);
        }

        $approvedCount = 0;
        $sumScores = 0.0;
        foreach ($history as $row) {
            $sumScores += (float) ($row['score'] ?? 0);
            if ((string) ($row['status'] ?? '') === 'approved') {
                $approvedCount++;
            }
        }

        $totalResults = count($history);
        $failedCount = max(0, $totalResults - $approvedCount);
        $averageScore = $totalResults > 0 ? ($sumScores / $totalResults) : 0.0;

        $this->render('student_portal/academic_history', [
            'title' => 'Historico Academico',
            'student' => $student,
            'profile' => $profile,
            'ra' => $ra,
            'terms' => $termList,
            'totalResults' => $totalResults,
            'approvedCount' => $approvedCount,
            'failedCount' => $failedCount,
            'averageScore' => $averageScore,
            'totalWorkload' => $totalWorkload,
            'issuedAt' => now(),
        ], 'layouts/student');
    }

    public function finances(): void
    {
        require_student_auth();

        $student   = current_student();
        $this->checkReenrollmentGate($student);
        $studentId = (int) ($student['id'] ?? 0);
        $companyId = (int) ($student['company_id'] ?? 0);

        $statusFilter = trim((string) request('status', ''));
        $page         = max(1, (int) request('page', 1));
        $perPage      = 20;
        $offset       = ($page - 1) * $perPage;

        $summary  = $this->portal->myFinancialSummary($studentId, $companyId);
        $invoices = $this->portal->myInvoices($studentId, $companyId, $statusFilter, $perPage + 1, $offset);

        $hasNextPage = count($invoices) > $perPage;
        if ($hasNextPage) {
            array_pop($invoices);
        }

        $this->render('student_portal/finances', [
            'title'        => 'Financeiro',
            'student'      => $student,
            'summary'      => $summary,
            'invoices'     => $invoices,
            'statusFilter' => $statusFilter,
            'page'         => $page,
            'hasNextPage'  => $hasNextPage,
        ], 'layouts/student');
    }

    public function calendar(): void
    {
        require_student_auth();

        $student = current_student();
        $this->checkReenrollmentGate($student);
        $studentId = (int) $student['id'];
        $fromDate = $this->normalizeDate((string) request('from'), date('Y-m-d'));
        $toDate = $this->normalizeDate((string) request('to'), date('Y-m-d', strtotime('+45 days')));
        $fromDateTime = $fromDate . ' 00:00:00';
        $toDateTime = $toDate . ' 23:59:59';

        $automation = $this->calendar->processAutomaticReminders(45, (int) ($student['company_id'] ?? 0));
        $events = $this->calendar->studentUnifiedEvents($studentId, $fromDateTime, $toDateTime);
        $reminders = $this->calendar->studentRecentReminders($studentId, 25);

        $this->render('student_portal/calendar', [
            'title' => 'Agenda Academica',
            'student' => $student,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'events' => $events,
            'reminders' => $reminders,
            'calendarFeatureAvailable' => $this->calendar->featureAvailable(),
            'automationSummary' => $automation,
        ], 'layouts/student');
    }

    public function takeExam(): void
    {
        require_student_auth();

        $student = current_student();
        $this->checkReenrollmentGate($student);
        $studentId = (int) $student['id'];
        $examId = (int) request('id');

        if ($examId <= 0) {
            $this->error('Exame invalido.');
            $this->redirect('student/exams');
        }

        $exam = $this->portal->findAvailableExam($studentId, $examId);
        if (!$exam) {
            $this->error('Exame nao encontrado para sua matricula.');
            $this->redirect('student/exams');
        }

        $externalUrl = trim((string) ($exam['external_url'] ?? ''));
        if ($externalUrl !== '') {
            $this->redirect('student/exams/external&id=' . $examId);
        }

        if ($this->portal->hasFinalExamResult($studentId, $examId)) {
            $this->error('Esse exame ja possui resultado registrado para voce.');
            $this->redirect('student/exams');
        }

        if ($this->portal->hasExamSubmission($studentId, $examId)) {
            $this->error('Esse exame ja foi enviado e aguarda correcao/publicacao do resultado.');
            $this->redirect('student/exams');
        }

        $questions = $this->portal->examQuestions($examId);
        if ($questions === []) {
            $this->error('Este exame ainda nao possui questoes cadastradas.');
            $this->redirect('student/exams');
        }

        $this->render('student_portal/exam_take', [
            'title' => 'Responder Prova',
            'student' => $student,
            'exam' => $exam,
            'questions' => $questions,
        ], 'layouts/student');
    }

    public function openExternalExam(): void
    {
        require_student_auth();

        $student = current_student();
        $this->checkReenrollmentGate($student);
        $studentId = (int) ($student['id'] ?? 0);
        $examId = (int) request('id');

        if ($examId <= 0) {
            $this->error('Exame invalido.');
            $this->redirect('student/exams');
        }

        $exam = $this->portal->findAvailableExam($studentId, $examId);
        if (!$exam) {
            $this->error('Exame nao encontrado para sua matricula.');
            $this->redirect('student/exams');
        }

        if ($this->portal->hasFinalExamResult($studentId, $examId)) {
            $this->error('Esse exame ja possui resultado publicado.');
            $this->redirect('student/exams');
        }

        $scheduledAt = trim((string) ($exam['scheduled_at'] ?? ''));
        $scheduledTs = $scheduledAt !== '' ? strtotime($scheduledAt) : false;
        if ($scheduledTs !== false && $scheduledTs > time()) {
            $this->error('Essa prova externa sera liberada em ' . date('d/m/Y H:i', $scheduledTs) . '.');
            $this->redirect('student/exams');
        }

        $externalUrl = trim((string) ($exam['external_url'] ?? ''));
        if ($externalUrl === '' || !$this->isHttpUrl($externalUrl)) {
            $this->error('Link externo da prova invalido. Contate seu professor.');
            $this->redirect('student/exams');
        }

        $this->portal->markExternalExamOpened($studentId, $examId);
        header('Location: ' . $externalUrl);
        exit;
    }

    public function submitExam(): void
    {
        require_student_auth();
        csrf_validate();

        $student = current_student();
        $studentId = (int) $student['id'];
        $examId = (int) post('exam_id');

        if ($examId <= 0) {
            $this->error('Exame invalido.');
            $this->redirect('student/exams');
        }

        $exam = $this->portal->findAvailableExam($studentId, $examId);
        if (!$exam) {
            $this->error('Exame nao encontrado para sua matricula.');
            $this->redirect('student/exams');
        }

        if (trim((string) ($exam['external_url'] ?? '')) !== '') {
            $this->error('Esta avaliacao e externa. Use o botao "Abrir prova externa" na lista de avaliacoes.');
            $this->redirect('student/exams');
        }

        if ($this->portal->hasFinalExamResult($studentId, $examId)) {
            $this->error('Esse exame ja possui resultado registrado para voce.');
            $this->redirect('student/exams');
        }

        if ($this->portal->hasExamSubmission($studentId, $examId)) {
            $this->error('Esse exame ja foi enviado anteriormente.');
            $this->redirect('student/exams');
        }

        $questions = $this->portal->examQuestions($examId);
        if ($questions === []) {
            $this->error('Este exame ainda nao possui questoes cadastradas.');
            $this->redirect('student/exams');
        }

        $answersInput = post('answers', []);
        if (!is_array($answersInput)) {
            $answersInput = [];
        }

        $gradedQuestions = 0;
        $correctAnswers = 0;
        $answers = [];

        foreach ($questions as $question) {
            $questionId = (int) $question['id'];
            $answerText = trim((string) ($answersInput[$questionId] ?? ''));
            $isCorrect = null;

            if ((string) $question['question_type'] === 'objective' && trim((string) $question['correct_answer']) !== '') {
                $gradedQuestions++;
                $isCorrect = $this->normalizeAnswer($answerText) === $this->normalizeAnswer((string) $question['correct_answer']) ? 1 : 0;
                if ($isCorrect === 1) {
                    $correctAnswers++;
                }
            }

            $answers[] = [
                'question_id' => $questionId,
                'answer_text' => $answerText,
                'is_correct' => $isCorrect,
            ];
        }

        $submittedAt = now();
        $score = null;
        $submissionStatus = 'pending_review';

        if ($gradedQuestions > 0) {
            $score = round(($correctAnswers / $gradedQuestions) * 10, 2);
            $submissionStatus = 'auto_graded';
        }

        $submissionId = $this->portal->createExamSubmission([
            'exam_id' => $examId,
            'student_id' => $studentId,
            'status' => $submissionStatus,
            'score' => $score,
            'graded_questions' => $gradedQuestions,
            'correct_answers' => $correctAnswers,
            'submitted_at' => $submittedAt,
        ]);

        if ($submissionId <= 0 && $score === null) {
            $this->error('Nao foi possivel registrar envio pendente. Atualize o banco com a migracao de exames do portal.');
            $this->redirect('student/exams');
        }

        if ($submissionId > 0) {
            foreach ($answers as $answer) {
                $this->portal->addExamSubmissionAnswer(
                    $submissionId,
                    (int) $answer['question_id'],
                    (string) $answer['answer_text'],
                    $answer['is_correct'] !== null ? (int) $answer['is_correct'] : null
                );
            }
        }

        if ($score !== null) {
            $this->portal->registerExamResultFromPortal(
                $studentId,
                $examId,
                (float) $score,
                (float) $exam['passing_score'],
                $submittedAt
            );

            $this->notifyStudentExamResult(
                $student,
                $examId,
                (string) ($exam['title'] ?? 'Avaliacao'),
                (string) ($exam['course_name'] ?? 'Curso'),
                (float) $score,
                (float) $exam['passing_score']
            );

            $this->success('Prova enviada. Nota calculada automaticamente: ' . number_format((float) $score, 2, ',', '.') . '.');
            $this->redirect('student/exams');
        }

        $this->success('Prova enviada com sucesso. Ela ficara pendente para correcao manual.');
        $this->redirect('student/exams');
    }

    private function normalizeAnswer(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/', ' ', $value) ?: $value;

        return strtolower($value);
    }

    private function notifyStudentExamResult(
        array $student,
        int $examId,
        string $examTitle,
        string $courseName,
        float $score,
        float $passingScore
    ): void {
        $studentId = (int) ($student['id'] ?? 0);
        $companyId = (int) ($student['company_id'] ?? 0);
        if ($studentId <= 0 || $companyId <= 0) {
            return;
        }

        $studentName = trim((string) ($student['name'] ?? 'Aluno'));
        $studentEmail = trim((string) ($student['email'] ?? ''));
        $approved = $score >= $passingScore;
        $statusLabel = $approved ? 'Aprovado(a)' : 'Necessita revisao';

        if ($this->portal->studentPortalNotificationsFeatureAvailable()) {
            $this->portal->createPortalNotification([
                'company_id' => $companyId,
                'student_id' => $studentId,
                'notification_type' => 'exam_result',
                'title' => 'Resultado de avaliacao publicado',
                'message' => sprintf(
                    '%s: sua nota em %s foi %s (minimo %s).',
                    $courseName,
                    $examTitle,
                    number_format($score, 2, ',', '.'),
                    number_format($passingScore, 2, ',', '.')
                ),
                'link_url' => route('student/exams'),
                'meta' => [
                    'exam_id' => $examId,
                    'exam_title' => $examTitle,
                    'course_name' => $courseName,
                    'score' => $score,
                    'passing_score' => $passingScore,
                    'status' => $approved ? 'approved' : 'failed',
                ],
            ]);
        }

        if ($studentEmail !== '' && filter_var($studentEmail, FILTER_VALIDATE_EMAIL)) {
            $body = '<p>Ola ' . e($studentName) . ',</p>'
                . '<p>Seu resultado foi publicado no portal do aluno.</p>'
                . '<p><strong>Curso:</strong> ' . e($courseName) . '<br>'
                . '<strong>Avaliacao:</strong> ' . e($examTitle) . '<br>'
                . '<strong>Nota:</strong> ' . e(number_format($score, 2, ',', '.')) . '<br>'
                . '<strong>Nota minima:</strong> ' . e(number_format($passingScore, 2, ',', '.')) . '<br>'
                . '<strong>Status:</strong> ' . e($statusLabel) . '</p>'
                . '<p><a href="' . e($this->absoluteUrl(route('student/exams'))) . '">Abrir portal do aluno</a></p>';

            $this->emails->send($studentEmail, 'Resultado de avaliacao publicado | ' . $courseName, $body, [
                'company_id' => $companyId,
                'is_html' => true,
            ]);
        }
    }

    private function absoluteUrl(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $base = trim((string) config('app.public_url', ''));
        if ($base === '') {
            $base = trim((string) config('app.base_url', ''));
        }

        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }

    private function normalizeTicketPriority(string $priority): string
    {
        $priority = strtolower(trim($priority));
        return in_array($priority, ['low', 'medium', 'high', 'urgent'], true) ? $priority : 'medium';
    }

    private function normalizeTicketStatus(string $status): string
    {
        $status = strtolower(trim($status));
        return in_array($status, ['open', 'in_progress', 'resolved', 'closed'], true) ? $status : '';
    }

    private function normalizeDate(string $value, string $fallback): string
    {
        $value = trim($value);
        if ($value === '') {
            return $fallback;
        }

        $ts = strtotime($value);
        if ($ts === false) {
            return $fallback;
        }

        return date('Y-m-d', $ts);
    }

    private function resolveArsenalFilePath(string $relativePath): ?string
    {
        $relativePath = trim($relativePath);
        if ($relativePath === '') {
            return null;
        }

        $uploadsBase = realpath(__DIR__ . '/../uploads');
        if (!$uploadsBase) {
            return null;
        }

        $fullPath = realpath(__DIR__ . '/../' . ltrim($relativePath, '/\\'));
        if (!$fullPath) {
            return null;
        }

        if (!str_starts_with($fullPath, $uploadsBase)) {
            return null;
        }

        return $fullPath;
    }

    private function detectMimeType(string $filePath): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $filePath);
                finfo_close($finfo);
                if (is_string($mime) && trim($mime) !== '') {
                    return $mime;
                }
            }
        }

        return 'application/octet-stream';
    }

    private function isHttpUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true);
    }

    // -------------------------------------------------------------------------
    // Intercâmbio Aneo
    // -------------------------------------------------------------------------

    public function exchangeForm(): void
    {
        require_student_auth();

        $student   = current_student();
        $this->checkReenrollmentGate($student);
        $studentId = (int) ($student['id'] ?? 0);

        $companies  = $this->exchange->listCompanies();
        $myRequests = $this->exchange->myRequests($studentId);
        $hasPending = $this->exchange->hasPendingRequest($studentId);

        $this->render('student_portal/exchange_form', [
            'title'      => 'Intercâmbio Aneo',
            'student'    => $student,
            'companies'  => $companies,
            'myRequests' => $myRequests,
            'hasPending' => $hasPending,
        ], 'layouts/student');
    }

    public function exchangeStore(): void
    {
        require_student_auth();
        csrf_validate();

        $student   = current_student();
        $studentId = (int) ($student['id'] ?? 0);
        $companyId = (int) ($student['company_id'] ?? 0);

        $studentName    = trim((string) post('student_name', $student['name'] ?? ''));
        $currentUnit    = trim((string) post('current_unit', ''));
        $targetUnit     = trim((string) post('target_unit', ''));
        $desiredMonth   = trim((string) post('desired_month', ''));
        $monthsEnrolled = max(0, (int) post('months_enrolled', 0));

        // Validação básica
        $errors = [];
        if ($studentName === '') { $errors[] = 'Nome completo é obrigatório.'; }
        if ($currentUnit === '')  { $errors[] = 'Unidade atual é obrigatória.'; }
        if ($targetUnit === '')   { $errors[] = 'Unidade de destino é obrigatória.'; }
        if ($desiredMonth === '') { $errors[] = 'Mês desejado é obrigatório.'; }

        // Valida formato YYYY-MM
        if ($desiredMonth !== '' && !preg_match('/^\d{4}-\d{2}$/', $desiredMonth)) {
            $errors[] = 'Formato de mês inválido.';
        }

        if ($errors !== []) {
            flash('error', implode(' ', $errors));
            $this->redirect('student/exchange');
        }

        if ($this->exchange->hasPendingRequest($studentId)) {
            flash('error', 'Você já possui uma solicitação de intercâmbio em andamento.');
            $this->redirect('student/exchange');
        }

        $ok = $this->exchange->submit(
            $studentId,
            $companyId,
            $studentName,
            $currentUnit,
            $targetUnit,
            $desiredMonth,
            $monthsEnrolled
        );

        if ($ok) {
            flash('success', 'Solicitação de intercâmbio enviada com sucesso! Entraremos em contato em breve.');
        } else {
            flash('error', 'Não foi possível registrar a solicitação. Tente novamente.');
        }

        $this->redirect('student/exchange');
    }

    // -------------------------------------------------------------------------
    // Rematrícula automática
    // -------------------------------------------------------------------------

    public function reenrollment(): void
    {
        require_student_auth();

        $student   = current_student();
        $studentId = (int) $student['id'];
        $companyId = (int) ($student['company_id'] ?? 0);

        // Se não está na janela, redireciona para o portal normalmente
        if (!$this->reenrollment->isDue($studentId)) {
            $this->redirect('student/dashboard');
        }

        $openInvoices = $this->reenrollment->openInvoices($studentId, $companyId);
        $period       = $this->reenrollment->getPendingPeriod($studentId);

        $this->render('student_portal/reenrollment', [
            'title'        => 'Rematrícula',
            'student'      => $student,
            'openInvoices' => $openInvoices,
            'period'       => $period,
            'canConfirm'   => empty($openInvoices),
        ], 'layouts/student');
    }

    public function reenrollmentConfirm(): void
    {
        require_student_auth();
        csrf_validate();

        $student   = current_student();
        $studentId = (int) $student['id'];
        $companyId = (int) ($student['company_id'] ?? 0);

        if (!$this->reenrollment->isDue($studentId)) {
            $this->redirect('student/dashboard');
        }

        $openInvoices = $this->reenrollment->openInvoices($studentId, $companyId);
        if (!empty($openInvoices)) {
            flash('error', 'Você possui faturas em aberto. Regularize sua situação antes de confirmar a rematrícula.');
            $this->redirect('student/reenrollment');
        }

        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $ok = $this->reenrollment->confirm($studentId, $companyId, $ip);

        if ($ok) {
            flash('success', 'Rematrícula confirmada com sucesso! Bem-vindo(a) ao novo período.');
        } else {
            flash('error', 'Não foi possível confirmar a rematrícula. Tente novamente ou contate o administrativo.');
        }

        $this->redirect('student/dashboard');
    }
}
