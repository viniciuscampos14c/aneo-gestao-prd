<?php

class CourseLiveSessionModel extends BaseModel
{
    private ?bool $zoomCredentialColumnsExist = null;

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
                 status, is_global, global_session_uuid, global_master_session_id,
                 zoom_raw_response, created_by, created_at, updated_at)
             VALUES
                (:company_id, :course_id, :title, :zoom_meeting_id, :zoom_password,
                 :join_url, :start_url, :scheduled_at, :duration_minutes, :notes,
                 'scheduled', :is_global, :global_session_uuid, :global_master_session_id,
                 :zoom_raw_response, :created_by, :created_at, :updated_at)"
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
            ':is_global'         => !empty($data['is_global']) ? 1 : 0,
            ':global_session_uuid' => $data['global_session_uuid'] ?? null,
            ':global_master_session_id' => $data['global_master_session_id'] ?? null,
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

    public function cancelGlobal(string $globalSessionUuid): int
    {
        if ($globalSessionUuid === '') {
            return 0;
        }

        $stmt = $this->db->prepare(
            "UPDATE course_live_sessions
             SET status = 'cancelled', updated_at = :updated_at
             WHERE global_session_uuid = :global_session_uuid"
        );
        $stmt->execute([
            ':updated_at' => now(),
            ':global_session_uuid' => $globalSessionUuid,
        ]);

        return $stmt->rowCount();
    }

    public function globalPeers(string $globalSessionUuid): array
    {
        if ($globalSessionUuid === '') {
            return [];
        }

        $stmt = $this->db->prepare(
            "SELECT cls.*, c.name AS course_name, co.trade_name AS company_name
             FROM course_live_sessions cls
             INNER JOIN courses c ON c.id = cls.course_id
             INNER JOIN companies co ON co.id = cls.company_id
             WHERE cls.global_session_uuid = :global_session_uuid
             ORDER BY cls.company_id ASC, cls.id ASC"
        );
        $stmt->execute([':global_session_uuid' => $globalSessionUuid]);

        return $stmt->fetchAll();
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

    public function findCourseName(int $courseId, int $companyId): ?string
    {
        $stmt = $this->db->prepare(
            "SELECT name
             FROM courses
             WHERE id = :course_id
               AND company_id = :company_id
             LIMIT 1"
        );
        $stmt->execute([
            ':course_id' => $courseId,
            ':company_id' => $companyId,
        ]);

        $name = $stmt->fetchColumn();
        if (!is_string($name) || trim($name) === '') {
            return null;
        }

        return trim($name);
    }

    public function equivalentPublishedCoursesForGlobal(int $courseId, int $companyId): array
    {
        $courseName = $this->findCourseName($courseId, $companyId);
        if ($courseName === null) {
            return [];
        }

        $stmt = $this->db->prepare(
            "SELECT
                c.id AS course_id,
                c.company_id,
                co.trade_name AS company_name
             FROM courses c
             INNER JOIN companies co ON co.id = c.company_id
             WHERE c.name = :name
               AND c.status = 'published'
               AND co.is_active = 1
             ORDER BY CASE WHEN c.company_id = :company_id THEN 0 ELSE 1 END,
                      co.trade_name ASC,
                      c.id ASC"
        );
        $stmt->execute([
            ':name' => $courseName,
            ':company_id' => $companyId,
        ]);

        return $stmt->fetchAll();
    }

    public function createGlobalCopies(array $targets, array $data): array
    {
        if ($targets === []) {
            return [];
        }

        $created = [];
        $this->db->beginTransaction();

        try {
            foreach ($targets as $target) {
                $sessionId = $this->create(array_merge($data, [
                    'company_id' => (int) $target['company_id'],
                    'course_id' => (int) $target['course_id'],
                    'is_global' => 1,
                ]));

                if ($sessionId === false) {
                    throw new RuntimeException('Nao foi possivel salvar uma das copias da aula global.');
                }

                $created[] = [
                    'session_id' => $sessionId,
                    'company_id' => (int) $target['company_id'],
                    'company_name' => (string) ($target['company_name'] ?? ''),
                    'course_id' => (int) $target['course_id'],
                ];
            }

            $masterId = (int) ($created[0]['session_id'] ?? 0);
            if ($masterId > 0 && !empty($data['global_session_uuid'])) {
                $stmt = $this->db->prepare(
                    "UPDATE course_live_sessions
                     SET global_master_session_id = :master_id
                     WHERE global_session_uuid = :global_session_uuid"
                );
                $stmt->execute([
                    ':master_id' => $masterId,
                    ':global_session_uuid' => $data['global_session_uuid'],
                ]);
            }

            $this->db->commit();
            return $created;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function enrolledStudentsForCourse(int $courseId, int $companyId): array
    {
        $stmt = $this->db->prepare(
            "SELECT DISTINCT
                s.id,
                s.full_name,
                s.email_primary,
                spa.login AS portal_login
             FROM enrollments e
             INNER JOIN students s ON s.id = e.student_id
             LEFT JOIN student_portal_accounts spa ON spa.student_id = s.id
             WHERE e.course_id = :course_id
               AND s.company_id = :company_id
               AND e.status IN ('active', 'completed')
               AND s.email_primary IS NOT NULL
               AND s.email_primary <> ''
             ORDER BY s.full_name ASC"
        );
        $stmt->execute([
            ':course_id' => $courseId,
            ':company_id' => $companyId,
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Credenciais Zoom da empresa.
     * Retorna null se qualquer credencial estiver vazia.
     */
    public function getZoomCredentials(int $companyId): ?array
    {
        if (!$this->hasZoomCredentialColumns()) {
            return null;
        }

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
        if (!$this->hasZoomCredentialColumns()) {
            return false;
        }

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

    private function hasZoomCredentialColumns(): bool
    {
        if ($this->zoomCredentialColumnsExist !== null) {
            return $this->zoomCredentialColumnsExist;
        }

        $this->zoomCredentialColumnsExist =
            $this->schemaColumnExists('companies', 'zoom_account_id')
            && $this->schemaColumnExists('companies', 'zoom_client_id')
            && $this->schemaColumnExists('companies', 'zoom_client_secret');

        return $this->zoomCredentialColumnsExist;
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
