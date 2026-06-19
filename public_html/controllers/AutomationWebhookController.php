<?php

class AutomationWebhookController extends BaseController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = db();
    }

    public function enrollment(): void
    {
        if (!(bool) config('automation.enabled', true)) {
            $this->json([
                'ok' => false,
                'message' => 'Automacoes desativadas no config.php.',
            ], 503);
        }

        $payload = $this->readPayload();
        $providedToken = $this->providedToken($payload);
        $configuredToken = trim((string) config('automation.enrollment_webhook_token', ''));

        if ($configuredToken !== '' && !hash_equals($configuredToken, $providedToken)) {
            $this->json([
                'ok' => false,
                'message' => 'Token inválido para webhook de automacao.',
            ], 401);
        }

        $companyId = (int) ($payload['company_id'] ?? 0);
        $leadId = (int) ($payload['lead_id'] ?? 0);
        $courseId = (int) ($payload['course_id'] ?? 0);
        $enrollmentStatus = $this->normalizeEnrollmentStatus((string) ($payload['enrollment_status'] ?? 'active'));
        $paymentStatus = strtolower(trim((string) ($payload['payment_status'] ?? ($payload['asaas_status'] ?? ''))));
        $contractStatus = strtolower(trim((string) ($payload['contract_status'] ?? ($payload['d4sign_status'] ?? ''))));
        $forceActivate = $this->isTruthy($payload['force_activate'] ?? false);
        $createPortal = $this->isTruthy($payload['create_portal_account'] ?? false);
        $portalLogin = strtolower(trim((string) ($payload['portal_login'] ?? '')));
        $portalPassword = trim((string) ($payload['portal_password'] ?? ''));
        $activateStudent = !array_key_exists('activate_student', $payload) || $this->isTruthy($payload['activate_student']);
        $actorId = isset($payload['actor_user_id']) && (int) $payload['actor_user_id'] > 0 ? (int) $payload['actor_user_id'] : null;

        if ($companyId <= 0 || $leadId <= 0) {
            $this->json([
                'ok' => false,
                'message' => 'Informe company_id e lead_id no payload.',
            ], 422);
        }

        $shouldActivate = $forceActivate
            || ($this->isPaymentApproved($paymentStatus) && $this->isContractSigned($contractStatus));

        if (!$shouldActivate) {
            $this->json([
                'ok' => true,
                'activated' => false,
                'message' => 'Regras de ativacao ainda não atendidas.',
                'required' => 'payment_status aprovado + contract_status assinado, ou force_activate=true',
                'received' => [
                    'payment_status' => $paymentStatus,
                    'contract_status' => $contractStatus,
                ],
            ], 202);
        }

        try {
            $this->db->beginTransaction();

            $company = $this->findCompany($companyId);
            if (!$company) {
                throw new RuntimeException('Empresa não encontrada para automacao.');
            }
            if ((int) ($company['is_active'] ?? 0) !== 1) {
                throw new RuntimeException('Empresa inativa. Ative a empresa para processar a automacao.');
            }

            $lead = $this->findLead($companyId, $leadId);
            if (!$lead) {
                throw new RuntimeException('Lead não encontrado para a empresa informada.');
            }

            $studentCreated = false;
            $studentId = (int) ($lead['converted_student_id'] ?? 0);
            if ($studentId <= 0 || !$this->studentBelongsCompany($studentId, $companyId)) {
                $studentId = $this->createStudentFromLead($lead, $actorId);
                $studentCreated = true;
                $this->markLeadConverted($companyId, $leadId, $studentId, $actorId, (int) ($lead['lead_status_id'] ?? 0));
            }

            if ($activateStudent) {
                $this->setStudentActive($companyId, $studentId, 1);
            }

            $enrollment = null;
            if ($courseId > 0) {
                $enrollment = $this->ensureEnrollment($companyId, $studentId, $courseId, $enrollmentStatus, $actorId);
            }

            $portal = null;
            if ($createPortal || $portalLogin !== '') {
                $portal = $this->upsertPortalAccount(
                    $companyId,
                    $studentId,
                    $portalLogin,
                    $portalPassword,
                    $this->isTruthy($payload['portal_is_active'] ?? true)
                );
            }

            $this->db->commit();

            $this->json([
                'ok' => true,
                'activated' => true,
                'message' => 'Automacao de entrada de aluno processada com sucesso.',
                'data' => [
                    'company_id' => $companyId,
                    'lead_id' => $leadId,
                    'student_id' => $studentId,
                    'student_created' => $studentCreated,
                    'course_id' => $courseId > 0 ? $courseId : null,
                    'enrollment' => $enrollment,
                    'portal' => $portal,
                    'student_activated' => $activateStudent,
                ],
            ]);
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            $this->json([
                'ok' => false,
                'message' => 'Falha ao processar automacao: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function readPayload(): array
    {
        $raw = file_get_contents('php://input');
        $payload = is_string($raw) ? json_decode($raw, true) : null;

        if (!is_array($payload) || $payload === []) {
            $payload = $_POST ?: [];
        }

        return is_array($payload) ? $payload : [];
    }

    private function providedToken(array $payload): string
    {
        $token = trim((string) request('token', ''));
        if ($token !== '') {
            return $token;
        }

        if (isset($_SERVER['HTTP_X_ANEO_TOKEN'])) {
            $token = trim((string) $_SERVER['HTTP_X_ANEO_TOKEN']);
            if ($token !== '') {
                return $token;
            }
        }

        if (isset($payload['token'])) {
            $token = trim((string) $payload['token']);
            if ($token !== '') {
                return $token;
            }
        }

        return '';
    }

    private function normalizeEnrollmentStatus(string $status): string
    {
        $status = strtolower(trim($status));
        $allowed = ['active', 'paused', 'completed', 'cancelled'];
        return in_array($status, $allowed, true) ? $status : 'active';
    }

    private function isTruthy($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower(trim((string) $value));
        return in_array($value, ['1', 'true', 'yes', 'sim', 'on'], true);
    }

    private function isPaymentApproved(string $status): bool
    {
        $defaults = ['confirmed', 'received', 'paid'];
        $allowed = config('automation.payment_approved_statuses', $defaults);
        $allowed = is_array($allowed) ? $allowed : $defaults;
        $allowed = array_map(fn ($item) => strtolower(trim((string) $item)), $allowed);

        return in_array($status, $allowed, true);
    }

    private function isContractSigned(string $status): bool
    {
        $defaults = ['signed', 'completed', 'concluded', 'done'];
        $allowed = config('automation.contract_signed_statuses', $defaults);
        $allowed = is_array($allowed) ? $allowed : $defaults;
        $allowed = array_map(fn ($item) => strtolower(trim((string) $item)), $allowed);

        return in_array($status, $allowed, true);
    }

    private function findCompany(int $companyId): ?array
    {
        $stmt = $this->db->prepare('SELECT id, legal_name, trade_name, cnpj, is_active
            FROM companies
            WHERE id = :id
            LIMIT 1');
        $stmt->execute([':id' => $companyId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function findLead(int $companyId, int $leadId): ?array
    {
        $stmt = $this->db->prepare('SELECT *
            FROM leads
            WHERE id = :id
              AND company_id = :company_id
            LIMIT 1');
        $stmt->execute([
            ':id' => $leadId,
            ':company_id' => $companyId,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function studentBelongsCompany(int $studentId, int $companyId): bool
    {
        $stmt = $this->db->prepare('SELECT id
            FROM students
            WHERE id = :id
              AND company_id = :company_id
            LIMIT 1');
        $stmt->execute([
            ':id' => $studentId,
            ':company_id' => $companyId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private function createStudentFromLead(array $lead, ?int $actorId): int
    {
        $defaultKanban = $this->defaultKanbanStatusId();

        $stmt = $this->db->prepare('INSERT INTO students (
            company_id, full_name, primary_contact, email_primary, phone, is_active,
            admin_info, notes, kanban_status_id, created_by, created_at, updated_at
        ) VALUES (
            :company_id, :full_name, :primary_contact, :email_primary, :phone, 1,
            :admin_info, :notes, :kanban_status_id, :created_by, :created_at, :updated_at
        )');

        $now = now();
        $stmt->execute([
            ':company_id' => (int) ($lead['company_id'] ?? 0),
            ':full_name' => (string) ($lead['full_name'] ?? ''),
            ':primary_contact' => (string) ($lead['full_name'] ?? ''),
            ':email_primary' => (string) ($lead['email'] ?? ''),
            ':phone' => (string) ($lead['phone'] ?? ''),
            ':admin_info' => 'Convertido do lead #' . (int) ($lead['id'] ?? 0),
            ':notes' => 'Origem lead: ' . ((string) ($lead['source'] ?? '') !== '' ? (string) $lead['source'] : 'Não informado'),
            ':kanban_status_id' => $defaultKanban,
            ':created_by' => $actorId,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        $studentId = (int) $this->db->lastInsertId();

        if ($defaultKanban) {
            $history = $this->db->prepare('INSERT INTO student_kanban_history (
                student_id, from_status_id, to_status_id, reason, changed_by, created_at
            ) VALUES (
                :student_id, :from_status_id, :to_status_id, :reason, :changed_by, :created_at
            )');

            $history->execute([
                ':student_id' => $studentId,
                ':from_status_id' => null,
                ':to_status_id' => $defaultKanban,
                ':reason' => 'Conversao automatica via n8n',
                ':changed_by' => $actorId,
                ':created_at' => $now,
            ]);
        }

        return $studentId;
    }

    private function defaultKanbanStatusId(): ?int
    {
        $value = $this->db->query('SELECT id FROM kanban_status WHERE is_default = 1 ORDER BY id ASC LIMIT 1')->fetchColumn();
        return $value ? (int) $value : null;
    }

    private function markLeadConverted(int $companyId, int $leadId, int $studentId, ?int $actorId, int $statusId = 0): void
    {
        $stmt = $this->db->prepare('UPDATE leads SET
            converted_student_id = :student_id,
            converted_at = :converted_at,
            updated_at = :updated_at
            WHERE id = :id
              AND company_id = :company_id');

        $stmt->execute([
            ':student_id' => $studentId,
            ':converted_at' => now(),
            ':updated_at' => now(),
            ':id' => $leadId,
            ':company_id' => $companyId,
        ]);

        if ($this->tableExists('lead_history')) {
            $history = $this->db->prepare('INSERT INTO lead_history (
                lead_id, interaction, status_id, created_by, created_at
            ) VALUES (
                :lead_id, :interaction, :status_id, :created_by, :created_at
            )');

            $history->execute([
                ':lead_id' => $leadId,
                ':interaction' => 'Lead convertido automaticamente em aluno #' . $studentId . ' via n8n.',
                ':status_id' => $statusId > 0 ? $statusId : null,
                ':created_by' => $actorId,
                ':created_at' => now(),
            ]);
        }
    }

    private function setStudentActive(int $companyId, int $studentId, int $active): void
    {
        $stmt = $this->db->prepare('UPDATE students
            SET is_active = :is_active, updated_at = :updated_at
            WHERE id = :id
              AND company_id = :company_id');

        $stmt->execute([
            ':is_active' => $active ? 1 : 0,
            ':updated_at' => now(),
            ':id' => $studentId,
            ':company_id' => $companyId,
        ]);
    }

    private function ensureEnrollment(int $companyId, int $studentId, int $courseId, string $status, ?int $actorId): array
    {
        if (!$this->courseBelongsCompany($courseId, $companyId)) {
            throw new RuntimeException('Curso informado não pertence a empresa ativa.');
        }

        $existing = $this->db->prepare('SELECT id, status
            FROM enrollments
            WHERE student_id = :student_id
              AND course_id = :course_id
            ORDER BY id DESC
            LIMIT 1');
        $existing->execute([
            ':student_id' => $studentId,
            ':course_id' => $courseId,
        ]);
        $row = $existing->fetch();

        if ($row) {
            $currentStatus = (string) ($row['status'] ?? '');
            if ($currentStatus !== $status) {
                $update = $this->db->prepare('UPDATE enrollments
                    SET status = :status, updated_at = :updated_at
                    WHERE id = :id');
                $update->execute([
                    ':status' => $status,
                    ':updated_at' => now(),
                    ':id' => (int) $row['id'],
                ]);
            }

            return [
                'id' => (int) $row['id'],
                'created' => false,
                'status' => $status,
            ];
        }

        $insert = $this->db->prepare('INSERT INTO enrollments (
            student_id, course_id, status, progress_percent, started_at, completed_at, created_by, created_at, updated_at
        ) VALUES (
            :student_id, :course_id, :status, 0, :started_at, :completed_at, :created_by, :created_at, :updated_at
        )');

        $startedAt = $status === 'active' ? now() : null;
        $completedAt = $status === 'completed' ? now() : null;
        $createdAt = now();

        $insert->execute([
            ':student_id' => $studentId,
            ':course_id' => $courseId,
            ':status' => $status,
            ':started_at' => $startedAt,
            ':completed_at' => $completedAt,
            ':created_by' => $actorId,
            ':created_at' => $createdAt,
            ':updated_at' => $createdAt,
        ]);

        return [
            'id' => (int) $this->db->lastInsertId(),
            'created' => true,
            'status' => $status,
        ];
    }

    private function courseBelongsCompany(int $courseId, int $companyId): bool
    {
        if ($this->columnExists('courses', 'company_id')) {
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

    private function upsertPortalAccount(int $companyId, int $studentId, string $login, string $password, bool $isActive): array
    {
        if (!$this->tableExists('student_portal_accounts')) {
            throw new RuntimeException('Tabela student_portal_accounts não existe. Execute a migração de portal do aluno.');
        }

        $student = $this->db->prepare('SELECT id, email_primary
            FROM students
            WHERE id = :id
              AND company_id = :company_id
            LIMIT 1');
        $student->execute([
            ':id' => $studentId,
            ':company_id' => $companyId,
        ]);
        $studentRow = $student->fetch();

        if (!$studentRow) {
            throw new RuntimeException('Aluno não encontrado para criar acesso de portal.');
        }

        $normalizedLogin = trim($login);
        if ($normalizedLogin === '') {
            $email = trim((string) ($studentRow['email_primary'] ?? ''));
            $normalizedLogin = $email !== '' ? strtolower($email) : ('aluno' . $studentId);
        }

        $conflict = $this->db->prepare('SELECT id, student_id
            FROM student_portal_accounts
            WHERE login = :login
            LIMIT 1');
        $conflict->execute([':login' => $normalizedLogin]);
        $conflictRow = $conflict->fetch();
        if ($conflictRow && (int) $conflictRow['student_id'] !== $studentId) {
            throw new RuntimeException('Login de portal ja esta em uso por outro aluno.');
        }

        $existing = $this->db->prepare('SELECT id
            FROM student_portal_accounts
            WHERE student_id = :student_id
            LIMIT 1');
        $existing->execute([':student_id' => $studentId]);
        $existingRow = $existing->fetch();

        $generatedPassword = '';
        if ($existingRow) {
            $sql = 'UPDATE student_portal_accounts
                SET login = :login,
                    is_active = :is_active,
                    updated_at = :updated_at';

            $params = [
                ':login' => $normalizedLogin,
                ':is_active' => $isActive ? 1 : 0,
                ':updated_at' => now(),
                ':id' => (int) $existingRow['id'],
            ];

            if ($password !== '') {
                $sql .= ', password_hash = :password_hash';
                $params[':password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            }

            $sql .= ' WHERE id = :id';

            $update = $this->db->prepare($sql);
            $update->execute($params);

            return [
                'created' => false,
                'login' => $normalizedLogin,
                'is_active' => $isActive ? 1 : 0,
            ];
        }

        $plainPassword = $password;
        if ($plainPassword === '') {
            $plainPassword = $this->generateTemporaryPassword();
            $generatedPassword = $plainPassword;
        }

        $insert = $this->db->prepare('INSERT INTO student_portal_accounts (
            student_id, login, password_hash, is_active, created_at, updated_at
        ) VALUES (
            :student_id, :login, :password_hash, :is_active, :created_at, :updated_at
        )');
        $now = now();
        $insert->execute([
            ':student_id' => $studentId,
            ':login' => $normalizedLogin,
            ':password_hash' => password_hash($plainPassword, PASSWORD_DEFAULT),
            ':is_active' => $isActive ? 1 : 0,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        return [
            'created' => true,
            'login' => $normalizedLogin,
            'is_active' => $isActive ? 1 : 0,
            'temporary_password' => $generatedPassword !== '' ? $generatedPassword : null,
        ];
    }

    private function generateTemporaryPassword(): string
    {
        return bin2hex(random_bytes(6));
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = :table_name');
        $stmt->execute([':table_name' => $table]);

        return ((int) $stmt->fetchColumn()) > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = :table_name
              AND column_name = :column_name');
        $stmt->execute([
            ':table_name' => $table,
            ':column_name' => $column,
        ]);

        return ((int) $stmt->fetchColumn()) > 0;
    }
}
