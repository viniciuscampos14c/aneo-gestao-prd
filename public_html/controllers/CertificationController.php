<?php

class CertificationController extends BaseController
{
    private StudentModel $students;
    private StudentPortalModel $portal;
    private ?bool $trialAccessTableExists = null;

    public function __construct()
    {
        $this->students = new StudentModel();
        $this->portal = new StudentPortalModel();
    }

    public function index(): void
    {
        require_auth();
        require_permission('certification');

        $filters = [
            'q' => trim((string) request('q', '')),
            'is_active' => '',
            'kanban_status_id' => '',
        ];

        $perPage = 25;
        $page = max(1, (int) request('page', 1));
        $result = $this->students->list($filters, $perPage, $page);
        $rows = $this->filterCertificationStudents($result['rows'] ?? []);

        $selectedStudentId = (int) request('student_id', 0);
        if ($selectedStudentId <= 0 && $rows !== []) {
            $selectedStudentId = (int) ($rows[0]['id'] ?? 0);
        }

        $selectedStudent = null;
        $documents = [];
        $profile = [];
        $courses = [];
        $history = [];
        $terms = [];
        $summary = [
            'ra' => '',
            'approved_count' => 0,
            'failed_count' => 0,
            'total_results' => 0,
            'average_score' => 0.0,
            'total_workload' => 0.0,
        ];

        if ($selectedStudentId > 0) {
            if ($this->isTrialStudent($selectedStudentId)) {
                $this->error('Alunos provenientes de degustacao nao aparecem no perfil certificador.');
                $this->redirect('certification');
            }

            $selectedStudent = $this->students->find($selectedStudentId);

            if (!$selectedStudent) {
                $this->error('Aluno nao encontrado para certificacao.');
                $this->redirect('certification');
            }

            $documents = $this->students->documents($selectedStudentId);
            $profile = $this->portal->studentAcademicProfile($selectedStudentId) ?? [];
            $courses = $this->portal->myCourses($selectedStudentId);
            $history = $this->portal->academicHistoryRecords($selectedStudentId);
            [$terms, $summary] = $this->buildAcademicHistoryPayload($selectedStudentId, $profile, $courses, $history);
        }

        $this->render('certification/index', [
            'title' => 'Certificacao',
            'filters' => $filters,
            'students' => $rows,
            'meta' => $result['meta'] ?? [],
            'selectedStudentId' => $selectedStudentId,
            'selectedStudent' => $selectedStudent,
            'documents' => $documents,
            'profile' => $profile,
            'courses' => $courses,
            'history' => $history,
            'terms' => $terms,
            'summary' => $summary,
        ]);
    }

    public function academicHistory(): void
    {
        require_auth();
        require_permission('certification');

        $studentId = (int) request('student_id', 0);
        if ($studentId <= 0) {
            $this->error('Aluno invalido para emitir historico academico.');
            $this->redirect('certification');
        }

        if ($this->isTrialStudent($studentId)) {
            $this->error('Alunos provenientes de degustacao nao possuem historico disponivel neste perfil.');
            $this->redirect('certification');
        }

        $student = $this->students->find($studentId);
        if (!$student) {
            $this->error('Aluno nao encontrado para certificacao.');
            $this->redirect('certification');
        }

        $profile = $this->portal->studentAcademicProfile($studentId) ?? [];
        $history = $this->portal->academicHistoryRecords($studentId);
        $courses = $this->portal->myCourses($studentId);
        [$terms, $summary] = $this->buildAcademicHistoryPayload($studentId, $profile, $courses, $history);

        $this->render('student_portal/academic_history', [
            'title' => 'Historico Academico',
            'student' => $student,
            'profile' => $profile,
            'ra' => (string) ($summary['ra'] ?? ''),
            'terms' => $terms,
            'totalResults' => (int) ($summary['total_results'] ?? 0),
            'approvedCount' => (int) ($summary['approved_count'] ?? 0),
            'failedCount' => (int) ($summary['failed_count'] ?? 0),
            'averageScore' => (float) ($summary['average_score'] ?? 0),
            'totalWorkload' => (float) ($summary['total_workload'] ?? 0),
            'issuedAt' => now(),
            'backRoute' => route('certification&student_id=' . $studentId),
            'backLabel' => 'Voltar para Certificacao',
            'issuedByLabel' => 'Documento emitido automaticamente pela area de Certificacao em ',
        ]);
    }

    private function buildAcademicHistoryPayload(int $studentId, array $profile, array $courses, array $history): array
    {
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
        $approvedCount = 0;
        $sumScores = 0.0;

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
            $terms[$termKey]['rows'][] = [
                'date' => $submittedAt,
                'course_name' => $courseName,
                'exam_title' => trim((string) ($row['exam_title'] ?? '')),
                'score' => (float) ($row['score'] ?? 0),
                'status' => (string) ($row['status'] ?? 'failed'),
                'workload' => (float) ($courseWorkload[strtolower($courseName)] ?? 0),
            ];

            $sumScores += (float) ($row['score'] ?? 0);
            if ((string) ($row['status'] ?? '') === 'approved') {
                $approvedCount++;
            }
        }

        $termList = array_values($terms);
        usort($termList, static function (array $a, array $b): int {
            return strcmp((string) ($b['sort_key'] ?? ''), (string) ($a['sort_key'] ?? ''));
        });

        $totalResults = count($history);
        $ra = trim((string) ($profile['ra'] ?? ''));
        if ($ra === '') {
            $ra = str_pad((string) $studentId, 9, '0', STR_PAD_LEFT);
        }

        return [
            $termList,
            [
                'ra' => $ra,
                'approved_count' => $approvedCount,
                'failed_count' => max(0, $totalResults - $approvedCount),
                'total_results' => $totalResults,
                'average_score' => $totalResults > 0 ? ($sumScores / $totalResults) : 0.0,
                'total_workload' => $totalWorkload,
            ],
        ];
    }

    private function filterCertificationStudents(array $rows): array
    {
        if ($rows === [] || !$this->trialAccessFeatureAvailable()) {
            return $rows;
        }

        $studentIds = [];
        foreach ($rows as $row) {
            $studentId = (int) ($row['id'] ?? 0);
            if ($studentId > 0) {
                $studentIds[] = $studentId;
            }
        }

        if ($studentIds === []) {
            return $rows;
        }

        $studentIds = array_values(array_unique($studentIds));
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $stmt = db()->prepare("SELECT DISTINCT student_id
            FROM course_trial_accesses
            WHERE student_id IN ({$placeholders})");
        $stmt->execute($studentIds);
        $trialIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        $trialMap = array_fill_keys($trialIds, true);

        return array_values(array_filter($rows, static function (array $row) use ($trialMap): bool {
            $studentId = (int) ($row['id'] ?? 0);
            return $studentId > 0 && !isset($trialMap[$studentId]);
        }));
    }

    private function isTrialStudent(int $studentId): bool
    {
        if ($studentId <= 0 || !$this->trialAccessFeatureAvailable()) {
            return false;
        }

        $stmt = db()->prepare('SELECT 1 FROM course_trial_accesses WHERE student_id = :student_id LIMIT 1');
        $stmt->execute([':student_id' => $studentId]);
        return (bool) $stmt->fetchColumn();
    }

    private function trialAccessFeatureAvailable(): bool
    {
        if ($this->trialAccessTableExists !== null) {
            return $this->trialAccessTableExists;
        }

        $stmt = db()->prepare("SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'course_trial_accesses'");
        $stmt->execute();
        $this->trialAccessTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->trialAccessTableExists;
    }
}
