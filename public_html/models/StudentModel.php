<?php

class StudentModel extends BaseModel
{
    private ?bool $portalAccountsTableExists = null;
    private ?bool $studentProfilePhotoColumnExists = null;

    public function stats(): array
    {
        $params = [':company_id' => $this->companyId()];

        return [
            'total' => (int) $this->scalar('SELECT COUNT(*) FROM students WHERE company_id = :company_id', $params),
            'active' => (int) $this->scalar('SELECT COUNT(*) FROM students WHERE company_id = :company_id AND is_active = 1', $params),
            'inactive' => (int) $this->scalar('SELECT COUNT(*) FROM students WHERE company_id = :company_id AND is_active = 0', $params),
            'contacts_active' => (int) $this->scalar("SELECT COUNT(*)
                FROM student_contacts sc
                INNER JOIN students s ON s.id = sc.student_id
                WHERE s.company_id = :company_id AND sc.is_active = 1", $params),
            'contacts_inactive' => (int) $this->scalar("SELECT COUNT(*)
                FROM student_contacts sc
                INNER JOIN students s ON s.id = sc.student_id
                WHERE s.company_id = :company_id AND sc.is_active = 0", $params),
        ];
    }

    public function allKanbanStatuses(): array
    {
        return $this->db->query('SELECT id, name, slug, color, display_order, is_default FROM kanban_status ORDER BY display_order ASC, id ASC')->fetchAll();
    }

    public function defaultKanbanStatusId(): ?int
    {
        $value = $this->db->query('SELECT id FROM kanban_status WHERE is_default = 1 ORDER BY id ASC LIMIT 1')->fetchColumn();
        return $value ? (int) $value : null;
    }

    public function list(array $filters, int $perPage, int $page): array
    {
        $where = ['s.company_id = :company_id'];
        $params = [':company_id' => $this->companyId()];

        if (!empty($filters['q'])) {
            $where[] = '(s.full_name LIKE :q OR s.email_primary LIKE :q OR s.phone LIKE :q OR s.ra LIKE :q OR s.rg LIKE :q OR s.cro LIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $where[] = 's.is_active = :is_active';
            $params[':is_active'] = (int) $filters['is_active'];
        }

        if (!empty($filters['kanban_status_id'])) {
            $where[] = 's.kanban_status_id = :kanban_status_id';
            $params[':kanban_status_id'] = (int) $filters['kanban_status_id'];
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*)
            FROM students s
            LEFT JOIN kanban_status ks ON ks.id = s.kanban_status_id
            WHERE {$whereSql}";

        $dataSql = "SELECT s.*, ks.name AS kanban_status_name, ks.color AS kanban_status_color
            FROM students s
            LEFT JOIN kanban_status ks ON ks.id = s.kanban_status_id
            WHERE {$whereSql}
            ORDER BY s.id DESC";

        return $this->paginate($countSql, $dataSql, $params, $perPage, $page);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT s.*, ks.name AS kanban_status_name, ks.color AS kanban_status_color
            FROM students s
            LEFT JOIN kanban_status ks ON ks.id = s.kanban_status_id
            WHERE s.id = :id
              AND s.company_id = :company_id
            LIMIT 1');
        $stmt->execute([
            ':id' => $id,
            ':company_id' => $this->companyId(),
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function create(array $data, int $createdBy): int
    {
        $statusId = $data['kanban_status_id'] ? (int) $data['kanban_status_id'] : $this->defaultKanbanStatusId();
        $supportsPhoto = $this->hasStudentProfilePhotoColumn();
        $insertSql = $supportsPhoto
            ? 'INSERT INTO students (
                company_id, full_name, primary_contact, email_primary, phone, profile_photo, is_active,
                admin_info, ra, birth_date, rg, cro, notes, monthly_fee, billing_day,
                kanban_status_id, created_by, created_at, updated_at
            ) VALUES (
                :company_id, :full_name, :primary_contact, :email_primary, :phone, :profile_photo, :is_active,
                :admin_info, :ra, :birth_date, :rg, :cro, :notes, :monthly_fee, :billing_day,
                :kanban_status_id, :created_by, :created_at, :updated_at
            )'
            : 'INSERT INTO students (
                company_id, full_name, primary_contact, email_primary, phone, is_active,
                admin_info, ra, birth_date, rg, cro, notes, monthly_fee, billing_day,
                kanban_status_id, created_by, created_at, updated_at
            ) VALUES (
                :company_id, :full_name, :primary_contact, :email_primary, :phone, :is_active,
                :admin_info, :ra, :birth_date, :rg, :cro, :notes, :monthly_fee, :billing_day,
                :kanban_status_id, :created_by, :created_at, :updated_at
            )';
        $stmt = $this->db->prepare($insertSql);

        $now = now();
        $params = [
            ':company_id' => $this->companyId(),
            ':full_name' => $data['full_name'],
            ':primary_contact' => $data['primary_contact'],
            ':email_primary' => $data['email_primary'],
            ':phone' => $data['phone'],
            ':is_active' => (int) ($data['is_active'] ?? 1),
            ':admin_info' => $data['admin_info'],
            ':ra' => $data['ra'],
            ':birth_date' => $data['birth_date'] ?: null,
            ':rg' => $data['rg'],
            ':cro' => $data['cro'],
            ':notes' => $data['notes'],
            ':monthly_fee' => (float) ($data['monthly_fee'] ?? 0),
            ':billing_day' => $data['billing_day'] !== '' ? (int) $data['billing_day'] : null,
            ':kanban_status_id' => $statusId,
            ':created_by' => $createdBy,
            ':created_at' => $now,
            ':updated_at' => $now,
        ];

        if ($supportsPhoto) {
            $params[':profile_photo'] = ($data['profile_photo'] ?? '') !== '' ? $data['profile_photo'] : null;
        }

        $stmt->execute($params);

        $studentId = (int) $this->db->lastInsertId();

        if ($statusId) {
            $this->registerKanbanHistory($studentId, null, $statusId, $createdBy, 'Cadastro do aluno');
        }

        return $studentId;
    }

    public function update(int $id, array $data, int $updatedBy): void
    {
        $current = $this->find($id);
        if (!$current) {
            return;
        }

        $statusId = $data['kanban_status_id'] ? (int) $data['kanban_status_id'] : (int) $current['kanban_status_id'];
        $supportsPhoto = $this->hasStudentProfilePhotoColumn();
        $updateSql = $supportsPhoto
            ? 'UPDATE students SET
                full_name = :full_name,
                primary_contact = :primary_contact,
                email_primary = :email_primary,
                phone = :phone,
                profile_photo = :profile_photo,
                is_active = :is_active,
                admin_info = :admin_info,
                ra = :ra,
                birth_date = :birth_date,
                rg = :rg,
                cro = :cro,
                notes = :notes,
                monthly_fee = :monthly_fee,
                billing_day = :billing_day,
                kanban_status_id = :kanban_status_id,
                updated_at = :updated_at
                WHERE id = :id AND company_id = :company_id'
            : 'UPDATE students SET
                full_name = :full_name,
                primary_contact = :primary_contact,
                email_primary = :email_primary,
                phone = :phone,
                is_active = :is_active,
                admin_info = :admin_info,
                ra = :ra,
                birth_date = :birth_date,
                rg = :rg,
                cro = :cro,
                notes = :notes,
                monthly_fee = :monthly_fee,
                billing_day = :billing_day,
                kanban_status_id = :kanban_status_id,
                updated_at = :updated_at
                WHERE id = :id AND company_id = :company_id';
        $stmt = $this->db->prepare($updateSql);

        $params = [
            ':full_name' => $data['full_name'],
            ':primary_contact' => $data['primary_contact'],
            ':email_primary' => $data['email_primary'],
            ':phone' => $data['phone'],
            ':is_active' => (int) ($data['is_active'] ?? 1),
            ':admin_info' => $data['admin_info'],
            ':ra' => $data['ra'],
            ':birth_date' => $data['birth_date'] ?: null,
            ':rg' => $data['rg'],
            ':cro' => $data['cro'],
            ':notes' => $data['notes'],
            ':monthly_fee' => (float) ($data['monthly_fee'] ?? 0),
            ':billing_day' => $data['billing_day'] !== '' ? (int) $data['billing_day'] : null,
            ':kanban_status_id' => $statusId,
            ':updated_at' => now(),
            ':id' => $id,
            ':company_id' => $this->companyId(),
        ];

        if ($supportsPhoto) {
            $params[':profile_photo'] = ($data['profile_photo'] ?? '') !== '' ? $data['profile_photo'] : null;
        }

        $stmt->execute($params);

        if ((int) $current['kanban_status_id'] !== (int) $statusId) {
            $this->registerKanbanHistory($id, (int) $current['kanban_status_id'], (int) $statusId, $updatedBy, 'Atualizacao manual');
        }
    }

    public function updateProfilePhoto(int $id, ?string $photoPath): void
    {
        if (!$this->hasStudentProfilePhotoColumn()) {
            return;
        }

        $stmt = $this->db->prepare('UPDATE students SET profile_photo = :profile_photo, updated_at = :updated_at WHERE id = :id AND company_id = :company_id');
        $stmt->execute([
            ':profile_photo' => ($photoPath ?? '') !== '' ? $photoPath : null,
            ':updated_at' => now(),
            ':id' => $id,
            ':company_id' => $this->companyId(),
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM students WHERE id = :id AND company_id = :company_id');
        $stmt->execute([
            ':id' => $id,
            ':company_id' => $this->companyId(),
        ]);
    }

    public function setActive(int $id, int $active): void
    {
        $stmt = $this->db->prepare('UPDATE students SET is_active = :is_active, updated_at = :updated_at WHERE id = :id AND company_id = :company_id');
        $stmt->execute([
            ':is_active' => $active,
            ':updated_at' => now(),
            ':id' => $id,
            ':company_id' => $this->companyId(),
        ]);
    }

    public function bulkAction(array $ids, string $action, ?int $kanbanStatusId, int $updatedBy): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids), fn ($id) => $id > 0));
        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        if ($action === 'activate' || $action === 'deactivate') {
            $active = $action === 'activate' ? 1 : 0;
            $stmt = $this->db->prepare("UPDATE students SET is_active = ?, updated_at = ? WHERE company_id = ? AND id IN ({$placeholders})");
            $stmt->execute(array_merge([$active, now(), $this->companyId()], $ids));
            return $stmt->rowCount();
        }

        if ($action === 'change_status' && $kanbanStatusId) {
            $current = $this->db->prepare("SELECT id, kanban_status_id FROM students WHERE company_id = ? AND id IN ({$placeholders})");
            $current->execute(array_merge([$this->companyId()], $ids));
            $rows = $current->fetchAll();

            $stmt = $this->db->prepare("UPDATE students SET kanban_status_id = ?, updated_at = ? WHERE company_id = ? AND id IN ({$placeholders})");
            $stmt->execute(array_merge([$kanbanStatusId, now(), $this->companyId()], $ids));

            foreach ($rows as $row) {
                $this->registerKanbanHistory((int) $row['id'], (int) $row['kanban_status_id'], $kanbanStatusId, $updatedBy, 'Alteracao em massa');
            }
            return $stmt->rowCount();
        }

        if ($action === 'delete') {
            $stmt = $this->db->prepare("DELETE FROM students WHERE company_id = ? AND id IN ({$placeholders})");
            $stmt->execute(array_merge([$this->companyId()], $ids));
            return $stmt->rowCount();
        }

        return 0;
    }

    public function registerKanbanHistory(int $studentId, ?int $fromStatusId, int $toStatusId, int $changedBy, string $reason = ''): void
    {
        if (!$this->find($studentId)) {
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
            ':changed_by' => $changedBy,
            ':created_at' => now(),
        ]);
    }

    public function kanbanHistory(int $studentId): array
    {
        $stmt = $this->db->prepare('SELECT h.*, fs.name AS from_status, ts.name AS to_status, u.name AS changed_by_name
            FROM student_kanban_history h
            INNER JOIN students s ON s.id = h.student_id AND s.company_id = :company_id
            LEFT JOIN kanban_status fs ON fs.id = h.from_status_id
            LEFT JOIN kanban_status ts ON ts.id = h.to_status_id
            LEFT JOIN users u ON u.id = h.changed_by
            WHERE h.student_id = :student_id
            ORDER BY h.id DESC');

        $stmt->execute([
            ':student_id' => $studentId,
            ':company_id' => $this->companyId(),
        ]);
        return $stmt->fetchAll();
    }

    public function financialHistory(int $studentId): array
    {
        $stmt = $this->db->prepare('SELECT i.id, i.invoice_number, i.due_date, i.amount, i.paid_amount, i.status,
                COALESCE(SUM(pi.amount), 0) AS payments_sum
            FROM invoices i
            LEFT JOIN payment_items pi ON pi.invoice_id = i.id
            WHERE i.student_id = :student_id
              AND i.company_id = :company_id
            GROUP BY i.id
            ORDER BY i.due_date DESC, i.id DESC');

        $stmt->execute([
            ':student_id' => $studentId,
            ':company_id' => $this->companyId(),
        ]);
        return $stmt->fetchAll();
    }

    public function documents(int $studentId): array
    {
        $stmt = $this->db->prepare('SELECT u.*
            FROM uploads u
            INNER JOIN students s ON s.id = u.entity_id
            WHERE u.entity_type = :entity_type
              AND u.entity_id = :entity_id
              AND s.company_id = :company_id
            ORDER BY u.id DESC');
        $stmt->execute([
            ':entity_type' => 'student',
            ':entity_id' => $studentId,
            ':company_id' => $this->companyId(),
        ]);
        return $stmt->fetchAll();
    }

    public function addDocument(int $studentId, string $fileName, string $path, string $type, int $createdBy): void
    {
        if (!$this->find($studentId)) {
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO uploads (
            entity_type, entity_id, file_name, file_path, file_type, created_by, created_at
        ) VALUES (
            :entity_type, :entity_id, :file_name, :file_path, :file_type, :created_by, :created_at
        )');

        $stmt->execute([
            ':entity_type' => 'student',
            ':entity_id' => $studentId,
            ':file_name' => $fileName,
            ':file_path' => $path,
            ':file_type' => $type,
            ':created_by' => $createdBy,
            ':created_at' => now(),
        ]);
    }

    public function createContact(int $studentId, string $name, string $email, string $phone, int $isPrimary = 0): void
    {
        if (!$this->find($studentId)) {
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO student_contacts (
            student_id, contact_name, email, phone, is_primary, is_active, created_at, updated_at
        ) VALUES (
            :student_id, :contact_name, :email, :phone, :is_primary, 1, :created_at, :updated_at
        )');

        $stmt->execute([
            ':student_id' => $studentId,
            ':contact_name' => $name,
            ':email' => $email,
            ':phone' => $phone,
            ':is_primary' => $isPrimary,
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);
    }

    public function createFromLead(array $lead, int $createdBy): int
    {
        $statusId = $this->defaultKanbanStatusId();
        $stmt = $this->db->prepare('INSERT INTO students (
            company_id, full_name, primary_contact, email_primary, phone, is_active,
            admin_info, notes, kanban_status_id, created_by, created_at, updated_at
        ) VALUES (
            :company_id, :full_name, :primary_contact, :email_primary, :phone, 1,
            :admin_info, :notes, :kanban_status_id, :created_by, :created_at, :updated_at
        )');

        $now = now();
        $stmt->execute([
            ':company_id' => (int) ($lead['company_id'] ?? $this->companyId()),
            ':full_name' => $lead['full_name'],
            ':primary_contact' => $lead['full_name'],
            ':email_primary' => $lead['email'],
            ':phone' => $lead['phone'],
            ':admin_info' => 'Convertido do lead #' . $lead['id'],
            ':notes' => 'Origem lead: ' . ($lead['source'] ?: 'Nao informado'),
            ':kanban_status_id' => $statusId,
            ':created_by' => $createdBy,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        $id = (int) $this->db->lastInsertId();

        if ($statusId) {
            $this->registerKanbanHistory($id, null, $statusId, $createdBy, 'Conversao de lead');
        }

        return $id;
    }

    public function portalFeatureAvailable(): bool
    {
        return $this->hasPortalAccountsTable();
    }

    public function studentPhotoFeatureAvailable(): bool
    {
        return $this->hasStudentProfilePhotoColumn();
    }

    public function findPortalAccount(int $studentId): ?array
    {
        if (!$this->hasPortalAccountsTable()) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT spa.id, spa.student_id, spa.login, spa.is_active, spa.last_login_at, spa.created_at, spa.updated_at
            FROM student_portal_accounts spa
            INNER JOIN students s ON s.id = spa.student_id
            WHERE spa.student_id = :student_id
              AND s.company_id = :company_id
            LIMIT 1');
        $stmt->execute([
            ':student_id' => $studentId,
            ':company_id' => $this->companyId(),
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findPortalAccountByLogin(string $login): ?array
    {
        if (!$this->hasPortalAccountsTable()) {
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
            ':company_id' => $this->companyId(),
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function upsertPortalAccount(int $studentId, string $login, ?string $password, int $isActive): void
    {
        if (!$this->hasPortalAccountsTable()) {
            return;
        }

        if (!$this->find($studentId)) {
            return;
        }

        $existing = $this->findPortalAccount($studentId);

        if ($existing) {
            $sql = 'UPDATE student_portal_accounts SET
                login = :login,
                is_active = :is_active,
                updated_at = :updated_at';

            $params = [
                ':login' => $login,
                ':is_active' => $isActive ? 1 : 0,
                ':updated_at' => now(),
                ':id' => (int) $existing['id'],
            ];

            if ($password !== null && $password !== '') {
                $sql .= ', password_hash = :password_hash';
                $params[':password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            }

            $sql .= ' WHERE id = :id';

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return;
        }

        $passwordToUse = ($password !== null && $password !== '') ? $password : bin2hex(random_bytes(6));
        $stmt = $this->db->prepare('INSERT INTO student_portal_accounts (
            student_id, login, password_hash, is_active, created_at, updated_at
        ) VALUES (
            :student_id, :login, :password_hash, :is_active, :created_at, :updated_at
        )');

        $stmt->execute([
            ':student_id' => $studentId,
            ':login' => $login,
            ':password_hash' => password_hash($passwordToUse, PASSWORD_DEFAULT),
            ':is_active' => $isActive ? 1 : 0,
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);
    }

    private function hasPortalAccountsTable(): bool
    {
        if ($this->portalAccountsTableExists !== null) {
            return $this->portalAccountsTableExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'student_portal_accounts'");
        $stmt->execute();
        $this->portalAccountsTableExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->portalAccountsTableExists;
    }

    private function hasStudentProfilePhotoColumn(): bool
    {
        if ($this->studentProfilePhotoColumnExists !== null) {
            return $this->studentProfilePhotoColumnExists;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'students'
              AND column_name = 'profile_photo'");
        $stmt->execute();
        $this->studentProfilePhotoColumnExists = ((int) $stmt->fetchColumn()) > 0;

        return $this->studentProfilePhotoColumnExists;
    }

    private function scalar(string $sql, array $params = [])
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}
