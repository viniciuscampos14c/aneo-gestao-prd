<?php

class DataImportModel extends BaseModel
{
    public function ensureSchema(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS data_import_batches (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL,
            import_type VARCHAR(40) NOT NULL,
            original_filename VARCHAR(255) NULL,
            stored_file_path VARCHAR(255) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'uploaded',
            total_rows INT UNSIGNED NOT NULL DEFAULT 0,
            valid_rows INT UNSIGNED NOT NULL DEFAULT 0,
            error_rows INT UNSIGNED NOT NULL DEFAULT 0,
            created_count INT UNSIGNED NOT NULL DEFAULT 0,
            updated_count INT UNSIGNED NOT NULL DEFAULT 0,
            skipped_count INT UNSIGNED NOT NULL DEFAULT 0,
            error_count INT UNSIGNED NOT NULL DEFAULT 0,
            options_json LONGTEXT NULL,
            summary_json LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            completed_at DATETIME NULL,
            KEY idx_data_import_batches_company (company_id, created_at),
            KEY idx_data_import_batches_type_status (import_type, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->db->exec("CREATE TABLE IF NOT EXISTS data_import_rows (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            batch_id INT UNSIGNED NOT NULL,
            `row_number` INT UNSIGNED NOT NULL,
            source_key VARCHAR(190) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'valid',
            action VARCHAR(40) NOT NULL DEFAULT 'pending',
            target_table VARCHAR(80) NULL,
            target_id INT UNSIGNED NULL,
            raw_data LONGTEXT NULL,
            normalized_data LONGTEXT NULL,
            errors_json LONGTEXT NULL,
            warnings_json LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_data_import_rows_batch (batch_id, `row_number`),
            KEY idx_data_import_rows_status (batch_id, status),
            KEY idx_data_import_rows_source (batch_id, source_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->db->exec("CREATE TABLE IF NOT EXISTS data_import_entity_map (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL,
            import_type VARCHAR(40) NOT NULL,
            entity_type VARCHAR(60) NOT NULL,
            source_key VARCHAR(190) NOT NULL,
            entity_id INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uk_data_import_entity_map (company_id, import_type, entity_type, source_key),
            KEY idx_data_import_entity_map_entity (entity_type, entity_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function featureAvailable(): bool
    {
        return $this->schemaTableExists('data_import_batches')
            && $this->schemaTableExists('data_import_rows')
            && $this->schemaTableExists('data_import_entity_map');
    }

    public function listBatches(int $perPage, int $page): array
    {
        $params = [':company_id' => $this->companyId()];
        return $this->paginate(
            'SELECT COUNT(*) FROM data_import_batches WHERE company_id = :company_id',
            'SELECT b.*, u.name AS user_name
             FROM data_import_batches b
             LEFT JOIN users u ON u.id = b.user_id
             WHERE b.company_id = :company_id
             ORDER BY b.id DESC',
            $params,
            $perPage,
            $page
        );
    }

    public function findBatch(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT b.*, u.name AS user_name
            FROM data_import_batches b
            LEFT JOIN users u ON u.id = b.user_id
            WHERE b.id = :id AND b.company_id = :company_id
            LIMIT 1');
        $stmt->execute([
            ':id' => $id,
            ':company_id' => $this->companyId(),
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function rowsForBatch(int $batchId, int $limit = 500): array
    {
        $stmt = $this->db->prepare('SELECT *
            FROM data_import_rows
            WHERE batch_id = :batch_id
            ORDER BY `row_number` ASC, id ASC
            LIMIT :limit');
        $stmt->bindValue(':batch_id', $batchId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function validRowsForBatch(int $batchId): array
    {
        $stmt = $this->db->prepare("SELECT *
            FROM data_import_rows
            WHERE batch_id = :batch_id AND status = 'valid'
            ORDER BY `row_number` ASC, id ASC");
        $stmt->execute([':batch_id' => $batchId]);
        return $stmt->fetchAll();
    }

    public function createBatch(array $data): int
    {
        $stmt = $this->db->prepare('INSERT INTO data_import_batches (
            company_id, user_id, import_type, original_filename, stored_file_path, status,
            options_json, created_at, updated_at
        ) VALUES (
            :company_id, :user_id, :import_type, :original_filename, :stored_file_path, :status,
            :options_json, :created_at, :updated_at
        )');

        $stmt->execute([
            ':company_id' => $this->companyId(),
            ':user_id' => (int) ($data['user_id'] ?? 0) ?: null,
            ':import_type' => (string) $data['import_type'],
            ':original_filename' => (string) ($data['original_filename'] ?? ''),
            ':stored_file_path' => (string) ($data['stored_file_path'] ?? ''),
            ':status' => (string) ($data['status'] ?? 'uploaded'),
            ':options_json' => $this->jsonEncode($data['options'] ?? []),
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function insertRow(int $batchId, array $row): int
    {
        $stmt = $this->db->prepare('INSERT INTO data_import_rows (
            batch_id, `row_number`, source_key, status, action, raw_data, normalized_data,
            errors_json, warnings_json, created_at, updated_at
        ) VALUES (
            :batch_id, :row_number, :source_key, :status, :action, :raw_data, :normalized_data,
            :errors_json, :warnings_json, :created_at, :updated_at
        )');

        $stmt->execute([
            ':batch_id' => $batchId,
            ':row_number' => (int) ($row['row_number'] ?? 0),
            ':source_key' => $row['source_key'] !== '' ? (string) $row['source_key'] : null,
            ':status' => (string) ($row['status'] ?? 'valid'),
            ':action' => (string) ($row['action'] ?? 'pending'),
            ':raw_data' => $this->jsonEncode($row['raw_data'] ?? []),
            ':normalized_data' => $this->jsonEncode($row['normalized_data'] ?? []),
            ':errors_json' => $this->jsonEncode($row['errors'] ?? []),
            ':warnings_json' => $this->jsonEncode($row['warnings'] ?? []),
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateBatchValidation(int $batchId, int $totalRows, int $validRows, int $errorRows): void
    {
        $stmt = $this->db->prepare('UPDATE data_import_batches SET
            status = :status,
            total_rows = :total_rows,
            valid_rows = :valid_rows,
            error_rows = :error_rows,
            error_count = :error_count,
            updated_at = :updated_at
            WHERE id = :id AND company_id = :company_id');
        $stmt->execute([
            ':status' => 'validated',
            ':total_rows' => $totalRows,
            ':valid_rows' => $validRows,
            ':error_rows' => $errorRows,
            ':error_count' => $errorRows,
            ':updated_at' => now(),
            ':id' => $batchId,
            ':company_id' => $this->companyId(),
        ]);
    }

    public function markBatchFailed(int $batchId, string $message): void
    {
        $stmt = $this->db->prepare('UPDATE data_import_batches SET
            status = :status,
            error_count = error_count + 1,
            summary_json = :summary_json,
            updated_at = :updated_at
            WHERE id = :id AND company_id = :company_id');
        $stmt->execute([
            ':status' => 'failed',
            ':summary_json' => $this->jsonEncode(['message' => $message]),
            ':updated_at' => now(),
            ':id' => $batchId,
            ':company_id' => $this->companyId(),
        ]);
    }

    public function markRowImported(int $rowId, string $action, string $targetTable, int $targetId): void
    {
        $stmt = $this->db->prepare("UPDATE data_import_rows SET
            status = 'imported',
            action = :action,
            target_table = :target_table,
            target_id = :target_id,
            updated_at = :updated_at
            WHERE id = :id");
        $stmt->execute([
            ':action' => $action,
            ':target_table' => $targetTable,
            ':target_id' => $targetId,
            ':updated_at' => now(),
            ':id' => $rowId,
        ]);
    }

    public function completeBatch(int $batchId, array $summary): void
    {
        $stmt = $this->db->prepare("UPDATE data_import_batches SET
            status = 'completed',
            created_count = :created_count,
            updated_count = :updated_count,
            skipped_count = :skipped_count,
            summary_json = :summary_json,
            completed_at = :completed_at,
            updated_at = :updated_at
            WHERE id = :id AND company_id = :company_id");
        $stmt->execute([
            ':created_count' => (int) ($summary['created_count'] ?? 0),
            ':updated_count' => (int) ($summary['updated_count'] ?? 0),
            ':skipped_count' => (int) ($summary['skipped_count'] ?? 0),
            ':summary_json' => $this->jsonEncode($summary),
            ':completed_at' => now(),
            ':updated_at' => now(),
            ':id' => $batchId,
            ':company_id' => $this->companyId(),
        ]);
    }

    public function decodeRowData(?string $json): array
    {
        $decoded = json_decode((string) $json, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function findStudentCandidate(string $email, string $ra, ?int $companyId = null): ?array
    {
        $where = ['company_id = :company_id'];
        $params = [':company_id' => $companyId && $companyId > 0 ? $companyId : $this->companyId()];
        $or = [];

        if ($email !== '') {
            $or[] = 'LOWER(email_primary) = :email';
            $params[':email'] = strtolower($email);
        }

        if ($ra !== '') {
            $or[] = 'LOWER(ra) = :ra';
            $params[':ra'] = strtolower($ra);
        }

        if ($or === []) {
            return null;
        }

        $where[] = '(' . implode(' OR ', $or) . ')';
        $stmt = $this->db->prepare('SELECT * FROM students WHERE ' . implode(' AND ', $where) . ' ORDER BY id ASC LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findCompanyByImportRef(string $ref): ?array
    {
        $ref = trim($ref);
        if ($ref === '' || !$this->schemaTableExists('companies')) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $ref) ?? '';
        if (ctype_digit($ref)) {
            $stmt = $this->db->prepare('SELECT id, legal_name, trade_name, cnpj
                FROM companies
                WHERE id = :id
                LIMIT 1');
            $stmt->execute([':id' => (int) $ref]);
            $row = $stmt->fetch();
            if ($row) {
                return $row;
            }
        }

        if ($digits !== '' && strlen($digits) >= 8) {
            $stmt = $this->db->prepare('SELECT id, legal_name, trade_name, cnpj
                FROM companies
                WHERE REPLACE(REPLACE(REPLACE(REPLACE(cnpj, \'.\', \'\'), \'/\', \'\'), \'-\', \'\'), \' \', \'\') = :cnpj
                LIMIT 1');
            $stmt->execute([':cnpj' => $digits]);
            $row = $stmt->fetch();
            if ($row) {
                return $row;
            }
        }

        $stmt = $this->db->prepare('SELECT id, legal_name, trade_name, cnpj
            FROM companies
            WHERE LOWER(legal_name) = :name
               OR LOWER(trade_name) = :name
            LIMIT 1');
        $stmt->execute([':name' => strtolower($ref)]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function upsertStudent(array $data, int $createdBy): array
    {
        $companyId = (int) ($data['company_id'] ?? 0);
        if ($companyId <= 0) {
            $companyId = $this->companyId();
        }

        $existing = $this->findStudentCandidate(
            (string) ($data['email_primary'] ?? ''),
            (string) ($data['ra'] ?? ''),
            $companyId
        );
        $payload = $this->mergeStudentImportPayload($existing ?: [], $data, $companyId);
        $statusId = !empty($payload['kanban_status_id']) ? (int) $payload['kanban_status_id'] : $this->defaultKanbanStatusId();
        $supportsPhoto = $this->schemaColumnExists('students', 'profile_photo');
        $supportsCity = $this->schemaColumnExists('students', 'city');
        $supportsPractice = $this->studentPracticeFeatureAvailable();

        if ($existing) {
            $studentId = (int) $existing['id'];
            $sets = [
                'full_name = :full_name',
                'primary_contact = :primary_contact',
                'email_primary = :email_primary',
                'phone = :phone',
                'is_active = :is_active',
                'admin_info = :admin_info',
                'ra = :ra',
                'birth_date = :birth_date',
                'enrolled_at = :enrolled_at',
                'rg = :rg',
                'cro = :cro',
                'notes = :notes',
                'monthly_fee = :monthly_fee',
                'billing_day = :billing_day',
                'kanban_status_id = :kanban_status_id',
                'updated_at = :updated_at',
            ];
            if ($supportsPhoto) {
                $sets[] = 'profile_photo = :profile_photo';
            }
            if ($supportsCity) {
                $sets[] = 'city = :city';
            }
            if ($supportsPractice) {
                $sets[] = 'practice_unit_id = :practice_unit_id';
                $sets[] = 'residency_level = :residency_level';
            }

            $params = $this->studentSqlParams($payload, $companyId, $statusId, $supportsPhoto, $supportsCity, $supportsPractice);
            $params[':id'] = $studentId;

            $stmt = $this->db->prepare('UPDATE students SET ' . implode(', ', $sets) . '
                WHERE id = :id AND company_id = :company_id');
            $stmt->execute($params);

            if ((int) ($existing['kanban_status_id'] ?? 0) !== $statusId && $statusId > 0) {
                $this->registerStudentKanbanHistory($studentId, (int) ($existing['kanban_status_id'] ?? 0) ?: null, $statusId, $createdBy, 'Atualizacao por importacao de dados');
            }
            $action = 'update';
        } else {
            $columns = [
                'company_id', 'full_name', 'primary_contact', 'email_primary', 'phone', 'is_active',
                'admin_info', 'ra', 'birth_date', 'enrolled_at', 'rg', 'cro', 'notes', 'monthly_fee',
                'billing_day', 'kanban_status_id', 'created_by', 'created_at', 'updated_at',
            ];
            $values = [
                ':company_id', ':full_name', ':primary_contact', ':email_primary', ':phone', ':is_active',
                ':admin_info', ':ra', ':birth_date', ':enrolled_at', ':rg', ':cro', ':notes', ':monthly_fee',
                ':billing_day', ':kanban_status_id', ':created_by', ':created_at', ':updated_at',
            ];
            if ($supportsPhoto) {
                $columns[] = 'profile_photo';
                $values[] = ':profile_photo';
            }
            if ($supportsCity) {
                $columns[] = 'city';
                $values[] = ':city';
            }
            if ($supportsPractice) {
                $columns[] = 'practice_unit_id';
                $columns[] = 'residency_level';
                $values[] = ':practice_unit_id';
                $values[] = ':residency_level';
            }

            $payload['ra'] = $this->resolveStudentRa((string) ($payload['ra'] ?? ''), $companyId);
            $params = $this->studentSqlParams($payload, $companyId, $statusId, $supportsPhoto, $supportsCity, $supportsPractice);
            $params[':created_by'] = $createdBy > 0 ? $createdBy : null;
            $params[':created_at'] = now();

            $stmt = $this->db->prepare('INSERT INTO students (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $values) . ')');
            $stmt->execute($params);
            $studentId = (int) $this->db->lastInsertId();

            if ($statusId > 0) {
                $this->registerStudentKanbanHistory($studentId, null, $statusId, $createdBy, 'Cadastro por importacao de dados');
            }
            $action = 'create';
        }

        if (!empty($data['portal_provided']) && (string) ($data['portal_login'] ?? '') !== '') {
            $this->upsertStudentPortalAccount(
                $studentId,
                $companyId,
                (string) $data['portal_login'],
                (string) ($data['portal_password'] ?? '') ?: null,
                (int) ($data['portal_is_active'] ?? 0)
            );
        }

        return ['id' => $studentId, 'action' => $action];
    }

    public function findUserByEmail(string $email): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        $stmt = $this->db->prepare('SELECT id, name, username, email, role, is_active
            FROM users
            WHERE LOWER(email) = :email
            LIMIT 1');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findUserByUsername(string $username): ?array
    {
        $username = strtolower(trim($username));
        if ($username === '') {
            return null;
        }

        $stmt = $this->db->prepare('SELECT id, name, username, email, role, is_active
            FROM users
            WHERE LOWER(username) = :username
            LIMIT 1');
        $stmt->execute([':username' => $username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function upsertProfessorUser(array $data): array
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $username = strtolower(trim((string) ($data['username'] ?? '')));
        $existing = $this->findUserByEmail($email) ?: $this->findUserByUsername($username);
        $userId = $existing ? (int) $existing['id'] : 0;

        $payload = [
            ':name' => trim((string) ($data['name'] ?? '')),
            ':username' => $username,
            ':email' => $email,
            ':role' => 'professor',
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
            ':updated_at' => now(),
        ];

        if ($userId > 0) {
            $sql = 'UPDATE users SET
                name = :name,
                username = :username,
                email = :email,
                role = :role,
                is_active = :is_active,
                updated_at = :updated_at';

            $password = (string) ($data['password'] ?? '');
            if ($password !== '') {
                $sql .= ', password_hash = :password_hash';
                $payload[':password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            }

            $sql .= ' WHERE id = :id';
            $payload[':id'] = $userId;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($payload);
            $action = 'update';
        } else {
            $payload[':password_hash'] = password_hash((string) ($data['password'] ?? ''), PASSWORD_DEFAULT);
            $payload[':created_at'] = now();

            $stmt = $this->db->prepare('INSERT INTO users (
                name, username, email, password_hash, role, is_active, created_at, updated_at
            ) VALUES (
                :name, :username, :email, :password_hash, :role, :is_active, :created_at, :updated_at
            )');
            $stmt->execute($payload);
            $userId = (int) $this->db->lastInsertId();
            $action = 'create';
        }

        $this->clearUserPermissions($userId);
        $this->linkUserToCurrentCompany($userId, $action === 'create' || !$this->userHasDefaultCompany($userId));
        $this->saveEntityMap('professors', 'user', $email !== '' ? $email : $username, $userId);

        return ['id' => $userId, 'action' => $action];
    }

    public function upsertAdministrativeUser(array $data): array
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $username = strtolower(trim((string) ($data['username'] ?? '')));
        $existing = $this->findUserByEmail($email) ?: $this->findUserByUsername($username);
        $userId = $existing ? (int) $existing['id'] : 0;
        $role = in_array((string) ($data['role'] ?? 'admin'), ['admin', 'suporte'], true)
            ? (string) $data['role']
            : 'admin';

        $payload = [
            ':name' => trim((string) ($data['name'] ?? '')),
            ':username' => $username,
            ':email' => $email,
            ':role' => $role,
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
            ':updated_at' => now(),
        ];

        if ($userId > 0) {
            $sql = 'UPDATE users SET
                name = :name,
                username = :username,
                email = :email,
                role = :role,
                is_active = :is_active,
                updated_at = :updated_at';

            $password = (string) ($data['password'] ?? '');
            if ($password !== '') {
                $sql .= ', password_hash = :password_hash';
                $payload[':password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            }

            $sql .= ' WHERE id = :id';
            $payload[':id'] = $userId;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($payload);
            $action = 'update';
        } else {
            $payload[':password_hash'] = password_hash((string) ($data['password'] ?? ''), PASSWORD_DEFAULT);
            $payload[':created_at'] = now();

            $stmt = $this->db->prepare('INSERT INTO users (
                name, username, email, password_hash, role, is_active, created_at, updated_at
            ) VALUES (
                :name, :username, :email, :password_hash, :role, :is_active, :created_at, :updated_at
            )');
            $stmt->execute($payload);
            $userId = (int) $this->db->lastInsertId();
            $action = 'create';
        }

        $this->replaceUserPermissions($userId, $role === 'suporte' ? (array) ($data['permissions'] ?? []) : []);
        $this->syncUserCompaniesForImport($userId, (array) ($data['company_ids'] ?? []));
        $this->saveEntityMap('admin_users', 'user', $email !== '' ? $email : $username, $userId);

        return ['id' => $userId, 'action' => $action];
    }

    public function findPracticeUnitByName(string $name, ?int $companyId = null): ?array
    {
        if ($name === '' || !$this->schemaTableExists('student_practice_units')) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT id, name FROM student_practice_units
            WHERE company_id = :company_id AND LOWER(name) = :name
            LIMIT 1');
        $stmt->execute([
            ':company_id' => $companyId && $companyId > 0 ? $companyId : $this->companyId(),
            ':name' => strtolower($name),
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function practiceUnitsFeatureAvailable(): bool
    {
        return $this->schemaTableExists('student_practice_units');
    }

    public function upsertPracticeUnit(array $data, int $createdBy): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $existing = $this->findPracticeUnitByName($name);
        $unitId = $existing ? (int) $existing['id'] : 0;
        $city = trim((string) ($data['city'] ?? ''));
        $state = strtoupper(trim((string) ($data['state'] ?? '')));
        $isActive = !empty($data['status_provided'])
            ? (!empty($data['is_active']) ? 1 : 0)
            : (int) ($existing['is_active'] ?? 1);

        if ($unitId > 0) {
            $stmt = $this->db->prepare('UPDATE student_practice_units SET
                name = :name,
                city = :city,
                state = :state,
                is_active = :is_active,
                updated_at = :updated_at
                WHERE id = :id AND company_id = :company_id');
            $stmt->execute([
                ':name' => $name,
                ':city' => $city !== '' ? $city : null,
                ':state' => $state !== '' ? $state : null,
                ':is_active' => $isActive,
                ':updated_at' => now(),
                ':id' => $unitId,
                ':company_id' => $this->companyId(),
            ]);

            return ['id' => $unitId, 'action' => 'update'];
        }

        $stmt = $this->db->prepare('INSERT INTO student_practice_units (
            company_id, name, city, state, is_active, created_by, created_at, updated_at
        ) VALUES (
            :company_id, :name, :city, :state, :is_active, :created_by, :created_at, :updated_at
        )');
        $stmt->execute([
            ':company_id' => $this->companyId(),
            ':name' => $name,
            ':city' => $city !== '' ? $city : null,
            ':state' => $state !== '' ? $state : null,
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
            ':created_by' => $createdBy,
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);

        return ['id' => (int) $this->db->lastInsertId(), 'action' => 'create'];
    }

    public function arsenalFeatureAvailable(): bool
    {
        return $this->schemaTableExists('arsenal_categories')
            && $this->schemaTableExists('arsenal_items')
            && $this->schemaTableExists('arsenal_item_courses')
            && $this->schemaTableExists('arsenal_item_students');
    }

    public function findArsenalItemCandidate(string $sourceKey, string $categoryName, string $title): ?array
    {
        $mappedId = $this->findMappedEntity('arsenal', 'item', $sourceKey);
        if ($mappedId) {
            $item = $this->findArsenalItemById($mappedId);
            if ($item) {
                return $item;
            }
        }

        $title = trim($title);
        if ($title === '' || !$this->arsenalFeatureAvailable()) {
            return null;
        }

        $category = $this->findArsenalCategoryByName($categoryName);
        $where = ['company_id = :company_id', 'LOWER(title) = :title'];
        $params = [
            ':company_id' => $this->companyId(),
            ':title' => strtolower($title),
        ];
        if ($category) {
            $where[] = 'category_id = :category_id';
            $params[':category_id'] = (int) $category['id'];
        }

        $stmt = $this->db->prepare('SELECT *
            FROM arsenal_items
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY id ASC
            LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function upsertArsenalItem(array $data, int $createdBy): array
    {
        $category = $this->ensureArsenalCategory(
            (string) ($data['category_name'] ?? ''),
            (string) ($data['category_description'] ?? ''),
            $createdBy
        );
        $categoryAction = (string) ($category['action'] ?? 'update');
        $categoryId = (int) ($category['id'] ?? 0);
        $existing = $this->findArsenalItemCandidate(
            (string) ($data['source_key'] ?? ''),
            (string) ($data['category_name'] ?? ''),
            (string) ($data['title'] ?? '')
        );
        $itemId = $existing ? (int) $existing['id'] : 0;
        $payload = [
            ':company_id' => $this->companyId(),
            ':category_id' => $categoryId > 0 ? $categoryId : null,
            ':title' => trim((string) ($data['title'] ?? '')),
            ':description' => trim((string) ($data['description'] ?? '')) ?: null,
            ':material_type' => 'link',
            ':file_name' => null,
            ':file_path' => null,
            ':file_type' => null,
            ':file_size' => null,
            ':external_url' => trim((string) ($data['external_url'] ?? '')) ?: null,
            ':visibility_scope' => trim((string) ($data['visibility_scope'] ?? 'global')),
            ':status' => trim((string) ($data['status'] ?? 'draft')),
            ':publish_start_at' => trim((string) ($data['publish_start_at'] ?? '')) ?: null,
            ':publish_end_at' => trim((string) ($data['publish_end_at'] ?? '')) ?: null,
            ':sort_order' => (int) ($data['sort_order'] ?? 0),
            ':updated_at' => now(),
        ];

        if ($itemId > 0) {
            $stmt = $this->db->prepare('UPDATE arsenal_items SET
                category_id = :category_id,
                title = :title,
                description = :description,
                material_type = :material_type,
                file_name = :file_name,
                file_path = :file_path,
                file_type = :file_type,
                file_size = :file_size,
                external_url = :external_url,
                visibility_scope = :visibility_scope,
                status = :status,
                publish_start_at = :publish_start_at,
                publish_end_at = :publish_end_at,
                sort_order = :sort_order,
                updated_at = :updated_at
                WHERE id = :id AND company_id = :company_id');
            $payload[':id'] = $itemId;
            $stmt->execute($payload);
            $itemAction = 'update';
        } else {
            $stmt = $this->db->prepare('INSERT INTO arsenal_items (
                company_id, category_id, title, description, material_type, file_name, file_path, file_type, file_size,
                external_url, visibility_scope, status, publish_start_at, publish_end_at, sort_order, created_by,
                created_at, updated_at
            ) VALUES (
                :company_id, :category_id, :title, :description, :material_type, :file_name, :file_path, :file_type, :file_size,
                :external_url, :visibility_scope, :status, :publish_start_at, :publish_end_at, :sort_order, :created_by,
                :created_at, :updated_at
            )');
            $payload[':created_by'] = $createdBy > 0 ? $createdBy : null;
            $payload[':created_at'] = now();
            $stmt->execute($payload);
            $itemId = (int) $this->db->lastInsertId();
            $itemAction = 'create';
        }

        $this->saveEntityMap('arsenal', 'item', (string) ($data['source_key'] ?? ''), $itemId);

        return [
            'category_id' => $categoryId,
            'category_action' => $categoryAction,
            'item_id' => $itemId,
            'item_action' => $itemAction,
        ];
    }

    private function findArsenalCategoryByName(string $name): ?array
    {
        $name = trim($name);
        if ($name === '' || !$this->arsenalFeatureAvailable()) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT *
            FROM arsenal_categories
            WHERE company_id = :company_id
              AND LOWER(name) = :name
            LIMIT 1');
        $stmt->execute([
            ':company_id' => $this->companyId(),
            ':name' => strtolower($name),
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function ensureArsenalCategory(string $name, string $description, int $createdBy): array
    {
        $name = trim($name);
        if ($name === '' || !$this->arsenalFeatureAvailable()) {
            return ['id' => 0, 'action' => 'update'];
        }

        $existing = $this->findArsenalCategoryByName($name);
        if ($existing) {
            $stmt = $this->db->prepare('UPDATE arsenal_categories SET
                description = COALESCE(NULLIF(:description, \'\'), description),
                is_active = 1,
                updated_at = :updated_at
                WHERE id = :id AND company_id = :company_id');
            $stmt->execute([
                ':description' => trim($description),
                ':updated_at' => now(),
                ':id' => (int) $existing['id'],
                ':company_id' => $this->companyId(),
            ]);
            return ['id' => (int) $existing['id'], 'action' => 'update'];
        }

        $stmt = $this->db->prepare('INSERT INTO arsenal_categories (
            company_id, name, description, is_active, created_by, created_at, updated_at
        ) VALUES (
            :company_id, :name, :description, 1, :created_by, :created_at, :updated_at
        )');
        $stmt->execute([
            ':company_id' => $this->companyId(),
            ':name' => $name,
            ':description' => trim($description) !== '' ? trim($description) : null,
            ':created_by' => $createdBy > 0 ? $createdBy : null,
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);

        return ['id' => (int) $this->db->lastInsertId(), 'action' => 'create'];
    }

    private function findArsenalItemById(int $id): ?array
    {
        if ($id <= 0 || !$this->arsenalFeatureAvailable()) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT *
            FROM arsenal_items
            WHERE id = :id AND company_id = :company_id
            LIMIT 1');
        $stmt->execute([
            ':id' => $id,
            ':company_id' => $this->companyId(),
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findMappedEntity(string $importType, string $entityType, string $sourceKey): ?int
    {
        if ($sourceKey === '') {
            return null;
        }

        $stmt = $this->db->prepare('SELECT entity_id FROM data_import_entity_map
            WHERE company_id = :company_id
              AND import_type = :import_type
              AND entity_type = :entity_type
              AND source_key = :source_key
            LIMIT 1');
        $stmt->execute([
            ':company_id' => $this->companyId(),
            ':import_type' => $importType,
            ':entity_type' => $entityType,
            ':source_key' => $sourceKey,
        ]);
        $value = $stmt->fetchColumn();
        return $value ? (int) $value : null;
    }

    public function saveEntityMap(string $importType, string $entityType, string $sourceKey, int $entityId): void
    {
        if ($sourceKey === '' || $entityId <= 0) {
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO data_import_entity_map (
            company_id, import_type, entity_type, source_key, entity_id, created_at, updated_at
        ) VALUES (
            :company_id, :import_type, :entity_type, :source_key, :entity_id, :created_at, :updated_at
        ) ON DUPLICATE KEY UPDATE
            entity_id = VALUES(entity_id),
            updated_at = VALUES(updated_at)');
        $stmt->execute([
            ':company_id' => $this->companyId(),
            ':import_type' => $importType,
            ':entity_type' => $entityType,
            ':source_key' => $sourceKey,
            ':entity_id' => $entityId,
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);
    }

    public function findCourseCandidate(string $sourceKey, string $name): ?array
    {
        $mappedId = $this->findMappedEntity('courses_ead', 'course', $sourceKey);
        if ($mappedId) {
            $course = $this->findCourseById($mappedId);
            if ($course) {
                return $course;
            }
        }

        if ($name === '') {
            return null;
        }

        $where = ['LOWER(name) = :name'];
        $params = [':name' => strtolower($name)];
        if ($this->schemaColumnExists('courses', 'company_id') && $this->companyId() > 0) {
            $where[] = 'company_id = :company_id';
            $params[':company_id'] = $this->companyId();
        }

        $stmt = $this->db->prepare('SELECT * FROM courses WHERE ' . implode(' AND ', $where) . ' ORDER BY id ASC LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function upsertCourse(array $data, int $createdBy): array
    {
        $categoryId = $this->ensureCourseCategory((string) ($data['category'] ?? ''), $createdBy);
        $existing = $this->findCourseCandidate((string) ($data['source_key'] ?? ''), (string) ($data['name'] ?? ''));
        $courseId = $existing ? (int) $existing['id'] : 0;
        $hasCompany = $this->schemaColumnExists('courses', 'company_id') && $this->companyId() > 0;

        $payload = [
            ':name' => (string) ($data['name'] ?? ''),
            ':description' => (string) ($data['description'] ?? ''),
            ':category_id' => $categoryId,
            ':cover_image' => (string) ($data['cover_image'] ?? ''),
            ':status' => (string) ($data['status'] ?? 'draft'),
            ':workload_hours' => ($data['workload_hours'] ?? '') !== '' ? (int) $data['workload_hours'] : null,
            ':curriculum' => (string) ($data['curriculum'] ?? ''),
            ':materials' => (string) ($data['materials'] ?? ''),
            ':live_link' => (string) ($data['live_link'] ?? ''),
            ':live_password' => (string) ($data['live_password'] ?? ''),
            ':live_meeting_id' => (string) ($data['live_meeting_id'] ?? ''),
            ':live_datetime' => (string) ($data['live_datetime'] ?? '') ?: null,
            ':updated_at' => now(),
        ];

        if ($courseId > 0) {
            $where = 'id = :id';
            $payload[':id'] = $courseId;
            if ($hasCompany) {
                $where .= ' AND company_id = :company_id';
                $payload[':company_id'] = $this->companyId();
            }

            $stmt = $this->db->prepare("UPDATE courses SET
                name = :name,
                description = :description,
                category_id = :category_id,
                cover_image = :cover_image,
                status = :status,
                workload_hours = :workload_hours,
                curriculum = :curriculum,
                materials = :materials,
                live_link = :live_link,
                live_password = :live_password,
                live_meeting_id = :live_meeting_id,
                live_datetime = :live_datetime,
                updated_at = :updated_at
                WHERE {$where}");
            $stmt->execute($payload);
            $action = 'update';
        } else {
            $columns = 'name, description, category_id, cover_image, status, workload_hours, curriculum, materials, live_link, live_password, live_meeting_id, live_datetime, created_by, created_at, updated_at';
            $values = ':name, :description, :category_id, :cover_image, :status, :workload_hours, :curriculum, :materials, :live_link, :live_password, :live_meeting_id, :live_datetime, :created_by, :created_at, :updated_at';
            $payload[':created_by'] = $createdBy;
            $payload[':created_at'] = now();

            if ($hasCompany) {
                $columns = 'company_id, ' . $columns;
                $values = ':company_id, ' . $values;
                $payload[':company_id'] = $this->companyId();
            }

            $stmt = $this->db->prepare("INSERT INTO courses ({$columns}) VALUES ({$values})");
            $stmt->execute($payload);
            $courseId = (int) $this->db->lastInsertId();
            $action = 'create';
        }

        $this->saveEntityMap('courses_ead', 'course', (string) ($data['source_key'] ?? ''), $courseId);

        return ['id' => $courseId, 'action' => $action];
    }

    public function upsertCourseModule(int $courseId, array $data, int $createdBy): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        $displayOrder = max(1, (int) ($data['display_order'] ?? 1));
        $existing = $this->findCourseModuleByIdentity($courseId, $title, $displayOrder);
        $moduleId = $existing ? (int) $existing['id'] : 0;

        if ($moduleId > 0) {
            $stmt = $this->db->prepare('UPDATE course_modules SET
                title = :title,
                description = :description,
                display_order = :display_order,
                is_active = :is_active,
                updated_at = :updated_at
                WHERE id = :id AND course_id = :course_id');
            $stmt->execute([
                ':title' => $title,
                ':description' => trim((string) ($data['description'] ?? '')) ?: null,
                ':display_order' => $displayOrder,
                ':is_active' => !empty($data['is_active']) ? 1 : 0,
                ':updated_at' => now(),
                ':id' => $moduleId,
                ':course_id' => $courseId,
            ]);
            return ['id' => $moduleId, 'action' => 'update'];
        }

        $stmt = $this->db->prepare('INSERT INTO course_modules (
            course_id, title, description, display_order, is_active, created_by, created_at, updated_at
        ) VALUES (
            :course_id, :title, :description, :display_order, :is_active, :created_by, :created_at, :updated_at
        )');
        $stmt->execute([
            ':course_id' => $courseId,
            ':title' => $title,
            ':description' => trim((string) ($data['description'] ?? '')) ?: null,
            ':display_order' => $displayOrder,
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
            ':created_by' => $createdBy,
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);

        return ['id' => (int) $this->db->lastInsertId(), 'action' => 'create'];
    }

    public function upsertCourseLesson(int $courseId, int $moduleId, array $data, int $createdBy): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        $displayOrder = max(1, (int) ($data['display_order'] ?? 1));
        $existing = $this->findCourseLessonByIdentity($courseId, $moduleId, $title, $displayOrder);
        $lessonId = $existing ? (int) $existing['id'] : 0;
        $params = [
            ':title' => $title,
            ':description' => trim((string) ($data['description'] ?? '')) ?: null,
            ':video_url' => trim((string) ($data['video_url'] ?? '')),
            ':duration_seconds' => !empty($data['duration_seconds']) ? (int) $data['duration_seconds'] : null,
            ':min_progress_percent' => (int) ($data['min_progress_percent'] ?? 70),
            ':is_required' => !empty($data['is_required']) ? 1 : 0,
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
            ':display_order' => $displayOrder,
            ':updated_at' => now(),
        ];

        if ($lessonId > 0) {
            $stmt = $this->db->prepare('UPDATE course_lessons SET
                title = :title,
                description = :description,
                video_url = :video_url,
                duration_seconds = :duration_seconds,
                min_progress_percent = :min_progress_percent,
                is_required = :is_required,
                is_active = :is_active,
                display_order = :display_order,
                updated_at = :updated_at
                WHERE id = :id AND course_id = :course_id AND module_id = :module_id');
            $params[':id'] = $lessonId;
            $params[':course_id'] = $courseId;
            $params[':module_id'] = $moduleId;
            $stmt->execute($params);
            return ['id' => $lessonId, 'action' => 'update'];
        }

        $stmt = $this->db->prepare('INSERT INTO course_lessons (
            course_id, module_id, title, description, lesson_type, video_url, duration_seconds,
            min_progress_percent, is_required, is_active, display_order, created_by, created_at, updated_at
        ) VALUES (
            :course_id, :module_id, :title, :description, :lesson_type, :video_url, :duration_seconds,
            :min_progress_percent, :is_required, :is_active, :display_order, :created_by, :created_at, :updated_at
        )');
        $params[':course_id'] = $courseId;
        $params[':module_id'] = $moduleId;
        $params[':lesson_type'] = 'video';
        $params[':created_by'] = $createdBy;
        $params[':created_at'] = now();
        $stmt->execute($params);

        return ['id' => (int) $this->db->lastInsertId(), 'action' => 'create'];
    }

    private function clearUserPermissions(int $userId): void
    {
        if ($userId <= 0 || !$this->schemaTableExists('user_permissions')) {
            return;
        }

        $stmt = $this->db->prepare('DELETE FROM user_permissions WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $userId]);
    }

    private function userHasDefaultCompany(int $userId): bool
    {
        if ($userId <= 0 || !$this->schemaTableExists('user_companies')) {
            return false;
        }

        $stmt = $this->db->prepare('SELECT COUNT(*)
            FROM user_companies
            WHERE user_id = :user_id AND is_default = 1');
        $stmt->execute([':user_id' => $userId]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    private function linkUserToCurrentCompany(int $userId, bool $asDefault): void
    {
        $companyId = $this->companyId();
        if ($userId <= 0 || $companyId <= 0 || !$this->schemaTableExists('user_companies') || !$this->schemaTableExists('companies')) {
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
                is_default = IF(VALUES(is_default) = 1, 1, is_default),
                updated_at = VALUES(updated_at)');
        $stmt->execute([
            ':user_id' => $userId,
            ':company_id' => $companyId,
            ':is_default' => $asDefault ? 1 : 0,
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);
    }

    private function replaceUserPermissions(int $userId, array $permissionKeys): void
    {
        $this->clearUserPermissions($userId);
        if ($userId <= 0 || !$this->schemaTableExists('user_permissions')) {
            return;
        }

        $permissionKeys = array_values(array_unique(array_filter(array_map('strval', $permissionKeys), fn ($key) => trim($key) !== '')));
        if ($permissionKeys === []) {
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO user_permissions (
            user_id, permission_key, allowed, created_at, updated_at
        ) VALUES (
            :user_id, :permission_key, 1, :created_at, :updated_at
        )');

        foreach ($permissionKeys as $permissionKey) {
            $stmt->execute([
                ':user_id' => $userId,
                ':permission_key' => $permissionKey,
                ':created_at' => now(),
                ':updated_at' => now(),
            ]);
        }
    }

    private function syncUserCompaniesForImport(int $userId, array $companyIds): void
    {
        if ($userId <= 0 || !$this->schemaTableExists('user_companies') || !$this->schemaTableExists('companies')) {
            return;
        }

        $companyIds = array_values(array_unique(array_filter(array_map('intval', $companyIds), fn ($id) => $id > 0)));
        if ($companyIds === []) {
            $companyIds = [$this->companyId()];
        }

        $companyIds = array_values(array_filter($companyIds, fn ($id) => $id > 0));
        if ($companyIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($companyIds), '?'));
        $delete = $this->db->prepare("DELETE FROM user_companies WHERE user_id = ? AND company_id NOT IN ({$placeholders})");
        $delete->execute(array_merge([$userId], $companyIds));

        $defaultCompanyId = $companyIds[0];
        $resetDefault = $this->db->prepare('UPDATE user_companies SET is_default = 0, updated_at = :updated_at WHERE user_id = :user_id');
        $resetDefault->execute([
            ':updated_at' => now(),
            ':user_id' => $userId,
        ]);

        $stmt = $this->db->prepare('INSERT INTO user_companies (
                user_id, company_id, is_default, created_at, updated_at
            ) VALUES (
                :user_id, :company_id, :is_default, :created_at, :updated_at
            )
            ON DUPLICATE KEY UPDATE
                is_default = VALUES(is_default),
                updated_at = VALUES(updated_at)');

        foreach ($companyIds as $companyId) {
            $stmt->execute([
                ':user_id' => $userId,
                ':company_id' => $companyId,
                ':is_default' => $companyId === $defaultCompanyId ? 1 : 0,
                ':created_at' => now(),
                ':updated_at' => now(),
            ]);
        }
    }

    private function mergeStudentImportPayload(array $existing, array $data, int $companyId): array
    {
        $keep = fn (string $key, $default = '') => ($data[$key] ?? '') !== '' && $data[$key] !== null ? $data[$key] : ($existing[$key] ?? $default);

        return [
            'company_id' => $companyId,
            'full_name' => (string) ($data['full_name'] ?? $existing['full_name'] ?? ''),
            'primary_contact' => $keep('primary_contact'),
            'email_primary' => $keep('email_primary'),
            'phone' => $keep('phone'),
            'profile_photo' => (string) ($existing['profile_photo'] ?? ''),
            'city' => $keep('city'),
            'is_active' => !empty($data['status_provided']) ? (int) $data['is_active'] : (int) ($existing['is_active'] ?? 1),
            'admin_info' => $keep('admin_info'),
            'ra' => $keep('ra'),
            'birth_date' => $keep('birth_date', null),
            'enrolled_at' => $keep('enrolled_at', null),
            'rg' => $keep('rg'),
            'cro' => $keep('cro'),
            'notes' => $keep('notes'),
            'monthly_fee' => !empty($data['monthly_fee_provided']) ? (float) $data['monthly_fee'] : (float) ($existing['monthly_fee'] ?? 0),
            'billing_day' => !empty($data['billing_day_provided']) ? ($data['billing_day'] ?? '') : ($existing['billing_day'] ?? ''),
            'kanban_status_id' => $existing['kanban_status_id'] ?? null,
            'practice_unit_id' => !empty($data['practice_unit_provided']) ? (int) $data['practice_unit_id'] : ($existing['practice_unit_id'] ?? null),
            'residency_level' => !empty($data['residency_provided']) ? (string) $data['residency_level'] : (string) ($existing['residency_level'] ?? 'R1'),
        ];
    }

    private function studentSqlParams(array $payload, int $companyId, ?int $statusId, bool $supportsPhoto, bool $supportsCity, bool $supportsPractice): array
    {
        $params = [
            ':company_id' => $companyId,
            ':full_name' => (string) ($payload['full_name'] ?? ''),
            ':primary_contact' => (string) ($payload['primary_contact'] ?? ''),
            ':email_primary' => (string) ($payload['email_primary'] ?? ''),
            ':phone' => (string) ($payload['phone'] ?? ''),
            ':is_active' => (int) ($payload['is_active'] ?? 1),
            ':admin_info' => (string) ($payload['admin_info'] ?? ''),
            ':ra' => (string) ($payload['ra'] ?? ''),
            ':birth_date' => ($payload['birth_date'] ?? '') !== '' ? $payload['birth_date'] : null,
            ':enrolled_at' => ($payload['enrolled_at'] ?? '') !== '' ? $payload['enrolled_at'] : null,
            ':rg' => (string) ($payload['rg'] ?? ''),
            ':cro' => (string) ($payload['cro'] ?? ''),
            ':notes' => (string) ($payload['notes'] ?? ''),
            ':monthly_fee' => (float) ($payload['monthly_fee'] ?? 0),
            ':billing_day' => ($payload['billing_day'] ?? '') !== '' ? (int) $payload['billing_day'] : null,
            ':kanban_status_id' => $statusId ?: null,
            ':updated_at' => now(),
        ];

        if ($supportsPhoto) {
            $params[':profile_photo'] = trim((string) ($payload['profile_photo'] ?? '')) ?: null;
        }
        if ($supportsCity) {
            $params[':city'] = trim((string) ($payload['city'] ?? '')) ?: null;
        }
        if ($supportsPractice) {
            $params[':practice_unit_id'] = !empty($payload['practice_unit_id']) ? (int) $payload['practice_unit_id'] : null;
            $params[':residency_level'] = in_array(($payload['residency_level'] ?? 'R1'), ['R1', 'R2', 'R3'], true) ? $payload['residency_level'] : 'R1';
        }

        return $params;
    }

    private function resolveStudentRa(string $ra, int $companyId): string
    {
        $ra = trim($ra);
        if ($ra !== '') {
            return $ra;
        }

        return StudentRaGenerator::nextForCompany($this->db, $companyId) ?? '';
    }

    private function defaultKanbanStatusId(): ?int
    {
        if (!$this->schemaTableExists('kanban_status')) {
            return null;
        }

        $value = $this->db->query('SELECT id FROM kanban_status WHERE is_default = 1 ORDER BY id ASC LIMIT 1')->fetchColumn();
        return $value ? (int) $value : null;
    }

    private function registerStudentKanbanHistory(int $studentId, ?int $fromStatusId, int $toStatusId, int $changedBy, string $reason): void
    {
        if ($studentId <= 0 || $toStatusId <= 0 || !$this->schemaTableExists('student_kanban_history')) {
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO student_kanban_history (
            student_id, from_status_id, to_status_id, reason, changed_by, created_at
        ) VALUES (
            :student_id, :from_status_id, :to_status_id, :reason, :changed_by, :created_at
        )');
        $stmt->execute([
            ':student_id' => $studentId,
            ':from_status_id' => $fromStatusId,
            ':to_status_id' => $toStatusId,
            ':reason' => $reason,
            ':changed_by' => $changedBy > 0 ? $changedBy : null,
            ':created_at' => now(),
        ]);
    }

    private function studentPracticeFeatureAvailable(): bool
    {
        return $this->schemaTableExists('student_practice_units')
            && $this->schemaColumnExists('students', 'practice_unit_id')
            && $this->schemaColumnExists('students', 'residency_level');
    }

    public function findStudentPortalAccountByLogin(string $login, ?int $companyId = null): ?array
    {
        if (!$this->schemaTableExists('student_portal_accounts')) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT spa.student_id, spa.login
            FROM student_portal_accounts spa
            INNER JOIN students s ON s.id = spa.student_id
            WHERE spa.login = :login
              AND s.company_id = :company_id
            LIMIT 1');
        $stmt->execute([
            ':login' => $login,
            ':company_id' => $companyId && $companyId > 0 ? $companyId : $this->companyId(),
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function upsertStudentPortalAccount(int $studentId, int $companyId, string $login, ?string $password, int $isActive): void
    {
        if ($studentId <= 0 || !$this->schemaTableExists('student_portal_accounts')) {
            return;
        }

        $stmt = $this->db->prepare('SELECT spa.id
            FROM student_portal_accounts spa
            INNER JOIN students s ON s.id = spa.student_id
            WHERE spa.student_id = :student_id
              AND s.company_id = :company_id
            LIMIT 1');
        $stmt->execute([
            ':student_id' => $studentId,
            ':company_id' => $companyId,
        ]);
        $accountId = (int) ($stmt->fetchColumn() ?: 0);

        if ($accountId > 0) {
            $sql = 'UPDATE student_portal_accounts SET login = :login, is_active = :is_active, updated_at = :updated_at';
            $params = [
                ':login' => $login,
                ':is_active' => $isActive ? 1 : 0,
                ':updated_at' => now(),
                ':id' => $accountId,
            ];
            if ($password !== null && $password !== '') {
                $sql .= ', password_hash = :password_hash';
                $params[':password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            }
            $sql .= ' WHERE id = :id';
            $update = $this->db->prepare($sql);
            $update->execute($params);
            return;
        }

        $insert = $this->db->prepare('INSERT INTO student_portal_accounts (
            student_id, login, password_hash, is_active, created_at, updated_at
        ) VALUES (
            :student_id, :login, :password_hash, :is_active, :created_at, :updated_at
        )');
        $insert->execute([
            ':student_id' => $studentId,
            ':login' => $login,
            ':password_hash' => password_hash($password ?: bin2hex(random_bytes(6)), PASSWORD_DEFAULT),
            ':is_active' => $isActive ? 1 : 0,
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);
    }

    public function beginTransaction(): void
    {
        $this->db->beginTransaction();
    }

    public function commit(): void
    {
        $this->db->commit();
    }

    public function rollBack(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    private function ensureCourseCategory(string $name, int $createdBy): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $hasCompany = $this->schemaColumnExists('course_categories', 'company_id') && $this->companyId() > 0;
        $where = ['LOWER(name) = :name'];
        $params = [':name' => strtolower($name)];
        if ($hasCompany) {
            $where[] = 'company_id = :company_id';
            $params[':company_id'] = $this->companyId();
        }

        $stmt = $this->db->prepare('SELECT id FROM course_categories WHERE ' . implode(' AND ', $where) . ' LIMIT 1');
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        if ($value) {
            return (int) $value;
        }

        $columns = 'name, created_by, created_at, updated_at';
        $values = ':name, :created_by, :created_at, :updated_at';
        $insert = [
            ':name' => $name,
            ':created_by' => $createdBy,
            ':created_at' => now(),
            ':updated_at' => now(),
        ];
        if ($hasCompany) {
            $columns = 'company_id, ' . $columns;
            $values = ':company_id, ' . $values;
            $insert[':company_id'] = $this->companyId();
        }

        $stmt = $this->db->prepare("INSERT INTO course_categories ({$columns}) VALUES ({$values})");
        $stmt->execute($insert);
        return (int) $this->db->lastInsertId();
    }

    private function findCourseById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $where = ['id = :id'];
        $params = [':id' => $id];
        if ($this->schemaColumnExists('courses', 'company_id') && $this->companyId() > 0) {
            $where[] = 'company_id = :company_id';
            $params[':company_id'] = $this->companyId();
        }

        $stmt = $this->db->prepare('SELECT * FROM courses WHERE ' . implode(' AND ', $where) . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function findCourseModuleByIdentity(int $courseId, string $title, int $displayOrder): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM course_modules
            WHERE course_id = :course_id AND (LOWER(title) = :title OR display_order = :display_order)
            ORDER BY CASE WHEN LOWER(title) = :title THEN 0 ELSE 1 END, id ASC
            LIMIT 1');
        $stmt->execute([
            ':course_id' => $courseId,
            ':title' => strtolower($title),
            ':display_order' => $displayOrder,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function findCourseLessonByIdentity(int $courseId, int $moduleId, string $title, int $displayOrder): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM course_lessons
            WHERE course_id = :course_id
              AND module_id = :module_id
              AND (LOWER(title) = :title OR display_order = :display_order)
            ORDER BY CASE WHEN LOWER(title) = :title THEN 0 ELSE 1 END, id ASC
            LIMIT 1');
        $stmt->execute([
            ':course_id' => $courseId,
            ':module_id' => $moduleId,
            ':title' => strtolower($title),
            ':display_order' => $displayOrder,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function jsonEncode($value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
    }
}
