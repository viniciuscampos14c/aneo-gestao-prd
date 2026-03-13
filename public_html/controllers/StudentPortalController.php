<?php

class StudentPortalController extends BaseController
{
    private StudentPortalModel $portal;
    private AcademicCalendarModel $calendar;

    public function __construct()
    {
        $this->portal = new StudentPortalModel();
        $this->calendar = new AcademicCalendarModel();
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
        $rows = $this->portal->myCourses((int) $student['id']);

        $this->render('student_portal/courses', [
            'title' => 'Meus Cursos',
            'student' => $student,
            'rows' => $rows,
        ], 'layouts/student');
    }

    public function live(): void
    {
        require_student_auth();

        $student = current_student();
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
        $data = $this->portal->materials((int) $student['id']);

        $this->render('student_portal/materials', [
            'title' => 'Materiais',
            'student' => $student,
            'courses' => $data['courses'],
            'uploads' => $data['uploads'],
        ], 'layouts/student');
    }

    public function arsenal(): void
    {
        require_student_auth();

        $student = current_student();
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
        $data = $this->portal->progress((int) $student['id']);

        $this->render('student_portal/progress', [
            'title' => 'Progresso',
            'student' => $student,
            'summary' => $data['summary'],
            'courses' => $data['courses'],
        ], 'layouts/student');
    }

    public function exams(): void
    {
        require_student_auth();

        $student = current_student();
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

    public function calendar(): void
    {
        require_student_auth();

        $student = current_student();
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
}
