<?php

class LeadModel extends BaseModel
{
    public function statuses(): array
    {
        $stmt = $this->db->prepare('SELECT ls.*, COUNT(l.id) AS total_leads
            FROM lead_status ls
            LEFT JOIN leads l ON l.lead_status_id = ls.id AND l.company_id = :company_id
            GROUP BY ls.id
            ORDER BY ls.display_order ASC, ls.id ASC');
        $stmt->execute([':company_id' => $this->companyId()]);

        return $stmt->fetchAll();
    }

    public function defaultStatusId(): ?int
    {
        $value = $this->db->query('SELECT id FROM lead_status WHERE is_default = 1 ORDER BY id ASC LIMIT 1')->fetchColumn();
        return $value ? (int) $value : null;
    }

    public function list(array $filters, int $perPage, int $page): array
    {
        $where = ['l.company_id = :company_id'];
        $params = [':company_id' => $this->companyId()];

        if (!empty($filters['q'])) {
            $where[] = '(l.full_name LIKE :q OR l.email LIKE :q OR l.phone LIKE :q OR l.source LIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        if (!empty($filters['status_id'])) {
            $where[] = 'l.lead_status_id = :status_id';
            $params[':status_id'] = (int) $filters['status_id'];
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*)
            FROM leads l
            LEFT JOIN lead_status ls ON ls.id = l.lead_status_id
            WHERE {$whereSql}";

        $dataSql = "SELECT l.*, ls.name AS status_name, ls.color AS status_color, u.name AS assigned_name
            FROM leads l
            LEFT JOIN lead_status ls ON ls.id = l.lead_status_id
            LEFT JOIN users u ON u.id = l.assigned_to
            WHERE {$whereSql}
            ORDER BY l.id DESC";

        return $this->paginate($countSql, $dataSql, $params, $perPage, $page);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM leads WHERE id = :id AND company_id = :company_id LIMIT 1');
        $stmt->execute([
            ':id' => $id,
            ':company_id' => $this->companyId(),
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data, int $createdBy): int
    {
        $statusId = $data['lead_status_id'] ? (int) $data['lead_status_id'] : $this->defaultStatusId();

        $stmt = $this->db->prepare('INSERT INTO leads (
            company_id, full_name, email, phone, lead_value, assigned_to, source,
            lead_status_id, unit_name, tags, last_contact_at, created_by,
            created_at, updated_at
        ) VALUES (
            :company_id, :full_name, :email, :phone, :lead_value, :assigned_to, :source,
            :lead_status_id, :unit_name, :tags, :last_contact_at, :created_by,
            :created_at, :updated_at
        )');

        $now = now();
        $stmt->execute([
            ':company_id' => $this->companyId(),
            ':full_name' => $data['full_name'],
            ':email' => $data['email'],
            ':phone' => $data['phone'],
            ':lead_value' => (float) ($data['lead_value'] ?? 0),
            ':assigned_to' => $data['assigned_to'] !== '' ? (int) $data['assigned_to'] : null,
            ':source' => $data['source'],
            ':lead_status_id' => $statusId,
            ':unit_name' => $data['unit_name'],
            ':tags' => $data['tags'],
            ':last_contact_at' => $data['last_contact_at'] ?: null,
            ':created_by' => $createdBy,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        $leadId = (int) $this->db->lastInsertId();

        $this->addHistory($leadId, 'Lead criado no sistema.', $createdBy, $statusId);

        return $leadId;
    }

    public function update(int $id, array $data, int $userId): void
    {
        $current = $this->find($id);
        if (!$current) {
            return;
        }

        $statusId = $data['lead_status_id'] ? (int) $data['lead_status_id'] : (int) $current['lead_status_id'];

        $stmt = $this->db->prepare('UPDATE leads SET
            full_name = :full_name,
            email = :email,
            phone = :phone,
            lead_value = :lead_value,
            assigned_to = :assigned_to,
            source = :source,
            lead_status_id = :lead_status_id,
            unit_name = :unit_name,
            tags = :tags,
            last_contact_at = :last_contact_at,
            updated_at = :updated_at
            WHERE id = :id
              AND company_id = :company_id');

        $stmt->execute([
            ':full_name' => $data['full_name'],
            ':email' => $data['email'],
            ':phone' => $data['phone'],
            ':lead_value' => (float) ($data['lead_value'] ?? 0),
            ':assigned_to' => $data['assigned_to'] !== '' ? (int) $data['assigned_to'] : null,
            ':source' => $data['source'],
            ':lead_status_id' => $statusId,
            ':unit_name' => $data['unit_name'],
            ':tags' => $data['tags'],
            ':last_contact_at' => $data['last_contact_at'] ?: null,
            ':updated_at' => now(),
            ':id' => $id,
            ':company_id' => $this->companyId(),
        ]);

        if ((int) $current['lead_status_id'] !== $statusId) {
            $this->addHistory($id, 'Status alterado para ID ' . $statusId, $userId, $statusId);
        }
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM leads WHERE id = :id AND company_id = :company_id');
        $stmt->execute([
            ':id' => $id,
            ':company_id' => $this->companyId(),
        ]);
    }

    public function setStatus(int $id, int $statusId, int $userId, string $note = ''): void
    {
        $stmt = $this->db->prepare('UPDATE leads SET lead_status_id = :status_id, updated_at = :updated_at WHERE id = :id AND company_id = :company_id');
        $stmt->execute([
            ':status_id' => $statusId,
            ':updated_at' => now(),
            ':id' => $id,
            ':company_id' => $this->companyId(),
        ]);

        $message = $note !== '' ? $note : 'Status alterado pelo funil.';
        $this->addHistory($id, $message, $userId, $statusId);
    }

    public function addHistory(int $leadId, string $interaction, int $createdBy, ?int $statusId = null): void
    {
        if (!$this->find($leadId)) {
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO lead_history (
            lead_id, interaction, status_id, created_by, created_at
        ) VALUES (
            :lead_id, :interaction, :status_id, :created_by, :created_at
        )');

        $stmt->execute([
            ':lead_id' => $leadId,
            ':interaction' => $interaction,
            ':status_id' => $statusId,
            ':created_by' => $createdBy,
            ':created_at' => now(),
        ]);
    }

    public function history(int $leadId): array
    {
        $stmt = $this->db->prepare('SELECT h.*, u.name AS created_by_name, s.name AS status_name
            FROM lead_history h
            INNER JOIN leads l ON l.id = h.lead_id AND l.company_id = :company_id
            LEFT JOIN users u ON u.id = h.created_by
            LEFT JOIN lead_status s ON s.id = h.status_id
            WHERE h.lead_id = :lead_id
            ORDER BY h.id DESC');

        $stmt->execute([
            ':lead_id' => $leadId,
            ':company_id' => $this->companyId(),
        ]);
        return $stmt->fetchAll();
    }

    public function usersAssignable(): array
    {
        return $this->db->query('SELECT id, name FROM users WHERE is_active = 1 ORDER BY name ASC')->fetchAll();
    }

    public function createStatus(array $data): int
    {
        if (!empty($data['is_default'])) {
            $this->db->exec('UPDATE lead_status SET is_default = 0');
        }

        $stmt = $this->db->prepare('INSERT INTO lead_status (name, color, display_order, is_default, created_at, updated_at)
            VALUES (:name, :color, :display_order, :is_default, :created_at, :updated_at)');

        $stmt->execute([
            ':name' => $data['name'],
            ':color' => $data['color'],
            ':display_order' => (int) $data['display_order'],
            ':is_default' => !empty($data['is_default']) ? 1 : 0,
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateStatusConfig(int $id, array $data): void
    {
        if (!empty($data['is_default'])) {
            $this->db->exec('UPDATE lead_status SET is_default = 0');
        }

        $stmt = $this->db->prepare('UPDATE lead_status SET name = :name, color = :color, display_order = :display_order,
            is_default = :is_default, updated_at = :updated_at WHERE id = :id');

        $stmt->execute([
            ':name' => $data['name'],
            ':color' => $data['color'],
            ':display_order' => (int) $data['display_order'],
            ':is_default' => !empty($data['is_default']) ? 1 : 0,
            ':updated_at' => now(),
            ':id' => $id,
        ]);
    }

    public function deleteStatusConfig(int $id): void
    {
        $default = $this->defaultStatusId();
        if (!$default || $default === $id) {
            return;
        }

        $stmt = $this->db->prepare('UPDATE leads SET lead_status_id = :default_status WHERE lead_status_id = :status_id AND company_id = :company_id');
        $stmt->execute([
            ':default_status' => $default,
            ':status_id' => $id,
            ':company_id' => $this->companyId(),
        ]);

        $del = $this->db->prepare('DELETE FROM lead_status WHERE id = :id');
        $del->execute([':id' => $id]);
    }

    public function setConverted(int $leadId, int $studentId, int $userId): void
    {
        $stmt = $this->db->prepare('UPDATE leads SET converted_student_id = :student_id, converted_at = :converted_at, updated_at = :updated_at WHERE id = :id AND company_id = :company_id');
        $stmt->execute([
            ':student_id' => $studentId,
            ':converted_at' => now(),
            ':updated_at' => now(),
            ':id' => $leadId,
            ':company_id' => $this->companyId(),
        ]);

        $this->addHistory($leadId, 'Lead convertido em aluno #' . $studentId, $userId);
    }

    public function bulkAction(array $ids, string $action, ?int $statusId, int $userId): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids), fn ($id) => $id > 0));
        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        if ($action === 'delete') {
            $stmt = $this->db->prepare("DELETE FROM leads WHERE company_id = ? AND id IN ({$placeholders})");
            $stmt->execute(array_merge([$this->companyId()], $ids));
            return $stmt->rowCount();
        }

        if ($action === 'status' && $statusId) {
            $stmt = $this->db->prepare("UPDATE leads SET lead_status_id = ?, updated_at = ? WHERE company_id = ? AND id IN ({$placeholders})");
            $stmt->execute(array_merge([$statusId, now(), $this->companyId()], $ids));

            foreach ($ids as $id) {
                $this->addHistory((int) $id, 'Alteracao em massa de status.', $userId, $statusId);
            }

            return $stmt->rowCount();
        }

        return 0;
    }
}
