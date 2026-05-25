<?php

class SupplierModel extends BaseModel
{
    public function tableExists(): bool
    {
        return $this->schemaTableExists('suppliers');
    }

    public function activeByCompany(int $companyId): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        $stmt = $this->db->prepare('SELECT *
            FROM suppliers
            WHERE company_id = :company_id
              AND is_active = 1
            ORDER BY name ASC');
        $stmt->execute([':company_id' => $companyId]);
        return $stmt->fetchAll();
    }

    public function allByCompany(int $companyId, array $filters, int $perPage, int $page): array
    {
        if (!$this->tableExists()) {
            return ['rows' => [], 'meta' => pagination_meta(0, $perPage, 1)];
        }

        $where = ['company_id = :company_id'];
        $params = [':company_id' => $companyId];

        if (!empty($filters['q'])) {
            $where[] = '(name LIKE :q OR document LIKE :q OR email LIKE :q OR phone LIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        if ($filters['status'] !== '') {
            $where[] = 'is_active = :is_active';
            $params[':is_active'] = $filters['status'] === 'active' ? 1 : 0;
        }

        $whereSql = implode(' AND ', $where);
        $countSql = "SELECT COUNT(*) FROM suppliers WHERE {$whereSql}";
        $dataSql = "SELECT *
            FROM suppliers
            WHERE {$whereSql}
            ORDER BY is_active DESC, name ASC";

        return $this->paginate($countSql, $dataSql, $params, $perPage, $page);
    }

    public function create(int $companyId, array $data, int $userId): int
    {
        if (!$this->tableExists()) {
            return 0;
        }

        $now = now();
        $stmt = $this->db->prepare('INSERT INTO suppliers (
            company_id, name, document, contact_name, email, phone, whatsapp, pix_key,
            bank_name, bank_agency, bank_account, notes, is_active, created_by, updated_by, created_at, updated_at
        ) VALUES (
            :company_id, :name, :document, :contact_name, :email, :phone, :whatsapp, :pix_key,
            :bank_name, :bank_agency, :bank_account, :notes, :is_active, :created_by, :updated_by, :created_at, :updated_at
        )');
        $stmt->execute([
            ':company_id' => $companyId,
            ':name' => $data['name'],
            ':document' => $data['document'] ?: null,
            ':contact_name' => $data['contact_name'] ?: null,
            ':email' => $data['email'] ?: null,
            ':phone' => $data['phone'] ?: null,
            ':whatsapp' => $data['whatsapp'] ?: null,
            ':pix_key' => $data['pix_key'] ?: null,
            ':bank_name' => $data['bank_name'] ?: null,
            ':bank_agency' => $data['bank_agency'] ?: null,
            ':bank_account' => $data['bank_account'] ?: null,
            ':notes' => $data['notes'] ?: null,
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
            ':created_by' => $userId > 0 ? $userId : null,
            ':updated_by' => $userId > 0 ? $userId : null,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function find(int $companyId, int $id): ?array
    {
        if (!$this->tableExists() || $id <= 0) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT *
            FROM suppliers
            WHERE company_id = :company_id
              AND id = :id
            LIMIT 1');
        $stmt->execute([
            ':company_id' => $companyId,
            ':id' => $id,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function setActive(int $companyId, int $id, bool $setActive, int $userId): bool
    {
        if (!$this->tableExists() || $id <= 0) {
            return false;
        }

        $stmt = $this->db->prepare('UPDATE suppliers
            SET is_active = :is_active,
                updated_by = :updated_by,
                updated_at = :updated_at
            WHERE company_id = :company_id
              AND id = :id');

        return $stmt->execute([
            ':is_active' => $setActive ? 1 : 0,
            ':updated_by' => $userId > 0 ? $userId : null,
            ':updated_at' => now(),
            ':company_id' => $companyId,
            ':id' => $id,
        ]);
    }
}
