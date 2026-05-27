<?php

class StudentPortalModel extends BaseModel
{
    private ?bool $portalAccountsTableExists = null;
    private ?bool $examSubmissionsTableExists = null;
    private ?bool $examSubmissionAnswersTableExists = null;
    private ?bool $examScheduleColumnExists = null;
    private ?bool $studentProfilePhotoColumnExists = null;
    private ?bool $courseCompanyColumnExists = null;
    private ?bool $arsenalItemsTableExists = null;
    private ?bool $arsenalCategoriesTableExists = null;
    private ?bool $arsenalItemCoursesTableExists = null;
    private ?bool $arsenalItemStudentsTableExists = null;
    private ?bool $arsenalAccessLogsTableExists = null;
    private ?bool $trialAccessTableExists = null;
    private ?bool $courseModulesTableExists = null;
    private ?bool $courseLessonsTableExists = null;
    private ?bool $studentLessonProgressTableExists = null;
    private ?bool $examExternalLinksTableExists = null;
    private ?bool $examInternalLinksTableExists = null;
    private ?bool $courseCommentsTableExists = null;
    private ?bool $studentDutyFeatureAvailable = null;
    private ?bool $studentPortalNotificationsTableExists = null;

    public function portalFeatureAvailable(): bool
    {
        return $this->hasPortalAccountsTable();
    }

    public function examSubmissionFeatureAvailable(): bool
    {
        return $this->hasExamSubmissionsTable() && $this->hasExamSubmissionAnswersTable();
    }

    public function examScheduleFeatureAvailable(): bool
    {
        return $this->hasExamScheduleColumn();
    }

    public function arsenalFeatureAvailable(): bool
    {
        return $this->hasArsenalItemsTable()
            && $this->hasArsenalCategoriesTable()
            && $this->hasArsenalItemCoursesTable()
            && $this->hasArsenalItemStudentsTable();
    }

    public function lmsFeatureAvailable(): bool
    {
        return $this->hasCourseModulesTable()
            && $this->hasCourseLessonsTable()
            && $this->hasStudentLessonProgressTable();
    }

    public function studentDutyScheduleFeatureAvailable(): bool
    {
        if ($this->studentDutyFeatureAvailable !== null) {
            return $this->studentDutyFeatureAvailable;
        }

        return $this->studentDutyFeatureAvailable =
            $this->schemaTableExists('student_practice_units')
            && $this->schemaTableExists('student_duty_schedules')
            && $this->schemaTableExists('student_duty_schedule_weeks')
            && $this->schemaTableExists('student_duty_assignments');
    }

    public function studentPortalNotificationsFeatureAvailable(): bool
    {
        if ($this->studentPortalNotificationsTableExists !== null) {
            return $this->studentPortalNotificationsTableExists;
        }

        return $this->studentPortalNotificationsTableExists = $this->schemaTableExists('student_portal_notifications');
    }

    public function courseCommentsFeatureAvailable(): bool
    {
        if ($this->courseCommentsTableExists !== null) {
            return $this->courseCommentsTableExists;
        }

        return $this->courseCommentsTableExists = $this->schemaTableExists('course_comments');
    }

    public function listCourseCommentsForStudent(int $studentId, int $courseId, int $limit = 12): array
    {
        if ($studentId <= 0 || $courseId <= 0 || !$this->courseCommentsFeatureAvailable()) {
            return [];
        }

        $course = $this->findStudentCourse($studentId, $courseId);
        if (!$course) {
            return [];
        }

        $companyId = $this->resolveStudentCompanyId($studentId);
        $limit = max(1, min(50, $limit));

        $sql = "SELECT
                cm.id,
                cm.course_id,
                cm.comment,
                cm.created_at,
                u.name AS author_name
            FROM course_comments cm
            INNER JOIN courses c ON c.id = cm.course_id
            LEFT JOIN users u ON u.id = cm.created_by
            WHERE cm.course_id = :course_id";
        $params = [':course_id' => $courseId];

        if ($this->hasCourseCompanyColumn() && $companyId !== null && $companyId > 0) {
            $sql .= ' AND c.company_id = :company_id';
            $params[':company_id'] = $companyId;
        }

        $sql .= " ORDER BY cm.id DESC
            LIMIT {$limit}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    public function notifyStudentsAboutCourseComment(int $courseId, string $comment, ?int $authorId = null): int
    {
        if ($courseId <= 0 || !$this->studentPortalNotificationsFeatureAvailable()) {
            return 0;
        }

        $comment = trim($comment);
        if ($comment === '') {
            return 0;
        }

        $course = $this->findCourseForNotifications($courseId);
        if (!$course) {
            return 0;
        }

        $students = $this->listStudentsToNotifyForCourse($courseId, (int) ($course['company_id'] ?? 0));
        if ($students === []) {
            return 0;
        }

        $authorName = $this->resolveUserName($authorId);
        $title = 'Novo comentario em ' . trim((string) ($course['name'] ?? 'seu curso'));
        $messagePrefix = $authorName !== '' ? $authorName . ' comentou: ' : 'Novo recado da equipe: ';
        $normalizedComment = preg_replace('/\s+/', ' ', $comment) ?? $comment;
        $message = $messagePrefix . mb_strimwidth($normalizedComment, 0, 220, '...');
        $linkUrl = route('student/course&course_id=' . $courseId);

        $created = 0;
        foreach ($students as $student) {
            $studentId = (int) ($student['id'] ?? 0);
            $companyId = (int) ($student['company_id'] ?? 0);
            if ($studentId <= 0 || $companyId <= 0) {
                continue;
            }

            $notificationId = $this->createPortalNotification([
                'company_id' => $companyId,
                'student_id' => $studentId,
                'notification_type' => 'course_comment',
                'title' => $title,
                'message' => $message,
                'link_url' => $linkUrl,
                'meta' => [
                    'course_id' => $courseId,
                    'course_name' => (string) ($course['name'] ?? ''),
                    'comment_excerpt' => $normalizedComment,
                ],
            ]);

            if ($notificationId > 0) {
                $created++;
            }
        }

        return $created;
    }

    public function myDutySchedule(int $studentId, bool $onlyPublished = true): array
    {
        if ($studentId <= 0 || !$this->studentDutyScheduleFeatureAvailable()) {
            return [];
        }

        $companyId = $this->resolveStudentCompanyId($studentId);
        $sql = "SELECT
                s.id AS schedule_id,
                s.title AS schedule_title,
                s.status AS schedule_status,
                s.start_date AS schedule_start_date,
                s.end_date AS schedule_end_date,
                u.name AS unit_name,
                u.city AS unit_city,
                u.state AS unit_state,
                w.id AS week_id,
                w.month_ref,
                w.week_order,
                w.start_date AS week_start_date,
                w.end_date AS week_end_date,
                w.notes AS week_notes,
                a.slot_group,
                a.position_order
            FROM student_duty_assignments a
            INNER JOIN student_duty_schedule_weeks w ON w.id = a.schedule_week_id
            INNER JOIN student_duty_schedules s ON s.id = w.schedule_id
            INNER JOIN student_practice_units u ON u.id = s.unit_id
            WHERE a.student_id = :student_id";
        $params = [':student_id' => $studentId];

        if ($companyId !== null && $companyId > 0) {
            $sql .= ' AND s.company_id = :company_id';
            $params[':company_id'] = $companyId;
        }

        if ($onlyPublished) {
            $sql .= " AND s.status = 'published'";
        }

        $sql .= ' ORDER BY w.start_date ASC, a.slot_group ASC, a.position_order ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    public function createPortalNotification(array $data): int
    {
        if (!$this->studentPortalNotificationsFeatureAvailable()) {
            return 0;
        }

        if ($this->portalNotificationExists($data)) {
            return 0;
        }

        $stmt = $this->db->prepare('INSERT INTO student_portal_notifications (
            company_id, student_id, notification_type, title, message, link_url, meta_json, is_read, created_at, read_at
        ) VALUES (
            :company_id, :student_id, :notification_type, :title, :message, :link_url, :meta_json, 0, :created_at, NULL
        )');
        $stmt->execute([
            ':company_id' => (int) ($data['company_id'] ?? 0),
            ':student_id' => (int) ($data['student_id'] ?? 0),
            ':notification_type' => trim((string) ($data['notification_type'] ?? 'general')),
            ':title' => trim((string) ($data['title'] ?? 'Notificacao')),
            ':message' => trim((string) ($data['message'] ?? '')),
            ':link_url' => trim((string) ($data['link_url'] ?? '')) ?: null,
            ':meta_json' => !empty($data['meta']) ? json_encode($data['meta'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            ':created_at' => now(),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function listRecentPortalNotifications(int $studentId, int $limit = 5): array
    {
        if ($studentId <= 0 || !$this->studentPortalNotificationsFeatureAvailable()) {
            return [];
        }

        $limit = max(1, min(50, $limit));
        $stmt = $this->db->prepare("SELECT *
            FROM student_portal_notifications
            WHERE student_id = :student_id
            ORDER BY is_read ASC, created_at DESC, id DESC
            LIMIT {$limit}");
        $stmt->execute([':student_id' => $studentId]);
        return $stmt->fetchAll() ?: [];
    }

    public function countUnreadPortalNotifications(int $studentId): int
    {
        if ($studentId <= 0 || !$this->studentPortalNotificationsFeatureAvailable()) {
            return 0;
        }

        $stmt = $this->db->prepare('SELECT COUNT(*)
            FROM student_portal_notifications
            WHERE student_id = :student_id
              AND is_read = 0');
        $stmt->execute([':student_id' => $studentId]);
        return (int) $stmt->fetchColumn();
    }

    public function markPortalNotificationsAsReadByType(int $studentId, string $type): void
    {
        if ($studentId <= 0 || !$this->studentPortalNotificationsFeatureAvailable()) {
            return;
        }

        $stmt = $this->db->prepare('UPDATE student_portal_notifications
            SET is_read = 1,
                read_at = :read_at
            WHERE student_id = :student_id
              AND notification_type = :notification_type
              AND is_read = 0');
        $stmt->execute([
            ':read_at' => now(),
            ':student_id' => $studentId,
            ':notification_type' => trim($type),
        ]);
    }

    public function markAllPortalNotificationsAsRead(int $studentId): void
    {
        if ($studentId <= 0 || !$this->studentPortalNotificationsFeatureAvailable()) {
            return;
        }

        $stmt = $this->db->prepare('UPDATE student_portal_notifications
            SET is_read = 1,
                read_at = :read_at
            WHERE student_id = :student_id
              AND is_read = 0');
        $stmt->execute([
            ':read_at' => now(),
            ':student_id' => $studentId,
        ]);
    }

    private function portalNotificationExists(array $data): bool
    {
        $stmt = $this->db->prepare('SELECT id
            FROM student_portal_notifications
            WHERE company_id = :company_id
              AND student_id = :student_id
              AND notification_type = :notification_type
              AND title = :title
              AND message = :message
            LIMIT 1');
        $stmt->execute([
            ':company_id' => (int) ($data['company_id'] ?? 0),
            ':student_id' => (int) ($data['student_id'] ?? 0),
            ':notification_type' => trim((string) ($data['notification_type'] ?? 'general')),
            ':title' => trim((string) ($data['title'] ?? 'Notificacao')),
            ':message' => trim((string) ($data['message'] ?? '')),
        ]);

        return (bool) $stmt->fetch();
    }

    public function findAccountByLogin(string $login): ?array
    {
        if (!$this->hasPortalAccountsTable()) {
            return null;
        }

        $photoSelect = $this->hasStudentProfilePhotoColumn() ? 's.profile_photo,' : 'NULL AS profile_photo,';

        $stmt = $this->db->prepare("SELECT
                spa.id,
                spa.student_id,
                spa.login,
                spa.password_hash,
                spa.is_active,
                s.company_id,
                s.full_name,
                s.email_primary,
                s.phone,
                {$photoSelect}
                s.is_active AS student_is_active
            FROM student_portal_accounts spa
            INNER JOIN students s ON s.id = spa.student_id
            WHERE (spa.login = :login OR s.email_primary = :login)
            LIMIT 1");
        $stmt->execute([':login' => $login]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function updateLastLogin(int $accountId): void
    {
        if (!$this->hasPortalAccountsTable()) {
            return;
        }

        $stmt = $this->db->prepare('UPDATE student_portal_accounts SET last_login_at = :last_login_at, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            ':last_login_at' => now(),
            ':updated_at' => now(),
            ':id' => $accountId,
        ]);
    }

    public function trialAccessContext(int $studentId, ?string $referenceDate = null): array
    {
        $context = [
            'is_trial' => false,
            'allowed_today' => false,
            'course_id' => 0,
            'course_name' => '',
            'access_date' => '',
            'status' => '',
            'access_scope' => '',
        ];

        if ($studentId <= 0 || !$this->hasTrialAccessTable()) {
            return $context;
        }

        $referenceDate = trim((string) $referenceDate);
        if ($referenceDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $referenceDate)) {
            $referenceDate = date('Y-m-d');
        }

        $stmt = $this->db->prepare("SELECT
                ta.id,
                ta.course_id,
                ta.access_date,
                ta.access_scope,
                ta.status,
                ta.last_login_at,
                c.name AS course_name
            FROM course_trial_accesses ta
            INNER JOIN courses c ON c.id = ta.course_id
            WHERE ta.student_id = :student_id
            ORDER BY ta.id DESC
            LIMIT 1");
        $stmt->execute([':student_id' => $studentId]);
        $row = $stmt->fetch();
        if (!$row) {
            return $context;
        }

        $status = trim((string) ($row['status'] ?? ''));
        $accessDate = trim((string) ($row['access_date'] ?? ''));

        if ($status === 'active' && $accessDate !== '' && $accessDate < $referenceDate) {
            $expireStmt = $this->db->prepare('UPDATE course_trial_accesses SET status = :status, updated_at = :updated_at WHERE id = :id');
            $expireStmt->execute([
                ':status' => 'expired',
                ':updated_at' => now(),
                ':id' => (int) $row['id'],
            ]);
            $status = 'expired';
        }

        $context['is_trial'] = true;
        $context['allowed_today'] = $status === 'active' && $accessDate === $referenceDate;
        $context['course_id'] = (int) ($row['course_id'] ?? 0);
        $context['course_name'] = trim((string) ($row['course_name'] ?? ''));
        $context['access_date'] = $accessDate;
        $context['status'] = $status;
        $context['access_scope'] = trim((string) ($row['access_scope'] ?? ''));

        return $context;
    }

    public function registerTrialLogin(int $studentId): void
    {
        if ($studentId <= 0 || !$this->hasTrialAccessTable()) {
            return;
        }

        $today = date('Y-m-d');
        $stmt = $this->db->prepare('UPDATE course_trial_accesses
            SET last_login_at = :last_login_at, updated_at = :updated_at
            WHERE student_id = :student_id
              AND status = :status
              AND access_date = :access_date');
        $stmt->execute([
            ':last_login_at' => now(),
            ':updated_at' => now(),
            ':student_id' => $studentId,
            ':status' => 'active',
            ':access_date' => $today,
        ]);
    }

    public function dashboardSummary(int $studentId): array
    {
        $this->syncStudentEnrollmentProgress($studentId);

        $trial = $this->trialAccessContext($studentId);
        if (!empty($trial['is_trial'])) {
            $upcomingLive = [];
            if (!empty($trial['allowed_today'])) {
                $upcomingLive = $this->trialLiveClasses(
                    $studentId,
                    (int) ($trial['course_id'] ?? 0),
                    (string) ($trial['access_date'] ?? '')
                );
            }

            return [
                'metrics' => [
                    'courses_total' => $upcomingLive === [] ? 0 : 1,
                    'courses_active' => $upcomingLive === [] ? 0 : 1,
                    'courses_completed' => 0,
                    'avg_progress' => 0,
                ],
                'learning_focus' => $this->emptyLearningFocus(),
                'upcoming_live' => $upcomingLive,
                'recent_results' => [],
                'upcoming_exams' => [],
            ];
        }

        $companyId = $this->resolveStudentCompanyId($studentId);
        $referenceNow = $this->appNow();

        $metricsSql = "SELECT
                COUNT(*) AS courses_total,
                SUM(CASE WHEN e.status = 'active' THEN 1 ELSE 0 END) AS courses_active,
                SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) AS courses_completed,
                AVG(e.progress_percent) AS avg_progress
            FROM enrollments e
            INNER JOIN courses c ON c.id = e.course_id
            WHERE e.student_id = :student_id
              AND c.status = 'published'";
        $metricsParams = [':student_id' => $studentId];
        if ($this->hasCourseCompanyColumn() && $companyId !== null && $companyId > 0) {
            $metricsSql .= ' AND c.company_id = :company_id';
            $metricsParams[':company_id'] = $companyId;
        }
        $metricsStmt = $this->db->prepare($metricsSql);
        $metricsStmt->execute($metricsParams);
        $metrics = $metricsStmt->fetch() ?: [];

        $upcomingSql = "SELECT
                c.id,
                c.name,
                c.live_link,
                c.live_password,
                c.live_meeting_id,
                c.live_datetime
            FROM enrollments e
            INNER JOIN courses c ON c.id = e.course_id
            WHERE e.student_id = :student_id
              AND e.status = 'active'
              AND c.status = 'published'
              AND c.live_link IS NOT NULL
              AND c.live_link <> ''
              AND c.live_datetime IS NOT NULL
              AND DATE_ADD(c.live_datetime, INTERVAL 120 MINUTE) >= :reference_now";
        $upcomingParams = [
            ':student_id' => $studentId,
            ':reference_now' => $referenceNow,
        ];
        if ($this->hasCourseCompanyColumn() && $companyId !== null && $companyId > 0) {
            $upcomingSql .= ' AND c.company_id = :company_id';
            $upcomingParams[':company_id'] = $companyId;
        }
        $upcomingSql .= ' ORDER BY c.live_datetime ASC LIMIT 3';
        $upcomingStmt = $this->db->prepare($upcomingSql);
        $upcomingStmt->execute($upcomingParams);

        $recentSql = "SELECT
                r.score,
                r.status,
                r.submitted_at,
                ex.title AS exam_title,
                c.name AS course_name
            FROM exam_results r
            INNER JOIN exams ex ON ex.id = r.exam_id
            INNER JOIN courses c ON c.id = ex.course_id
            WHERE r.student_id = :student_id";
        $recentParams = [':student_id' => $studentId];
        if ($this->hasCourseCompanyColumn() && $companyId !== null && $companyId > 0) {
            $recentSql .= ' AND c.company_id = :company_id';
            $recentParams[':company_id'] = $companyId;
        }
        $recentSql .= ' ORDER BY r.submitted_at DESC, r.id DESC LIMIT 5';
        $recentResultsStmt = $this->db->prepare($recentSql);
        $recentResultsStmt->execute($recentParams);

        $upcomingExams = [];
        if ($this->hasExamScheduleColumn()) {
            $upcomingExamsSql = "SELECT
                    ex.id,
                    ex.title,
                    ex.scheduled_at,
                    ex.passing_score,
                    c.name AS course_name
                FROM exams ex
                INNER JOIN courses c ON c.id = ex.course_id
                INNER JOIN enrollments e ON e.course_id = c.id
                WHERE e.student_id = :student_id
                  AND e.status IN ('active', 'completed')
                  AND c.status = 'published'
                  AND ex.scheduled_at IS NOT NULL
                  AND ex.scheduled_at >= NOW()";
            $upcomingExamsParams = [':student_id' => $studentId];
            if ($this->hasCourseCompanyColumn() && $companyId !== null && $companyId > 0) {
                $upcomingExamsSql .= ' AND c.company_id = :company_id';
                $upcomingExamsParams[':company_id'] = $companyId;
            }
            $upcomingExamsSql .= ' ORDER BY ex.scheduled_at ASC LIMIT 6';
            $upcomingExamsStmt = $this->db->prepare($upcomingExamsSql);
            $upcomingExamsStmt->execute($upcomingExamsParams);
            $upcomingExams = $upcomingExamsStmt->fetchAll();
        }

        return [
            'metrics' => [
                'courses_total' => (int) ($metrics['courses_total'] ?? 0),
                'courses_active' => (int) ($metrics['courses_active'] ?? 0),
                'courses_completed' => (int) ($metrics['courses_completed'] ?? 0),
                'avg_progress' => (float) ($metrics['avg_progress'] ?? 0),
            ],
            'learning_focus' => $this->dashboardLearningFocus($studentId),
            'upcoming_live' => $upcomingStmt->fetchAll(),
            'recent_results' => $recentResultsStmt->fetchAll(),
            'upcoming_exams' => $upcomingExams,
        ];
    }

    public function myCourses(int $studentId): array
    {
        $this->syncStudentEnrollmentProgress($studentId);

        $companyId = $this->resolveStudentCompanyId($studentId);
        $modulesSelect = $this->lmsFeatureAvailable()
            ? '(SELECT COUNT(*) FROM course_modules cm WHERE cm.course_id = c.id AND cm.is_active = 1) AS modules_total,'
            : '0 AS modules_total,';

        $sql = "SELECT
                e.id,
                e.status AS enrollment_status,
                e.progress_percent,
                e.started_at,
                e.completed_at,
                c.id AS course_id,
                c.name,
                c.description,
                c.cover_image,
                c.workload_hours,
                c.live_link,
                c.live_password,
                c.live_meeting_id,
                c.live_datetime,
                {$modulesSelect}
                cat.name AS category_name
            FROM enrollments e
            INNER JOIN courses c ON c.id = e.course_id
            LEFT JOIN course_categories cat ON cat.id = c.category_id
            WHERE e.student_id = :student_id
              AND c.status = 'published'";
        $params = [':student_id' => $studentId];
        if ($this->hasCourseCompanyColumn() && $companyId !== null && $companyId > 0) {
            $sql .= ' AND c.company_id = :company_id';
            $params[':company_id'] = $companyId;
        }
        $sql .= ' ORDER BY c.name ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    private function emptyLearningFocus(): array
    {
        return [
            'has_course' => false,
            'all_done' => false,
            'course' => null,
            'module' => null,
            'lesson' => null,
            'completion_percent' => 0,
            'completion_remaining' => 100,
            'cta_url' => route('student/courses'),
        ];
    }

    private function dashboardLearningFocus(int $studentId): array
    {
        if ($studentId <= 0 || !$this->lmsFeatureAvailable()) {
            return $this->emptyLearningFocus();
        }

        $courses = array_values(array_filter($this->myCourses($studentId), static function (array $course): bool {
            return (int) ($course['modules_total'] ?? 0) > 0
                && in_array((string) ($course['enrollment_status'] ?? ''), ['active', 'completed'], true);
        }));

        if ($courses === []) {
            return $this->emptyLearningFocus();
        }

        usort($courses, static function (array $a, array $b): int {
            $aCompleted = (string) ($a['enrollment_status'] ?? '') === 'completed';
            $bCompleted = (string) ($b['enrollment_status'] ?? '') === 'completed';
            if ($aCompleted !== $bCompleted) {
                return $aCompleted ? 1 : -1;
            }

            $aProgress = (int) ($a['progress_percent'] ?? 0);
            $bProgress = (int) ($b['progress_percent'] ?? 0);
            $aStarted = $aProgress > 0;
            $bStarted = $bProgress > 0;
            if ($aStarted !== $bStarted) {
                return $aStarted ? -1 : 1;
            }

            return $bProgress <=> $aProgress;
        });

        $completedFallback = null;
        foreach ($courses as $course) {
            $courseId = (int) ($course['course_id'] ?? 0);
            if ($courseId <= 0) {
                continue;
            }

            $path = $this->courseLearningPath($studentId, $courseId);
            if (!$path) {
                continue;
            }

            $summary = is_array($path['summary'] ?? null) ? $path['summary'] : [];
            $progress = max(0, min(100, (int) ($summary['progress_percent'] ?? $course['progress_percent'] ?? 0)));
            $selectedLesson = is_array($path['selected_lesson'] ?? null) ? $path['selected_lesson'] : null;
            $selectedModule = null;

            if ($selectedLesson) {
                foreach (($path['modules'] ?? []) as $module) {
                    if ((int) ($module['id'] ?? 0) === (int) ($selectedLesson['module_id'] ?? 0)) {
                        $selectedModule = $module;
                        break;
                    }
                }
            }

            $focus = [
                'has_course' => true,
                'all_done' => !empty($summary['course_completed']),
                'course' => [
                    'id' => $courseId,
                    'name' => (string) ($course['name'] ?? 'Curso'),
                    'progress_percent' => $progress,
                    'enrollment_status' => (string) ($course['enrollment_status'] ?? ''),
                ],
                'module' => $selectedModule ? [
                    'id' => (int) ($selectedModule['id'] ?? 0),
                    'title' => (string) ($selectedModule['title'] ?? 'Modulo atual'),
                    'display_order' => (int) ($selectedModule['display_order'] ?? 0),
                    'completed_lessons' => (int) ($selectedModule['completed_lessons'] ?? 0),
                    'total_lessons' => (int) ($selectedModule['total_lessons'] ?? 0),
                ] : null,
                'lesson' => $selectedLesson ? [
                    'id' => (int) ($selectedLesson['id'] ?? 0),
                    'title' => (string) ($selectedLesson['title'] ?? 'Proxima aula'),
                    'progress_percent' => (int) ($selectedLesson['progress_percent'] ?? 0),
                    'required_percent' => (int) ($selectedLesson['min_progress_percent'] ?? 70),
                ] : null,
                'completion_percent' => $progress,
                'completion_remaining' => max(0, 100 - $progress),
                'cta_url' => $selectedLesson
                    ? route('student/course&course_id=' . $courseId . '&lesson_id=' . (int) ($selectedLesson['id'] ?? 0))
                    : route('student/course&course_id=' . $courseId),
            ];

            if (empty($summary['course_completed'])) {
                return $focus;
            }

            $completedFallback = $completedFallback ?? $focus;
        }

        return $completedFallback ?? $this->emptyLearningFocus();
    }

    public function upcomingLiveClasses(int $studentId): array
    {
        $trial = $this->trialAccessContext($studentId);
        if (!empty($trial['is_trial'])) {
            if (empty($trial['allowed_today'])) {
                return [];
            }

            return $this->trialLiveClasses(
                $studentId,
                (int) ($trial['course_id'] ?? 0),
                (string) ($trial['access_date'] ?? '')
            );
        }

        $companyId = $this->resolveStudentCompanyId($studentId);
        $referenceNow = $this->appNow();

        // Verifica se a tabela de aulas Zoom existe (migration aplicada)
        $hasLiveSessions = $this->courseLiveSessionsTableExists();

        if ($hasLiveSessions) {
            // Fonte 1: nova tabela course_live_sessions (criadas via API Zoom)
            $sqlNew = "SELECT
                    cls.id,
                    cls.title AS name,
                    c.name AS course_name,
                    cls.join_url AS live_link,
                    cls.zoom_password AS live_password,
                    cls.zoom_meeting_id AS live_meeting_id,
                    cls.scheduled_at AS live_datetime,
                    cls.duration_minutes,
                    'zoom' AS platform
                FROM course_live_sessions cls
                INNER JOIN courses c ON c.id = cls.course_id
                INNER JOIN enrollments e ON e.course_id = cls.course_id
                WHERE e.student_id = :student_id
                  AND e.status = 'active'
                  AND c.status = 'published'
                  AND cls.status = 'scheduled'
                  AND DATE_ADD(cls.scheduled_at, INTERVAL COALESCE(NULLIF(cls.duration_minutes, 0), 120) MINUTE) >= :reference_now";
            $paramsNew = [
                ':student_id' => $studentId,
                ':reference_now' => $referenceNow,
            ];

            // Fonte 2: legado (courses.live_link preenchido manualmente)
            $sqlLegacy = "SELECT
                    c.id,
                    c.name,
                    c.name AS course_name,
                    c.live_link,
                    c.live_password,
                    c.live_meeting_id,
                    c.live_datetime,
                    NULL AS duration_minutes,
                    'zoom' AS platform
                FROM enrollments e
                INNER JOIN courses c ON c.id = e.course_id
                WHERE e.student_id = :student_id
                  AND e.status = 'active'
                  AND c.status = 'published'
                  AND c.live_link IS NOT NULL
                  AND c.live_link <> ''
                  AND c.live_datetime IS NOT NULL
                  AND DATE_ADD(c.live_datetime, INTERVAL 120 MINUTE) >= :reference_now";
            $paramsLegacy = [
                ':student_id' => $studentId,
                ':reference_now' => $referenceNow,
            ];

            if ($this->hasCourseCompanyColumn() && $companyId !== null && $companyId > 0) {
                $sqlNew .= ' AND c.company_id = :company_id';
                $sqlLegacy .= ' AND c.company_id = :company_id';
                $paramsNew[':company_id'] = $companyId;
                $paramsLegacy[':company_id'] = $companyId;
            }

            $stmtNew = $this->db->prepare($sqlNew);
            $stmtNew->execute($paramsNew);
            $rowsNew = $stmtNew->fetchAll();

            $stmtLegacy = $this->db->prepare($sqlLegacy);
            $stmtLegacy->execute($paramsLegacy);
            $rowsLegacy = $stmtLegacy->fetchAll();

            $rows = array_merge($rowsNew, $rowsLegacy);
            usort($rows, static function (array $a, array $b): int {
                $aTs = strtotime((string) ($a['live_datetime'] ?? '')) ?: 0;
                $bTs = strtotime((string) ($b['live_datetime'] ?? '')) ?: 0;
                return $aTs <=> $bTs;
            });

            return $rows;
        }

        // Fallback: apenas legado
        $sql = "SELECT
                c.id,
                c.name,
                c.name AS course_name,
                c.live_link,
                c.live_password,
                c.live_meeting_id,
                c.live_datetime,
                NULL AS duration_minutes,
                'zoom' AS platform
            FROM enrollments e
            INNER JOIN courses c ON c.id = e.course_id
            WHERE e.student_id = :student_id
              AND e.status = 'active'
              AND c.status = 'published'
              AND c.live_link IS NOT NULL
              AND c.live_link <> ''
              AND c.live_datetime IS NOT NULL
              AND DATE_ADD(c.live_datetime, INTERVAL 120 MINUTE) >= :reference_now";
        $params = [
            ':student_id' => $studentId,
            ':reference_now' => $referenceNow,
        ];
        if ($this->hasCourseCompanyColumn() && $companyId !== null && $companyId > 0) {
            $sql .= ' AND c.company_id = :company_id';
            $params[':company_id'] = $companyId;
        }
        $sql .= ' ORDER BY c.live_datetime ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function appNow(): string
    {
        $tzName = (string) config('app.timezone', 'America/Sao_Paulo');
        try {
            $tz = new DateTimeZone($tzName !== '' ? $tzName : 'America/Sao_Paulo');
        } catch (Throwable $e) {
            $tz = new DateTimeZone('America/Sao_Paulo');
        }

        return (new DateTimeImmutable('now', $tz))->format('Y-m-d H:i:s');
    }

    private function courseLiveSessionsTableExists(): bool
    {
        static $checked = null;
        if ($checked !== null) return $checked;
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = 'course_live_sessions'"
        );
        $stmt->execute();
        $checked = ((int) $stmt->fetchColumn()) > 0;
        return $checked;
    }

    public function materials(int $studentId): array
    {
        $companyId = $this->resolveStudentCompanyId($studentId);

        $coursesSql = "SELECT
                c.id,
                c.name,
                c.materials,
                c.updated_at
            FROM enrollments e
            INNER JOIN courses c ON c.id = e.course_id
            WHERE e.student_id = :student_id
              AND c.status = 'published'";
        $coursesParams = [':student_id' => $studentId];
        if ($this->hasCourseCompanyColumn() && $companyId !== null && $companyId > 0) {
            $coursesSql .= ' AND c.company_id = :company_id';
            $coursesParams[':company_id'] = $companyId;
        }
        $coursesSql .= ' ORDER BY c.name ASC';
        $coursesStmt = $this->db->prepare($coursesSql);
        $coursesStmt->execute($coursesParams);

        $uploadsSql = "SELECT
                u.id,
                u.entity_id AS course_id,
                u.file_name,
                u.file_path,
                u.created_at,
                c.name AS course_name
            FROM uploads u
            INNER JOIN courses c ON c.id = u.entity_id
            INNER JOIN enrollments e ON e.course_id = c.id
            WHERE u.entity_type = 'course'
              AND e.student_id = :student_id
              AND c.status = 'published'";
        $uploadsParams = [':student_id' => $studentId];
        if ($this->hasCourseCompanyColumn() && $companyId !== null && $companyId > 0) {
            $uploadsSql .= ' AND c.company_id = :company_id';
            $uploadsParams[':company_id'] = $companyId;
        }
        $uploadsSql .= ' ORDER BY c.name ASC, u.id DESC';
        $uploadsStmt = $this->db->prepare($uploadsSql);
        $uploadsStmt->execute($uploadsParams);

        return [
            'courses' => $coursesStmt->fetchAll(),
            'uploads' => $uploadsStmt->fetchAll(),
        ];
    }

    public function arsenal(int $studentId, array $filters = []): array
    {
        if (!$this->arsenalFeatureAvailable()) {
            return [];
        }

        $companyId = $this->resolveStudentCompanyId($studentId);
        if ($companyId === null || $companyId <= 0) {
            return [];
        }

        $where = [
            'ai.company_id = :company_id',
            "ai.status = 'published'",
            '(ai.publish_start_at IS NULL OR ai.publish_start_at <= NOW())',
            '(ai.publish_end_at IS NULL OR ai.publish_end_at >= NOW())',
            "(
                ai.visibility_scope = 'global'
                OR (
                    ai.visibility_scope = 'student'
                    AND EXISTS (
                        SELECT 1
                        FROM arsenal_item_students ais
                        WHERE ais.company_id = ai.company_id
                          AND ais.arsenal_item_id = ai.id
                          AND ais.student_id = :student_id_scope
                    )
                )
                OR (
                    ai.visibility_scope = 'course'
                    AND EXISTS (
                        SELECT 1
                        FROM arsenal_item_courses aic
                        INNER JOIN enrollments e ON e.course_id = aic.course_id
                        WHERE aic.company_id = ai.company_id
                          AND aic.arsenal_item_id = ai.id
                          AND e.student_id = :student_id_course
                          AND e.status IN ('active', 'completed')
                    )
                )
            )",
        ];
        $params = [
            ':company_id' => $companyId,
            ':student_id_scope' => $studentId,
            ':student_id_course' => $studentId,
        ];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(ai.title LIKE :q OR ai.description LIKE :q OR ai.file_name LIKE :q OR ai.external_url LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

        $materialType = trim((string) ($filters['material_type'] ?? ''));
        if (in_array($materialType, ['file', 'link'], true)) {
            $where[] = 'ai.material_type = :material_type';
            $params[':material_type'] = $materialType;
        }

        $categoryId = (int) ($filters['category_id'] ?? 0);
        if ($categoryId > 0) {
            $where[] = 'ai.category_id = :category_id';
            $params[':category_id'] = $categoryId;
        }

        $sql = 'SELECT
                ai.id,
                ai.category_id,
                ai.title,
                ai.description,
                ai.material_type,
                ai.file_name,
                ai.file_path,
                ai.file_type,
                ai.file_size,
                ai.external_url,
                ai.visibility_scope,
                ai.publish_start_at,
                ai.publish_end_at,
                ai.updated_at,
                ac.name AS category_name
            FROM arsenal_items ai
            LEFT JOIN arsenal_categories ac ON ac.id = ai.category_id AND ac.company_id = ai.company_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY ai.sort_order ASC, ai.updated_at DESC, ai.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function findAccessibleArsenalItem(int $studentId, int $itemId): ?array
    {
        if (!$this->arsenalFeatureAvailable() || $itemId <= 0) {
            return null;
        }

        $companyId = $this->resolveStudentCompanyId($studentId);
        if ($companyId === null || $companyId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare("SELECT
                ai.id,
                ai.company_id,
                ai.title,
                ai.material_type,
                ai.file_name,
                ai.file_path,
                ai.file_type,
                ai.file_size,
                ai.external_url
            FROM arsenal_items ai
            WHERE ai.id = :item_id
              AND ai.company_id = :company_id
              AND ai.status = 'published'
              AND (ai.publish_start_at IS NULL OR ai.publish_start_at <= NOW())
              AND (ai.publish_end_at IS NULL OR ai.publish_end_at >= NOW())
              AND (
                    ai.visibility_scope = 'global'
                    OR (
                        ai.visibility_scope = 'student'
                        AND EXISTS (
                            SELECT 1
                            FROM arsenal_item_students ais
                            WHERE ais.company_id = ai.company_id
                              AND ais.arsenal_item_id = ai.id
                              AND ais.student_id = :student_id_scope
                        )
                    )
                    OR (
                        ai.visibility_scope = 'course'
                        AND EXISTS (
                            SELECT 1
                            FROM arsenal_item_courses aic
                            INNER JOIN enrollments e ON e.course_id = aic.course_id
                            WHERE aic.company_id = ai.company_id
                              AND aic.arsenal_item_id = ai.id
                              AND e.student_id = :student_id_course
                              AND e.status IN ('active', 'completed')
                        )
                    )
              )
            LIMIT 1");
        $stmt->execute([
            ':item_id' => $itemId,
            ':company_id' => $companyId,
            ':student_id_scope' => $studentId,
            ':student_id_course' => $studentId,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function logArsenalAccess(int $studentId, int $itemId, string $action, ?string $ipAddress, ?string $userAgent): void
    {
        if (!$this->hasArsenalAccessLogsTable()) {
            return;
        }

        $companyId = $this->resolveStudentCompanyId($studentId);
        if ($companyId === null || $companyId <= 0 || $itemId <= 0) {
            return;
        }

        $action = trim($action);
        if ($action === '') {
            $action = 'open';
        }

        $stmt = $this->db->prepare('INSERT INTO arsenal_access_logs (
            company_id, arsenal_item_id, student_id, action, ip_address, user_agent, created_at
        ) VALUES (
            :company_id, :item_id, :student_id, :action, :ip_address, :user_agent, :created_at
        )');
        $stmt->execute([
            ':company_id' => $companyId,
            ':item_id' => $itemId,
            ':student_id' => $studentId,
            ':action' => substr($action, 0, 40),
            ':ip_address' => $ipAddress !== null ? substr($ipAddress, 0, 64) : null,
            ':user_agent' => $userAgent !== null ? substr($userAgent, 0, 255) : null,
            ':created_at' => now(),
        ]);
    }

    public function progress(int $studentId): array
    {
        $this->syncStudentEnrollmentProgress($studentId);

        $companyId = $this->resolveStudentCompanyId($studentId);

        $summarySql = "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN e.status = 'active' THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN e.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
                AVG(e.progress_percent) AS avg_progress
            FROM enrollments e
            INNER JOIN courses c ON c.id = e.course_id
            WHERE e.student_id = :student_id
              AND c.status = 'published'";
        $summaryParams = [':student_id' => $studentId];
        if ($this->hasCourseCompanyColumn() && $companyId !== null && $companyId > 0) {
            $summarySql .= ' AND c.company_id = :company_id';
            $summaryParams[':company_id'] = $companyId;
        }
        $summaryStmt = $this->db->prepare($summarySql);
        $summaryStmt->execute($summaryParams);

        $coursesSql = "SELECT
                c.id AS course_id,
                c.name,
                e.status,
                e.progress_percent,
                e.started_at,
                e.completed_at
            FROM enrollments e
            INNER JOIN courses c ON c.id = e.course_id
            WHERE e.student_id = :student_id
              AND c.status = 'published'";
        $coursesParams = [':student_id' => $studentId];
        if ($this->hasCourseCompanyColumn() && $companyId !== null && $companyId > 0) {
            $coursesSql .= ' AND c.company_id = :company_id';
            $coursesParams[':company_id'] = $companyId;
        }
        $coursesSql .= ' ORDER BY c.name ASC';
        $coursesStmt = $this->db->prepare($coursesSql);
        $coursesStmt->execute($coursesParams);

        return [
            'summary' => $summaryStmt->fetch() ?: [
                'total' => 0,
                'active' => 0,
                'completed' => 0,
                'cancelled' => 0,
                'avg_progress' => 0,
            ],
            'courses' => $coursesStmt->fetchAll(),
        ];
    }

    public function courseLearningPath(int $studentId, int $courseId, ?int $preferredLessonId = null): ?array
    {
        if ($studentId <= 0 || $courseId <= 0 || !$this->lmsFeatureAvailable()) {
            return null;
        }

        $course = $this->findStudentCourse($studentId, $courseId);
        if (!$course) {
            return null;
        }

        $progressSync = $this->syncEnrollmentProgressFromLessons($studentId, $courseId);
        $statusMap = $this->moduleStatusMap($studentId, $courseId);

        $moduleStmt = $this->db->prepare('SELECT
                id,
                title,
                description,
                display_order
            FROM course_modules
            WHERE course_id = :course_id
              AND is_active = 1
            ORDER BY display_order ASC, id ASC');
        $moduleStmt->execute([':course_id' => $courseId]);
        $moduleRows = $moduleStmt->fetchAll();

        $lessonStmt = $this->db->prepare('SELECT
                cl.id,
                cl.module_id,
                cl.title,
                cl.description,
                cl.video_url,
                cl.duration_seconds,
                cl.min_progress_percent,
                cl.is_required,
                cl.display_order,
                COALESCE(slp.watched_seconds, 0) AS watched_seconds,
                COALESCE(slp.last_position_seconds, 0) AS last_position_seconds,
                COALESCE(slp.progress_percent, 0) AS progress_percent,
                slp.completed_at
            FROM course_lessons cl
            LEFT JOIN student_lesson_progress slp ON slp.lesson_id = cl.id AND slp.student_id = :student_id
            WHERE cl.course_id = :course_id
              AND cl.is_active = 1
            ORDER BY cl.module_id ASC, cl.display_order ASC, cl.id ASC');
        $lessonStmt->execute([
            ':student_id' => $studentId,
            ':course_id' => $courseId,
        ]);
        $lessonRows = $lessonStmt->fetchAll();

        $lessonsByModule = [];
        foreach ($lessonRows as $lesson) {
            $moduleId = (int) ($lesson['module_id'] ?? 0);
            if (!isset($lessonsByModule[$moduleId])) {
                $lessonsByModule[$moduleId] = [];
            }

            $progressPercent = (int) ($lesson['progress_percent'] ?? 0);
            $threshold = (int) ($lesson['min_progress_percent'] ?? 70);
            if ($threshold <= 0 || $threshold > 100) {
                $threshold = 70;
            }

            $lesson['progress_percent'] = $progressPercent;
            $lesson['min_progress_percent'] = $threshold;
            $lesson['is_completed'] = !empty($lesson['completed_at']);
            $lesson['ready_to_complete'] = empty($lesson['completed_at']) && $progressPercent >= $threshold;
            $lessonsByModule[$moduleId][] = $lesson;
        }

        $modules = [];
        $firstUnlockedIncomplete = null;
        $firstUnlocked = null;

        foreach ($moduleRows as $row) {
            $moduleId = (int) ($row['id'] ?? 0);
            $moduleStatus = $statusMap[$moduleId] ?? [
                'is_unlocked' => false,
                'is_completed' => false,
                'required_lessons' => 0,
                'required_completed_lessons' => 0,
                'total_lessons' => 0,
                'completed_lessons' => 0,
                'next_module_id' => null,
            ];

            $moduleLessons = $lessonsByModule[$moduleId] ?? [];
            $previousLessonCompleted = true;
            foreach ($moduleLessons as $lessonIndex => $moduleLesson) {
                $lessonCompleted = !empty($moduleLesson['is_completed']);
                $moduleLesson['is_unlocked'] = !empty($moduleStatus['is_unlocked']) && $previousLessonCompleted;
                $moduleLessons[$lessonIndex] = $moduleLesson;

                if (!$lessonCompleted) {
                    $previousLessonCompleted = false;
                }
            }

            $module = [
                'id' => $moduleId,
                'title' => (string) ($row['title'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'display_order' => (int) ($row['display_order'] ?? 0),
                'is_unlocked' => !empty($moduleStatus['is_unlocked']),
                'is_completed' => !empty($moduleStatus['is_completed']),
                'required_lessons' => (int) ($moduleStatus['required_lessons'] ?? 0),
                'required_completed_lessons' => (int) ($moduleStatus['required_completed_lessons'] ?? 0),
                'total_lessons' => (int) ($moduleStatus['total_lessons'] ?? 0),
                'completed_lessons' => (int) ($moduleStatus['completed_lessons'] ?? 0),
                'next_module_id' => $moduleStatus['next_module_id'] ?? null,
                'lessons' => $moduleLessons,
            ];

            if ($module['is_unlocked']) {
                foreach ($moduleLessons as $lesson) {
                    if (empty($lesson['is_unlocked'])) {
                        continue;
                    }
                    if ($firstUnlocked === null) {
                        $firstUnlocked = $lesson;
                    }
                    if ($firstUnlockedIncomplete === null && empty($lesson['is_completed'])) {
                        $firstUnlockedIncomplete = $lesson;
                    }
                }
            }

            $modules[] = $module;
        }

        $selectedLesson = null;
        $preferredLessonId = (int) ($preferredLessonId ?? 0);
        if ($preferredLessonId > 0) {
            foreach ($modules as $module) {
                if (empty($module['is_unlocked'])) {
                    continue;
                }
                foreach ($module['lessons'] as $lesson) {
                    if (!empty($lesson['is_unlocked']) && (int) ($lesson['id'] ?? 0) === $preferredLessonId) {
                        $selectedLesson = $lesson;
                        break 2;
                    }
                }
            }
        }

        if ($selectedLesson === null) {
            $selectedLesson = $firstUnlockedIncomplete ?? $firstUnlocked;
        }

        return [
            'course' => $course,
            'modules' => $modules,
            'selected_lesson' => $selectedLesson,
            'summary' => [
                'progress_percent' => (int) ($progressSync['progress_percent'] ?? (int) ($course['progress_percent'] ?? 0)),
                'course_completed' => !empty($progressSync['course_completed']),
                'required_lessons' => (int) ($progressSync['required_lessons'] ?? 0),
                'required_completed_lessons' => (int) ($progressSync['required_completed_lessons'] ?? 0),
            ],
        ];
    }

    public function recordLessonProgress(
        int $studentId,
        int $courseId,
        int $lessonId,
        int $watchedSeconds,
        int $durationSeconds,
        int $positionSeconds
    ): array {
        $result = [
            'ok' => false,
            'message' => 'Nao foi possivel registrar o progresso.',
        ];

        if ($studentId <= 0 || $courseId <= 0 || $lessonId <= 0 || !$this->lmsFeatureAvailable()) {
            $result['message'] = 'Funcionalidade de progresso indisponivel.';
            return $result;
        }

        $course = $this->findStudentCourse($studentId, $courseId);
        if (!$course) {
            $result['message'] = 'Curso nao encontrado para sua matricula.';
            return $result;
        }

        $lesson = $this->findStudentCourseLesson($studentId, $courseId, $lessonId);
        if (!$lesson) {
            $result['message'] = 'Aula nao encontrada para este curso.';
            return $result;
        }

        $moduleId = (int) ($lesson['module_id'] ?? 0);
        $statusBefore = $this->moduleStatusMap($studentId, $courseId);
        if (!isset($statusBefore[$moduleId]) || empty($statusBefore[$moduleId]['is_unlocked'])) {
            $result['message'] = 'Modulo ainda bloqueado. Conclua o modulo anterior primeiro.';
            return $result;
        }

        $threshold = (int) ($lesson['min_progress_percent'] ?? 70);
        if ($threshold <= 0 || $threshold > 100) {
            $threshold = 70;
        }

        $durationFromLesson = (int) ($lesson['duration_seconds'] ?? 0);
        if ($durationFromLesson > 0) {
            $durationSeconds = max($durationSeconds, $durationFromLesson);
        }
        $durationSeconds = max(1, $durationSeconds);

        $watchedSeconds = max(0, $watchedSeconds);
        $positionSeconds = max(0, $positionSeconds);

        $stmt = $this->db->prepare('SELECT
                id,
                watched_seconds,
                last_position_seconds,
                progress_percent,
                completed_at
            FROM student_lesson_progress
            WHERE student_id = :student_id
              AND lesson_id = :lesson_id
            LIMIT 1');
        $stmt->execute([
            ':student_id' => $studentId,
            ':lesson_id' => $lessonId,
        ]);
        $existing = $stmt->fetch() ?: null;

        $existingWatched = (int) ($existing['watched_seconds'] ?? 0);
        $existingPosition = (int) ($existing['last_position_seconds'] ?? 0);
        $existingProgress = (int) ($existing['progress_percent'] ?? 0);
        $wasCompleted = !empty($existing['completed_at']);

        $newWatched = max($existingWatched, $watchedSeconds, $positionSeconds);
        if ($newWatched > $durationSeconds) {
            $newWatched = $durationSeconds;
        }

        $newPosition = max($existingPosition, $positionSeconds);
        if ($newPosition > $durationSeconds) {
            $newPosition = $durationSeconds;
        }

        $calculatedProgress = (int) round(($newWatched / max(1, $durationSeconds)) * 100);
        $calculatedProgress = min(100, max(0, $calculatedProgress));
        $newProgress = max($existingProgress, $calculatedProgress);
        if ($newProgress > 100) {
            $newProgress = 100;
        }

        $isReadyToComplete = $newProgress >= $threshold;
        $completedAt = $existing['completed_at'] ?? null;
        $isCompleted = $completedAt !== null && $completedAt !== '';

        // Evita o estado "99% e bloqueado": ao atingir o minimo exigido, conclui a aula.
        if (!$isCompleted && $isReadyToComplete) {
            $completedAt = now();
            $isCompleted = true;
            $newProgress = 100;
        }

        if ($existing) {
            $updateStmt = $this->db->prepare('UPDATE student_lesson_progress SET
                watched_seconds = :watched_seconds,
                last_position_seconds = :last_position_seconds,
                progress_percent = :progress_percent,
                completed_at = :completed_at,
                last_event_at = :last_event_at,
                updated_at = :updated_at
                WHERE id = :id');
            $updateStmt->execute([
                ':watched_seconds' => $newWatched,
                ':last_position_seconds' => $newPosition,
                ':progress_percent' => $newProgress,
                ':completed_at' => $completedAt,
                ':last_event_at' => now(),
                ':updated_at' => now(),
                ':id' => (int) $existing['id'],
            ]);
        } else {
            $insertStmt = $this->db->prepare('INSERT INTO student_lesson_progress (
                student_id,
                course_id,
                module_id,
                lesson_id,
                watched_seconds,
                last_position_seconds,
                progress_percent,
                completed_at,
                last_event_at,
                created_at,
                updated_at
            ) VALUES (
                :student_id,
                :course_id,
                :module_id,
                :lesson_id,
                :watched_seconds,
                :last_position_seconds,
                :progress_percent,
                :completed_at,
                :last_event_at,
                :created_at,
                :updated_at
            )');
            $insertStmt->execute([
                ':student_id' => $studentId,
                ':course_id' => $courseId,
                ':module_id' => $moduleId,
                ':lesson_id' => $lessonId,
                ':watched_seconds' => $newWatched,
                ':last_position_seconds' => $newPosition,
                ':progress_percent' => $newProgress,
                ':completed_at' => $completedAt,
                ':last_event_at' => now(),
                ':created_at' => now(),
                ':updated_at' => now(),
            ]);
        }

        $progressSync = $this->syncEnrollmentProgressFromLessons($studentId, $courseId);
        $statusAfter = $this->moduleStatusMap($studentId, $courseId);
        $moduleStatus = $statusAfter[$moduleId] ?? [];
        $nextModuleId = $moduleStatus['next_module_id'] ?? null;
        $nextModuleUnlocked = false;
        if ($nextModuleId !== null && isset($statusAfter[(int) $nextModuleId])) {
            $nextModuleUnlocked = !empty($statusAfter[(int) $nextModuleId]['is_unlocked']);
        }

        $result['ok'] = true;
        $result['message'] = 'Progresso salvo.';
        $result['progress_percent'] = $newProgress;
        $result['required_percent'] = $threshold;
        $result['lesson_completed'] = $isCompleted;
        $result['lesson_ready_to_complete'] = !$isCompleted && $isReadyToComplete;
        $result['lesson_just_completed'] = !$wasCompleted && $isCompleted;
        $result['module_completed'] = !empty($moduleStatus['is_completed']);
        $result['next_module_id'] = $nextModuleId;
        $result['next_module_unlocked'] = $nextModuleUnlocked;
        $result['course_progress_percent'] = (int) ($progressSync['progress_percent'] ?? 0);
        $result['course_completed'] = !empty($progressSync['course_completed']);

        return $result;
    }

    public function confirmLessonCompletion(int $studentId, int $courseId, int $lessonId): array
    {
        $result = [
            'ok' => false,
            'message' => 'Nao foi possivel concluir a aula.',
        ];

        if ($studentId <= 0 || $courseId <= 0 || $lessonId <= 0 || !$this->lmsFeatureAvailable()) {
            $result['message'] = 'Funcionalidade de conclusao indisponivel.';
            return $result;
        }

        $lesson = $this->findStudentCourseLesson($studentId, $courseId, $lessonId);
        if (!$lesson) {
            $result['message'] = 'Aula nao encontrada para este curso.';
            return $result;
        }

        $threshold = (int) ($lesson['min_progress_percent'] ?? 70);
        if ($threshold <= 0 || $threshold > 100) {
            $threshold = 70;
        }

        $stmt = $this->db->prepare('SELECT id, progress_percent, completed_at
            FROM student_lesson_progress
            WHERE student_id = :student_id
              AND lesson_id = :lesson_id
            LIMIT 1');
        $stmt->execute([
            ':student_id' => $studentId,
            ':lesson_id' => $lessonId,
        ]);
        $progress = $stmt->fetch() ?: null;

        if (!$progress || (int) ($progress['progress_percent'] ?? 0) < $threshold) {
            $result['message'] = 'Esta aula ainda nao atingiu o percentual minimo para conclusao.';
            return $result;
        }

        if (empty($progress['completed_at'])) {
            $update = $this->db->prepare('UPDATE student_lesson_progress SET
                progress_percent = 100,
                completed_at = :completed_at,
                last_event_at = :last_event_at,
                updated_at = :updated_at
                WHERE id = :id');
            $update->execute([
                ':completed_at' => now(),
                ':last_event_at' => now(),
                ':updated_at' => now(),
                ':id' => (int) $progress['id'],
            ]);
        }

        $progressSync = $this->syncEnrollmentProgressFromLessons($studentId, $courseId);
        $lessonStatus = $this->findStudentCourseLesson($studentId, $courseId, $lessonId);
        $moduleId = (int) ($lessonStatus['module_id'] ?? 0);
        $statusAfter = $this->moduleStatusMap($studentId, $courseId);
        $moduleStatus = $statusAfter[$moduleId] ?? [];
        $nextModuleId = $moduleStatus['next_module_id'] ?? null;
        $nextModuleUnlocked = false;
        if ($nextModuleId !== null && isset($statusAfter[(int) $nextModuleId])) {
            $nextModuleUnlocked = !empty($statusAfter[(int) $nextModuleId]['is_unlocked']);
        }

        $result['ok'] = true;
        $result['message'] = 'Aula concluida com sucesso.';
        $result['progress_percent'] = 100;
        $result['lesson_completed'] = true;
        $result['lesson_just_completed'] = true;
        $result['module_completed'] = !empty($moduleStatus['is_completed']);
        $result['next_module_id'] = $nextModuleId;
        $result['next_module_unlocked'] = $nextModuleUnlocked;
        $result['course_progress_percent'] = (int) ($progressSync['progress_percent'] ?? 0);
        $result['course_completed'] = !empty($progressSync['course_completed']);

        return $result;
    }

    public function listAvailableExams(int $studentId): array
    {
        $submissionSelect = 'NULL AS submission_id, NULL AS submission_status, NULL AS submission_submitted_at';
        $submissionJoin = '';
        $externalSelect = 'NULL AS external_link_id, NULL AS external_url, NULL AS external_instructions, NULL AS external_due_at';
        $externalJoin = '';
        $internalSelect = 'NULL AS internal_link_id, 0 AS internal_links_total';
        $internalJoin = '';
        $internalCountJoin = '';
        $internalVisibilityWhere = '';
        $hasScheduleColumn = $this->hasExamScheduleColumn();
        $scheduleSelect = $hasScheduleColumn ? 'ex.scheduled_at AS scheduled_at' : 'NULL AS scheduled_at';
        $orderSql = $hasScheduleColumn
            ? 'ORDER BY (ex.scheduled_at IS NULL) ASC, ex.scheduled_at ASC, ex.id DESC'
            : 'ORDER BY ex.id DESC';
        $companyId = $this->resolveStudentCompanyId($studentId);

        $params = [
            ':student_id' => $studentId,
            ':student_id_result' => $studentId,
        ];

        if ($this->hasExamSubmissionsTable()) {
            $submissionSelect = 'sub.id AS submission_id, sub.status AS submission_status, sub.submitted_at AS submission_submitted_at';
            $submissionJoin = "LEFT JOIN (
                    SELECT s.id, s.exam_id, s.student_id, s.status, s.submitted_at
                    FROM exam_submissions s
                    INNER JOIN (
                        SELECT exam_id, student_id, MAX(id) AS max_id
                        FROM exam_submissions
                        GROUP BY exam_id, student_id
                    ) latest ON latest.max_id = s.id
                ) sub ON sub.exam_id = ex.id AND sub.student_id = :student_id_submission";
            $params[':student_id_submission'] = $studentId;
        }

        if ($this->hasExamExternalLinksTable()) {
            $externalSelect = 'eel.id AS external_link_id, eel.external_url AS external_url, eel.instructions AS external_instructions, eel.due_at AS external_due_at';
            $externalJoin = 'LEFT JOIN exam_external_links eel
                ON eel.exam_id = ex.id
               AND eel.student_id = :student_id_external
               AND eel.is_active = 1';
            $params[':student_id_external'] = $studentId;
        }

        if ($this->hasExamInternalLinksTable()) {
            $internalSelect = 'eil.id AS internal_link_id, COALESCE(eilc.internal_links_total, 0) AS internal_links_total';
            $internalJoin = 'LEFT JOIN exam_internal_links eil
                ON eil.exam_id = ex.id
               AND eil.student_id = :student_id_internal
               AND eil.is_active = 1';
            $internalCountJoin = "LEFT JOIN (
                    SELECT exam_id, COUNT(*) AS internal_links_total
                    FROM exam_internal_links
                    WHERE is_active = 1
                    GROUP BY exam_id
                ) eilc ON eilc.exam_id = ex.id";
            $internalVisibilityWhere = ' AND (COALESCE(eilc.internal_links_total, 0) = 0 OR eil.id IS NOT NULL)';
            $params[':student_id_internal'] = $studentId;
        }

        $companyFilter = '';
        if ($this->hasCourseCompanyColumn() && $companyId !== null && $companyId > 0) {
            $companyFilter = ' AND c.company_id = :company_id';
            $params[':company_id'] = $companyId;
        }

        $sql = "SELECT
                ex.id,
                ex.title,
                ex.description,
                ex.passing_score,
                {$scheduleSelect},
                c.name AS course_name,
                COALESCE(q.questions_total, 0) AS questions_total,
                COALESCE(q.objective_total, 0) AS objective_total,
                r.id AS result_id,
                r.score AS result_score,
                r.status AS result_status,
                {$externalSelect},
                {$internalSelect},
                {$submissionSelect}
            FROM exams ex
            INNER JOIN courses c ON c.id = ex.course_id
            INNER JOIN enrollments e ON e.course_id = c.id AND e.student_id = :student_id
            LEFT JOIN (
                SELECT
                    exam_id,
                    COUNT(*) AS questions_total,
                    SUM(CASE WHEN question_type = 'objective' THEN 1 ELSE 0 END) AS objective_total
                FROM exam_questions
                GROUP BY exam_id
            ) q ON q.exam_id = ex.id
            LEFT JOIN (
                SELECT latest.id, latest.exam_id, latest.student_id, latest.score, latest.status
                FROM exam_results latest
                INNER JOIN (
                    SELECT exam_id, student_id, MAX(id) AS max_id
                    FROM exam_results
                    GROUP BY exam_id, student_id
                ) latest_idx ON latest_idx.max_id = latest.id
            ) r ON r.exam_id = ex.id AND r.student_id = :student_id_result
            {$externalJoin}
            {$internalJoin}
            {$internalCountJoin}
            {$submissionJoin}
            WHERE c.status = 'published'
              AND e.status IN ('active', 'completed')
              {$companyFilter}
              {$internalVisibilityWhere}
            {$orderSql}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function findAvailableExam(int $studentId, int $examId): ?array
    {
        $scheduleSelect = $this->hasExamScheduleColumn() ? 'ex.scheduled_at AS scheduled_at,' : '';
        $externalSelect = 'NULL AS external_link_id, NULL AS external_url, NULL AS external_instructions, NULL AS external_due_at,';
        $externalJoin = '';
        $internalSelect = 'NULL AS internal_link_id,';
        $internalJoin = '';
        $internalCountJoin = '';
        $internalVisibilityWhere = '';
        $companyId = $this->resolveStudentCompanyId($studentId);
        $companyFilter = '';
        $params = [
            ':exam_id' => $examId,
            ':student_id' => $studentId,
        ];
        if ($this->hasExamExternalLinksTable()) {
            $externalSelect = 'eel.id AS external_link_id, eel.external_url AS external_url, eel.instructions AS external_instructions, eel.due_at AS external_due_at,';
            $externalJoin = 'LEFT JOIN exam_external_links eel
                ON eel.exam_id = ex.id
               AND eel.student_id = :student_id_external
               AND eel.is_active = 1';
            $params[':student_id_external'] = $studentId;
        }
        if ($this->hasExamInternalLinksTable()) {
            $internalSelect = 'eil.id AS internal_link_id,';
            $internalJoin = 'LEFT JOIN exam_internal_links eil
                ON eil.exam_id = ex.id
               AND eil.student_id = :student_id_internal
               AND eil.is_active = 1';
            $internalCountJoin = "LEFT JOIN (
                    SELECT exam_id, COUNT(*) AS internal_links_total
                    FROM exam_internal_links
                    WHERE is_active = 1
                    GROUP BY exam_id
                ) eilc ON eilc.exam_id = ex.id";
            $internalVisibilityWhere = ' AND (COALESCE(eilc.internal_links_total, 0) = 0 OR eil.id IS NOT NULL)';
            $params[':student_id_internal'] = $studentId;
        }
        if ($this->hasCourseCompanyColumn() && $companyId !== null && $companyId > 0) {
            $companyFilter = ' AND c.company_id = :company_id';
            $params[':company_id'] = $companyId;
        }

        $stmt = $this->db->prepare("SELECT
                ex.id,
                ex.title,
                ex.description,
                ex.passing_score,
                {$scheduleSelect}
                {$externalSelect}
                {$internalSelect}
                c.id AS course_id,
                c.name AS course_name
            FROM exams ex
            INNER JOIN courses c ON c.id = ex.course_id
            INNER JOIN enrollments e ON e.course_id = c.id
            {$externalJoin}
            {$internalJoin}
            {$internalCountJoin}
            WHERE ex.id = :exam_id
              AND e.student_id = :student_id
              AND c.status = 'published'
              AND e.status IN ('active', 'completed')
              {$companyFilter}
              {$internalVisibilityWhere}
            LIMIT 1");
        $stmt->execute($params);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function markExternalExamOpened(int $studentId, int $examId): void
    {
        if ($studentId <= 0 || $examId <= 0 || !$this->hasExamExternalLinksTable()) {
            return;
        }

        $companyId = $this->resolveStudentCompanyId($studentId);
        $companyFilter = '';
        $params = [
            ':opened_at' => now(),
            ':updated_at' => now(),
            ':exam_id' => $examId,
            ':student_id' => $studentId,
        ];
        if ($this->hasCourseCompanyColumn() && $companyId !== null && $companyId > 0) {
            $companyFilter = ' AND c.company_id = :company_id';
            $params[':company_id'] = $companyId;
        }

        $stmt = $this->db->prepare("UPDATE exam_external_links eel
            INNER JOIN exams ex ON ex.id = eel.exam_id
            INNER JOIN courses c ON c.id = ex.course_id
            SET eel.first_opened_at = COALESCE(eel.first_opened_at, :opened_at),
                eel.last_opened_at = :opened_at,
                eel.open_count = COALESCE(eel.open_count, 0) + 1,
                eel.updated_at = :updated_at
            WHERE eel.exam_id = :exam_id
              AND eel.student_id = :student_id
              AND eel.is_active = 1
              {$companyFilter}");
        $stmt->execute($params);
    }

    public function upcomingExamCalendar(int $studentId, int $limit = 12): array
    {
        if (!$this->hasExamScheduleColumn()) {
            return [];
        }

        $companyId = $this->resolveStudentCompanyId($studentId);
        $limit = max(1, $limit);

        $internalJoin = '';
        $internalCountJoin = '';
        $internalVisibilityWhere = '';
        $sql = "SELECT
                ex.id,
                ex.title,
                ex.scheduled_at,
                ex.passing_score,
                c.name AS course_name
            FROM exams ex
            INNER JOIN courses c ON c.id = ex.course_id
            INNER JOIN enrollments e ON e.course_id = c.id";
        $params = [':student_id' => $studentId];

        if ($this->hasExamInternalLinksTable()) {
            $internalJoin = ' LEFT JOIN exam_internal_links eil
                ON eil.exam_id = ex.id
               AND eil.student_id = :student_id_internal
               AND eil.is_active = 1';
            $internalCountJoin = " LEFT JOIN (
                    SELECT exam_id, COUNT(*) AS internal_links_total
                    FROM exam_internal_links
                    WHERE is_active = 1
                    GROUP BY exam_id
                ) eilc ON eilc.exam_id = ex.id";
            $internalVisibilityWhere = ' AND (COALESCE(eilc.internal_links_total, 0) = 0 OR eil.id IS NOT NULL)';
            $params[':student_id_internal'] = $studentId;
        }

        $sql .= "{$internalJoin}{$internalCountJoin}
            WHERE e.student_id = :student_id
              AND e.status IN ('active', 'completed')
              AND c.status = 'published'
              AND ex.scheduled_at IS NOT NULL
              AND ex.scheduled_at >= NOW()
              {$internalVisibilityWhere}";
        if ($this->hasCourseCompanyColumn() && $companyId !== null && $companyId > 0) {
            $sql .= ' AND c.company_id = :company_id';
            $params[':company_id'] = $companyId;
        }
        $sql .= " ORDER BY ex.scheduled_at ASC LIMIT {$limit}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function examQuestions(int $examId): array
    {
        $stmt = $this->db->prepare('SELECT id, question_type, question_text, options_json, correct_answer
            FROM exam_questions
            WHERE exam_id = :exam_id
            ORDER BY id ASC');
        $stmt->execute([':exam_id' => $examId]);

        return $stmt->fetchAll();
    }

    public function hasFinalExamResult(int $studentId, int $examId): bool
    {
        $companyId = $this->resolveStudentCompanyId($studentId);
        $sql = 'SELECT COUNT(*)
            FROM exam_results r
            INNER JOIN exams ex ON ex.id = r.exam_id
            INNER JOIN courses c ON c.id = ex.course_id
            WHERE r.student_id = :student_id
              AND r.exam_id = :exam_id';
        $params = [
            ':student_id' => $studentId,
            ':exam_id' => $examId,
        ];
        if ($this->hasCourseCompanyColumn() && $companyId !== null && $companyId > 0) {
            $sql .= ' AND c.company_id = :company_id';
            $params[':company_id'] = $companyId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function hasExamSubmission(int $studentId, int $examId): bool
    {
        if (!$this->hasExamSubmissionsTable()) {
            return false;
        }

        $companyId = $this->resolveStudentCompanyId($studentId);
        $sql = 'SELECT COUNT(*)
            FROM exam_submissions s
            INNER JOIN exams ex ON ex.id = s.exam_id
            INNER JOIN courses c ON c.id = ex.course_id
            WHERE s.student_id = :student_id
              AND s.exam_id = :exam_id';
        $params = [
            ':student_id' => $studentId,
            ':exam_id' => $examId,
        ];
        if ($this->hasCourseCompanyColumn() && $companyId !== null && $companyId > 0) {
            $sql .= ' AND c.company_id = :company_id';
            $params[':company_id'] = $companyId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function createExamSubmission(array $data): int
    {
        if (!$this->hasExamSubmissionsTable()) {
            return 0;
        }

        $studentId = (int) ($data['student_id'] ?? 0);
        $examId = (int) ($data['exam_id'] ?? 0);
        if ($studentId <= 0 || $examId <= 0 || !$this->examBelongsToStudentCompany($examId, $studentId)) {
            return 0;
        }

        $stmt = $this->db->prepare('INSERT INTO exam_submissions (
            exam_id, student_id, status, score, graded_questions, correct_answers,
            submitted_at, created_by, created_at, updated_at
        ) VALUES (
            :exam_id, :student_id, :status, :score, :graded_questions, :correct_answers,
            :submitted_at, NULL, :created_at, :updated_at
        )');

        $stmt->execute([
            ':exam_id' => $examId,
            ':student_id' => $studentId,
            ':status' => (string) $data['status'],
            ':score' => $data['score'] !== null ? (float) $data['score'] : null,
            ':graded_questions' => (int) $data['graded_questions'],
            ':correct_answers' => (int) $data['correct_answers'],
            ':submitted_at' => (string) $data['submitted_at'],
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function addExamSubmissionAnswer(int $submissionId, int $questionId, string $answerText, ?int $isCorrect): void
    {
        if (!$this->hasExamSubmissionAnswersTable()) {
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO exam_submission_answers (
            submission_id, question_id, answer_text, is_correct, created_at
        ) VALUES (
            :submission_id, :question_id, :answer_text, :is_correct, :created_at
        )');

        $stmt->execute([
            ':submission_id' => $submissionId,
            ':question_id' => $questionId,
            ':answer_text' => $answerText,
            ':is_correct' => $isCorrect,
            ':created_at' => now(),
        ]);
    }

    public function registerExamResultFromPortal(int $studentId, int $examId, float $score, float $passingScore, string $submittedAt): void
    {
        if (!$this->examBelongsToStudentCompany($examId, $studentId)) {
            return;
        }

        $status = $score >= $passingScore ? 'approved' : 'failed';

        $existingId = $this->findLatestExamResultId($examId, $studentId);
        if ($existingId > 0) {
            $stmt = $this->db->prepare('UPDATE exam_results SET
                score = :score,
                status = :status,
                submitted_at = :submitted_at
                WHERE id = :id');
            $stmt->execute([
                ':score' => $score,
                ':status' => $status,
                ':submitted_at' => $submittedAt,
                ':id' => $existingId,
            ]);
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO exam_results (
            exam_id, student_id, score, status, submitted_at, created_by, created_at
        ) VALUES (
            :exam_id, :student_id, :score, :status, :submitted_at, NULL, :created_at
        )');

        $stmt->execute([
            ':exam_id' => $examId,
            ':student_id' => $studentId,
            ':score' => $score,
            ':status' => $status,
            ':submitted_at' => $submittedAt,
            ':created_at' => now(),
        ]);
    }

    public function pendingExamSubmissions(int $studentId): array
    {
        if (!$this->hasExamSubmissionsTable()) {
            return [];
        }

        $companyId = $this->resolveStudentCompanyId($studentId);
        $sql = "SELECT
                s.id,
                s.exam_id,
                s.status,
                s.submitted_at,
                ex.title AS exam_title,
                c.name AS course_name
            FROM exam_submissions s
            INNER JOIN exams ex ON ex.id = s.exam_id
            INNER JOIN courses c ON c.id = ex.course_id
            WHERE s.student_id = :student_id
              AND s.status = 'pending_review'";
        $params = [':student_id' => $studentId];
        if ($this->hasCourseCompanyColumn() && $companyId !== null && $companyId > 0) {
            $sql .= ' AND c.company_id = :company_id';
            $params[':company_id'] = $companyId;
        }
        $sql .= ' ORDER BY s.submitted_at DESC, s.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function examHistory(int $studentId): array
    {
        $companyId = $this->resolveStudentCompanyId($studentId);
        $sql = "SELECT
                r.id,
                r.score,
                r.status,
                r.submitted_at,
                ex.title AS exam_title,
                ex.passing_score,
                c.name AS course_name
            FROM exam_results r
            INNER JOIN (
                SELECT exam_id, student_id, MAX(id) AS max_id
                FROM exam_results
                GROUP BY exam_id, student_id
            ) latest ON latest.max_id = r.id
            INNER JOIN exams ex ON ex.id = r.exam_id
            INNER JOIN courses c ON c.id = ex.course_id
            WHERE r.student_id = :student_id";
        $params = [':student_id' => $studentId];
        if ($this->hasCourseCompanyColumn() && $companyId !== null && $companyId > 0) {
            $sql .= ' AND c.company_id = :company_id';
            $params[':company_id'] = $companyId;
        }
        $sql .= ' ORDER BY r.submitted_at DESC, r.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function academicHistoryRecords(int $studentId): array
    {
        $companyId = $this->resolveStudentCompanyId($studentId);
        $sql = "SELECT
                r.id,
                r.score,
                r.status,
                r.submitted_at,
                ex.title AS exam_title,
                ex.passing_score,
                c.id AS course_id,
                c.name AS course_name
            FROM exam_results r
            INNER JOIN (
                SELECT r2.exam_id, r2.student_id, MAX(r2.id) AS max_id
                FROM exam_results r2
                GROUP BY r2.exam_id, r2.student_id
            ) latest ON latest.max_id = r.id
            INNER JOIN exams ex ON ex.id = r.exam_id
            INNER JOIN courses c ON c.id = ex.course_id
            WHERE r.student_id = :student_id";
        $params = [':student_id' => $studentId];
        if ($this->hasCourseCompanyColumn() && $companyId !== null && $companyId > 0) {
            $sql .= ' AND c.company_id = :company_id';
            $params[':company_id'] = $companyId;
        }
        $sql .= ' ORDER BY r.submitted_at DESC, r.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function studentAcademicProfile(int $studentId): ?array
    {
        if ($studentId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare("SELECT
                s.id,
                s.full_name,
                s.email_primary,
                s.phone,
                s.ra,
                s.birth_date,
                s.rg,
                s.cro,
                s.company_id,
                c.legal_name AS company_legal_name,
                c.trade_name AS company_trade_name,
                c.cnpj AS company_cnpj
            FROM students s
            LEFT JOIN companies c ON c.id = s.company_id
            WHERE s.id = :student_id
            LIMIT 1");
        $stmt->execute([':student_id' => $studentId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function findStudentCourse(int $studentId, int $courseId): ?array
    {
        $companyId = $this->resolveStudentCompanyId($studentId);
        $companyFilter = '';
        $params = [
            ':student_id' => $studentId,
            ':course_id' => $courseId,
        ];

        if ($this->hasCourseCompanyColumn() && $companyId !== null && $companyId > 0) {
            $companyFilter = ' AND c.company_id = :company_id';
            $params[':company_id'] = $companyId;
        }

        $stmt = $this->db->prepare("SELECT
                e.id AS enrollment_id,
                e.status AS enrollment_status,
                e.progress_percent,
                e.started_at,
                e.completed_at,
                c.id AS course_id,
                c.name,
                c.description,
                c.cover_image,
                c.workload_hours
            FROM enrollments e
            INNER JOIN courses c ON c.id = e.course_id
            WHERE e.student_id = :student_id
              AND e.course_id = :course_id
              AND e.status IN ('active', 'completed')
              AND c.status = 'published'
              {$companyFilter}
            LIMIT 1");
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function findStudentCourseLesson(int $studentId, int $courseId, int $lessonId): ?array
    {
        $companyId = $this->resolveStudentCompanyId($studentId);
        $companyFilter = '';
        $params = [
            ':student_id' => $studentId,
            ':course_id' => $courseId,
            ':lesson_id' => $lessonId,
        ];

        if ($this->hasCourseCompanyColumn() && $companyId !== null && $companyId > 0) {
            $companyFilter = ' AND c.company_id = :company_id';
            $params[':company_id'] = $companyId;
        }

        $stmt = $this->db->prepare("SELECT
                cl.id,
                cl.course_id,
                cl.module_id,
                cl.title,
                cl.video_url,
                cl.duration_seconds,
                cl.min_progress_percent
            FROM course_lessons cl
            INNER JOIN course_modules cm ON cm.id = cl.module_id
            INNER JOIN courses c ON c.id = cl.course_id
            INNER JOIN enrollments e ON e.course_id = c.id
            WHERE cl.id = :lesson_id
              AND cl.course_id = :course_id
              AND cl.is_active = 1
              AND cm.is_active = 1
              AND e.student_id = :student_id
              AND e.status IN ('active', 'completed')
              AND c.status = 'published'
              {$companyFilter}
            LIMIT 1");
        $stmt->execute($params);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        if (!$this->isLessonUnlockedForStudent($studentId, $courseId, (int) ($row['module_id'] ?? 0), $lessonId)) {
            return null;
        }

        return $row;
    }

    private function isLessonUnlockedForStudent(int $studentId, int $courseId, int $moduleId, int $lessonId): bool
    {
        if ($studentId <= 0 || $courseId <= 0 || $moduleId <= 0 || $lessonId <= 0) {
            return false;
        }

        $statusMap = $this->moduleStatusMap($studentId, $courseId);
        if (empty($statusMap[$moduleId]['is_unlocked'])) {
            return false;
        }

        $stmt = $this->db->prepare('SELECT
                cl.id,
                COALESCE(slp.completed_at, NULL) AS completed_at
            FROM course_lessons cl
            LEFT JOIN student_lesson_progress slp ON slp.lesson_id = cl.id AND slp.student_id = :student_id
            WHERE cl.module_id = :module_id
              AND cl.is_active = 1
            ORDER BY cl.display_order ASC, cl.id ASC');
        $stmt->execute([
            ':student_id' => $studentId,
            ':module_id' => $moduleId,
        ]);
        $lessons = $stmt->fetchAll();
        if ($lessons === []) {
            return false;
        }

        $previousLessonCompleted = true;
        foreach ($lessons as $lessonRow) {
            $currentLessonId = (int) ($lessonRow['id'] ?? 0);
            $isUnlocked = $previousLessonCompleted;
            $isCompleted = !empty($lessonRow['completed_at']);

            if ($currentLessonId === $lessonId) {
                return $isUnlocked;
            }

            if (!$isCompleted) {
                $previousLessonCompleted = false;
            }
        }

        return false;
    }

    private function moduleStatusMap(int $studentId, int $courseId): array
    {
        if ($studentId <= 0 || $courseId <= 0 || !$this->lmsFeatureAvailable()) {
            return [];
        }

        $stmt = $this->db->prepare('SELECT
                cm.id AS module_id,
                cm.display_order,
                COUNT(cl.id) AS total_lessons,
                SUM(CASE WHEN cl.is_required = 1 THEN 1 ELSE 0 END) AS required_lessons,
                SUM(CASE WHEN cl.is_required = 1 AND slp.completed_at IS NOT NULL THEN 1 ELSE 0 END) AS required_completed_lessons,
                SUM(CASE WHEN slp.completed_at IS NOT NULL THEN 1 ELSE 0 END) AS completed_lessons
            FROM course_modules cm
            LEFT JOIN course_lessons cl ON cl.module_id = cm.id AND cl.is_active = 1
            LEFT JOIN student_lesson_progress slp ON slp.lesson_id = cl.id AND slp.student_id = :student_id
            WHERE cm.course_id = :course_id
              AND cm.is_active = 1
            GROUP BY cm.id, cm.display_order
            ORDER BY cm.display_order ASC, cm.id ASC');
        $stmt->execute([
            ':student_id' => $studentId,
            ':course_id' => $courseId,
        ]);
        $rows = $stmt->fetchAll();
        if ($rows === []) {
            return [];
        }

        $map = [];
        $previousModulesCompleted = true;
        $total = count($rows);

        foreach ($rows as $index => $row) {
            $moduleId = (int) ($row['module_id'] ?? 0);
            $totalLessons = (int) ($row['total_lessons'] ?? 0);
            $requiredLessons = (int) ($row['required_lessons'] ?? 0);
            $completedLessons = (int) ($row['completed_lessons'] ?? 0);
            $requiredCompletedLessons = (int) ($row['required_completed_lessons'] ?? 0);

            $isCompleted = $this->isModuleCompleted(
                $totalLessons,
                $requiredLessons,
                $completedLessons,
                $requiredCompletedLessons
            );
            $isUnlocked = $index === 0 ? true : $previousModulesCompleted;

            $nextModuleId = null;
            if ($index < ($total - 1)) {
                $nextModuleId = (int) ($rows[$index + 1]['module_id'] ?? 0);
                if ($nextModuleId <= 0) {
                    $nextModuleId = null;
                }
            }

            $map[$moduleId] = [
                'module_id' => $moduleId,
                'display_order' => (int) ($row['display_order'] ?? 0),
                'total_lessons' => $totalLessons,
                'required_lessons' => $requiredLessons,
                'completed_lessons' => $completedLessons,
                'required_completed_lessons' => $requiredCompletedLessons,
                'is_completed' => $isCompleted,
                'is_unlocked' => $isUnlocked,
                'next_module_id' => $nextModuleId,
            ];

            if (!$isCompleted) {
                $previousModulesCompleted = false;
            }
        }

        return $map;
    }

    private function isModuleCompleted(
        int $totalLessons,
        int $requiredLessons,
        int $completedLessons,
        int $requiredCompletedLessons
    ): bool {
        if ($requiredLessons > 0) {
            return $requiredCompletedLessons >= $requiredLessons;
        }

        if ($totalLessons > 0) {
            return $completedLessons >= $totalLessons;
        }

        return true;
    }

    private function syncEnrollmentProgressFromLessons(int $studentId, int $courseId): array
    {
        $course = $this->findStudentCourse($studentId, $courseId);
        if (!$course) {
            return [
                'progress_percent' => 0,
                'course_completed' => false,
                'required_lessons' => 0,
                'required_completed_lessons' => 0,
            ];
        }

        if (!$this->lmsFeatureAvailable()) {
            return [
                'progress_percent' => (int) ($course['progress_percent'] ?? 0),
                'course_completed' => ((string) ($course['enrollment_status'] ?? '')) === 'completed',
                'required_lessons' => 0,
                'required_completed_lessons' => 0,
            ];
        }

        $stmt = $this->db->prepare('SELECT
                COUNT(cl.id) AS total_lessons,
                SUM(CASE WHEN cl.is_required = 1 THEN 1 ELSE 0 END) AS required_lessons,
                SUM(CASE WHEN slp.completed_at IS NOT NULL THEN 1 ELSE 0 END) AS completed_lessons,
                SUM(CASE WHEN cl.is_required = 1 AND slp.completed_at IS NOT NULL THEN 1 ELSE 0 END) AS required_completed_lessons,
                AVG(COALESCE(slp.progress_percent, 0)) AS avg_progress_all,
                AVG(CASE WHEN cl.is_required = 1 THEN COALESCE(slp.progress_percent, 0) END) AS avg_progress_required
            FROM course_lessons cl
            INNER JOIN course_modules cm ON cm.id = cl.module_id
            LEFT JOIN student_lesson_progress slp ON slp.lesson_id = cl.id AND slp.student_id = :student_id
            WHERE cl.course_id = :course_id
              AND cl.is_active = 1
              AND cm.is_active = 1');
        $stmt->execute([
            ':student_id' => $studentId,
            ':course_id' => $courseId,
        ]);
        $row = $stmt->fetch() ?: [];

        $totalLessons = (int) ($row['total_lessons'] ?? 0);
        $requiredLessons = (int) ($row['required_lessons'] ?? 0);
        $completedLessons = (int) ($row['completed_lessons'] ?? 0);
        $requiredCompletedLessons = (int) ($row['required_completed_lessons'] ?? 0);
        $avgProgressAll = (float) ($row['avg_progress_all'] ?? 0);
        $avgProgressRequired = (float) ($row['avg_progress_required'] ?? 0);

        if ($totalLessons <= 0) {
            return [
                'progress_percent' => (int) ($course['progress_percent'] ?? 0),
                'course_completed' => ((string) ($course['enrollment_status'] ?? '')) === 'completed',
                'required_lessons' => 0,
                'required_completed_lessons' => 0,
            ];
        }

        $progressPercent = 0;
        $courseCompleted = false;

        if ($requiredLessons > 0) {
            $progressPercent = (int) round($avgProgressRequired);
            $courseCompleted = $requiredCompletedLessons >= $requiredLessons;
        } elseif ($totalLessons > 0) {
            $progressPercent = (int) round($avgProgressAll);
            $courseCompleted = $completedLessons >= $totalLessons;
        }

        $progressPercent = max(0, min(100, $progressPercent));
        $enrollmentStatus = (string) ($course['enrollment_status'] ?? '');

        if ($enrollmentStatus !== 'cancelled') {
            $startedAt = $course['started_at'] ?? null;
            if (($startedAt === null || $startedAt === '') && $progressPercent > 0) {
                $startedAt = date('Y-m-d');
            }

            $completedAt = null;
            $targetStatus = 'active';
            if ($courseCompleted) {
                $targetStatus = 'completed';
                $completedAt = $course['completed_at'] ?: date('Y-m-d');
            }

            $updateStmt = $this->db->prepare('UPDATE enrollments SET
                progress_percent = :progress_percent,
                status = :status,
                started_at = :started_at,
                completed_at = :completed_at,
                updated_at = :updated_at
                WHERE id = :id');
            $updateStmt->execute([
                ':progress_percent' => $progressPercent,
                ':status' => $targetStatus,
                ':started_at' => $startedAt,
                ':completed_at' => $completedAt,
                ':updated_at' => now(),
                ':id' => (int) ($course['enrollment_id'] ?? 0),
            ]);
        }

        return [
            'progress_percent' => $progressPercent,
            'course_completed' => $courseCompleted,
            'required_lessons' => $requiredLessons,
            'required_completed_lessons' => $requiredCompletedLessons,
        ];
    }

    private function syncStudentEnrollmentProgress(int $studentId): void
    {
        if ($studentId <= 0 || !$this->lmsFeatureAvailable()) {
            return;
        }

        $stmt = $this->db->prepare("SELECT course_id
            FROM enrollments
            WHERE student_id = :student_id
              AND status <> 'cancelled'");
        $stmt->execute([':student_id' => $studentId]);

        foreach ($stmt->fetchAll() as $row) {
            $courseId = (int) ($row['course_id'] ?? 0);
            if ($courseId > 0) {
                $this->syncEnrollmentProgressFromLessons($studentId, $courseId);
            }
        }
    }

    private function trialLiveClasses(int $studentId, int $courseId, string $accessDate): array
    {
        if ($studentId <= 0 || $courseId <= 0 || $accessDate === '') {
            return [];
        }

        $companyId = $this->resolveStudentCompanyId($studentId);
        $referenceNow = $this->appNow();
        $rows = [];

        if ($this->courseLiveSessionsTableExists()) {
            $sqlNew = "SELECT
                    cls.id,
                    cls.title AS name,
                    c.name AS course_name,
                    cls.join_url AS live_link,
                    cls.zoom_password AS live_password,
                    cls.zoom_meeting_id AS live_meeting_id,
                    cls.scheduled_at AS live_datetime,
                    cls.duration_minutes,
                    e.progress_percent,
                    e.status AS enrollment_status,
                    'zoom' AS platform
                FROM course_live_sessions cls
                INNER JOIN courses c ON c.id = cls.course_id
                INNER JOIN enrollments e ON e.course_id = c.id
                WHERE e.student_id = :student_id
                  AND c.id = :course_id
                  AND e.status = 'active'
                  AND c.status = 'published'
                  AND cls.status = 'scheduled'
                  AND DATE(cls.scheduled_at) = :access_date
                  AND DATE_ADD(cls.scheduled_at, INTERVAL COALESCE(NULLIF(cls.duration_minutes, 0), 120) MINUTE) >= :reference_now";

            $paramsNew = [
                ':student_id' => $studentId,
                ':course_id' => $courseId,
                ':access_date' => $accessDate,
                ':reference_now' => $referenceNow,
            ];

            if ($this->hasCourseCompanyColumn() && $companyId !== null && $companyId > 0) {
                $sqlNew .= ' AND c.company_id = :company_id';
                $paramsNew[':company_id'] = $companyId;
            }

            $sqlNew .= ' ORDER BY cls.scheduled_at ASC';
            $stmtNew = $this->db->prepare($sqlNew);
            $stmtNew->execute($paramsNew);
            $rows = $stmtNew->fetchAll();
        }

        if ($rows !== []) {
            return $rows;
        }

        $sqlLegacy = "SELECT
                c.id,
                c.name,
                c.name AS course_name,
                c.live_link,
                c.live_password,
                c.live_meeting_id,
                c.live_datetime,
                NULL AS duration_minutes,
                e.progress_percent,
                e.status AS enrollment_status,
                'zoom' AS platform
            FROM courses c
            INNER JOIN enrollments e ON e.course_id = c.id
            WHERE e.student_id = :student_id
              AND c.id = :course_id
              AND e.status = 'active'
              AND c.status = 'published'
              AND c.live_link IS NOT NULL
              AND c.live_link <> ''
              AND c.live_datetime IS NOT NULL
              AND DATE(c.live_datetime) = :access_date
              AND DATE_ADD(c.live_datetime, INTERVAL 120 MINUTE) >= :reference_now";

        $paramsLegacy = [
            ':student_id' => $studentId,
            ':course_id' => $courseId,
            ':access_date' => $accessDate,
            ':reference_now' => $referenceNow,
        ];

        if ($this->hasCourseCompanyColumn() && $companyId !== null && $companyId > 0) {
            $sqlLegacy .= ' AND c.company_id = :company_id';
            $paramsLegacy[':company_id'] = $companyId;
        }

        $sqlLegacy .= ' ORDER BY c.live_datetime ASC';

        $stmtLegacy = $this->db->prepare($sqlLegacy);
        $stmtLegacy->execute($paramsLegacy);

        return $stmtLegacy->fetchAll();
    }

    private function examBelongsToStudentCompany(int $examId, int $studentId): bool
    {
        if ($examId <= 0 || $studentId <= 0) {
            return false;
        }

        $companyId = $this->resolveStudentCompanyId($studentId);
        if ($companyId === null) {
            return false;
        }

        if ($this->hasCourseCompanyColumn() && $companyId > 0) {
            $stmt = $this->db->prepare('SELECT ex.id
                FROM exams ex
                INNER JOIN courses c ON c.id = ex.course_id
                WHERE ex.id = :exam_id
                  AND c.company_id = :company_id
                LIMIT 1');
            $stmt->execute([
                ':exam_id' => $examId,
                ':company_id' => $companyId,
            ]);
            return (bool) $stmt->fetchColumn();
        }

        $stmt = $this->db->prepare('SELECT id FROM exams WHERE id = :exam_id LIMIT 1');
        $stmt->execute([':exam_id' => $examId]);
        return (bool) $stmt->fetchColumn();
    }

    private function resolveStudentCompanyId(int $studentId): ?int
    {
        if ($studentId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT company_id FROM students WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $studentId]);
        $value = $stmt->fetchColumn();

        return $value !== false ? (int) $value : null;
    }

    private function findCourseForNotifications(int $courseId): ?array
    {
        if ($courseId <= 0) {
            return null;
        }

        $companySelect = $this->hasCourseCompanyColumn() ? 'company_id,' : 'NULL AS company_id,';
        $stmt = $this->db->prepare("SELECT
                id,
                {$companySelect}
                name
            FROM courses
            WHERE id = :id
            LIMIT 1");
        $stmt->execute([':id' => $courseId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function listStudentsToNotifyForCourse(int $courseId, int $companyId): array
    {
        if ($courseId <= 0) {
            return [];
        }

        $sql = "SELECT DISTINCT
                s.id,
                s.company_id
            FROM enrollments e
            INNER JOIN students s ON s.id = e.student_id
            WHERE e.course_id = :course_id
              AND e.status IN ('active', 'completed')
              AND s.is_active = 1";
        $params = [':course_id' => $courseId];

        if ($companyId > 0) {
            $sql .= ' AND s.company_id = :company_id';
            $params[':company_id'] = $companyId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    private function resolveUserName(?int $userId): string
    {
        if (($userId ?? 0) <= 0) {
            return '';
        }

        $stmt = $this->db->prepare('SELECT name FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => (int) $userId]);
        $value = $stmt->fetchColumn();

        return $value !== false ? trim((string) $value) : '';
    }

    private function hasPortalAccountsTable(): bool
    {
        if ($this->portalAccountsTableExists !== null) {
            return $this->portalAccountsTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'student_portal_accounts'");
        $stmt->execute();
        $this->portalAccountsTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->portalAccountsTableExists;
    }

    private function hasExamSubmissionsTable(): bool
    {
        if ($this->examSubmissionsTableExists !== null) {
            return $this->examSubmissionsTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'exam_submissions'");
        $stmt->execute();
        $this->examSubmissionsTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->examSubmissionsTableExists;
    }

    private function hasExamSubmissionAnswersTable(): bool
    {
        if ($this->examSubmissionAnswersTableExists !== null) {
            return $this->examSubmissionAnswersTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'exam_submission_answers'");
        $stmt->execute();
        $this->examSubmissionAnswersTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->examSubmissionAnswersTableExists;
    }

    private function hasExamScheduleColumn(): bool
    {
        if ($this->examScheduleColumnExists !== null) {
            return $this->examScheduleColumnExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'exams'
              AND column_name = 'scheduled_at'");
        $stmt->execute();
        $this->examScheduleColumnExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->examScheduleColumnExists;
    }

    private function hasExamExternalLinksTable(): bool
    {
        if ($this->examExternalLinksTableExists !== null) {
            return $this->examExternalLinksTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'exam_external_links'");
        $stmt->execute();
        $this->examExternalLinksTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->examExternalLinksTableExists;
    }

    private function hasExamInternalLinksTable(): bool
    {
        if ($this->examInternalLinksTableExists !== null) {
            return $this->examInternalLinksTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'exam_internal_links'");
        $stmt->execute();
        $this->examInternalLinksTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->examInternalLinksTableExists;
    }

    private function hasStudentProfilePhotoColumn(): bool
    {
        if ($this->studentProfilePhotoColumnExists !== null) {
            return $this->studentProfilePhotoColumnExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'students'
              AND column_name = 'profile_photo'");
        $stmt->execute();
        $this->studentProfilePhotoColumnExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->studentProfilePhotoColumnExists;
    }

    private function hasCourseCompanyColumn(): bool
    {
        if ($this->courseCompanyColumnExists !== null) {
            return $this->courseCompanyColumnExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'courses'
              AND column_name = 'company_id'");
        $stmt->execute();
        $this->courseCompanyColumnExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->courseCompanyColumnExists;
    }

    private function hasArsenalItemsTable(): bool
    {
        if ($this->arsenalItemsTableExists !== null) {
            return $this->arsenalItemsTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'arsenal_items'");
        $stmt->execute();
        $this->arsenalItemsTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->arsenalItemsTableExists;
    }

    private function hasArsenalCategoriesTable(): bool
    {
        if ($this->arsenalCategoriesTableExists !== null) {
            return $this->arsenalCategoriesTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'arsenal_categories'");
        $stmt->execute();
        $this->arsenalCategoriesTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->arsenalCategoriesTableExists;
    }

    private function hasArsenalItemCoursesTable(): bool
    {
        if ($this->arsenalItemCoursesTableExists !== null) {
            return $this->arsenalItemCoursesTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'arsenal_item_courses'");
        $stmt->execute();
        $this->arsenalItemCoursesTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->arsenalItemCoursesTableExists;
    }

    private function hasArsenalItemStudentsTable(): bool
    {
        if ($this->arsenalItemStudentsTableExists !== null) {
            return $this->arsenalItemStudentsTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'arsenal_item_students'");
        $stmt->execute();
        $this->arsenalItemStudentsTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->arsenalItemStudentsTableExists;
    }

    private function hasArsenalAccessLogsTable(): bool
    {
        if ($this->arsenalAccessLogsTableExists !== null) {
            return $this->arsenalAccessLogsTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'arsenal_access_logs'");
        $stmt->execute();
        $this->arsenalAccessLogsTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->arsenalAccessLogsTableExists;
    }

    private function hasTrialAccessTable(): bool
    {
        if ($this->trialAccessTableExists !== null) {
            return $this->trialAccessTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'course_trial_accesses'");
        $stmt->execute();
        $this->trialAccessTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->trialAccessTableExists;
    }

    private function hasCourseModulesTable(): bool
    {
        if ($this->courseModulesTableExists !== null) {
            return $this->courseModulesTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'course_modules'");
        $stmt->execute();
        $this->courseModulesTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->courseModulesTableExists;
    }

    private function hasCourseLessonsTable(): bool
    {
        if ($this->courseLessonsTableExists !== null) {
            return $this->courseLessonsTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'course_lessons'");
        $stmt->execute();
        $this->courseLessonsTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->courseLessonsTableExists;
    }

    private function hasStudentLessonProgressTable(): bool
    {
        if ($this->studentLessonProgressTableExists !== null) {
            return $this->studentLessonProgressTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'student_lesson_progress'");
        $stmt->execute();
        $this->studentLessonProgressTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->studentLessonProgressTableExists;
    }

    // -----------------------------------------------------------------
    // Financeiro do aluno
    // -----------------------------------------------------------------

    /**
     * Retorna totais financeiros do aluno para os cards de resumo.
     */
    public function myFinancialSummary(int $studentId, int $companyId): array
    {
        $base = 'FROM invoices WHERE student_id = :sid AND company_id = :cid';
        $p    = [':sid' => $studentId, ':cid' => $companyId];
        $activeStatuses = "('open','partial','overdue','paid')";

        $scalar = function (string $sql) use ($p): mixed {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($p);
            return $stmt->fetchColumn();
        };

        $totalOpen    = (float) ($scalar("SELECT COALESCE(SUM(amount - paid_amount),0) $base AND status IN ('open','partial')") ?: 0);
        $countOpen    = (int)   ($scalar("SELECT COUNT(*) $base AND status IN ('open','partial')") ?: 0);
        $totalOverdue = (float) ($scalar("SELECT COALESCE(SUM(amount - paid_amount),0) $base AND status = 'overdue'") ?: 0);
        $countOverdue = (int)   ($scalar("SELECT COUNT(*) $base AND status = 'overdue'") ?: 0);
        $totalPaid    = (float) ($scalar("SELECT COALESCE(SUM(paid_amount),0) $base AND status = 'paid'") ?: 0);
        $countPaid    = (int)   ($scalar("SELECT COUNT(*) $base AND status = 'paid'") ?: 0);
        $nextDue      = $scalar("SELECT MIN(due_date) $base AND status IN ('open','partial') AND due_date >= CURDATE()") ?: null;
        $remainingInstallments = (int) ($scalar("SELECT COUNT(*) $base AND status IN ('open','partial','overdue')") ?: 0);
        $totalInstallments = (int) ($scalar("SELECT COUNT(*) $base AND status IN {$activeStatuses}") ?: 0);

        return [
            'total_open'    => $totalOpen,
            'count_open'    => $countOpen,
            'total_overdue' => $totalOverdue,
            'count_overdue' => $countOverdue,
            'total_paid'    => $totalPaid,
            'count_paid'    => $countPaid,
            'next_due_date' => $nextDue,
            'remaining_installments' => $remainingInstallments,
            'total_installments' => $totalInstallments,
        ];
    }

    /**
     * Retorna lista paginada de faturas do aluno no portal.
     *
     * @param string $statusFilter '' = todas | 'upcoming' | 'overdue' | 'paid'
     */
    public function myInvoices(int $studentId, int $companyId, string $statusFilter, int $limit, int $offset): array
    {
        $where  = "i.student_id = :sid
            AND i.company_id = :cid
            AND i.status IN ('open','partial','overdue','paid')";
        $params = [':sid' => $studentId, ':cid' => $companyId];

        switch ($statusFilter) {
            case 'upcoming':
                $where .= " AND i.status IN ('open','partial')";
                break;
            case 'overdue':
                $where .= " AND i.status = 'overdue'";
                break;
            case 'paid':
                $where .= " AND i.status = 'paid'";
                break;
        }

        // Boleto: traz URL apenas se existir e não estiver cancelado
        $sql = "SELECT
                    i.id,
                    i.invoice_number,
                    i.due_date,
                    i.amount,
                    i.paid_amount,
                    i.status,
                    i.tags,
                    i.project_name,
                    i.paid_at,
                    bs.boleto_url,
                    bs.digitable_line,
                    bs.status AS boleto_status
                FROM invoices i
                LEFT JOIN bank_slips bs
                    ON bs.invoice_id = i.id
                   AND bs.status NOT IN ('cancelled','failed')
                WHERE {$where}
                ORDER BY i.due_date DESC, i.id DESC
                LIMIT :lim OFFSET :off";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function findLatestExamResultId(int $examId, int $studentId): int
    {
        if ($examId <= 0 || $studentId <= 0) {
            return 0;
        }

        $stmt = $this->db->prepare('SELECT id
            FROM exam_results
            WHERE exam_id = :exam_id
              AND student_id = :student_id
            ORDER BY submitted_at DESC, id DESC
            LIMIT 1');
        $stmt->execute([
            ':exam_id' => $examId,
            ':student_id' => $studentId,
        ]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }
}
