<?php

class CompanyModel extends BaseModel
{
    public function list(array $filters, int $perPage, int $page): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = '(c.legal_name LIKE :q OR c.trade_name LIKE :q OR c.cnpj LIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        if ($filters['is_active'] !== '' && $filters['is_active'] !== null) {
            $where[] = 'c.is_active = :is_active';
            $params[':is_active'] = (int) $filters['is_active'];
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) FROM companies c WHERE {$whereSql}";
        $dataSql = "SELECT
                c.*,
                (SELECT COUNT(*) FROM user_companies uc WHERE uc.company_id = c.id) AS users_count
            FROM companies c
            WHERE {$whereSql}
            ORDER BY c.legal_name ASC";

        return $this->paginate($countSql, $dataSql, $params, $perPage, $page);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM companies WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('INSERT INTO companies (
            legal_name, trade_name, cnpj, is_active, created_at, updated_at
        ) VALUES (
            :legal_name, :trade_name, :cnpj, :is_active, :created_at, :updated_at
        )');

        $stmt->execute([
            ':legal_name' => $data['legal_name'],
            ':trade_name' => $data['trade_name'] !== '' ? $data['trade_name'] : null,
            ':cnpj' => $this->formatCnpj($data['cnpj']),
            ':is_active' => (int) $data['is_active'],
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare('UPDATE companies SET
            legal_name = :legal_name,
            trade_name = :trade_name,
            cnpj = :cnpj,
            is_active = :is_active,
            updated_at = :updated_at
            WHERE id = :id');

        $stmt->execute([
            ':legal_name' => $data['legal_name'],
            ':trade_name' => $data['trade_name'] !== '' ? $data['trade_name'] : null,
            ':cnpj' => $this->formatCnpj($data['cnpj']),
            ':is_active' => (int) $data['is_active'],
            ':updated_at' => now(),
            ':id' => $id,
        ]);
    }

    public function setActive(int $id, int $isActive): void
    {
        $stmt = $this->db->prepare('UPDATE companies SET is_active = :is_active, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            ':is_active' => $isActive ? 1 : 0,
            ':updated_at' => now(),
            ':id' => $id,
        ]);
    }

    public function activeCompanies(): array
    {
        return $this->db->query('SELECT id, legal_name, trade_name, cnpj
            FROM companies
            WHERE is_active = 1
            ORDER BY legal_name ASC')->fetchAll();
    }

    public function normalizeCnpj(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    public function formatCnpj(string $value): string
    {
        $digits = $this->normalizeCnpj($value);
        if (strlen($digits) !== 14) {
            return $digits;
        }

        return substr($digits, 0, 2) . '.' .
            substr($digits, 2, 3) . '.' .
            substr($digits, 5, 3) . '/' .
            substr($digits, 8, 4) . '-' .
            substr($digits, 12, 2);
    }
}
