<?php

class GestaoAlunoModel extends BaseModel
{
    private array $columnExistsCache = [];

    // -------------------------------------------------------------------------
    // BOARD
    // -------------------------------------------------------------------------

    public function board(string $search = '', bool $archived = false): array
    {
        $citySelect = $this->tableColumnExists('students', 'city') ? 's.city' : "'' AS city";
        $columns = $this->db->query(
            'SELECT * FROM kanban_status ORDER BY display_order ASC, id ASC'
        )->fetchAll();

        $archivedFlag = $archived ? 1 : 0;

        foreach ($columns as &$col) {
            $params = [':col_id' => $col['id'], ':arch' => $archivedFlag];

            $financeSelect = $this->schemaTableExists('invoices')
                ? ",
                (SELECT COUNT(*) FROM invoices i
                    WHERE i.student_id = s.id AND i.status IN ('open','partial','overdue')) AS finance_open_count,
                (SELECT COUNT(*) FROM invoices i
                    WHERE i.student_id = s.id AND i.status = 'overdue') AS finance_overdue_count,
                (SELECT COALESCE(SUM(GREATEST(i.amount - i.paid_amount, 0)), 0) FROM invoices i
                    WHERE i.student_id = s.id AND i.status IN ('open','partial','overdue')) AS finance_open_amount"
                : ", 0 AS finance_open_count, 0 AS finance_overdue_count, 0 AS finance_open_amount";

            $sql = "SELECT
                s.id, s.full_name, s.email_primary, s.phone, {$citySelect},
                s.gda_priority, s.gda_due_date, s.gda_cover_color,
                s.gda_assigned_to, s.gda_display_order,
                u.name AS assigned_name,
                (SELECT COUNT(*) FROM gda_notes n WHERE n.student_id = s.id) AS notes_count,
                (SELECT COUNT(*) FROM gda_attachments a WHERE a.student_id = s.id) AS attachment_count,
                (SELECT COUNT(*) FROM gda_checklist_items ci
                    INNER JOIN gda_checklists c ON c.id = ci.checklist_id
                    WHERE c.student_id = s.id AND ci.is_done = 1) AS checklist_done,
                (SELECT COUNT(*) FROM gda_checklist_items ci2
                    INNER JOIN gda_checklists c2 ON c2.id = ci2.checklist_id
                    WHERE c2.student_id = s.id) AS checklist_total
                {$financeSelect}
            FROM students s
            LEFT JOIN users u ON u.id = s.gda_assigned_to
            WHERE s.kanban_status_id = :col_id AND s.gda_is_archived = :arch";

            if ($search !== '') {
                $sql .= ' AND (s.full_name LIKE :q OR s.email_primary LIKE :q OR s.phone LIKE :q)';
                $params[':q'] = '%' . $search . '%';
            }

            $sql .= ' ORDER BY s.gda_display_order ASC, s.full_name ASC';

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $students = $stmt->fetchAll();

            // Carregar labels por aluno
            foreach ($students as &$student) {
                $student['labels'] = $this->cardLabels((int) $student['id']);
                $student['members'] = $this->cardMembers((int) $student['id']);
            }
            unset($student);

            $col['students'] = $students;
            $col['total_students'] = count($students);
        }
        unset($col);

        return $columns;
    }

    private function tableColumnExists(string $table, string $column): bool
    {
        $cacheKey = $table . '.' . $column;
        if (array_key_exists($cacheKey, $this->columnExistsCache)) {
            return $this->columnExistsCache[$cacheKey];
        }

        $stmt = $this->db->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND COLUMN_NAME = :column
             LIMIT 1'
        );
        $stmt->execute([
            ':table' => $table,
            ':column' => $column,
        ]);

        $exists = (bool) $stmt->fetchColumn();
        $this->columnExistsCache[$cacheKey] = $exists;

        return $exists;
    }

    // -------------------------------------------------------------------------
    // CARD DATA (modal)
    // -------------------------------------------------------------------------

    public function getCardData(int $studentId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT s.*, ks.name AS column_name, ks.color AS column_color,
                u.name AS assigned_name
            FROM students s
            LEFT JOIN kanban_status ks ON ks.id = s.kanban_status_id
            LEFT JOIN users u ON u.id = s.gda_assigned_to
            WHERE s.id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $studentId]);
        $student = $stmt->fetch();
        if (!$student) {
            return null;
        }

        $student['notes']         = $this->notes($studentId);
        $student['attachments']   = $this->attachments($studentId);
        $student['history']       = $this->history($studentId);
        $student['labels']        = $this->cardLabels($studentId);
        $student['all_labels']    = $this->allLabels();
        $student['members']       = $this->cardMembers($studentId);
        $student['all_users']     = $this->allUsers();
        $student['checklists']    = $this->checklistsWithItems($studentId);
        $student['custom_fields'] = $this->customFieldsWithValues($studentId);
        $student['templates']     = $this->allTemplates();
        $student['financial_snapshot'] = $this->financialSnapshot($studentId);

        return $student;
    }

    public function financialSnapshot(int $studentId): array
    {
        $empty = [
            'summary' => [
                'total' => 0,
                'open' => 0,
                'overdue' => 0,
                'paid' => 0,
                'open_amount' => 0.0,
            ],
            'installments' => [],
        ];

        if (!$this->schemaTableExists('invoices')) {
            return $empty;
        }

        $hasPaymentItems = $this->schemaTableExists('payment_items');
        $paymentsSelect = $hasPaymentItems ? 'COALESCE(SUM(pi.amount), 0)' : '0';
        $paymentsJoin = $hasPaymentItems ? 'LEFT JOIN payment_items pi ON pi.invoice_id = i.id' : '';
        $companyFilter = '';
        $params = [':student_id' => $studentId];

        if ($this->schemaColumnExists('invoices', 'company_id') && $this->companyId() > 0) {
            $companyFilter = ' AND i.company_id = :company_id';
            $params[':company_id'] = $this->companyId();
        }

        $stmt = $this->db->prepare(
            "SELECT i.id, i.invoice_number, i.due_date, i.amount, i.paid_amount, i.status,
                    {$paymentsSelect} AS payments_sum
             FROM invoices i
             {$paymentsJoin}
             WHERE i.student_id = :student_id
             {$companyFilter}
               AND i.due_date <= CURDATE()
             GROUP BY i.id, i.invoice_number, i.due_date, i.amount, i.paid_amount, i.status
             ORDER BY i.due_date DESC, i.id DESC
             LIMIT 3"
        );
        $stmt->execute($params);
        $installments = $stmt->fetchAll();

        if ($installments === []) {
            $stmt = $this->db->prepare(
                "SELECT i.id, i.invoice_number, i.due_date, i.amount, i.paid_amount, i.status,
                        {$paymentsSelect} AS payments_sum
                 FROM invoices i
                 {$paymentsJoin}
                 WHERE i.student_id = :student_id
                 {$companyFilter}
                 GROUP BY i.id, i.invoice_number, i.due_date, i.amount, i.paid_amount, i.status
                 ORDER BY i.due_date ASC, i.id ASC
                 LIMIT 3"
            );
            $stmt->execute($params);
            $installments = $stmt->fetchAll();
        }

        $summary = [
            'total' => count($installments),
            'open' => 0,
            'overdue' => 0,
            'paid' => 0,
            'open_amount' => 0.0,
        ];
        $today = date('Y-m-d');

        foreach ($installments as &$row) {
            $amount = (float) ($row['amount'] ?? 0);
            $paidAmount = max((float) ($row['paid_amount'] ?? 0), (float) ($row['payments_sum'] ?? 0));
            $balance = max($amount - $paidAmount, 0);
            $status = (string) ($row['status'] ?? 'open');
            $isPaid = $status === 'paid' || $balance <= 0.009;
            $isOverdue = !$isPaid && (($status === 'overdue') || (!empty($row['due_date']) && (string) $row['due_date'] < $today));

            $row['paid_amount_effective'] = $paidAmount;
            $row['balance_amount'] = $balance;
            $row['is_paid'] = $isPaid ? 1 : 0;
            $row['is_overdue'] = $isOverdue ? 1 : 0;

            if ($isPaid) {
                $summary['paid']++;
            } elseif ($isOverdue) {
                $summary['overdue']++;
                $summary['open_amount'] += $balance;
            } else {
                $summary['open']++;
                $summary['open_amount'] += $balance;
            }
        }
        unset($row);

        return [
            'summary' => $summary,
            'installments' => $installments,
        ];
    }

    // -------------------------------------------------------------------------
    // MOVE / REORDER
    // -------------------------------------------------------------------------

    public function moveStudent(int $studentId, int $columnId, int $userId): void
    {
        $stmt = $this->db->prepare('SELECT id, kanban_status_id FROM students WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $studentId]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new RuntimeException('Aluno não encontrado.');
        }

        $fromId = $row['kanban_status_id'] ? (int) $row['kanban_status_id'] : null;
        if ($fromId === $columnId) {
            return;
        }

        $this->db->prepare(
            'UPDATE students SET kanban_status_id = :col, updated_at = :now WHERE id = :id'
        )->execute([':col' => $columnId, ':now' => now(), ':id' => $studentId]);

        $this->db->prepare(
            'INSERT INTO student_kanban_history
                (student_id, from_status_id, to_status_id, reason, changed_by, created_at)
             VALUES (:sid, :from, :to, :reason, :by, :now)'
        )->execute([
            ':sid'    => $studentId,
            ':from'   => $fromId,
            ':to'     => $columnId,
            ':reason' => 'Movido na Gestão do Aluno',
            ':by'     => $userId,
            ':now'    => now(),
        ]);

        $this->runAutomations('card_moved', (string) $columnId, $studentId, $userId);
    }

    public function reorderCards(int $columnId, array $studentIds): void
    {
        foreach ($studentIds as $order => $sid) {
            $this->db->prepare(
                'UPDATE students SET gda_display_order = :ord WHERE id = :id AND kanban_status_id = :col'
            )->execute([':ord' => $order, ':id' => (int) $sid, ':col' => $columnId]);
        }
    }

    // -------------------------------------------------------------------------
    // CARD META
    // -------------------------------------------------------------------------

    public function updateCardMeta(int $studentId, array $data): void
    {
        $fields = [];
        $params = [':id' => $studentId, ':now' => now()];

        $allowed = ['gda_priority', 'gda_due_date', 'gda_cover_color', 'gda_description', 'gda_assigned_to'];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "$f = :$f";
                $params[":$f"] = $data[$f] === '' ? null : $data[$f];
            }
        }

        if (empty($fields)) {
            return;
        }

        $this->db->prepare(
            'UPDATE students SET ' . implode(', ', $fields) . ', updated_at = :now WHERE id = :id'
        )->execute($params);
    }

    public function archiveCard(int $studentId, bool $archive): void
    {
        $this->db->prepare(
            'UPDATE students SET gda_is_archived = :v, updated_at = :now WHERE id = :id'
        )->execute([':v' => $archive ? 1 : 0, ':now' => now(), ':id' => $studentId]);
    }

    public function archivedCards(): array
    {
        return $this->db->query(
            "SELECT s.id, s.full_name, ks.name AS column_name
             FROM students s
             LEFT JOIN kanban_status ks ON ks.id = s.kanban_status_id
             WHERE s.gda_is_archived = 1
             ORDER BY s.full_name ASC"
        )->fetchAll();
    }

    // -------------------------------------------------------------------------
    // NOTAS
    // -------------------------------------------------------------------------

    public function notes(int $studentId): array
    {
        $stmt = $this->db->prepare(
            'SELECT n.*, u.name AS user_name FROM gda_notes n
             LEFT JOIN users u ON u.id = n.user_id
             WHERE n.student_id = :sid ORDER BY n.id DESC'
        );
        $stmt->execute([':sid' => $studentId]);
        return $stmt->fetchAll();
    }

    public function saveNote(int $studentId, int $userId, string $note): int
    {
        $this->db->prepare(
            'INSERT INTO gda_notes (student_id, user_id, note, created_at)
             VALUES (:sid, :uid, :note, :now)'
        )->execute([':sid' => $studentId, ':uid' => $userId, ':note' => $note, ':now' => now()]);
        return (int) $this->db->lastInsertId();
    }

    public function deleteNote(int $id): void
    {
        $this->db->prepare('DELETE FROM gda_notes WHERE id = :id')->execute([':id' => $id]);
    }

    // -------------------------------------------------------------------------
    // ETIQUETAS
    // -------------------------------------------------------------------------

    public function allLabels(): array
    {
        return $this->db->query('SELECT * FROM gda_labels ORDER BY display_order ASC, id ASC')->fetchAll();
    }

    public function cardLabels(int $studentId): array
    {
        $stmt = $this->db->prepare(
            'SELECT l.* FROM gda_labels l
             INNER JOIN gda_card_labels cl ON cl.label_id = l.id
             WHERE cl.student_id = :sid ORDER BY l.display_order ASC'
        );
        $stmt->execute([':sid' => $studentId]);
        return $stmt->fetchAll();
    }

    public function saveLabel(array $data): int
    {
        if (!empty($data['id'])) {
            $this->db->prepare(
                'UPDATE gda_labels SET name = :name, color = :color, display_order = :ord WHERE id = :id'
            )->execute([':name' => $data['name'], ':color' => $data['color'], ':ord' => (int) $data['display_order'], ':id' => (int) $data['id']]);
            return (int) $data['id'];
        }
        $this->db->prepare(
            'INSERT INTO gda_labels (company_id, name, color, display_order) VALUES (:cid, :name, :color, :ord)'
        )->execute([':cid' => 0, ':name' => $data['name'], ':color' => $data['color'], ':ord' => (int) ($data['display_order'] ?? 99)]);
        return (int) $this->db->lastInsertId();
    }

    public function deleteLabel(int $id): void
    {
        $this->db->prepare('DELETE FROM gda_card_labels WHERE label_id = :id')->execute([':id' => $id]);
        $this->db->prepare('DELETE FROM gda_labels WHERE id = :id')->execute([':id' => $id]);
    }

    public function setCardLabels(int $studentId, array $labelIds): void
    {
        $this->db->prepare('DELETE FROM gda_card_labels WHERE student_id = :sid')->execute([':sid' => $studentId]);
        foreach ($labelIds as $lid) {
            $this->db->prepare(
                'INSERT IGNORE INTO gda_card_labels (student_id, label_id) VALUES (:sid, :lid)'
            )->execute([':sid' => $studentId, ':lid' => (int) $lid]);
        }
    }

    // -------------------------------------------------------------------------
    // MEMBROS
    // -------------------------------------------------------------------------

    public function allUsers(): array
    {
        if ($this->tableColumnExists('users', 'is_active')) {
            return $this->db->query('SELECT id, name FROM users WHERE is_active = 1 ORDER BY name ASC')->fetchAll();
        }

        if ($this->tableColumnExists('users', 'active')) {
            return $this->db->query('SELECT id, name FROM users WHERE active = 1 ORDER BY name ASC')->fetchAll();
        }

        return $this->db->query('SELECT id, name FROM users ORDER BY name ASC')->fetchAll();
    }

    public function cardMembers(int $studentId): array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id AS user_id, u.name FROM users u
             INNER JOIN gda_card_members m ON m.user_id = u.id
             WHERE m.student_id = :sid ORDER BY u.name ASC'
        );
        $stmt->execute([':sid' => $studentId]);
        return $stmt->fetchAll();
    }

    public function setCardMembers(int $studentId, array $userIds): void
    {
        $this->db->prepare('DELETE FROM gda_card_members WHERE student_id = :sid')->execute([':sid' => $studentId]);
        foreach ($userIds as $uid) {
            $this->db->prepare(
                'INSERT IGNORE INTO gda_card_members (student_id, user_id) VALUES (:sid, :uid)'
            )->execute([':sid' => $studentId, ':uid' => (int) $uid]);
        }
    }

    // -------------------------------------------------------------------------
    // CHECKLISTS
    // -------------------------------------------------------------------------

    public function checklistsWithItems(int $studentId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM gda_checklists WHERE student_id = :sid ORDER BY display_order ASC, id ASC'
        );
        $stmt->execute([':sid' => $studentId]);
        $lists = $stmt->fetchAll();

        foreach ($lists as &$list) {
            $stmt2 = $this->db->prepare(
                'SELECT * FROM gda_checklist_items WHERE checklist_id = :cid ORDER BY display_order ASC, id ASC'
            );
            $stmt2->execute([':cid' => $list['id']]);
            $list['items'] = $stmt2->fetchAll();
        }
        unset($list);

        return $lists;
    }

    public function saveChecklist(int $studentId, string $title): int
    {
        $this->db->prepare(
            'INSERT INTO gda_checklists (student_id, title, display_order, created_at) VALUES (:sid, :title, 99, :now)'
        )->execute([':sid' => $studentId, ':title' => $title, ':now' => now()]);
        return (int) $this->db->lastInsertId();
    }

    public function deleteChecklist(int $id): void
    {
        $this->db->prepare('DELETE FROM gda_checklist_items WHERE checklist_id = :id')->execute([':id' => $id]);
        $this->db->prepare('DELETE FROM gda_checklists WHERE id = :id')->execute([':id' => $id]);
    }

    public function saveChecklistItem(int $checklistId, string $text): int
    {
        $this->db->prepare(
            'INSERT INTO gda_checklist_items (checklist_id, text, is_done, display_order, created_at)
             VALUES (:cid, :text, 0, 99, :now)'
        )->execute([':cid' => $checklistId, ':text' => $text, ':now' => now()]);
        return (int) $this->db->lastInsertId();
    }

    public function toggleChecklistItem(int $id, bool $done): void
    {
        $this->db->prepare(
            'UPDATE gda_checklist_items SET is_done = :done WHERE id = :id'
        )->execute([':done' => $done ? 1 : 0, ':id' => $id]);
    }

    public function deleteChecklistItem(int $id): void
    {
        $this->db->prepare('DELETE FROM gda_checklist_items WHERE id = :id')->execute([':id' => $id]);
    }

    // -------------------------------------------------------------------------
    // BUSCA PARA QUICK ADD
    // -------------------------------------------------------------------------

    public function searchStudents(string $q, int $columnId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, full_name, email_primary FROM students
             WHERE (full_name LIKE :q OR email_primary LIKE :q OR phone LIKE :q)
               AND gda_is_archived = 0
             LIMIT 20"
        );
        $stmt->execute([':q' => '%' . $q . '%']);
        return $stmt->fetchAll();
    }

    public function quickAddCard(int $studentId, int $columnId): void
    {
        $this->db->prepare(
            'UPDATE students SET kanban_status_id = :col, gda_is_archived = 0, updated_at = :now WHERE id = :id'
        )->execute([':col' => $columnId, ':now' => now(), ':id' => $studentId]);
    }

    // -------------------------------------------------------------------------
    // ANEXOS
    // -------------------------------------------------------------------------

    public function attachments(int $studentId): array
    {
        $stmt = $this->db->prepare(
            'SELECT a.*, u.name AS uploader_name FROM gda_attachments a
             LEFT JOIN users u ON u.id = a.uploaded_by
             WHERE a.student_id = :sid ORDER BY a.id DESC'
        );
        $stmt->execute([':sid' => $studentId]);
        return $stmt->fetchAll();
    }

    public function saveAttachment(int $studentId, int $userId, array $file): int
    {
        $originalName = $file['original_file_name'] ?? ($file['original_name'] ?? basename((string) ($file['file_name'] ?? 'arquivo')));

        $this->db->prepare(
            'INSERT INTO gda_attachments
                (student_id, file_name, original_file_name, file_type, file_size, uploaded_by, created_at)
             VALUES (:sid, :fn, :ofn, :ft, :fs, :uid, :now)'
        )->execute([
            ':sid'  => $studentId,
            ':fn'   => $file['file_name'],
            ':ofn'  => $originalName,
            ':ft'   => $file['file_type'],
            ':fs'   => $file['file_size'],
            ':uid'  => $userId,
            ':now'  => now(),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function findAttachment(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM gda_attachments WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function deleteAttachment(int $id): ?string
    {
        $row = $this->findAttachment($id);
        if (!$row) {
            return null;
        }
        $this->db->prepare('DELETE FROM gda_attachments WHERE id = :id')->execute([':id' => $id]);
        return $row['file_name'];
    }

    // -------------------------------------------------------------------------
    // CAMPOS PERSONALIZADOS
    // -------------------------------------------------------------------------

    public function allCustomFields(): array
    {
        return $this->db->query(
            'SELECT * FROM gda_custom_fields ORDER BY display_order ASC, id ASC'
        )->fetchAll();
    }

    public function customFieldsWithValues(int $studentId): array
    {
        $fields = $this->allCustomFields();
        foreach ($fields as &$f) {
            $stmt = $this->db->prepare(
                'SELECT value FROM gda_custom_field_values WHERE student_id = :sid AND field_id = :fid LIMIT 1'
            );
            $stmt->execute([':sid' => $studentId, ':fid' => $f['id']]);
            $row = $stmt->fetch();
            $f['value'] = $row ? $row['value'] : null;
        }
        unset($f);
        return $fields;
    }

    public function saveCustomField(array $data): int
    {
        if (!empty($data['id'])) {
            $this->db->prepare(
                'UPDATE gda_custom_fields SET name = :name, field_type = :ft, options_json = :opt, display_order = :ord WHERE id = :id'
            )->execute([':name' => $data['name'], ':ft' => $data['field_type'], ':opt' => $data['options_json'] ?? null, ':ord' => (int) $data['display_order'], ':id' => (int) $data['id']]);
            return (int) $data['id'];
        }
        $this->db->prepare(
            'INSERT INTO gda_custom_fields (company_id, name, field_type, options_json, display_order)
             VALUES (:cid, :name, :ft, :opt, :ord)'
        )->execute([':cid' => 0, ':name' => $data['name'], ':ft' => $data['field_type'], ':opt' => $data['options_json'] ?? null, ':ord' => (int) ($data['display_order'] ?? 99)]);
        return (int) $this->db->lastInsertId();
    }

    public function deleteCustomField(int $id): void
    {
        $this->db->prepare('DELETE FROM gda_custom_field_values WHERE field_id = :id')->execute([':id' => $id]);
        $this->db->prepare('DELETE FROM gda_custom_fields WHERE id = :id')->execute([':id' => $id]);
    }

    public function saveCustomFieldValue(int $studentId, int $fieldId, ?string $value): void
    {
        $this->db->prepare(
            'INSERT INTO gda_custom_field_values (student_id, field_id, value)
             VALUES (:sid, :fid, :val)
             ON DUPLICATE KEY UPDATE value = :val'
        )->execute([':sid' => $studentId, ':fid' => $fieldId, ':val' => $value]);
    }

    // -------------------------------------------------------------------------
    // AUTOMAÇÕES
    // -------------------------------------------------------------------------

    public function allAutomations(): array
    {
        return $this->db->query('SELECT * FROM gda_automations ORDER BY id ASC')->fetchAll();
    }

    public function saveAutomation(array $data): int
    {
        if (!empty($data['id'])) {
            $this->db->prepare(
                'UPDATE gda_automations SET name=:name, trigger_type=:tt, trigger_value=:tv,
                 action_type=:at, action_value=:av, is_active=:active WHERE id=:id'
            )->execute([
                ':name' => $data['name'], ':tt' => $data['trigger_type'], ':tv' => $data['trigger_value'] ?? null,
                ':at' => $data['action_type'], ':av' => $data['action_value'] ?? null,
                ':active' => !empty($data['is_active']) ? 1 : 0, ':id' => (int) $data['id'],
            ]);
            return (int) $data['id'];
        }
        $this->db->prepare(
            'INSERT INTO gda_automations (company_id, name, trigger_type, trigger_value, action_type, action_value, is_active, created_at)
             VALUES (:cid, :name, :tt, :tv, :at, :av, :active, :now)'
        )->execute([
            ':cid' => 0, ':name' => $data['name'], ':tt' => $data['trigger_type'],
            ':tv' => $data['trigger_value'] ?? null, ':at' => $data['action_type'],
            ':av' => $data['action_value'] ?? null,
            ':active' => !empty($data['is_active']) ? 1 : 0, ':now' => now(),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function deleteAutomation(int $id): void
    {
        $this->db->prepare('DELETE FROM gda_automations WHERE id = :id')->execute([':id' => $id]);
    }

    private function runAutomations(string $triggerType, string $triggerValue, int $studentId, int $userId): void
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM gda_automations WHERE trigger_type = :tt AND trigger_value = :tv AND is_active = 1'
        );
        $stmt->execute([':tt' => $triggerType, ':tv' => $triggerValue]);
        $automations = $stmt->fetchAll();

        foreach ($automations as $auto) {
            try {
                if (in_array($auto['action_type'], ['move_to', 'move_to_column'], true) && !empty($auto['action_value'])) {
                    $targetCol = (int) $auto['action_value'];
                    $this->moveStudent($studentId, $targetCol, $userId);
                } elseif ($auto['action_type'] === 'set_priority' && !empty($auto['action_value'])) {
                    $this->updateCardMeta($studentId, ['gda_priority' => $auto['action_value']]);
                } elseif (in_array($auto['action_type'], ['set_label', 'add_label'], true) && !empty($auto['action_value'])) {
                    $this->db->prepare(
                        'INSERT IGNORE INTO gda_card_labels (student_id, label_id) VALUES (:sid, :lid)'
                    )->execute([':sid' => $studentId, ':lid' => (int) $auto['action_value']]);
                }
            } catch (Throwable $e) {
                error_log('[GDA_AUTOMATION] ' . $e->getMessage());
            }
        }
    }

    // -------------------------------------------------------------------------
    // TEMPLATES
    // -------------------------------------------------------------------------

    public function allTemplates(): array
    {
        $templates = $this->db->query('SELECT * FROM gda_templates ORDER BY display_order ASC, id ASC')->fetchAll();
        foreach ($templates as &$tpl) {
            $stmt = $this->db->prepare(
                'SELECT tc.*, GROUP_CONCAT(ti.text ORDER BY ti.display_order SEPARATOR "||") AS items_text
                 FROM gda_template_checklists tc
                 LEFT JOIN gda_template_items ti ON ti.checklist_id = tc.id
                 WHERE tc.template_id = :tid GROUP BY tc.id ORDER BY tc.display_order ASC'
            );
            $stmt->execute([':tid' => $tpl['id']]);
            $tpl['checklists'] = $stmt->fetchAll();
        }
        unset($tpl);
        return $templates;
    }

    public function saveTemplate(array $data): int
    {
        if (!empty($data['id'])) {
            $this->db->prepare(
                'UPDATE gda_templates SET name=:name, description=:desc, priority=:pri, label_ids=:lids, display_order=:ord WHERE id=:id'
            )->execute([':name' => $data['name'], ':desc' => $data['description'] ?? null, ':pri' => $data['priority'] ?? 'none', ':lids' => $data['label_ids'] ?? null, ':ord' => (int) ($data['display_order'] ?? 99), ':id' => (int) $data['id']]);
            $templateId = (int) $data['id'];
            $this->db->prepare('DELETE FROM gda_template_items WHERE checklist_id IN (SELECT id FROM gda_template_checklists WHERE template_id = :tid)')->execute([':tid' => $templateId]);
            $this->db->prepare('DELETE FROM gda_template_checklists WHERE template_id = :tid')->execute([':tid' => $templateId]);
        } else {
            $this->db->prepare(
                'INSERT INTO gda_templates (company_id, name, description, priority, label_ids, display_order)
                 VALUES (:cid, :name, :desc, :pri, :lids, :ord)'
            )->execute([':cid' => 0, ':name' => $data['name'], ':desc' => $data['description'] ?? null, ':pri' => $data['priority'] ?? 'none', ':lids' => $data['label_ids'] ?? null, ':ord' => (int) ($data['display_order'] ?? 99)]);
            $templateId = (int) $this->db->lastInsertId();
        }

        if (!empty($data['checklists'])) {
            foreach ($data['checklists'] as $order => $cl) {
                $this->db->prepare(
                    'INSERT INTO gda_template_checklists (template_id, title, display_order) VALUES (:tid, :title, :ord)'
                )->execute([':tid' => $templateId, ':title' => $cl['title'], ':ord' => $order]);
                $clId = (int) $this->db->lastInsertId();
                if (!empty($cl['items'])) {
                    foreach ($cl['items'] as $iOrder => $item) {
                        $this->db->prepare(
                            'INSERT INTO gda_template_items (checklist_id, text, display_order) VALUES (:cid, :text, :ord)'
                        )->execute([':cid' => $clId, ':text' => $item, ':ord' => $iOrder]);
                    }
                }
            }
        }

        return $templateId;
    }

    public function deleteTemplate(int $id): void
    {
        $this->db->prepare('DELETE FROM gda_template_items WHERE checklist_id IN (SELECT id FROM gda_template_checklists WHERE template_id = :id)')->execute([':id' => $id]);
        $this->db->prepare('DELETE FROM gda_template_checklists WHERE template_id = :id')->execute([':id' => $id]);
        $this->db->prepare('DELETE FROM gda_templates WHERE id = :id')->execute([':id' => $id]);
    }

    public function applyTemplate(int $studentId, int $templateId): void
    {
        $stmt = $this->db->prepare('SELECT * FROM gda_templates WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $templateId]);
        $tpl = $stmt->fetch();
        if (!$tpl) {
            return;
        }

        // Aplicar prioridade
        if (!empty($tpl['priority']) && $tpl['priority'] !== 'none') {
            $this->updateCardMeta($studentId, ['gda_priority' => $tpl['priority']]);
        }

        // Aplicar etiquetas
        if (!empty($tpl['label_ids'])) {
            $ids = json_decode($tpl['label_ids'], true);
            if (is_array($ids)) {
                foreach ($ids as $lid) {
                    $this->db->prepare(
                        'INSERT IGNORE INTO gda_card_labels (student_id, label_id) VALUES (:sid, :lid)'
                    )->execute([':sid' => $studentId, ':lid' => (int) $lid]);
                }
            }
        }

        // Aplicar checklists
        $stmt2 = $this->db->prepare('SELECT * FROM gda_template_checklists WHERE template_id = :tid ORDER BY display_order ASC');
        $stmt2->execute([':tid' => $templateId]);
        $checklists = $stmt2->fetchAll();

        foreach ($checklists as $cl) {
            $this->db->prepare(
                'INSERT INTO gda_checklists (student_id, title, display_order, created_at) VALUES (:sid, :title, :ord, :now)'
            )->execute([':sid' => $studentId, ':title' => $cl['title'], ':ord' => $cl['display_order'], ':now' => now()]);
            $clId = (int) $this->db->lastInsertId();

            $stmt3 = $this->db->prepare('SELECT * FROM gda_template_items WHERE checklist_id = :cid ORDER BY display_order ASC');
            $stmt3->execute([':cid' => $cl['id']]);
            $items = $stmt3->fetchAll();

            foreach ($items as $item) {
                $this->db->prepare(
                    'INSERT INTO gda_checklist_items (checklist_id, text, is_done, display_order, created_at)
                     VALUES (:cid, :text, 0, :ord, :now)'
                )->execute([':cid' => $clId, ':text' => $item['text'], ':ord' => $item['display_order'], ':now' => now()]);
            }
        }
    }

    // -------------------------------------------------------------------------
    // HISTÓRICO
    // -------------------------------------------------------------------------

    public function history(int $studentId): array
    {
        $stmt = $this->db->prepare(
            'SELECT h.*, h.created_at AS changed_at,
                    fs.name AS from_name, ts.name AS to_name, u.name AS user_name
             FROM student_kanban_history h
             LEFT JOIN kanban_status fs ON fs.id = h.from_status_id
             LEFT JOIN kanban_status ts ON ts.id = h.to_status_id
             LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.student_id = :sid ORDER BY h.id DESC LIMIT 50'
        );
        $stmt->execute([':sid' => $studentId]);
        return $stmt->fetchAll();
    }

    // -------------------------------------------------------------------------
    // COLUNAS (delegado ao KanbanModel, mas repetido aqui para self-contained)
    // -------------------------------------------------------------------------

    public function allColumns(): array
    {
        return $this->db->query(
            'SELECT ks.*, COUNT(s.id) AS total_students
             FROM kanban_status ks
             LEFT JOIN students s ON s.kanban_status_id = ks.id AND s.gda_is_archived = 0
             GROUP BY ks.id ORDER BY ks.display_order ASC, ks.id ASC'
        )->fetchAll();
    }

    public function findColumn(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM kanban_status WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createColumn(array $data): int
    {
        if (!empty($data['is_default'])) {
            $this->db->exec('UPDATE kanban_status SET is_default = 0');
        }
        $this->db->prepare(
            'INSERT INTO kanban_status (name, slug, color, display_order, is_default, created_at, updated_at)
             VALUES (:name, :slug, :color, :ord, :def, :now, :now)'
        )->execute([
            ':name' => $data['name'],
            ':slug' => $this->slug($data['name']),
            ':color' => $data['color'] ?? '#0ea5e9',
            ':ord' => (int) ($data['display_order'] ?? 99),
            ':def' => !empty($data['is_default']) ? 1 : 0,
            ':now' => now(),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateColumn(int $id, array $data): void
    {
        if (!empty($data['is_default'])) {
            $this->db->exec('UPDATE kanban_status SET is_default = 0');
        }
        $this->db->prepare(
            'UPDATE kanban_status SET name=:name, slug=:slug, color=:color,
             display_order=:ord, is_default=:def, updated_at=:now WHERE id=:id'
        )->execute([
            ':name' => $data['name'],
            ':slug' => $this->slug($data['name']),
            ':color' => $data['color'] ?? '#0ea5e9',
            ':ord' => (int) ($data['display_order'] ?? 99),
            ':def' => !empty($data['is_default']) ? 1 : 0,
            ':now' => now(),
            ':id' => $id,
        ]);
    }

    public function deleteColumn(int $id): void
    {
        $stmt = $this->db->query('SELECT id FROM kanban_status WHERE is_default = 1 LIMIT 1');
        $default = $stmt->fetch();
        if (!$default || (int) $default['id'] === $id) {
            return;
        }
        $this->db->prepare(
            'UPDATE students SET kanban_status_id = :def WHERE kanban_status_id = :id'
        )->execute([':def' => (int) $default['id'], ':id' => $id]);
        $this->db->prepare('DELETE FROM kanban_status WHERE id = :id')->execute([':id' => $id]);
    }

    // -------------------------------------------------------------------------
    // CALENDÁRIO
    // -------------------------------------------------------------------------

    public function calendarCards(int $year, int $month): array
    {
        $from = sprintf('%04d-%02d-01', $year, $month);
        $to   = date('Y-m-t', strtotime($from));
        $stmt = $this->db->prepare(
            "SELECT s.id, s.full_name, s.gda_due_date, s.gda_priority,
                    ks.name AS column_name, ks.color AS column_color
             FROM students s
             LEFT JOIN kanban_status ks ON ks.id = s.kanban_status_id
             WHERE s.gda_due_date BETWEEN :from AND :to AND s.gda_is_archived = 0
             ORDER BY s.gda_due_date ASC"
        );
        $stmt->execute([':from' => $from, ':to' => $to]);
        return $stmt->fetchAll();
    }

    // -------------------------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------------------------

    private function slug(string $name): string
    {
        $s = strtolower(trim($name));
        $s = preg_replace('/[^a-z0-9]+/i', '-', $s);
        return trim((string) $s, '-');
    }
}
