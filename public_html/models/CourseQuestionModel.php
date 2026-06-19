<?php

class CourseQuestionModel extends BaseModel
{
    public function featureAvailable(): bool
    {
        return $this->schemaTableExists('course_questions')
            && $this->schemaTableExists('course_question_messages');
    }

    public function countOpenForCompany(int $companyId): int
    {
        if (!$this->featureAvailable() || $companyId <= 0) {
            return 0;
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM course_questions
             WHERE company_id = :company_id
               AND status = 'open'"
        );
        $stmt->execute([':company_id' => $companyId]);

        return (int) $stmt->fetchColumn();
    }

    public function createFromStudent(
        int $companyId,
        int $studentId,
        int $courseId,
        ?int $lessonId,
        string $subject,
        string $message
    ): int {
        if (!$this->featureAvailable()) {
            throw new RuntimeException('Módulo de dúvidas ainda não está disponível.');
        }

        $subject = trim($subject);
        $message = trim($message);
        if ($subject === '' || $message === '') {
            throw new RuntimeException('Assunto e dúvida são obrigatórios.');
        }

        if (!$this->studentCanAsk($companyId, $studentId, $courseId, $lessonId)) {
            throw new RuntimeException('Curso ou aula não pertence a matrícula do aluno.');
        }

        $now = now();
        $this->db->beginTransaction();
        try {
            $question = $this->db->prepare(
                "INSERT INTO course_questions (
                    company_id, course_id, lesson_id, student_id, subject, status,
                    last_message_at, created_at, updated_at
                ) VALUES (
                    :company_id, :course_id, :lesson_id, :student_id, :subject, 'open',
                    :last_message_at, :created_at, :updated_at
                )"
            );
            $question->execute([
                ':company_id' => $companyId,
                ':course_id' => $courseId,
                ':lesson_id' => $lessonId,
                ':student_id' => $studentId,
                ':subject' => mb_strimwidth($subject, 0, 180, ''),
                ':last_message_at' => $now,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            $questionId = (int) $this->db->lastInsertId();

            $this->insertMessage($questionId, 'student', $studentId, $message, $now);
            $this->db->commit();

            return $questionId;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function listForStudent(int $studentId): array
    {
        if (!$this->featureAvailable() || $studentId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare(
            "SELECT
                q.*,
                c.name AS course_name,
                cl.title AS lesson_title
             FROM course_questions q
             INNER JOIN courses c ON c.id = q.course_id
             LEFT JOIN course_lessons cl ON cl.id = q.lesson_id
             WHERE q.student_id = :student_id
             ORDER BY q.last_message_at DESC, q.id DESC"
        );
        $stmt->execute([':student_id' => $studentId]);

        return $this->attachMessages($stmt->fetchAll() ?: []);
    }

    public function listForProfessor(int $companyId, string $status = ''): array
    {
        if (!$this->featureAvailable() || $companyId <= 0) {
            return [];
        }

        $where = ['q.company_id = :company_id'];
        $params = [':company_id' => $companyId];
        if (in_array($status, ['open', 'answered', 'resolved'], true)) {
            $where[] = 'q.status = :status';
            $params[':status'] = $status;
        }

        $stmt = $this->db->prepare(
            "SELECT
                q.*,
                c.name AS course_name,
                cl.title AS lesson_title,
                s.full_name AS student_name,
                s.email_primary AS student_email
             FROM course_questions q
             INNER JOIN courses c ON c.id = q.course_id
             INNER JOIN students s ON s.id = q.student_id
             LEFT JOIN course_lessons cl ON cl.id = q.lesson_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY
                CASE q.status WHEN 'open' THEN 0 WHEN 'answered' THEN 1 ELSE 2 END,
                q.last_message_at DESC,
                q.id DESC"
        );
        $stmt->execute($params);

        return $this->attachMessages($stmt->fetchAll() ?: []);
    }

    public function replyAsProfessor(int $questionId, int $companyId, int $userId, string $message): ?array
    {
        $message = trim($message);
        if (!$this->featureAvailable() || $message === '') {
            return null;
        }

        $question = $this->findForCompany($questionId, $companyId);
        if (!$question) {
            return null;
        }

        $now = now();
        $this->db->beginTransaction();
        try {
            $this->insertMessage($questionId, 'professor', $userId, $message, $now);
            $update = $this->db->prepare(
                "UPDATE course_questions
                 SET status = 'answered',
                     last_message_at = :last_message_at,
                     updated_at = :updated_at
                 WHERE id = :id AND company_id = :company_id"
            );
            $update->execute([
                ':last_message_at' => $now,
                ':updated_at' => $now,
                ':id' => $questionId,
                ':company_id' => $companyId,
            ]);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        $question['reply_excerpt'] = mb_strimwidth($message, 0, 220, '...');
        return $question;
    }

    public function resolve(int $questionId, int $companyId): bool
    {
        if (!$this->featureAvailable()) {
            return false;
        }

        $stmt = $this->db->prepare(
            "UPDATE course_questions
             SET status = 'resolved', updated_at = :updated_at
             WHERE id = :id AND company_id = :company_id"
        );
        $stmt->execute([
            ':updated_at' => now(),
            ':id' => $questionId,
            ':company_id' => $companyId,
        ]);

        return $stmt->rowCount() > 0;
    }

    private function studentCanAsk(int $companyId, int $studentId, int $courseId, ?int $lessonId): bool
    {
        $sql = "SELECT COUNT(*)
            FROM enrollments e
            INNER JOIN students s ON s.id = e.student_id
            INNER JOIN courses c ON c.id = e.course_id
            WHERE e.student_id = :student_id
              AND e.course_id = :course_id
              AND s.company_id = :company_id
              AND c.company_id = :company_id
              AND e.status IN ('active', 'completed')";
        $params = [
            ':student_id' => $studentId,
            ':course_id' => $courseId,
            ':company_id' => $companyId,
        ];

        if ($lessonId !== null && $lessonId > 0) {
            $sql .= ' AND EXISTS (
                SELECT 1 FROM course_lessons cl
                WHERE cl.id = :lesson_id AND cl.course_id = e.course_id
            )';
            $params[':lesson_id'] = $lessonId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function findForCompany(int $questionId, int $companyId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT q.*, c.name AS course_name, s.full_name AS student_name
             FROM course_questions q
             INNER JOIN courses c ON c.id = q.course_id
             INNER JOIN students s ON s.id = q.student_id
             WHERE q.id = :id AND q.company_id = :company_id
             LIMIT 1"
        );
        $stmt->execute([':id' => $questionId, ':company_id' => $companyId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function insertMessage(int $questionId, string $senderType, int $senderId, string $message, string $createdAt): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO course_question_messages (
                question_id, sender_type, sender_id, message, created_at
             ) VALUES (
                :question_id, :sender_type, :sender_id, :message, :created_at
             )"
        );
        $stmt->execute([
            ':question_id' => $questionId,
            ':sender_type' => $senderType,
            ':sender_id' => $senderId,
            ':message' => $message,
            ':created_at' => $createdAt,
        ]);
    }

    private function attachMessages(array $questions): array
    {
        if ($questions === []) {
            return [];
        }

        $ids = array_values(array_filter(array_map(
            static fn (array $row): int => (int) ($row['id'] ?? 0),
            $questions
        )));
        if ($ids === []) {
            return $questions;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT m.*, u.name AS professor_name
             FROM course_question_messages m
             LEFT JOIN users u ON m.sender_type = 'professor' AND u.id = m.sender_id
             WHERE m.question_id IN ({$placeholders})
             ORDER BY m.created_at ASC, m.id ASC"
        );
        $stmt->execute($ids);

        $messages = [];
        foreach ($stmt->fetchAll() ?: [] as $message) {
            $messages[(int) $message['question_id']][] = $message;
        }
        foreach ($questions as &$question) {
            $question['messages'] = $messages[(int) $question['id']] ?? [];
        }
        unset($question);

        return $questions;
    }
}
