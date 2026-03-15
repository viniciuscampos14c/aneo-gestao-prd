<?php

class SystemAuditLogModel extends BaseModel
{
    private ?bool $tableExists = null;

    public function tableExists(): bool
    {
        if ($this->tableExists !== null) {
            return $this->tableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'system_audit_logs'");
        $stmt->execute();
        $this->tableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->tableExists;
    }

    public function insert(array $data): void
    {
        if (!$this->tableExists()) {
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO system_audit_logs (
            company_id, user_id, user_name, user_email, user_role,
            module, action, entity_type, entity_id, entity_label, description,
            changes_json, metadata_json, created_at
        ) VALUES (
            :company_id, :user_id, :user_name, :user_email, :user_role,
            :module, :action, :entity_type, :entity_id, :entity_label, :description,
            :changes_json, :metadata_json, :created_at
        )');

        $stmt->execute([
            ':company_id' => $data['company_id'] ?? null,
            ':user_id' => $data['user_id'] ?? null,
            ':user_name' => (string) ($data['user_name'] ?? ''),
            ':user_email' => (string) ($data['user_email'] ?? ''),
            ':user_role' => (string) ($data['user_role'] ?? ''),
            ':module' => (string) ($data['module'] ?? ''),
            ':action' => (string) ($data['action'] ?? ''),
            ':entity_type' => (string) ($data['entity_type'] ?? ''),
            ':entity_id' => $data['entity_id'] ?? null,
            ':entity_label' => $data['entity_label'] ?? null,
            ':description' => $data['description'] ?? null,
            ':changes_json' => $data['changes_json'] ?? null,
            ':metadata_json' => $data['metadata_json'] ?? null,
            ':created_at' => (string) ($data['created_at'] ?? now()),
        ]);
    }

    public function list(array $filters, int $perPage, int $page): array
    {
        if (!$this->tableExists()) {
            return [
                'rows' => [],
                'meta' => pagination_meta(0, $perPage, 1),
            ];
        }

        $where = ['1=1'];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(l.user_name LIKE :q OR l.user_email LIKE :q OR l.module LIKE :q OR l.action LIKE :q OR l.entity_type LIKE :q OR l.entity_label LIKE :q OR l.description LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

        $module = trim((string) ($filters['module'] ?? ''));
        if ($module !== '') {
            $where[] = 'l.module = :module';
            $params[':module'] = $module;
        }

        $userRole = trim((string) ($filters['user_role'] ?? ''));
        if ($userRole !== '') {
            $where[] = 'l.user_role = :user_role';
            $params[':user_role'] = $userRole;
        }

        $companyId = (int) ($filters['company_id'] ?? 0);
        if ($companyId > 0) {
            $where[] = 'l.company_id = :company_id';
            $params[':company_id'] = $companyId;
        }

        $startDate = trim((string) ($filters['start_date'] ?? ''));
        if ($startDate !== '') {
            $where[] = 'DATE(l.created_at) >= :start_date';
            $params[':start_date'] = $startDate;
        }

        $endDate = trim((string) ($filters['end_date'] ?? ''));
        if ($endDate !== '') {
            $where[] = 'DATE(l.created_at) <= :end_date';
            $params[':end_date'] = $endDate;
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) FROM system_audit_logs l WHERE {$whereSql}";
        $dataSql = "SELECT
                l.*,
                c.trade_name AS company_trade_name,
                c.legal_name AS company_legal_name
            FROM system_audit_logs l
            LEFT JOIN companies c ON c.id = l.company_id
            WHERE {$whereSql}
            ORDER BY l.id DESC";

        return $this->paginate($countSql, $dataSql, $params, $perPage, $page);
    }

    public function modules(): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        $stmt = $this->db->query('SELECT DISTINCT module
            FROM system_audit_logs
            WHERE module <> ""
            ORDER BY module ASC');

        return array_values(array_filter(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
    }
}
