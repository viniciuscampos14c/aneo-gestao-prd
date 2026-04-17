<?php

class StudentExchangeModel extends BaseModel
{
    private ?bool $tableExists = null;

    // -------------------------------------------------------------------------
    // Feature guard
    // -------------------------------------------------------------------------

    public function featureAvailable(): bool
    {
        if ($this->tableExists !== null) {
            return $this->tableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'student_exchange_requests'");
        $stmt->execute();
        $this->tableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->tableExists;
    }

    // -------------------------------------------------------------------------
    // Student side
    // -------------------------------------------------------------------------

    public function submit(
        int    $studentId,
        int    $companyId,
        string $studentName,
        string $currentUnit,
        string $targetUnit,
        string $desiredMonth,
        int    $monthsEnrolled
    ): bool {
        if (!$this->featureAvailable()) {
            return false;
        }

        $stmt = $this->db->prepare('INSERT INTO student_exchange_requests (
            company_id, student_id, student_name, current_unit, target_unit,
            desired_month, months_enrolled, status, admin_notes, created_at, updated_at
        ) VALUES (
            :company_id, :student_id, :student_name, :current_unit, :target_unit,
            :desired_month, :months_enrolled, :status, NULL, :created_at, :updated_at
        )');

        $now = now();
        return $stmt->execute([
            ':company_id'      => $companyId,
            ':student_id'      => $studentId,
            ':student_name'    => $studentName,
            ':current_unit'    => $currentUnit,
            ':target_unit'     => $targetUnit,
            ':desired_month'   => $desiredMonth,
            ':months_enrolled' => $monthsEnrolled,
            ':status'          => 'pending',
            ':created_at'      => $now,
            ':updated_at'      => $now,
        ]);
    }

    /** Verifica se o aluno já tem uma solicitação pendente/visualizada */
    public function hasPendingRequest(int $studentId): bool
    {
        if (!$this->featureAvailable()) {
            return false;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM student_exchange_requests
            WHERE student_id = :sid AND status IN ('pending','viewed')");
        $stmt->execute([':sid' => $studentId]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    /** Últimas solicitações do aluno */
    public function myRequests(int $studentId): array
    {
        if (!$this->featureAvailable()) {
            return [];
        }

        $stmt = $this->db->prepare("SELECT * FROM student_exchange_requests
            WHERE student_id = :sid
            ORDER BY created_at DESC
            LIMIT 10");
        $stmt->execute([':sid' => $studentId]);
        return $stmt->fetchAll();
    }

    // -------------------------------------------------------------------------
    // Admin side
    // -------------------------------------------------------------------------

    public function listRequests(array $filters, int $perPage, int $page): array
    {
        if (!$this->featureAvailable()) {
            return ['rows' => [], 'meta' => ['total' => 0, 'page' => 1, 'per_page' => $perPage, 'last_page' => 1]];
        }

        $where  = ['1=1'];
        $params = [];

        $companyId = (int) current_company_id();
        if ($companyId > 0) {
            $where[]                = 'r.company_id = :company_id';
            $params[':company_id']  = $companyId;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where[]         = 'r.status = :status';
            $params[':status'] = $status;
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[]    = '(r.student_name LIKE :q OR r.current_unit LIKE :q OR r.target_unit LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

        $whereStr = implode(' AND ', $where);

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM student_exchange_requests r WHERE $whereStr");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;

        $stmt = $this->db->prepare("SELECT r.*, s.login AS student_login
            FROM student_exchange_requests r
            LEFT JOIN students s ON s.id = r.student_id
            WHERE $whereStr
            ORDER BY
                CASE r.status WHEN 'pending' THEN 1 WHEN 'viewed' THEN 2 WHEN 'approved' THEN 3 ELSE 4 END,
                r.created_at DESC
            LIMIT :limit OFFSET :offset");

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return [
            'rows' => $rows,
            'meta' => [
                'total'     => $total,
                'page'      => $page,
                'per_page'  => $perPage,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    public function findById(int $id, int $companyId): ?array
    {
        if (!$this->featureAvailable()) {
            return null;
        }

        $params = [':id' => $id];
        $andCompany = '';
        if ($companyId > 0) {
            $andCompany         = ' AND r.company_id = :company_id';
            $params[':company_id'] = $companyId;
        }

        $stmt = $this->db->prepare("SELECT r.*, s.login AS student_login, s.email_primary AS student_email
            FROM student_exchange_requests r
            LEFT JOIN students s ON s.id = r.student_id
            WHERE r.id = :id$andCompany
            LIMIT 1");
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function markViewed(int $id): void
    {
        if (!$this->featureAvailable()) {
            return;
        }

        $this->db->prepare("UPDATE student_exchange_requests
            SET status = 'viewed', updated_at = :now
            WHERE id = :id AND status = 'pending'")
            ->execute([':id' => $id, ':now' => now()]);
    }

    public function updateStatus(int $id, string $status, string $notes, int $companyId): bool
    {
        if (!$this->featureAvailable()) {
            return false;
        }

        $allowed = ['pending', 'viewed', 'approved', 'rejected'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }

        $params = [
            ':status'     => $status,
            ':notes'      => $notes !== '' ? $notes : null,
            ':updated_at' => now(),
            ':id'         => $id,
        ];

        $andCompany = '';
        if ($companyId > 0) {
            $andCompany                = ' AND company_id = :company_id';
            $params[':company_id']     = $companyId;
        }

        $stmt = $this->db->prepare("UPDATE student_exchange_requests
            SET status = :status, admin_notes = :notes, updated_at = :updated_at
            WHERE id = :id$andCompany");

        return $stmt->execute($params);
    }

    /** Contagens por status — para badges no menu */
    public function countByStatus(int $companyId): array
    {
        if (!$this->featureAvailable()) {
            return ['pending' => 0, 'viewed' => 0, 'approved' => 0, 'rejected' => 0, 'total' => 0];
        }

        $params = [];
        $andCompany = '';
        if ($companyId > 0) {
            $andCompany = 'WHERE company_id = :company_id';
            $params[':company_id'] = $companyId;
        }

        $stmt = $this->db->prepare("SELECT status, COUNT(*) AS cnt
            FROM student_exchange_requests
            $andCompany
            GROUP BY status");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $counts = ['pending' => 0, 'viewed' => 0, 'approved' => 0, 'rejected' => 0, 'total' => 0];
        foreach ($rows as $row) {
            $s = (string) ($row['status'] ?? '');
            $c = (int) ($row['cnt'] ?? 0);
            if (isset($counts[$s])) {
                $counts[$s] = $c;
            }
            $counts['total'] += $c;
        }

        return $counts;
    }

    /** Lista de empresas (unidades) para o formulário do aluno */
    public function listCompanies(): array
    {
        $stmt = $this->db->prepare("SELECT id, trade_name, legal_name
            FROM companies
            WHERE is_active = 1
            ORDER BY trade_name ASC, legal_name ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
