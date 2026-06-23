<?php

class ProfessorDashboardModel extends BaseModel
{
    public function overview(): array
    {
        $companyId = $this->companyId();
        if ($companyId <= 0) {
            return $this->emptyOverview();
        }

        $overview = $this->emptyOverview();
        $overview['published_courses'] = $this->count(
            "SELECT COUNT(*) FROM courses WHERE company_id = :company_id AND status = 'published'",
            [':company_id' => $companyId]
        );
        $overview['active_enrollments'] = $this->count(
            "SELECT COUNT(*)
             FROM enrollments e
             INNER JOIN courses c ON c.id = e.course_id
             WHERE c.company_id = :company_id
               AND e.status IN ('active', 'completed')",
            [':company_id' => $companyId]
        );
        $overview['average_progress'] = round((float) $this->value(
            "SELECT COALESCE(AVG(e.progress_percent), 0)
             FROM enrollments e
             INNER JOIN courses c ON c.id = e.course_id
             WHERE c.company_id = :company_id
               AND e.status IN ('active', 'completed')",
            [':company_id' => $companyId]
        ), 1);
        $overview['attendance_rate'] = $this->attendanceRate($companyId);
        $overview['students_at_risk'] = $this->count(
            "SELECT COUNT(*)
             FROM enrollments e
             INNER JOIN courses c ON c.id = e.course_id
             WHERE c.company_id = :company_id
               AND e.status = 'active'
               AND e.progress_percent < 40",
            [':company_id' => $companyId]
        );
        $overview['exam_results'] = $this->count(
            "SELECT COUNT(*)
             FROM exam_results er
             INNER JOIN exams ex ON ex.id = er.exam_id
             INNER JOIN courses c ON c.id = ex.course_id
             WHERE c.company_id = :company_id",
            [':company_id' => $companyId]
        );
        $overview['exam_approval_rate'] = round((float) $this->value(
            "SELECT COALESCE(
                100 * SUM(CASE WHEN er.status = 'approved' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0),
                0
             )
             FROM exam_results er
             INNER JOIN exams ex ON ex.id = er.exam_id
             INNER JOIN courses c ON c.id = ex.course_id
             WHERE c.company_id = :company_id",
            [':company_id' => $companyId]
        ), 1);
        $overview['pending_reviews'] = $this->pendingReviewCount($companyId);
        $overview['recent_comments'] = $this->count(
            "SELECT COUNT(*)
             FROM course_comments cm
             INNER JOIN courses c ON c.id = cm.course_id
             WHERE c.company_id = :company_id
               AND cm.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            [':company_id' => $companyId]
        );
        $overview['pending_questions'] = $this->pendingQuestionCount($companyId);
        $overview['questions_available'] = $this->schemaTableExists('course_questions')
            && $this->schemaTableExists('course_question_messages');

        return $overview;
    }

    public function coursePerformance(int $limit = 6): array
    {
        $limit = max(1, min(12, $limit));
        $stmt = $this->db->prepare(
            "SELECT
                c.id,
                c.name,
                c.status,
                SUM(CASE WHEN e.status IN ('active', 'completed') THEN 1 ELSE 0 END) AS enrollments_total,
                COALESCE(AVG(CASE WHEN e.status IN ('active', 'completed') THEN e.progress_percent END), 0) AS average_progress,
                COALESCE(
                    100 * COUNT(DISTINCT CASE WHEN e.status IN ('active', 'completed') AND recent.student_id IS NOT NULL THEN e.id END)
                    / NULLIF(COUNT(DISTINCT CASE WHEN e.status IN ('active', 'completed') THEN e.id END), 0),
                    0
                ) AS attendance_rate,
                SUM(CASE WHEN e.status = 'active' AND e.progress_percent < 40 THEN 1 ELSE 0 END) AS students_at_risk
             FROM courses c
             LEFT JOIN enrollments e ON e.course_id = c.id
             LEFT JOIN (
                SELECT student_id, course_id
                FROM student_lesson_progress
                WHERE last_event_at >= DATE_SUB(NOW(), INTERVAL 15 DAY)
                GROUP BY student_id, course_id
             ) recent ON recent.student_id = e.student_id AND recent.course_id = e.course_id
             WHERE c.company_id = :company_id
             GROUP BY c.id
             ORDER BY
                CASE WHEN c.status = 'published' THEN 0 ELSE 1 END,
                enrollments_total DESC,
                c.name ASC
             LIMIT {$limit}"
        );
        $stmt->execute([':company_id' => $this->companyId()]);

        return $stmt->fetchAll() ?: [];
    }

    public function studentsAtRisk(int $limit = 5): array
    {
        $limit = max(1, min(12, $limit));
        $stmt = $this->db->prepare(
            "SELECT
                s.id,
                s.full_name,
                c.id AS course_id,
                c.name AS course_name,
                e.progress_percent,
                MAX(slp.last_event_at) AS last_activity_at
             FROM enrollments e
             INNER JOIN students s ON s.id = e.student_id
             INNER JOIN courses c ON c.id = e.course_id
             LEFT JOIN student_lesson_progress slp
               ON slp.student_id = e.student_id
              AND slp.course_id = e.course_id
             WHERE c.company_id = :company_id
               AND e.status = 'active'
               AND e.progress_percent < 40
             GROUP BY e.id, s.id, c.id
             ORDER BY e.progress_percent ASC, last_activity_at ASC, s.full_name ASC
             LIMIT {$limit}"
        );
        $stmt->execute([':company_id' => $this->companyId()]);

        return $stmt->fetchAll() ?: [];
    }

    public function upcomingEvents(int $limit = 8): array
    {
        $limit = max(1, min(16, $limit));
        $events = [];

        $examStmt = $this->db->prepare(
            "SELECT
                ex.id,
                ex.title,
                ex.scheduled_at AS event_at,
                c.name AS course_name
             FROM exams ex
             INNER JOIN courses c ON c.id = ex.course_id
             WHERE c.company_id = :company_id
               AND ex.scheduled_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 45 DAY)
             ORDER BY ex.scheduled_at ASC
             LIMIT {$limit}"
        );
        $examStmt->execute([':company_id' => $this->companyId()]);
        foreach ($examStmt->fetchAll() ?: [] as $row) {
            $row['type'] = 'exam';
            $events[] = $row;
        }

        if ($this->schemaTableExists('course_live_sessions')) {
            $liveStmt = $this->db->prepare(
                "SELECT
                    cls.id,
                    cls.title,
                    cls.scheduled_at AS event_at,
                    c.name AS course_name
                 FROM course_live_sessions cls
                 INNER JOIN courses c ON c.id = cls.course_id
                 WHERE cls.company_id = :company_id
                   AND cls.status = 'scheduled'
                   AND cls.scheduled_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 45 DAY)
                 ORDER BY cls.scheduled_at ASC
                 LIMIT {$limit}"
            );
            $liveStmt->execute([':company_id' => $this->companyId()]);
            foreach ($liveStmt->fetchAll() ?: [] as $row) {
                $row['type'] = 'live';
                $events[] = $row;
            }
        }

        usort($events, static fn (array $a, array $b): int =>
            strcmp((string) ($a['event_at'] ?? ''), (string) ($b['event_at'] ?? ''))
        );

        return array_slice($events, 0, $limit);
    }

    public function recentComments(int $limit = 5): array
    {
        $limit = max(1, min(12, $limit));
        $stmt = $this->db->prepare(
            "SELECT
                cm.id,
                cm.comment,
                cm.created_at,
                c.id AS course_id,
                c.name AS course_name,
                u.name AS author_name
             FROM course_comments cm
             INNER JOIN courses c ON c.id = cm.course_id
             LEFT JOIN users u ON u.id = cm.created_by
             WHERE c.company_id = :company_id
             ORDER BY cm.created_at DESC, cm.id DESC
             LIMIT {$limit}"
        );
        $stmt->execute([':company_id' => $this->companyId()]);

        return $stmt->fetchAll() ?: [];
    }

    private function pendingReviewCount(int $companyId): int
    {
        if (!$this->schemaTableExists('exam_submissions')) {
            return 0;
        }

        return $this->count(
            "SELECT COUNT(*)
             FROM exam_submissions sub
             INNER JOIN exams ex ON ex.id = sub.exam_id
             INNER JOIN courses c ON c.id = ex.course_id
             WHERE c.company_id = :company_id
               AND sub.status = 'pending_review'",
            [':company_id' => $companyId]
        );
    }

    private function pendingQuestionCount(int $companyId): int
    {
        if (!$this->schemaTableExists('course_questions')) {
            return 0;
        }

        return $this->count(
            "SELECT COUNT(*)
             FROM course_questions
             WHERE company_id = :company_id
               AND status = 'open'",
            [':company_id' => $companyId]
        );
    }

    private function count(string $sql, array $params): int
    {
        return (int) $this->value($sql, $params);
    }

    private function attendanceRate(int $companyId): float
    {
        if (!$this->schemaTableExists('student_lesson_progress')) {
            return 0.0;
        }

        return round((float) $this->value(
            "SELECT COALESCE(
                100 * COUNT(DISTINCT CASE WHEN recent.student_id IS NOT NULL THEN e.id END)
                / NULLIF(COUNT(DISTINCT e.id), 0),
                0
             )
             FROM enrollments e
             INNER JOIN courses c ON c.id = e.course_id
             LEFT JOIN (
                SELECT student_id, course_id
                FROM student_lesson_progress
                WHERE last_event_at >= DATE_SUB(NOW(), INTERVAL 15 DAY)
                GROUP BY student_id, course_id
             ) recent ON recent.student_id = e.student_id AND recent.course_id = e.course_id
             WHERE c.company_id = :company_id
               AND e.status IN ('active', 'completed')",
            [':company_id' => $companyId]
        ), 1);
    }

    private function value(string $sql, array $params): mixed
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn();
    }

    private function emptyOverview(): array
    {
        return [
            'published_courses' => 0,
            'active_enrollments' => 0,
            'average_progress' => 0.0,
            'attendance_rate' => 0.0,
            'students_at_risk' => 0,
            'exam_results' => 0,
            'exam_approval_rate' => 0.0,
            'pending_reviews' => 0,
            'recent_comments' => 0,
            'pending_questions' => 0,
            'questions_available' => false,
        ];
    }
}
