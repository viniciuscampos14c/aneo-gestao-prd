<?php

class AcademicCalendarModel extends BaseModel
{
    private ?bool $activitiesTableExists = null;
    private ?bool $remindersTableExists = null;
    private ?bool $examScheduleColumnExists = null;
    private ?bool $courseCompanyColumnExists = null;

    public function featureAvailable(): bool
    {
        return $this->hasActivitiesTable() && $this->hasRemindersTable();
    }

    public function processAutomaticReminders(int $horizonDays = 45, ?int $companyId = null): array
    {
        if (!$this->featureAvailable()) {
            return ['available' => false, 'queued' => 0, 'sent' => 0];
        }

        $horizonDays = max(1, $horizonDays);
        $companyId = $this->normalizeCompanyId($companyId);

        if ($this->hasCourseCompanyColumn() && $companyId <= 0) {
            return ['available' => true, 'queued' => 0, 'sent' => 0];
        }

        $queued = 0;

        $queued += $this->queueExamRemindersForStudents($horizonDays, $companyId);
        $queued += $this->queueExamRemindersForTeachers($horizonDays, $companyId);
        $queued += $this->queueLiveRemindersForStudents($horizonDays, $companyId);
        $queued += $this->queueLiveRemindersForTeachers($horizonDays, $companyId);
        $queued += $this->queueActivityRemindersForStudents($horizonDays, $companyId);
        $queued += $this->queueActivityRemindersForTeachers($horizonDays, $companyId);

        $sent = $this->dispatchDueReminders($companyId);

        return ['available' => true, 'queued' => $queued, 'sent' => $sent];
    }

    public function adminUnifiedEvents(string $fromDateTime, string $toDateTime, ?int $companyId = null): array
    {
        $companyId = $this->normalizeCompanyId($companyId);
        if ($this->hasCourseCompanyColumn() && $companyId <= 0) {
            return [];
        }

        $queries = [];

        if ($this->hasExamScheduleColumn()) {
            $queries[] = "SELECT
                    'exam' AS event_type,
                    ex.id AS event_id,
                    ex.course_id AS course_id,
                    c.name AS course_name,
                    ex.title AS event_title,
                    ex.description AS event_description,
                    ex.scheduled_at AS event_datetime,
                    24 AS reminder_hours_before,
                    DATE_SUB(ex.scheduled_at, INTERVAL 24 HOUR) AS reminder_datetime,
                    (
                        SELECT COUNT(*)
                        FROM enrollments e
                        WHERE e.course_id = ex.course_id
                          AND e.status IN ('active', 'completed')
                    ) AS audience_students
                FROM exams ex
                INNER JOIN courses c ON c.id = ex.course_id
                WHERE ex.scheduled_at IS NOT NULL
                  AND ex.scheduled_at BETWEEN :from_dt AND :to_dt";

            if ($this->hasCourseCompanyColumn() && $companyId > 0) {
                $queries[count($queries) - 1] .= ' AND c.company_id = :company_id';
            }
        }

        $liveSql = "SELECT
                'live_class' AS event_type,
                c.id AS event_id,
                c.id AS course_id,
                c.name AS course_name,
                'Aula ao vivo' AS event_title,
                CONCAT('Link: ', COALESCE(c.live_link, 'N/D')) AS event_description,
                c.live_datetime AS event_datetime,
                24 AS reminder_hours_before,
                DATE_SUB(c.live_datetime, INTERVAL 24 HOUR) AS reminder_datetime,
                (
                    SELECT COUNT(*)
                    FROM enrollments e
                    WHERE e.course_id = c.id
                      AND e.status IN ('active', 'completed')
                ) AS audience_students
            FROM courses c
            WHERE c.live_datetime IS NOT NULL
              AND c.live_datetime BETWEEN :from_dt AND :to_dt";
        if ($this->hasCourseCompanyColumn() && $companyId > 0) {
            $liveSql .= ' AND c.company_id = :company_id';
        }
        $queries[] = $liveSql;

        if ($this->hasActivitiesTable()) {
            $activitySql = "SELECT
                    'activity' AS event_type,
                    a.id AS event_id,
                    a.course_id AS course_id,
                    c.name AS course_name,
                    a.title AS event_title,
                    a.description AS event_description,
                    a.due_datetime AS event_datetime,
                    a.reminder_hours_before AS reminder_hours_before,
                    DATE_SUB(a.due_datetime, INTERVAL a.reminder_hours_before HOUR) AS reminder_datetime,
                    (
                        SELECT COUNT(*)
                        FROM enrollments e
                        WHERE e.course_id = a.course_id
                          AND e.status IN ('active', 'completed')
                    ) AS audience_students
                FROM course_activities a
                INNER JOIN courses c ON c.id = a.course_id
                WHERE a.is_active = 1
                  AND a.due_datetime BETWEEN :from_dt AND :to_dt";
            if ($this->hasCourseCompanyColumn() && $companyId > 0) {
                $activitySql .= ' AND c.company_id = :company_id';
            }
            $queries[] = $activitySql;
        }

        if ($queries === []) {
            return [];
        }

        $sql = implode(' UNION ALL ', $queries) . ' ORDER BY event_datetime ASC';
        $stmt = $this->db->prepare($sql);
        $params = [
            ':from_dt' => $fromDateTime,
            ':to_dt' => $toDateTime,
        ];
        if ($this->hasCourseCompanyColumn() && $companyId > 0) {
            $params[':company_id'] = $companyId;
        }
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function studentUnifiedEvents(int $studentId, string $fromDateTime, string $toDateTime): array
    {
        $queries = [];
        $companyId = $this->resolveStudentCompanyId($studentId);
        if ($this->hasCourseCompanyColumn() && ($companyId === null || $companyId <= 0)) {
            return [];
        }

        if ($this->hasExamScheduleColumn()) {
            $examSql = "SELECT
                    'exam' AS event_type,
                    ex.id AS event_id,
                    ex.course_id AS course_id,
                    c.name AS course_name,
                    ex.title AS event_title,
                    ex.description AS event_description,
                    ex.scheduled_at AS event_datetime,
                    24 AS reminder_hours_before,
                    DATE_SUB(ex.scheduled_at, INTERVAL 24 HOUR) AS reminder_datetime
                FROM exams ex
                INNER JOIN courses c ON c.id = ex.course_id
                INNER JOIN enrollments e ON e.course_id = c.id
                WHERE e.student_id = :student_id
                  AND e.status IN ('active', 'completed')
                  AND ex.scheduled_at IS NOT NULL
                  AND ex.scheduled_at BETWEEN :from_dt AND :to_dt";
            if ($this->hasCourseCompanyColumn() && $companyId !== null && $companyId > 0) {
                $examSql .= ' AND c.company_id = :company_id';
            }
            $queries[] = $examSql;
        }

        $liveSql = "SELECT
                'live_class' AS event_type,
                c.id AS event_id,
                c.id AS course_id,
                c.name AS course_name,
                'Aula ao vivo' AS event_title,
                CONCAT('Link: ', COALESCE(c.live_link, 'N/D')) AS event_description,
                c.live_datetime AS event_datetime,
                24 AS reminder_hours_before,
                DATE_SUB(c.live_datetime, INTERVAL 24 HOUR) AS reminder_datetime
            FROM courses c
            INNER JOIN enrollments e ON e.course_id = c.id
            WHERE e.student_id = :student_id
              AND e.status IN ('active', 'completed')
              AND c.live_datetime IS NOT NULL
              AND c.live_datetime BETWEEN :from_dt AND :to_dt";
        if ($this->hasCourseCompanyColumn() && $companyId !== null && $companyId > 0) {
            $liveSql .= ' AND c.company_id = :company_id';
        }
        $queries[] = $liveSql;

        if ($this->hasActivitiesTable()) {
            $activitySql = "SELECT
                    'activity' AS event_type,
                    a.id AS event_id,
                    a.course_id AS course_id,
                    c.name AS course_name,
                    a.title AS event_title,
                    a.description AS event_description,
                    a.due_datetime AS event_datetime,
                    a.reminder_hours_before AS reminder_hours_before,
                    DATE_SUB(a.due_datetime, INTERVAL a.reminder_hours_before HOUR) AS reminder_datetime
                FROM course_activities a
                INNER JOIN courses c ON c.id = a.course_id
                INNER JOIN enrollments e ON e.course_id = c.id
                WHERE a.is_active = 1
                  AND e.student_id = :student_id
                  AND e.status IN ('active', 'completed')
                  AND a.due_datetime BETWEEN :from_dt AND :to_dt";
            if ($this->hasCourseCompanyColumn() && $companyId !== null && $companyId > 0) {
                $activitySql .= ' AND c.company_id = :company_id';
            }
            $queries[] = $activitySql;
        }

        if ($queries === []) {
            return [];
        }

        $sql = implode(' UNION ALL ', $queries) . ' ORDER BY event_datetime ASC';
        $stmt = $this->db->prepare($sql);
        $params = [
            ':student_id' => $studentId,
            ':from_dt' => $fromDateTime,
            ':to_dt' => $toDateTime,
        ];
        if ($this->hasCourseCompanyColumn() && $companyId !== null && $companyId > 0) {
            $params[':company_id'] = $companyId;
        }
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function listCoursesForActivities(?int $companyId = null): array
    {
        $companyId = $this->normalizeCompanyId($companyId);

        if ($this->hasCourseCompanyColumn() && $companyId > 0) {
            $stmt = $this->db->prepare('SELECT id, name, status FROM courses WHERE company_id = :company_id ORDER BY name ASC');
            $stmt->execute([':company_id' => $companyId]);
            return $stmt->fetchAll();
        }

        return $this->db->query('SELECT id, name, status FROM courses ORDER BY name ASC')->fetchAll();
    }

    public function listActivities(string $fromDateTime, string $toDateTime, int $limit = 80, ?int $companyId = null): array
    {
        if (!$this->hasActivitiesTable()) {
            return [];
        }

        $companyId = $this->normalizeCompanyId($companyId);
        if ($this->hasCourseCompanyColumn() && $companyId <= 0) {
            return [];
        }

        $limit = max(1, $limit);
        $sql = "SELECT
                a.id,
                a.course_id,
                c.name AS course_name,
                a.title,
                a.description,
                a.due_datetime,
                a.reminder_hours_before,
                a.is_active
            FROM course_activities a
            INNER JOIN courses c ON c.id = a.course_id
            WHERE a.due_datetime BETWEEN :from_dt AND :to_dt";
        if ($this->hasCourseCompanyColumn() && $companyId > 0) {
            $sql .= ' AND c.company_id = :company_id';
        }
        $sql .= " ORDER BY a.due_datetime ASC LIMIT {$limit}";

        $stmt = $this->db->prepare($sql);
        $params = [
            ':from_dt' => $fromDateTime,
            ':to_dt' => $toDateTime,
        ];
        if ($this->hasCourseCompanyColumn() && $companyId > 0) {
            $params[':company_id'] = $companyId;
        }
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function createActivity(array $data, int $createdBy, ?int $companyId = null): int
    {
        if (!$this->hasActivitiesTable()) {
            return 0;
        }

        $companyId = $this->normalizeCompanyId($companyId);
        $courseId = (int) ($data['course_id'] ?? 0);

        if ($courseId <= 0 || !$this->courseBelongsToCompany($courseId, $companyId)) {
            return 0;
        }

        $stmt = $this->db->prepare('INSERT INTO course_activities (
            course_id, title, description, due_datetime, reminder_hours_before,
            is_active, created_by, created_at, updated_at
        ) VALUES (
            :course_id, :title, :description, :due_datetime, :reminder_hours_before,
            :is_active, :created_by, :created_at, :updated_at
        )');

        $stmt->execute([
            ':course_id' => $courseId,
            ':title' => (string) $data['title'],
            ':description' => (string) ($data['description'] ?? ''),
            ':due_datetime' => (string) $data['due_datetime'],
            ':reminder_hours_before' => (int) ($data['reminder_hours_before'] ?? 24),
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
            ':created_by' => $createdBy,
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function deleteActivity(int $activityId, ?int $companyId = null): void
    {
        if (!$this->hasActivitiesTable()) {
            return;
        }

        $companyId = $this->normalizeCompanyId($companyId);
        if ($this->hasCourseCompanyColumn() && $companyId > 0) {
            $stmt = $this->db->prepare('DELETE a
                FROM course_activities a
                INNER JOIN courses c ON c.id = a.course_id
                WHERE a.id = :id
                  AND c.company_id = :company_id');
            $stmt->execute([
                ':id' => $activityId,
                ':company_id' => $companyId,
            ]);
            return;
        }

        $stmt = $this->db->prepare('DELETE FROM course_activities WHERE id = :id');
        $stmt->execute([':id' => $activityId]);
    }

    public function adminRecentReminders(int $limit = 20, ?int $companyId = null): array
    {
        if (!$this->hasRemindersTable()) {
            return [];
        }

        $companyId = $this->normalizeCompanyId($companyId);
        if ($this->hasCourseCompanyColumn() && $companyId <= 0) {
            return [];
        }

        $limit = max(1, $limit);
        $sql = "SELECT
                r.id,
                r.event_type,
                r.course_id,
                c.name AS course_name,
                r.recipient_type,
                r.recipient_id,
                r.message,
                r.scheduled_for,
                r.sent_at,
                CASE
                    WHEN r.recipient_type = 'student' THEN s.full_name
                    WHEN r.recipient_type = 'teacher' THEN u.name
                    ELSE CONCAT('#', r.recipient_id)
                END AS recipient_name
            FROM academic_reminders r
            LEFT JOIN courses c ON c.id = r.course_id
            LEFT JOIN students s ON r.recipient_type = 'student' AND s.id = r.recipient_id
            LEFT JOIN users u ON r.recipient_type = 'teacher' AND u.id = r.recipient_id
            WHERE r.status = 'sent'";
        if ($this->hasCourseCompanyColumn() && $companyId > 0) {
            $sql .= ' AND c.company_id = :company_id';
        }
        $sql .= " ORDER BY r.sent_at DESC, r.id DESC LIMIT {$limit}";

        $stmt = $this->db->prepare($sql);
        $params = [];
        if ($this->hasCourseCompanyColumn() && $companyId > 0) {
            $params[':company_id'] = $companyId;
        }
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function studentRecentReminders(int $studentId, int $limit = 20): array
    {
        if (!$this->hasRemindersTable()) {
            return [];
        }

        $companyId = $this->resolveStudentCompanyId($studentId);
        if ($this->hasCourseCompanyColumn() && ($companyId === null || $companyId <= 0)) {
            return [];
        }

        $limit = max(1, $limit);
        $sql = "SELECT
                r.id,
                r.event_type,
                r.course_id,
                c.name AS course_name,
                r.message,
                r.scheduled_for,
                r.sent_at
            FROM academic_reminders r
            LEFT JOIN courses c ON c.id = r.course_id
            WHERE r.recipient_type = 'student'
              AND r.recipient_id = :student_id
              AND r.status = 'sent'";
        if ($this->hasCourseCompanyColumn() && $companyId !== null && $companyId > 0) {
            $sql .= ' AND c.company_id = :company_id';
        }
        $sql .= " ORDER BY r.sent_at DESC, r.id DESC LIMIT {$limit}";

        $stmt = $this->db->prepare($sql);
        $params = [':student_id' => $studentId];
        if ($this->hasCourseCompanyColumn() && $companyId !== null && $companyId > 0) {
            $params[':company_id'] = $companyId;
        }
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    private function queueExamRemindersForStudents(int $horizonDays, int $companyId): int
    {
        if (!$this->hasExamScheduleColumn() || !$this->hasRemindersTable()) {
            return 0;
        }

        $sql = "INSERT IGNORE INTO academic_reminders (
                event_type, event_id, course_id,
                recipient_type, recipient_id,
                scheduled_for, message, status,
                sent_at, created_at, updated_at
            )
            SELECT
                'exam', ex.id, ex.course_id,
                'student', e.student_id,
                DATE_SUB(ex.scheduled_at, INTERVAL 24 HOUR),
                CONCAT('Lembrete automatico: prova \"', ex.title, '\" do curso \"', c.name, '\" em ', DATE_FORMAT(ex.scheduled_at, '%d/%m/%Y %H:%i')),
                'pending',
                NULL, NOW(), NOW()
            FROM exams ex
            INNER JOIN courses c ON c.id = ex.course_id
            INNER JOIN enrollments e ON e.course_id = ex.course_id
            WHERE ex.scheduled_at IS NOT NULL
              AND e.status IN ('active', 'completed')
              AND ex.scheduled_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL {$horizonDays} DAY)";
        $params = [];
        if ($this->hasCourseCompanyColumn() && $companyId > 0) {
            $sql .= ' AND c.company_id = :company_id';
            $params[':company_id'] = $companyId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    private function queueExamRemindersForTeachers(int $horizonDays, int $companyId): int
    {
        if (!$this->hasExamScheduleColumn() || !$this->hasRemindersTable()) {
            return 0;
        }

        $sql = "INSERT IGNORE INTO academic_reminders (
                event_type, event_id, course_id,
                recipient_type, recipient_id,
                scheduled_for, message, status,
                sent_at, created_at, updated_at
            )
            SELECT
                'exam', ex.id, ex.course_id,
                'teacher', c.created_by,
                DATE_SUB(ex.scheduled_at, INTERVAL 24 HOUR),
                CONCAT('Lembrete automatico: prova \"', ex.title, '\" do curso \"', c.name, '\" em ', DATE_FORMAT(ex.scheduled_at, '%d/%m/%Y %H:%i')),
                'pending',
                NULL, NOW(), NOW()
            FROM exams ex
            INNER JOIN courses c ON c.id = ex.course_id
            WHERE ex.scheduled_at IS NOT NULL
              AND c.created_by IS NOT NULL
              AND ex.scheduled_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL {$horizonDays} DAY)";
        $params = [];
        if ($this->hasCourseCompanyColumn() && $companyId > 0) {
            $sql .= ' AND c.company_id = :company_id';
            $params[':company_id'] = $companyId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    private function queueLiveRemindersForStudents(int $horizonDays, int $companyId): int
    {
        if (!$this->hasRemindersTable()) {
            return 0;
        }

        $sql = "INSERT IGNORE INTO academic_reminders (
                event_type, event_id, course_id,
                recipient_type, recipient_id,
                scheduled_for, message, status,
                sent_at, created_at, updated_at
            )
            SELECT
                'live_class', c.id, c.id,
                'student', e.student_id,
                DATE_SUB(c.live_datetime, INTERVAL 24 HOUR),
                CONCAT('Lembrete automatico: aula ao vivo do curso \"', c.name, '\" em ', DATE_FORMAT(c.live_datetime, '%d/%m/%Y %H:%i')),
                'pending',
                NULL, NOW(), NOW()
            FROM courses c
            INNER JOIN enrollments e ON e.course_id = c.id
            WHERE c.live_datetime IS NOT NULL
              AND e.status IN ('active', 'completed')
              AND c.live_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL {$horizonDays} DAY)";
        $params = [];
        if ($this->hasCourseCompanyColumn() && $companyId > 0) {
            $sql .= ' AND c.company_id = :company_id';
            $params[':company_id'] = $companyId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    private function queueLiveRemindersForTeachers(int $horizonDays, int $companyId): int
    {
        if (!$this->hasRemindersTable()) {
            return 0;
        }

        $sql = "INSERT IGNORE INTO academic_reminders (
                event_type, event_id, course_id,
                recipient_type, recipient_id,
                scheduled_for, message, status,
                sent_at, created_at, updated_at
            )
            SELECT
                'live_class', c.id, c.id,
                'teacher', c.created_by,
                DATE_SUB(c.live_datetime, INTERVAL 24 HOUR),
                CONCAT('Lembrete automatico: aula ao vivo do curso \"', c.name, '\" em ', DATE_FORMAT(c.live_datetime, '%d/%m/%Y %H:%i')),
                'pending',
                NULL, NOW(), NOW()
            FROM courses c
            WHERE c.live_datetime IS NOT NULL
              AND c.created_by IS NOT NULL
              AND c.live_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL {$horizonDays} DAY)";
        $params = [];
        if ($this->hasCourseCompanyColumn() && $companyId > 0) {
            $sql .= ' AND c.company_id = :company_id';
            $params[':company_id'] = $companyId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    private function queueActivityRemindersForStudents(int $horizonDays, int $companyId): int
    {
        if (!$this->hasActivitiesTable() || !$this->hasRemindersTable()) {
            return 0;
        }

        $sql = "INSERT IGNORE INTO academic_reminders (
                event_type, event_id, course_id,
                recipient_type, recipient_id,
                scheduled_for, message, status,
                sent_at, created_at, updated_at
            )
            SELECT
                'activity', a.id, a.course_id,
                'student', e.student_id,
                DATE_SUB(a.due_datetime, INTERVAL a.reminder_hours_before HOUR),
                CONCAT('Lembrete automatico: atividade \"', a.title, '\" do curso \"', c.name, '\" vence em ', DATE_FORMAT(a.due_datetime, '%d/%m/%Y %H:%i')),
                'pending',
                NULL, NOW(), NOW()
            FROM course_activities a
            INNER JOIN courses c ON c.id = a.course_id
            INNER JOIN enrollments e ON e.course_id = a.course_id
            WHERE a.is_active = 1
              AND e.status IN ('active', 'completed')
              AND a.due_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL {$horizonDays} DAY)";
        $params = [];
        if ($this->hasCourseCompanyColumn() && $companyId > 0) {
            $sql .= ' AND c.company_id = :company_id';
            $params[':company_id'] = $companyId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    private function queueActivityRemindersForTeachers(int $horizonDays, int $companyId): int
    {
        if (!$this->hasActivitiesTable() || !$this->hasRemindersTable()) {
            return 0;
        }

        $sql = "INSERT IGNORE INTO academic_reminders (
                event_type, event_id, course_id,
                recipient_type, recipient_id,
                scheduled_for, message, status,
                sent_at, created_at, updated_at
            )
            SELECT
                'activity', a.id, a.course_id,
                'teacher', c.created_by,
                DATE_SUB(a.due_datetime, INTERVAL a.reminder_hours_before HOUR),
                CONCAT('Lembrete automatico: atividade \"', a.title, '\" do curso \"', c.name, '\" vence em ', DATE_FORMAT(a.due_datetime, '%d/%m/%Y %H:%i')),
                'pending',
                NULL, NOW(), NOW()
            FROM course_activities a
            INNER JOIN courses c ON c.id = a.course_id
            WHERE a.is_active = 1
              AND c.created_by IS NOT NULL
              AND a.due_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL {$horizonDays} DAY)";
        $params = [];
        if ($this->hasCourseCompanyColumn() && $companyId > 0) {
            $sql .= ' AND c.company_id = :company_id';
            $params[':company_id'] = $companyId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    private function dispatchDueReminders(int $companyId): int
    {
        if (!$this->hasRemindersTable()) {
            return 0;
        }

        if ($this->hasCourseCompanyColumn() && $companyId > 0) {
            $stmt = $this->db->prepare("UPDATE academic_reminders r
                INNER JOIN courses c ON c.id = r.course_id
                SET r.status = 'sent',
                    r.sent_at = NOW(),
                    r.updated_at = NOW()
                WHERE r.status = 'pending'
                  AND r.scheduled_for <= NOW()
                  AND c.company_id = :company_id");
            $stmt->execute([':company_id' => $companyId]);
            return $stmt->rowCount();
        }

        $stmt = $this->db->prepare("UPDATE academic_reminders
            SET status = 'sent',
                sent_at = NOW(),
                updated_at = NOW()
            WHERE status = 'pending'
              AND scheduled_for <= NOW()");
        $stmt->execute();

        return $stmt->rowCount();
    }

    private function courseBelongsToCompany(int $courseId, int $companyId): bool
    {
        if ($courseId <= 0) {
            return false;
        }

        if ($this->hasCourseCompanyColumn() && $companyId > 0) {
            $stmt = $this->db->prepare('SELECT id
                FROM courses
                WHERE id = :id
                  AND company_id = :company_id
                LIMIT 1');
            $stmt->execute([
                ':id' => $courseId,
                ':company_id' => $companyId,
            ]);
            return (bool) $stmt->fetchColumn();
        }

        $stmt = $this->db->prepare('SELECT id FROM courses WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $courseId]);
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

    private function normalizeCompanyId(?int $companyId = null): int
    {
        return (int) ($companyId ?? $this->companyId() ?? 0);
    }

    private function hasActivitiesTable(): bool
    {
        if ($this->activitiesTableExists !== null) {
            return $this->activitiesTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'course_activities'");
        $stmt->execute();
        $this->activitiesTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->activitiesTableExists;
    }

    private function hasRemindersTable(): bool
    {
        if ($this->remindersTableExists !== null) {
            return $this->remindersTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'academic_reminders'");
        $stmt->execute();
        $this->remindersTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->remindersTableExists;
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
}
