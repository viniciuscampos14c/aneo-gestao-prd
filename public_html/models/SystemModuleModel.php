<?php

class SystemModuleModel extends BaseModel
{
    public function ensureSchema(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS system_modules (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            module_key VARCHAR(80) NOT NULL,
            title VARCHAR(160) NOT NULL,
            version VARCHAR(40) NOT NULL,
            min_core_version VARCHAR(40) NULL,
            description TEXT NULL,
            author VARCHAR(160) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'inactive',
            installed_by INT UNSIGNED NULL,
            installed_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            package_filename VARCHAR(255) NULL,
            package_hash CHAR(64) NULL,
            install_path VARCHAR(255) NULL,
            manifest_json LONGTEXT NULL,
            permissions_json LONGTEXT NULL,
            menu_json LONGTEXT NULL,
            migrations_json LONGTEXT NULL,
            last_error TEXT NULL,
            UNIQUE KEY uk_system_modules_key (module_key),
            KEY idx_system_modules_status (status),
            KEY idx_system_modules_installed_at (installed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->db->exec("CREATE TABLE IF NOT EXISTS system_module_permissions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            module_id INT UNSIGNED NOT NULL,
            module_key VARCHAR(80) NOT NULL,
            permission_key VARCHAR(120) NOT NULL,
            label VARCHAR(190) NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uk_system_module_permission (module_key, permission_key),
            KEY idx_system_module_permissions_module (module_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->db->exec("CREATE TABLE IF NOT EXISTS system_module_migrations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            module_id INT UNSIGNED NOT NULL,
            module_key VARCHAR(80) NOT NULL,
            migration_file VARCHAR(255) NOT NULL,
            checksum CHAR(64) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'executed',
            executed_by INT UNSIGNED NULL,
            executed_at DATETIME NOT NULL,
            error_message TEXT NULL,
            UNIQUE KEY uk_system_module_migration (module_key, migration_file),
            KEY idx_system_module_migrations_module (module_id),
            KEY idx_system_module_migrations_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->db->exec("CREATE TABLE IF NOT EXISTS system_module_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            module_id INT UNSIGNED NULL,
            module_key VARCHAR(80) NULL,
            action VARCHAR(60) NOT NULL,
            level VARCHAR(20) NOT NULL DEFAULT 'info',
            message TEXT NOT NULL,
            context_json LONGTEXT NULL,
            user_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            KEY idx_system_module_logs_module (module_id, created_at),
            KEY idx_system_module_logs_action (action, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function featureAvailable(): bool
    {
        return $this->schemaTableExists('system_modules')
            && $this->schemaTableExists('system_module_permissions')
            && $this->schemaTableExists('system_module_migrations')
            && $this->schemaTableExists('system_module_logs');
    }

    public function listModules(): array
    {
        $stmt = $this->db->query('SELECT m.*, u.name AS installed_by_name
            FROM system_modules m
            LEFT JOIN users u ON u.id = m.installed_by
            ORDER BY m.installed_at DESC, m.id DESC');

        return $stmt->fetchAll();
    }

    public function listActiveModules(): array
    {
        if (!$this->schemaTableExists('system_modules')) {
            return [];
        }

        $stmt = $this->db->query("SELECT *
            FROM system_modules
            WHERE status = 'active'
            ORDER BY title ASC, id ASC");

        return $stmt->fetchAll();
    }

    public function decodeJson($json): array
    {
        $decoded = json_decode((string) ($json ?? ''), true);
        return is_array($decoded) ? $decoded : [];
    }

    public function stats(): array
    {
        $stmt = $this->db->query('SELECT status, COUNT(*) AS total FROM system_modules GROUP BY status');
        $stats = [
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
            'error' => 0,
        ];

        foreach ($stmt->fetchAll() as $row) {
            $status = (string) ($row['status'] ?? '');
            $total = (int) ($row['total'] ?? 0);
            $stats['total'] += $total;
            if (array_key_exists($status, $stats)) {
                $stats[$status] = $total;
            }
        }

        return $stats;
    }

    public function findModule(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT m.*, u.name AS installed_by_name
            FROM system_modules m
            LEFT JOIN users u ON u.id = m.installed_by
            WHERE m.id = :id
            LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findByKey(string $moduleKey): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM system_modules WHERE module_key = :module_key LIMIT 1');
        $stmt->execute([':module_key' => $moduleKey]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function createModule(array $data): int
    {
        $stmt = $this->db->prepare('INSERT INTO system_modules (
            module_key, title, version, min_core_version, description, author, status,
            installed_by, installed_at, updated_at, package_filename, package_hash,
            install_path, manifest_json, permissions_json, menu_json, migrations_json, last_error
        ) VALUES (
            :module_key, :title, :version, :min_core_version, :description, :author, :status,
            :installed_by, :installed_at, :updated_at, :package_filename, :package_hash,
            :install_path, :manifest_json, :permissions_json, :menu_json, :migrations_json, :last_error
        )');

        $stmt->execute([
            ':module_key' => (string) $data['module_key'],
            ':title' => (string) $data['title'],
            ':version' => (string) $data['version'],
            ':min_core_version' => $this->nullableString($data['min_core_version'] ?? null),
            ':description' => $this->nullableString($data['description'] ?? null),
            ':author' => $this->nullableString($data['author'] ?? null),
            ':status' => (string) ($data['status'] ?? 'inactive'),
            ':installed_by' => (int) ($data['installed_by'] ?? 0) ?: null,
            ':installed_at' => now(),
            ':updated_at' => now(),
            ':package_filename' => $this->nullableString($data['package_filename'] ?? null),
            ':package_hash' => $this->nullableString($data['package_hash'] ?? null),
            ':install_path' => $this->nullableString($data['install_path'] ?? null),
            ':manifest_json' => $this->jsonEncode($data['manifest'] ?? []),
            ':permissions_json' => $this->jsonEncode($data['permissions'] ?? []),
            ':menu_json' => $this->jsonEncode($data['menu'] ?? []),
            ':migrations_json' => $this->jsonEncode($data['migrations'] ?? []),
            ':last_error' => $this->nullableString($data['last_error'] ?? null),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function setStatus(int $moduleId, string $status, ?string $lastError = null): void
    {
        $stmt = $this->db->prepare('UPDATE system_modules
            SET status = :status, last_error = :last_error, updated_at = :updated_at
            WHERE id = :id');
        $stmt->execute([
            ':id' => $moduleId,
            ':status' => $status,
            ':last_error' => $lastError,
            ':updated_at' => now(),
        ]);
    }

    public function replacePermissions(int $moduleId, string $moduleKey, array $permissions): void
    {
        $delete = $this->db->prepare('DELETE FROM system_module_permissions WHERE module_id = :module_id');
        $delete->execute([':module_id' => $moduleId]);

        if ($permissions === []) {
            return;
        }

        $insert = $this->db->prepare('INSERT INTO system_module_permissions (
            module_id, module_key, permission_key, label, created_at
        ) VALUES (
            :module_id, :module_key, :permission_key, :label, :created_at
        )');

        foreach ($permissions as $permission) {
            $insert->execute([
                ':module_id' => $moduleId,
                ':module_key' => $moduleKey,
                ':permission_key' => (string) ($permission['key'] ?? ''),
                ':label' => (string) ($permission['label'] ?? $permission['key'] ?? ''),
                ':created_at' => now(),
            ]);
        }
    }

    public function recordMigration(
        int $moduleId,
        string $moduleKey,
        string $migrationFile,
        string $checksum,
        string $status,
        ?string $errorMessage = null
    ): void {
        $stmt = $this->db->prepare('INSERT INTO system_module_migrations (
            module_id, module_key, migration_file, checksum, status, executed_by, executed_at, error_message
        ) VALUES (
            :module_id, :module_key, :migration_file, :checksum, :status, :executed_by, :executed_at, :error_message
        ) ON DUPLICATE KEY UPDATE
            checksum = VALUES(checksum),
            status = VALUES(status),
            executed_by = VALUES(executed_by),
            executed_at = VALUES(executed_at),
            error_message = VALUES(error_message)');

        $stmt->execute([
            ':module_id' => $moduleId,
            ':module_key' => $moduleKey,
            ':migration_file' => $migrationFile,
            ':checksum' => $checksum,
            ':status' => $status,
            ':executed_by' => (int) (current_user()['id'] ?? 0) ?: null,
            ':executed_at' => now(),
            ':error_message' => $errorMessage,
        ]);
    }

    public function listMigrations(?int $moduleId = null): array
    {
        if ($moduleId !== null && $moduleId > 0) {
            $stmt = $this->db->prepare('SELECT * FROM system_module_migrations
                WHERE module_id = :module_id
                ORDER BY executed_at DESC, id DESC');
            $stmt->execute([':module_id' => $moduleId]);
            return $stmt->fetchAll();
        }

        $stmt = $this->db->query('SELECT * FROM system_module_migrations ORDER BY executed_at DESC, id DESC LIMIT 80');
        return $stmt->fetchAll();
    }

    public function listLogs(?int $moduleId = null, int $limit = 80): array
    {
        $limit = max(10, min(200, $limit));

        if ($moduleId !== null && $moduleId > 0) {
            $stmt = $this->db->prepare('SELECT l.*, u.name AS user_name
                FROM system_module_logs l
                LEFT JOIN users u ON u.id = l.user_id
                WHERE l.module_id = :module_id
                ORDER BY l.created_at DESC, l.id DESC
                LIMIT :limit');
            $stmt->bindValue(':module_id', $moduleId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        }

        $stmt = $this->db->prepare('SELECT l.*, u.name AS user_name
            FROM system_module_logs l
            LEFT JOIN users u ON u.id = l.user_id
            ORDER BY l.created_at DESC, l.id DESC
            LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function log(?int $moduleId, ?string $moduleKey, string $action, string $level, string $message, array $context = []): void
    {
        $stmt = $this->db->prepare('INSERT INTO system_module_logs (
            module_id, module_key, action, level, message, context_json, user_id, created_at
        ) VALUES (
            :module_id, :module_key, :action, :level, :message, :context_json, :user_id, :created_at
        )');

        $stmt->execute([
            ':module_id' => $moduleId,
            ':module_key' => $moduleKey,
            ':action' => $action,
            ':level' => $level,
            ':message' => $message,
            ':context_json' => $this->jsonEncode($context),
            ':user_id' => (int) (current_user()['id'] ?? 0) ?: null,
            ':created_at' => now(),
        ]);
    }

    public function executeSql(string $sql): void
    {
        $this->db->exec($sql);
    }

    private function jsonEncode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value !== '' ? $value : null;
    }
}
