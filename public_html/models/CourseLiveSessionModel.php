<?php

class CourseLiveSessionModel extends BaseModel
{
    // -------------------------------------------------------------------------
    // Listagem admin
    // -------------------------------------------------------------------------

    public function list(int $companyId, array $filters, int $perPage, int $page): array
    {
        $where  = ['cls.company_id = :company_id'];
        $params = [':company_id' => $companyId];

        if (!empty($filters['course_id'])) {
            $where[]              = 'cls.course_id = :course_id';
            $params[':course_id'] = (int) $filters['course_id'];
        }

        if (!empty($filters['status'])) {
            $where[]            = 'cls.status = :status';
            $params[':status']  = $filters['status'];
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) FROM course_live_sessions cls WHERE {$whereSql}";

        $dataSql = "SELECT cls.*, c.name AS course_name
                    FROM course_live_sessions cls
                    INNER JOIN courses c ON c.id = cls.course_id
                    WHERE {$whereSql}
                    ORDER BY cls.scheduled_at DESC";

        return $this->paginate($countSql, $dataSql, $params, $perPage, $page);
    }

    /**
     * Todas as sessões de um curso específico (para exibir no edit do curso).
     */
    public function listByCourse(int $courseId, int $companyId): array
    {
        $stmt = $this->db->prepare(
            "SELECT cls.*
             FROM course_live_sessions cls
             WHERE cls.course_id = :course_id AND cls.company_id = :company_id
             ORDER BY cls.scheduled_at DESC"
        );
        $stmt->execute([':course_id' => $courseId, ':company_id' => $companyId]);
        return $stmt->fetchAll();
    }

    // -------------------------------------------------------------------------
    // Busca individual
    // -------------------------------------------------------------------------

    public function findById(int $id, int $companyId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT cls.*, c.name AS course_name
             FROM course_live_sessions cls
             INNER JOIN courses c ON c.id = cls.course_id
             WHERE cls.id = :id AND cls.company_id = :company_id
             LIMIT 1"
        );
        $stmt->execute([':id' => $id, ':company_id' => $companyId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // -------------------------------------------------------------------------
    // Escrita
    // -------------------------------------------------------------------------

    /**
     * Insere nova sessão e retorna o ID gerado.
     *
     * @param array $data Campos: company_id, course_id, title, zoom_meeting_id,
     *                    zoom_password, join_url, start_url, scheduled_at,
     *                    duration_minutes, notes, zoom_raw_response, created_by
     * @return int|false  ID inserido ou false em caso de falha
     */
    public function create(array $data): int|false
    {
        $now  = now();
        $stmt = $this->db->prepare(
            "INSERT INTO course_live_sessions
                (company_id, course_id, title, zoom_meeting_id, zoom_password,
                 join_url, start_url, scheduled_at, duration_minutes, notes,
                 status, zoom_raw_response, created_by, created_at, updated_at)
             VALUES
                (:company_id, :course_id, :title, :zoom_meeting_id, :zoom_password,
                 :join_url, :start_url, :scheduled_at, :duration_minutes, :notes,
                 'scheduled', :zoom_raw_response, :created_by, :created_at, :updated_at)"
        );

        $ok = $stmt->execute([
            ':company_id'        => (int) $data['company_id'],
            ':course_id'         => (int) $data['course_id'],
            ':title'             => $data['title'],
            ':zoom_meeting_id'   => $data['zoom_meeting_id']    ?? null,
            ':zoom_password'     => $data['zoom_password']      ?? null,
            ':join_url'          => $data['join_url']           ?? null,
            ':start_url'         => $data['start_url']          ?? null,
            ':scheduled_at'      => $data['scheduled_at'],
            ':duration_minutes'  => (int) $data['duration_minutes'],
            ':notes'             => $data['notes']              ?? null,
            ':zoom_raw_response' => $data['zoom_raw_response']  ?? null,
            ':created_by'        => (int) $data['created_by'],
            ':created_at'        => $now,
            ':updated_at'        => $now,
        ]);

        return $ok ? (int) $this->db->lastInsertId() : false;
    }

    /**
     * Atualiza sessão existente (campos editáveis antes da aula acontecer).
     */
    public function update(int $id, array $data, int $companyId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE course_live_sessions
             SET course_id        = :course_id,
                 title            = :title,
                 scheduled_at     = :scheduled_at,
                 duration_minutes = :duration_minutes,
                 notes            = :notes,
                 updated_at       = :updated_at
             WHERE id = :id AND company_id = :company_id"
        );

        return $stmt->execute([
            ':course_id'        => (int) $data['course_id'],
            ':title'            => $data['title'],
            ':scheduled_at'     => $data['scheduled_at'],
            ':duration_minutes' => (int) $data['duration_minutes'],
            ':notes'            => $data['notes'] ?? null,
            ':updated_at'       => now(),
            ':id'               => $id,
            ':company_id'       => $companyId,
        ]);
    }

    /**
     * Muda status para 'cancelled'.
     */
    public function cancel(int $id, int $companyId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE course_live_sessions
             SET status = 'cancelled', updated_at = :updated_at
             WHERE id = :id AND company_id = :company_id"
        );
        return $stmt->execute([
            ':updated_at' => now(),
            ':id'         => $id,
            ':company_id' => $companyId,
        ]);
    }

    // -------------------------------------------------------------------------
    // Auxiliares
    // -------------------------------------------------------------------------

    /**
     * Cursos publicados disponíveis para o <select> do formulário.
     */
    public function listCourseOptions(int $companyId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, name FROM courses
             WHERE company_id = :company_id AND status = 'published'
             ORDER BY name ASC"
        );
        $stmt->execute([':company_id' => $companyId]);
        return $stmt->fetchAll();
    }

    /**
     * Credenciais Zoom da empresa.
     * Retorna null se qualquer credencial estiver vazia.
     */
    public function getZoomCredentials(int $companyId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT zoom_account_id, zoom_client_id, zoom_client_secret
             FROM companies WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $companyId]);
        $row = $stmt->fetch();

        if (
            $row === false
            || empty($row['zoom_account_id'])
            || empty($row['zoom_client_id'])
            || empty($row['zoom_client_secret'])
        ) {
            return null;
        }

        return $row;
    }

    /**
     * Salva credenciais Zoom da empresa.
     */
    public function saveZoomCredentials(int $companyId, string $accountId, string $clientId, string $clientSecret): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE companies
             SET zoom_account_id    = :account_id,
                 zoom_client_id     = :client_id,
                 zoom_client_secret = :client_secret,
                 updated_at         = :updated_at
             WHERE id = :id"
        );
        return $stmt->execute([
            ':account_id'    => $accountId    ?: null,
            ':client_id'     => $clientId     ?: null,
            ':client_secret' => $clientSecret ?: null,
            ':updated_at'    => now(),
            ':id'            => $companyId,
        ]);
    }

    // -------------------------------------------------------------------------
    // Portal do aluno
    // -------------------------------------------------------------------------

    /**
     * Sessões futuras (agendadas) para um aluno, via JOIN com enrollments.
     */
    public function upcomingForStudent(int $studentId): array
    {
        $stmt = $this->db->prepare(
            "SELECT cls.id, cls.title, cls.join_url, cls.zoom_password,
                    cls.zoom_meeting_id, cls.scheduled_at, cls.duration_minutes,
                    c.name AS course_name
             FROM course_live_sessions cls
             INNER JOIN courses c ON c.id = cls.course_id
             INNER JOIN enrollments e ON e.course_id = cls.course_id
             WHERE e.student_id = :sid
               AND e.status = 'active'
               AND c.status = 'published'
               AND cls.status = 'scheduled'
               AND cls.scheduled_at >= NOW()
             ORDER BY cls.scheduled_at ASC"
        );
        $stmt->execute([':sid' => $studentId]);
        return $stmt->fetchAll();
    }

    /**
     * Verifica se a tabela existe (usado para UNION retrocompatível no portal).
     */
    public function tableExists(): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = 'course_live_sessions'"
        );
        $stmt->execute();
        return ((int) $stmt->fetchColumn()) > 0;
    }
}
