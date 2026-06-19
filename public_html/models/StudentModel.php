<?php

class StudentModel extends BaseModel
{
    private ?bool $portalAccountsTableExists = null;
    private ?bool $studentProfilePhotoColumnExists = null;
    private ?bool $practiceUnitColumnExists = null;
    private ?bool $residencyLevelColumnExists = null;
    private ?bool $studentCityColumnExists = null;
    private ?bool $studentCpfColumnExists = null;
    private ?bool $studentFinancialPlanColumnsExist = null;

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
        $unitJoin = $this->practiceScheduleFeatureAvailable()
            ? 'LEFT JOIN student_practice_units spu ON spu.id = s.practice_unit_id'
            : '';

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
            {$unitJoin}
            WHERE {$whereSql}";

        $dataSql = "SELECT s.*, ks.name AS kanban_status_name, ks.color AS kanban_status_color" .
            ($this->practiceScheduleFeatureAvailable() ? ', spu.name AS practice_unit_name' : '') . "
            FROM students s
            LEFT JOIN kanban_status ks ON ks.id = s.kanban_status_id
            {$unitJoin}
            WHERE {$whereSql}
            ORDER BY s.id DESC";

        return $this->paginate($countSql, $dataSql, $params, $perPage, $page);
    }

    public function find(int $id): ?array
    {
        $sql = 'SELECT s.*, ks.name AS kanban_status_name, ks.color AS kanban_status_color' .
            ($this->practiceScheduleFeatureAvailable() ? ', spu.name AS practice_unit_name' : '') . '
            FROM students s
            LEFT JOIN kanban_status ks ON ks.id = s.kanban_status_id
            ' . ($this->practiceScheduleFeatureAvailable() ? 'LEFT JOIN student_practice_units spu ON spu.id = s.practice_unit_id' : '') . '
            WHERE s.id = :id
              AND s.company_id = :company_id
            LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':company_id' => $this->companyId(),
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function create(array $data, int $createdBy): int
    {
        $statusId = $this->resolveKanbanStatusId($data['kanban_status_id'] ?? null);
        $supportsPhoto = $this->hasStudentProfilePhotoColumn();
        $supportsCity = $this->hasStudentCityColumn();
        $supportsCpf = $this->hasStudentCpfColumn();
        $supportsPractice = $this->practiceScheduleFeatureAvailable();
        $supportsFinancialPlan = $this->financialPlanFeatureAvailable();
        $data['ra'] = $this->resolveStudentRa((string) ($data['ra'] ?? ''), $this->companyId());
        $insertSql = $supportsPhoto
            ? 'INSERT INTO students (
                company_id, full_name, primary_contact, email_primary, phone' . ($supportsCity ? ', city' : '') . ', profile_photo, is_active,
                admin_info, ra, birth_date, enrolled_at' . ($supportsPractice ? ', practice_unit_id, residency_level' : '') . ($supportsCpf ? ', cpf' : '') . ', rg, cro, notes, monthly_fee, billing_day,
                ' . ($supportsFinancialPlan ? 'financial_plan_profile, financial_plan_installments, financial_plan_first_due_date, financial_plan_payment_method_id, financial_plan_auto_generate, financial_plan_boleto_days_before, financial_plan_generated_at,' : '') . '
                kanban_status_id, created_by, created_at, updated_at
            ) VALUES (
                :company_id, :full_name, :primary_contact, :email_primary, :phone' . ($supportsCity ? ', :city' : '') . ', :profile_photo, :is_active,
                :admin_info, :ra, :birth_date, :enrolled_at' . ($supportsPractice ? ', :practice_unit_id, :residency_level' : '') . ($supportsCpf ? ', :cpf' : '') . ', :rg, :cro, :notes, :monthly_fee, :billing_day,
                ' . ($supportsFinancialPlan ? ':financial_plan_profile, :financial_plan_installments, :financial_plan_first_due_date, :financial_plan_payment_method_id, :financial_plan_auto_generate, :financial_plan_boleto_days_before, :financial_plan_generated_at,' : '') . '
                :kanban_status_id, :created_by, :created_at, :updated_at
            )'
            : 'INSERT INTO students (
                company_id, full_name, primary_contact, email_primary, phone' . ($supportsCity ? ', city' : '') . ', is_active,
                admin_info, ra, birth_date, enrolled_at' . ($supportsPractice ? ', practice_unit_id, residency_level' : '') . ($supportsCpf ? ', cpf' : '') . ', rg, cro, notes, monthly_fee, billing_day,
                ' . ($supportsFinancialPlan ? 'financial_plan_profile, financial_plan_installments, financial_plan_first_due_date, financial_plan_payment_method_id, financial_plan_auto_generate, financial_plan_boleto_days_before, financial_plan_generated_at,' : '') . '
                kanban_status_id, created_by, created_at, updated_at
            ) VALUES (
                :company_id, :full_name, :primary_contact, :email_primary, :phone' . ($supportsCity ? ', :city' : '') . ', :is_active,
                :admin_info, :ra, :birth_date, :enrolled_at' . ($supportsPractice ? ', :practice_unit_id, :residency_level' : '') . ($supportsCpf ? ', :cpf' : '') . ', :rg, :cro, :notes, :monthly_fee, :billing_day,
                ' . ($supportsFinancialPlan ? ':financial_plan_profile, :financial_plan_installments, :financial_plan_first_due_date, :financial_plan_payment_method_id, :financial_plan_auto_generate, :financial_plan_boleto_days_before, :financial_plan_generated_at,' : '') . '
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
            ':enrolled_at' => $data['enrolled_at'] ?: null,
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
        if ($supportsCity) {
            $params[':city'] = trim((string) ($data['city'] ?? '')) ?: null;
        }
        if ($supportsCpf) {
            $params[':cpf'] = preg_replace('/\D/', '', (string) ($data['cpf'] ?? '')) ?: null;
        }
        if ($supportsPractice) {
            $params[':practice_unit_id'] = !empty($data['practice_unit_id']) ? (int) $data['practice_unit_id'] : null;
            $params[':residency_level'] = in_array(($data['residency_level'] ?? 'R1'), ['R1', 'R2', 'R3'], true) ? $data['residency_level'] : 'R1';
        }
        if ($supportsFinancialPlan) {
            $params[':financial_plan_profile'] = trim((string) ($data['financial_plan_profile'] ?? '')) !== ''
                ? trim((string) $data['financial_plan_profile'])
                : null;
            $params[':financial_plan_installments'] = !empty($data['financial_plan_installments'])
                ? (int) $data['financial_plan_installments']
                : null;
            $params[':financial_plan_first_due_date'] = trim((string) ($data['financial_plan_first_due_date'] ?? '')) !== ''
                ? trim((string) $data['financial_plan_first_due_date'])
                : null;
            $params[':financial_plan_payment_method_id'] = !empty($data['financial_plan_payment_method_id'])
                ? (int) $data['financial_plan_payment_method_id']
                : null;
            $params[':financial_plan_auto_generate'] = !empty($data['financial_plan_auto_generate']) ? 1 : 0;
            $params[':financial_plan_boleto_days_before'] = max(0, min(60, (int) ($data['financial_plan_boleto_days_before'] ?? 10)));
            $params[':financial_plan_generated_at'] = !empty($data['financial_plan_generated_at'])
                ? trim((string) $data['financial_plan_generated_at'])
                : null;
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

        $currentStatusId = $this->positiveIntOrNull($current['kanban_status_id'] ?? null);
        $statusId = $this->resolveKanbanStatusId($data['kanban_status_id'] ?? null, $currentStatusId);
        $supportsPhoto = $this->hasStudentProfilePhotoColumn();
        $supportsCity = $this->hasStudentCityColumn();
        $supportsCpf = $this->hasStudentCpfColumn();
        $supportsPractice = $this->practiceScheduleFeatureAvailable();
        $supportsFinancialPlan = $this->financialPlanFeatureAvailable();
        $updateSql = $supportsPhoto
            ? 'UPDATE students SET
                full_name = :full_name,
                primary_contact = :primary_contact,
                email_primary = :email_primary,
                phone = :phone,
                ' . ($supportsCity ? 'city = :city,' : '') . '
                profile_photo = :profile_photo,
                is_active = :is_active,
                admin_info = :admin_info,
                ra = :ra,
                birth_date = :birth_date,
                enrolled_at = :enrolled_at,
                ' . ($supportsPractice ? 'practice_unit_id = :practice_unit_id, residency_level = :residency_level,' : '') . '
                rg = :rg,
                cro = :cro,
                notes = :notes,
                monthly_fee = :monthly_fee,
                billing_day = :billing_day,
                ' . ($supportsCpf ? 'cpf = :cpf,' : '') . '
                ' . ($supportsFinancialPlan ? 'financial_plan_profile = :financial_plan_profile,
                financial_plan_installments = :financial_plan_installments,
                financial_plan_first_due_date = :financial_plan_first_due_date,
                financial_plan_payment_method_id = :financial_plan_payment_method_id,
                financial_plan_auto_generate = :financial_plan_auto_generate,
                financial_plan_boleto_days_before = :financial_plan_boleto_days_before,
                financial_plan_generated_at = :financial_plan_generated_at,' : '') . '
                kanban_status_id = :kanban_status_id,
                updated_at = :updated_at
                WHERE id = :id AND company_id = :company_id'
            : 'UPDATE students SET
                full_name = :full_name,
                primary_contact = :primary_contact,
                email_primary = :email_primary,
                phone = :phone,
                ' . ($supportsCity ? 'city = :city,' : '') . '
                is_active = :is_active,
                admin_info = :admin_info,
                ra = :ra,
                birth_date = :birth_date,
                enrolled_at = :enrolled_at,
                ' . ($supportsPractice ? 'practice_unit_id = :practice_unit_id, residency_level = :residency_level,' : '') . '
                rg = :rg,
                cro = :cro,
                notes = :notes,
                monthly_fee = :monthly_fee,
                billing_day = :billing_day,
                ' . ($supportsCpf ? 'cpf = :cpf,' : '') . '
                ' . ($supportsFinancialPlan ? 'financial_plan_profile = :financial_plan_profile,
                financial_plan_installments = :financial_plan_installments,
                financial_plan_first_due_date = :financial_plan_first_due_date,
                financial_plan_payment_method_id = :financial_plan_payment_method_id,
                financial_plan_auto_generate = :financial_plan_auto_generate,
                financial_plan_boleto_days_before = :financial_plan_boleto_days_before,
                financial_plan_generated_at = :financial_plan_generated_at,' : '') . '
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
            ':enrolled_at' => $data['enrolled_at'] ?: null,
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
        if ($supportsCity) {
            $params[':city'] = trim((string) ($data['city'] ?? '')) ?: null;
        }
        if ($supportsCpf) {
            $params[':cpf'] = preg_replace('/\D/', '', (string) ($data['cpf'] ?? '')) ?: null;
        }
        if ($supportsPractice) {
            $params[':practice_unit_id'] = !empty($data['practice_unit_id']) ? (int) $data['practice_unit_id'] : null;
            $params[':residency_level'] = in_array(($data['residency_level'] ?? 'R1'), ['R1', 'R2', 'R3'], true) ? $data['residency_level'] : 'R1';
        }
        if ($supportsFinancialPlan) {
            $params[':financial_plan_profile'] = trim((string) ($data['financial_plan_profile'] ?? '')) !== ''
                ? trim((string) $data['financial_plan_profile'])
                : null;
            $params[':financial_plan_installments'] = !empty($data['financial_plan_installments'])
                ? (int) $data['financial_plan_installments']
                : null;
            $params[':financial_plan_first_due_date'] = trim((string) ($data['financial_plan_first_due_date'] ?? '')) !== ''
                ? trim((string) $data['financial_plan_first_due_date'])
                : null;
            $params[':financial_plan_payment_method_id'] = !empty($data['financial_plan_payment_method_id'])
                ? (int) $data['financial_plan_payment_method_id']
                : null;
            $params[':financial_plan_auto_generate'] = !empty($data['financial_plan_auto_generate']) ? 1 : 0;
            $params[':financial_plan_boleto_days_before'] = max(0, min(60, (int) ($data['financial_plan_boleto_days_before'] ?? 10)));
            $params[':financial_plan_generated_at'] = !empty($data['financial_plan_generated_at'])
                ? trim((string) $data['financial_plan_generated_at'])
                : null;
        }

        $stmt->execute($params);

        $historyFromStatusId = $this->kanbanStatusExists($currentStatusId) ? $currentStatusId : null;
        if ($statusId !== null && $historyFromStatusId !== $statusId) {
            $this->registerKanbanHistory($id, $historyFromStatusId, $statusId, $updatedBy, 'Atualizacao manual');
        }
    }

    public function financialPlanFeatureAvailable(): bool
    {
        if ($this->studentFinancialPlanColumnsExist !== null) {
            return $this->studentFinancialPlanColumnsExist;
        }

        $requiredColumns = [
            'financial_plan_profile',
            'financial_plan_installments',
            'financial_plan_first_due_date',
            'financial_plan_payment_method_id',
            'financial_plan_auto_generate',
            'financial_plan_boleto_days_before',
            'financial_plan_generated_at',
        ];

        foreach ($requiredColumns as $column) {
            if (!$this->schemaColumnExists('students', $column)) {
                return $this->studentFinancialPlanColumnsExist = false;
            }
        }

        return $this->studentFinancialPlanColumnsExist = true;
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

    public function registerKanbanHistory(int $studentId, ?int $fromStatusId, int $toStatusId, ?int $changedBy, string $reason = ''): void
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
            ':changed_by' => $changedBy !== null && $changedBy > 0 ? $changedBy : null,
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
        $companyId = (int) ($lead['company_id'] ?? $this->companyId());
        $stmt = $this->db->prepare('INSERT INTO students (
            company_id, full_name, primary_contact, email_primary, phone, is_active,
            admin_info, ra, notes, kanban_status_id, created_by, created_at, updated_at
        ) VALUES (
            :company_id, :full_name, :primary_contact, :email_primary, :phone, 1,
            :admin_info, :ra, :notes, :kanban_status_id, :created_by, :created_at, :updated_at
        )');

        $now = now();
        $stmt->execute([
            ':company_id' => $companyId,
            ':full_name' => $lead['full_name'],
            ':primary_contact' => $lead['full_name'],
            ':email_primary' => $lead['email'],
            ':phone' => $lead['phone'],
            ':admin_info' => 'Convertido do lead #' . $lead['id'],
            ':ra' => $this->resolveStudentRa('', $companyId),
            ':notes' => 'Origem lead: ' . ($lead['source'] ?: 'Não informado'),
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

    private function resolveStudentRa(string $ra, int $companyId): string
    {
        $ra = trim($ra);
        if ($ra !== '') {
            return $ra;
        }

        return StudentRaGenerator::nextForCompany($this->db, $companyId) ?? '';
    }

    public function portalFeatureAvailable(): bool
    {
        return $this->hasPortalAccountsTable();
    }

    public function practiceScheduleFeatureAvailable(): bool
    {
        return $this->hasPracticeUnitColumn()
            && $this->hasResidencyLevelColumn()
            && $this->schemaTableExists('student_practice_units');
    }

    public function studentPhotoFeatureAvailable(): bool
    {
        return $this->hasStudentProfilePhotoColumn();
    }

    public function practiceUnits(): array
    {
        if (!$this->practiceScheduleFeatureAvailable()) {
            return [];
        }

        $stmt = $this->db->prepare('SELECT id, name, city, state, is_active
            FROM student_practice_units
            WHERE company_id = :company_id
              AND is_active = 1
            ORDER BY name ASC');
        $stmt->execute([':company_id' => $this->companyId()]);
        return $stmt->fetchAll();
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

        $this->portalAccountsTableExists = $this->schemaTableExists('student_portal_accounts');

        return $this->portalAccountsTableExists;
    }

    private function hasStudentProfilePhotoColumn(): bool
    {
        if ($this->studentProfilePhotoColumnExists !== null) {
            return $this->studentProfilePhotoColumnExists;
        }

        $this->studentProfilePhotoColumnExists = $this->schemaColumnExists('students', 'profile_photo');

        return $this->studentProfilePhotoColumnExists;
    }

    private function hasStudentCityColumn(): bool
    {
        if ($this->studentCityColumnExists !== null) {
            return $this->studentCityColumnExists;
        }

        $this->studentCityColumnExists = $this->schemaColumnExists('students', 'city');

        return $this->studentCityColumnExists;
    }

    private function hasStudentCpfColumn(): bool
    {
        if ($this->studentCpfColumnExists !== null) {
            return $this->studentCpfColumnExists;
        }

        $this->studentCpfColumnExists = $this->schemaColumnExists('students', 'cpf');

        return $this->studentCpfColumnExists;
    }

    private function resolveKanbanStatusId($requestedStatusId, $fallbackStatusId = null): ?int
    {
        foreach ([$requestedStatusId, $fallbackStatusId, $this->defaultKanbanStatusId()] as $candidate) {
            $statusId = $this->positiveIntOrNull($candidate);
            if ($this->kanbanStatusExists($statusId)) {
                return $statusId;
            }
        }

        return null;
    }

    private function positiveIntOrNull($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $intValue = (int) $value;
        return $intValue > 0 ? $intValue : null;
    }

    private function kanbanStatusExists(?int $statusId): bool
    {
        if ($statusId === null || $statusId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM kanban_status WHERE id = :id');
        $stmt->execute([':id' => $statusId]);

        return ((int) $stmt->fetchColumn()) > 0;
    }

    private function hasPracticeUnitColumn(): bool
    {
        if ($this->practiceUnitColumnExists !== null) {
            return $this->practiceUnitColumnExists;
        }

        $this->practiceUnitColumnExists = $this->schemaColumnExists('students', 'practice_unit_id');
        return $this->practiceUnitColumnExists;
    }

    private function hasResidencyLevelColumn(): bool
    {
        if ($this->residencyLevelColumnExists !== null) {
            return $this->residencyLevelColumnExists;
        }

        $this->residencyLevelColumnExists = $this->schemaColumnExists('students', 'residency_level');
        return $this->residencyLevelColumnExists;
    }

    private function scalar(string $sql, array $params = [])
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}
