<?php

class UserModel extends BaseModel
{
    private ?bool $permissionsTableExists = null;
    private ?bool $userCompaniesTableExists = null;
    private ?bool $companiesTableExists = null;

    public function findByLogin(string $login): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE (email = :login OR username = :login) AND is_active = 1 LIMIT 1');
        $stmt->execute([':login' => $login]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, name, email, username, role, is_active FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findForEdit(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, name, email, username, role, is_active FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function list(array $filters, int $perPage, int $page): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = '(u.name LIKE :q OR u.email LIKE :q OR u.username LIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        if (!empty($filters['role'])) {
            $where[] = 'u.role = :role';
            $params[':role'] = $filters['role'];
        }

        if ($filters['is_active'] !== '' && $filters['is_active'] !== null) {
            $where[] = 'u.is_active = :is_active';
            $params[':is_active'] = (int) $filters['is_active'];
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) FROM users u WHERE {$whereSql}";
        $dataSql = "SELECT
                u.id,
                u.name,
                u.username,
                u.email,
                u.role,
                u.is_active,
                u.last_login_at,
                u.created_at
            FROM users u
            WHERE {$whereSql}
            ORDER BY u.id DESC";

        return $this->paginate($countSql, $dataSql, $params, $perPage, $page);
    }

    public function createUser(array $data, array $permissionKeys, array $companyIds = []): int
    {
        $stmt = $this->db->prepare('INSERT INTO users (
            name, username, email, password_hash, role, is_active, created_at, updated_at
        ) VALUES (
            :name, :username, :email, :password_hash, :role, :is_active, :created_at, :updated_at
        )');

        $stmt->execute([
            ':name' => $data['name'],
            ':username' => $data['username'],
            ':email' => $data['email'],
            ':password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            ':role' => $data['role'],
            ':is_active' => (int) $data['is_active'],
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);

        $id = (int) $this->db->lastInsertId();
        $this->savePermissions($id, $permissionKeys);

        $this->syncUserCompanies($id, $companyIds);

        return $id;
    }

    public function updateUser(int $id, array $data, array $permissionKeys, array $companyIds = []): void
    {
        $sql = 'UPDATE users SET
            name = :name,
            username = :username,
            email = :email,
            role = :role,
            is_active = :is_active,
            updated_at = :updated_at';

        $params = [
            ':name' => $data['name'],
            ':username' => $data['username'],
            ':email' => $data['email'],
            ':role' => $data['role'],
            ':is_active' => (int) $data['is_active'],
            ':updated_at' => now(),
            ':id' => $id,
        ];

        if (!empty($data['password'])) {
            $sql .= ', password_hash = :password_hash';
            $params[':password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $sql .= ' WHERE id = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $this->savePermissions($id, $permissionKeys);
        $this->syncUserCompanies($id, $companyIds);
    }

    public function setActive(int $id, int $active): void
    {
        $stmt = $this->db->prepare('UPDATE users SET is_active = :is_active, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            ':is_active' => $active ? 1 : 0,
            ':updated_at' => now(),
            ':id' => $id,
        ]);
    }

    public function deleteUser(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function permissionKeys(int $userId): array
    {
        if (!$this->hasPermissionsTable()) {
            return [];
        }

        $stmt = $this->db->prepare('SELECT permission_key FROM user_permissions WHERE user_id = :user_id AND allowed = 1');
        $stmt->execute([':user_id' => $userId]);

        return array_values(array_unique(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
    }

    public function companiesForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        if ($this->hasCompaniesTable() && $this->hasUserCompaniesTable()) {
            $stmt = $this->db->prepare('SELECT
                    c.id,
                    c.legal_name,
                    c.trade_name,
                    c.cnpj,
                    uc.is_default
                FROM user_companies uc
                INNER JOIN companies c ON c.id = uc.company_id
                WHERE uc.user_id = :user_id
                  AND c.is_active = 1
                ORDER BY uc.is_default DESC, c.legal_name ASC');
            $stmt->execute([':user_id' => $userId]);
            $rows = $stmt->fetchAll();
            return $rows ?: [];
        }

        if (!$this->hasCompaniesTable()) {
            return [];
        }

        $stmt = $this->db->query('SELECT
                id,
                legal_name,
                trade_name,
                cnpj,
                0 AS is_default
            FROM companies
            WHERE is_active = 1
            ORDER BY legal_name ASC');

        return $stmt->fetchAll();
    }

    public function userCanAccessCompany(int $userId, int $companyId): bool
    {
        if ($userId <= 0 || $companyId <= 0) {
            return false;
        }

        foreach ($this->companiesForUser($userId) as $company) {
            if ((int) ($company['id'] ?? 0) === $companyId) {
                return true;
            }
        }

        return false;
    }

    public function listActiveCompanies(): array
    {
        if (!$this->hasCompaniesTable()) {
            return [];
        }

        $stmt = $this->db->query('SELECT id, legal_name, trade_name, cnpj
            FROM companies
            WHERE is_active = 1
            ORDER BY legal_name ASC');
        return $stmt->fetchAll();
    }

    public function companyIdsForUser(int $userId): array
    {
        if ($userId <= 0 || !$this->hasUserCompaniesTable()) {
            return [];
        }

        $stmt = $this->db->prepare('SELECT company_id
            FROM user_companies
            WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_unique(array_map('intval', $rows ?: [])));
    }

    public function ensureUserCompany(int $userId, int $companyId, bool $asDefault = false): void
    {
        if (!$this->hasUserCompaniesTable() || !$this->hasCompaniesTable() || $userId <= 0 || $companyId <= 0) {
            return;
        }

        if ($asDefault) {
            $reset = $this->db->prepare('UPDATE user_companies SET is_default = 0, updated_at = :updated_at WHERE user_id = :user_id');
            $reset->execute([
                ':updated_at' => now(),
                ':user_id' => $userId,
            ]);
        }

        $stmt = $this->db->prepare('INSERT INTO user_companies (
                user_id, company_id, is_default, created_at, updated_at
            ) VALUES (
                :user_id, :company_id, :is_default, :created_at, :updated_at
            )
            ON DUPLICATE KEY UPDATE
                is_default = VALUES(is_default),
                updated_at = VALUES(updated_at)');
        $stmt->execute([
            ':user_id' => $userId,
            ':company_id' => $companyId,
            ':is_default' => $asDefault ? 1 : 0,
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);
    }

    public function syncUserCompanies(int $userId, array $companyIds): void
    {
        if (!$this->hasUserCompaniesTable() || !$this->hasCompaniesTable() || $userId <= 0) {
            return;
        }

        $cleanIds = array_values(array_unique(array_filter(array_map('intval', $companyIds), fn ($id) => $id > 0)));
        if ($cleanIds === []) {
            $currentCompanyId = current_company_id();
            if ($currentCompanyId !== null && $currentCompanyId > 0) {
                $cleanIds = [$currentCompanyId];
            }
        }

        if ($cleanIds === []) {
            return;
        }

        $this->db->beginTransaction();
        try {
            $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
            $delete = $this->db->prepare("DELETE FROM user_companies WHERE user_id = ? AND company_id NOT IN ({$placeholders})");
            $delete->execute(array_merge([$userId], $cleanIds));

            $defaultCompanyId = $cleanIds[0];
            $resetDefault = $this->db->prepare('UPDATE user_companies SET is_default = 0, updated_at = :updated_at WHERE user_id = :user_id');
            $resetDefault->execute([
                ':updated_at' => now(),
                ':user_id' => $userId,
            ]);

            foreach ($cleanIds as $companyId) {
                $this->ensureUserCompany($userId, $companyId, $companyId === $defaultCompanyId);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function savePermissions(int $userId, array $permissionKeys): void
    {
        if (!$this->hasPermissionsTable()) {
            return;
        }

        $permissionKeys = array_values(array_unique(array_filter(array_map('strval', $permissionKeys), fn ($key) => $key !== '')));

        $delete = $this->db->prepare('DELETE FROM user_permissions WHERE user_id = :user_id');
        $delete->execute([':user_id' => $userId]);

        if ($permissionKeys === []) {
            return;
        }

        $insert = $this->db->prepare('INSERT INTO user_permissions (
            user_id, permission_key, allowed, created_at, updated_at
        ) VALUES (
            :user_id, :permission_key, 1, :created_at, :updated_at
        )');

        foreach ($permissionKeys as $key) {
            $insert->execute([
                ':user_id' => $userId,
                ':permission_key' => $key,
                ':created_at' => now(),
                ':updated_at' => now(),
            ]);
        }
    }

    private function hasPermissionsTable(): bool
    {
        if ($this->permissionsTableExists !== null) {
            return $this->permissionsTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'user_permissions'");
        $stmt->execute();
        $this->permissionsTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->permissionsTableExists;
    }

    private function hasUserCompaniesTable(): bool
    {
        if ($this->userCompaniesTableExists !== null) {
            return $this->userCompaniesTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'user_companies'");
        $stmt->execute();
        $this->userCompaniesTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->userCompaniesTableExists;
    }

    private function hasCompaniesTable(): bool
    {
        if ($this->companiesTableExists !== null) {
            return $this->companiesTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'companies'");
        $stmt->execute();
        $this->companiesTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->companiesTableExists;
    }
}
