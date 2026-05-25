<?php

class PayableModel extends BaseModel
{
    public function tableExists(): bool
    {
        return $this->schemaTableExists('payables');
    }

    public function paymentsTableExists(): bool
    {
        return $this->schemaTableExists('payable_payments');
    }

    public function attachmentsTableExists(): bool
    {
        return $this->schemaTableExists('payable_attachments');
    }

    public function recurrenceColumnsAvailable(): bool
    {
        return $this->schemaColumnExists('payables', 'is_recurring')
            && $this->schemaColumnExists('payables', 'recurrence_parent_id');
    }

    public function refreshOverduePayables(): void
    {
        if (!$this->tableExists()) {
            return;
        }

        $stmt = $this->db->prepare("UPDATE payables
            SET status = 'overdue',
                updated_at = :updated_at
            WHERE company_id = :company_id
              AND due_date < CURDATE()
              AND status IN ('open', 'partial')");
        $stmt->execute([
            ':updated_at' => now(),
            ':company_id' => $this->companyId(),
        ]);
    }

    public function stats(array $filters = []): array
    {
        if (!$this->tableExists()) {
            return [
                'open_count' => 0,
                'overdue_count' => 0,
                'paid_count' => 0,
                'draft_count' => 0,
                'open_amount' => 0.0,
                'overdue_amount' => 0.0,
                'paid_period_amount' => 0.0,
                'partial_count' => 0,
            ];
        }

        $this->refreshOverduePayables();
        $companyId = $this->companyId();
        $startDate = (string) ($filters['start_date'] ?? '');
        $endDate = (string) ($filters['end_date'] ?? '');

        return [
            'open_count' => (int) $this->scalar("SELECT COUNT(*) FROM payables WHERE company_id = :company_id AND status = 'open'", [':company_id' => $companyId]),
            'overdue_count' => (int) $this->scalar("SELECT COUNT(*) FROM payables WHERE company_id = :company_id AND status = 'overdue'", [':company_id' => $companyId]),
            'paid_count' => (int) $this->scalar("SELECT COUNT(*) FROM payables WHERE company_id = :company_id AND status = 'paid'", [':company_id' => $companyId]),
            'draft_count' => (int) $this->scalar("SELECT COUNT(*) FROM payables WHERE company_id = :company_id AND status = 'draft'", [':company_id' => $companyId]),
            'partial_count' => (int) $this->scalar("SELECT COUNT(*) FROM payables WHERE company_id = :company_id AND status = 'partial'", [':company_id' => $companyId]),
            'open_amount' => (float) $this->scalar("SELECT COALESCE(SUM(amount - paid_amount), 0) FROM payables WHERE company_id = :company_id AND status IN ('open','partial')", [':company_id' => $companyId]),
            'overdue_amount' => (float) $this->scalar("SELECT COALESCE(SUM(amount - paid_amount), 0) FROM payables WHERE company_id = :company_id AND status = 'overdue'", [':company_id' => $companyId]),
            'paid_period_amount' => ($startDate !== '' && $endDate !== '')
                ? (float) $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM payable_payments WHERE company_id = :company_id AND paid_at BETWEEN :start_date AND :end_date", [
                    ':company_id' => $companyId,
                    ':start_date' => $startDate,
                    ':end_date' => $endDate,
                ])
                : 0.0,
        ];
    }

    public function dueAlerts(int $daysAhead = 7, int $limit = 12): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        $this->refreshOverduePayables();
        $daysAhead = max(0, min(90, $daysAhead));
        $limit = max(1, min(50, $limit));

        $stmt = $this->db->prepare("SELECT
                p.id,
                p.payable_number,
                p.description,
                p.category,
                p.due_date,
                p.amount,
                p.paid_amount,
                GREATEST(p.amount - p.paid_amount, 0) AS outstanding_amount,
                p.status,
                s.name AS supplier_name,
                DATEDIFF(p.due_date, CURDATE()) AS days_until_due
            FROM payables p
            INNER JOIN suppliers s ON s.id = p.supplier_id
            WHERE p.company_id = :company_id
              AND p.status IN ('open', 'partial', 'overdue')
              AND GREATEST(p.amount - p.paid_amount, 0) > 0
              AND p.due_date <= DATE_ADD(CURDATE(), INTERVAL {$daysAhead} DAY)
            ORDER BY
                CASE
                    WHEN p.due_date < CURDATE() THEN 0
                    WHEN p.due_date = CURDATE() THEN 1
                    ELSE 2
                END ASC,
                p.due_date ASC,
                p.id ASC
            LIMIT {$limit}");
        $stmt->bindValue(':company_id', $this->companyId(), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function dueAlertCount(int $daysAhead = 7): int
    {
        if (!$this->tableExists()) {
            return 0;
        }

        $this->refreshOverduePayables();
        $daysAhead = max(0, min(90, $daysAhead));
        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM payables
            WHERE company_id = :company_id
              AND status IN ('open', 'partial', 'overdue')
              AND GREATEST(amount - paid_amount, 0) > 0
              AND due_date <= DATE_ADD(CURDATE(), INTERVAL {$daysAhead} DAY)");
        $stmt->bindValue(':company_id', $this->companyId(), PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function list(array $filters, int $perPage, int $page): array
    {
        if (!$this->tableExists()) {
            return ['rows' => [], 'meta' => pagination_meta(0, $perPage, 1)];
        }

        $this->refreshOverduePayables();
        $hasPaymentMethods = (new PaymentMethodModel())->tableExists();

        $where = ['p.company_id = :company_id'];
        $params = [':company_id' => $this->companyId()];

        if (!empty($filters['q'])) {
            $where[] = '(p.payable_number LIKE :q OR p.description LIKE :q OR p.category LIKE :q OR s.name LIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        if (!empty($filters['supplier_id'])) {
            $where[] = 'p.supplier_id = :supplier_id';
            $params[':supplier_id'] = (int) $filters['supplier_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'p.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $where[] = 'p.due_date BETWEEN :start_date AND :end_date';
            $params[':start_date'] = $filters['start_date'];
            $params[':end_date'] = $filters['end_date'];
        }

        $whereSql = implode(' AND ', $where);
        $paymentJoin = $hasPaymentMethods
            ? "LEFT JOIN payment_methods pm ON pm.id = p.payment_method_id AND pm.company_id = p.company_id"
            : "";
        $paymentFields = $hasPaymentMethods
            ? "pm.name AS payment_method_name"
            : "NULL AS payment_method_name";

        $countSql = "SELECT COUNT(*)
            FROM payables p
            INNER JOIN suppliers s ON s.id = p.supplier_id
            {$paymentJoin}
            WHERE {$whereSql}";
        $dataSql = "SELECT p.*, s.name AS supplier_name, s.phone AS supplier_phone, s.whatsapp AS supplier_whatsapp, {$paymentFields}
            FROM payables p
            INNER JOIN suppliers s ON s.id = p.supplier_id
            {$paymentJoin}
            WHERE {$whereSql}
            ORDER BY FIELD(p.status, 'overdue', 'open', 'partial', 'draft', 'paid', 'cancelled'), p.due_date ASC, p.id DESC";

        $result = $this->paginate($countSql, $dataSql, $params, $perPage, $page);
        $result['rows'] = array_map(function (array $row): array {
            $row['outstanding_amount'] = max(0, (float) ($row['amount'] ?? 0) - (float) ($row['paid_amount'] ?? 0));
            $row['days_overdue'] = 0;
            if (in_array((string) ($row['status'] ?? ''), ['overdue'], true) && !empty($row['due_date'])) {
                $days = (int) floor((time() - strtotime((string) $row['due_date'])) / 86400);
                $row['days_overdue'] = max(0, $days);
            }
            return $row;
        }, $result['rows']);

        return $result;
    }

    public function create(array $data, int $createdBy): int
    {
        if (!$this->tableExists()) {
            return 0;
        }

        $companyId = $this->companyId();
        $now = now();
        $stmt = $this->db->prepare('INSERT INTO payables (
            company_id, supplier_id, payment_method_id, payable_number, description, category,
            competence_date, due_date, amount, paid_amount, paid_at, status, notes,
            is_recurring, recurrence_interval, recurrence_until, recurrence_parent_id,
            created_by, updated_by, created_at, updated_at
        ) VALUES (
            :company_id, :supplier_id, :payment_method_id, :payable_number, :description, :category,
            :competence_date, :due_date, :amount, 0, NULL, :status, :notes,
            :is_recurring, :recurrence_interval, :recurrence_until, :recurrence_parent_id,
            :created_by, :updated_by, :created_at, :updated_at
        )');
        $stmt->execute([
            ':company_id' => $companyId,
            ':supplier_id' => (int) $data['supplier_id'],
            ':payment_method_id' => !empty($data['payment_method_id']) ? (int) $data['payment_method_id'] : null,
            ':payable_number' => $data['payable_number'],
            ':description' => $data['description'],
            ':category' => $data['category'] ?: null,
            ':competence_date' => $data['competence_date'] ?: null,
            ':due_date' => $data['due_date'],
            ':amount' => (float) $data['amount'],
            ':status' => $data['status'] ?: 'open',
            ':notes' => $data['notes'] ?: null,
            ':is_recurring' => !empty($data['is_recurring']) ? 1 : 0,
            ':recurrence_interval' => !empty($data['is_recurring']) ? $this->normalizeRecurrenceInterval((string) ($data['recurrence_interval'] ?? 'monthly')) : null,
            ':recurrence_until' => !empty($data['is_recurring']) && !empty($data['recurrence_until']) ? $data['recurrence_until'] : null,
            ':recurrence_parent_id' => !empty($data['recurrence_parent_id']) ? (int) $data['recurrence_parent_id'] : null,
            ':created_by' => $createdBy > 0 ? $createdBy : null,
            ':updated_by' => $createdBy > 0 ? $createdBy : null,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function nextNumber(): string
    {
        if (!$this->tableExists()) {
            return 'PAGAR-' . date('YmdHis');
        }

        $prefix = 'PAGAR-' . date('ym');
        $stmt = $this->db->prepare('SELECT payable_number
            FROM payables
            WHERE company_id = :company_id
              AND payable_number LIKE :prefix
            ORDER BY id DESC
            LIMIT 1');
        $stmt->execute([
            ':company_id' => $this->companyId(),
            ':prefix' => $prefix . '%',
        ]);
        $last = (string) $stmt->fetchColumn();
        if ($last === '') {
            return $prefix . '-001';
        }

        $parts = explode('-', $last);
        $seq = (int) end($parts);
        return $prefix . '-' . str_pad((string) ($seq + 1), 3, '0', STR_PAD_LEFT);
    }

    public function find(int $id): ?array
    {
        if (!$this->tableExists() || $id <= 0) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT p.*, s.name AS supplier_name
            FROM payables p
            INNER JOIN suppliers s ON s.id = p.supplier_id
            WHERE p.company_id = :company_id
              AND p.id = :id
            LIMIT 1');
        $stmt->execute([
            ':company_id' => $this->companyId(),
            ':id' => $id,
        ]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $row['outstanding_amount'] = max(0, (float) ($row['amount'] ?? 0) - (float) ($row['paid_amount'] ?? 0));
        return $row;
    }

    public function update(int $payableId, array $data, int $updatedBy): array
    {
        if (!$this->tableExists()) {
            return ['ok' => false, 'message' => 'Estrutura de contas a pagar indisponivel no banco.'];
        }

        $payable = $this->find($payableId);
        if (!$payable) {
            return ['ok' => false, 'message' => 'Conta a pagar nao encontrada.'];
        }

        if ((string) ($payable['status'] ?? '') === 'cancelled') {
            return ['ok' => false, 'message' => 'Contas canceladas nao podem ser editadas.'];
        }

        $amount = (float) ($data['amount'] ?? 0);
        $paidAmount = (float) ($payable['paid_amount'] ?? 0);
        if ($amount <= 0) {
            return ['ok' => false, 'message' => 'Informe um valor valido para a conta a pagar.'];
        }

        if ($amount + 0.0001 < $paidAmount) {
            return ['ok' => false, 'message' => 'O novo valor nao pode ser menor que o total ja pago.'];
        }

        $requestedStatus = strtolower(trim((string) ($data['status'] ?? 'open')));
        $allowedRequestedStatuses = ['draft', 'open', 'partial', 'paid', 'overdue'];
        if (!in_array($requestedStatus, $allowedRequestedStatuses, true)) {
            $requestedStatus = 'open';
        }

        $resolvedStatus = $this->resolveStatusForUpdate($requestedStatus, $amount, $paidAmount, (string) ($data['due_date'] ?? ''));
        if ($resolvedStatus === 'paid' && trim((string) ($payable['paid_at'] ?? '')) === '') {
            $resolvedPaidAt = trim((string) ($data['paid_at'] ?? date('Y-m-d')));
        } elseif ($paidAmount > 0) {
            $resolvedPaidAt = trim((string) ($payable['paid_at'] ?? '')) !== '' ? (string) $payable['paid_at'] : trim((string) ($data['paid_at'] ?? date('Y-m-d')));
        } else {
            $resolvedPaidAt = null;
        }

        $now = now();
        $stmt = $this->db->prepare('UPDATE payables
            SET supplier_id = :supplier_id,
                payment_method_id = :payment_method_id,
                payable_number = :payable_number,
                description = :description,
                category = :category,
                competence_date = :competence_date,
                due_date = :due_date,
                amount = :amount,
                status = :status,
                paid_at = :paid_at,
                notes = :notes,
                is_recurring = :is_recurring,
                recurrence_interval = :recurrence_interval,
                recurrence_until = :recurrence_until,
                updated_by = :updated_by,
                updated_at = :updated_at
            WHERE company_id = :company_id
              AND id = :id');

        $ok = $stmt->execute([
            ':supplier_id' => (int) ($data['supplier_id'] ?? 0),
            ':payment_method_id' => !empty($data['payment_method_id']) ? (int) $data['payment_method_id'] : null,
            ':payable_number' => trim((string) ($data['payable_number'] ?? '')),
            ':description' => trim((string) ($data['description'] ?? '')),
            ':category' => trim((string) ($data['category'] ?? '')) !== '' ? trim((string) $data['category']) : null,
            ':competence_date' => trim((string) ($data['competence_date'] ?? '')) !== '' ? trim((string) $data['competence_date']) : null,
            ':due_date' => trim((string) ($data['due_date'] ?? '')),
            ':amount' => $amount,
            ':status' => $resolvedStatus,
            ':paid_at' => $resolvedPaidAt,
            ':notes' => trim((string) ($data['notes'] ?? '')) !== '' ? trim((string) $data['notes']) : null,
            ':is_recurring' => !empty($data['is_recurring']) ? 1 : 0,
            ':recurrence_interval' => !empty($data['is_recurring']) ? $this->normalizeRecurrenceInterval((string) ($data['recurrence_interval'] ?? 'monthly')) : null,
            ':recurrence_until' => !empty($data['is_recurring']) && !empty($data['recurrence_until']) ? trim((string) $data['recurrence_until']) : null,
            ':updated_by' => $updatedBy > 0 ? $updatedBy : null,
            ':updated_at' => $now,
            ':company_id' => $this->companyId(),
            ':id' => $payableId,
        ]);

        if (!$ok) {
            return ['ok' => false, 'message' => 'Nao foi possivel atualizar a conta a pagar.'];
        }

        return ['ok' => true, 'message' => 'Conta a pagar atualizada com sucesso.'];
    }

    public function cancel(int $payableId, int $updatedBy): array
    {
        if (!$this->tableExists()) {
            return ['ok' => false, 'message' => 'Estrutura de contas a pagar indisponivel no banco.'];
        }

        $payable = $this->find($payableId);
        if (!$payable) {
            return ['ok' => false, 'message' => 'Conta a pagar nao encontrada.'];
        }

        $status = (string) ($payable['status'] ?? '');
        if ($status === 'cancelled') {
            return ['ok' => false, 'message' => 'Esta conta ja esta cancelada.'];
        }

        if ((float) ($payable['paid_amount'] ?? 0) > 0) {
            return ['ok' => false, 'message' => 'Nao e permitido cancelar contas com pagamento registrado.'];
        }

        $stmt = $this->db->prepare('UPDATE payables
            SET status = :status,
                updated_by = :updated_by,
                updated_at = :updated_at
            WHERE company_id = :company_id
              AND id = :id');
        $ok = $stmt->execute([
            ':status' => 'cancelled',
            ':updated_by' => $updatedBy > 0 ? $updatedBy : null,
            ':updated_at' => now(),
            ':company_id' => $this->companyId(),
            ':id' => $payableId,
        ]);

        if (!$ok) {
            return ['ok' => false, 'message' => 'Nao foi possivel cancelar a conta a pagar.'];
        }

        return ['ok' => true, 'message' => 'Conta a pagar cancelada com sucesso.'];
    }

    public function generateRecurringPayables(string $referenceDate, int $createdBy): array
    {
        if (!$this->tableExists() || !$this->recurrenceColumnsAvailable()) {
            return ['ok' => false, 'message' => 'Estrutura de recorrencia indisponivel no banco.', 'created' => 0, 'existing' => 0];
        }

        $referenceDate = trim($referenceDate);
        if ($referenceDate === '' || strtotime($referenceDate) === false) {
            return ['ok' => false, 'message' => 'Data de referencia invalida.', 'created' => 0, 'existing' => 0];
        }

        $companyId = $this->companyId();
        $stmt = $this->db->prepare("SELECT *
            FROM payables
            WHERE company_id = :company_id
              AND is_recurring = 1
              AND recurrence_parent_id IS NULL
              AND status <> 'cancelled'
            ORDER BY due_date ASC, id ASC");
        $stmt->execute([':company_id' => $companyId]);

        $created = 0;
        $existing = 0;
        foreach ($stmt->fetchAll() as $template) {
            $interval = $this->normalizeRecurrenceInterval((string) ($template['recurrence_interval'] ?? 'monthly'));
            $stepMonths = $this->recurrenceIntervalMonths($interval);
            $templateDueDate = (string) ($template['due_date'] ?? '');
            if ($templateDueDate === '' || strtotime($templateDueDate) === false) {
                continue;
            }

            $endDate = $referenceDate;
            $until = trim((string) ($template['recurrence_until'] ?? ''));
            if ($until !== '' && strtotime($until) !== false && strtotime($until) < strtotime($endDate)) {
                $endDate = $until;
            }

            $occurrence = 1;
            while (true) {
                $dueDate = $this->addMonthsPreservingDay($templateDueDate, $stepMonths * $occurrence);
                if (strtotime($dueDate) > strtotime($endDate)) {
                    break;
                }

                if ($this->recurringPayableExists((int) $template['id'], $dueDate)) {
                    $existing++;
                    $occurrence++;
                    continue;
                }

                $competenceDate = null;
                if (!empty($template['competence_date']) && strtotime((string) $template['competence_date']) !== false) {
                    $competenceDate = $this->addMonthsPreservingDay((string) $template['competence_date'], $stepMonths * $occurrence);
                }

                $this->create([
                    'supplier_id' => (int) $template['supplier_id'],
                    'payment_method_id' => !empty($template['payment_method_id']) ? (int) $template['payment_method_id'] : null,
                    'payable_number' => $this->buildRecurringPayableNumber((string) $template['payable_number'], $dueDate),
                    'description' => (string) $template['description'],
                    'category' => (string) ($template['category'] ?? ''),
                    'competence_date' => $competenceDate,
                    'due_date' => $dueDate,
                    'amount' => (float) $template['amount'],
                    'status' => 'open',
                    'notes' => (string) ($template['notes'] ?? ''),
                    'is_recurring' => 0,
                    'recurrence_interval' => $interval,
                    'recurrence_until' => $until !== '' ? $until : null,
                    'recurrence_parent_id' => (int) $template['id'],
                ], $createdBy);
                $created++;
                $occurrence++;
            }
        }

        return [
            'ok' => true,
            'message' => $created . ' conta(s) recorrente(s) gerada(s).',
            'created' => $created,
            'existing' => $existing,
        ];
    }

    public function attachmentsByPayables(array $payableIds): array
    {
        if (!$this->attachmentsTableExists() || $payableIds === []) {
            return [];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $payableIds))));
        if ($ids === []) {
            return [];
        }

        $placeholders = [];
        $params = [':company_id' => $this->companyId()];
        foreach ($ids as $idx => $id) {
            $key = ':id_' . $idx;
            $placeholders[] = $key;
            $params[$key] = $id;
        }

        $stmt = $this->db->prepare('SELECT *
            FROM payable_attachments
            WHERE company_id = :company_id
              AND payable_id IN (' . implode(',', $placeholders) . ')
            ORDER BY created_at DESC, id DESC');
        $stmt->execute($params);

        $grouped = [];
        foreach ($stmt->fetchAll() as $row) {
            $payableId = (int) ($row['payable_id'] ?? 0);
            if (!isset($grouped[$payableId])) {
                $grouped[$payableId] = [];
            }
            $grouped[$payableId][] = $row;
        }

        return $grouped;
    }

    public function addAttachment(int $payableId, array $data, int $createdBy): int
    {
        if (!$this->attachmentsTableExists()) {
            return 0;
        }

        $payable = $this->find($payableId);
        if (!$payable) {
            return 0;
        }

        $now = now();
        $stmt = $this->db->prepare('INSERT INTO payable_attachments (
            company_id, payable_id, attachment_type, original_file_name, stored_file_name,
            file_path, file_type, file_size, notes, created_by, created_at, updated_at
        ) VALUES (
            :company_id, :payable_id, :attachment_type, :original_file_name, :stored_file_name,
            :file_path, :file_type, :file_size, :notes, :created_by, :created_at, :updated_at
        )');
        $stmt->execute([
            ':company_id' => $this->companyId(),
            ':payable_id' => $payableId,
            ':attachment_type' => (string) ($data['attachment_type'] ?? 'outro'),
            ':original_file_name' => (string) ($data['original_file_name'] ?? ''),
            ':stored_file_name' => (string) ($data['stored_file_name'] ?? ''),
            ':file_path' => (string) ($data['file_path'] ?? ''),
            ':file_type' => (string) ($data['file_type'] ?? ''),
            ':file_size' => (int) ($data['file_size'] ?? 0),
            ':notes' => trim((string) ($data['notes'] ?? '')) !== '' ? trim((string) $data['notes']) : null,
            ':created_by' => $createdBy > 0 ? $createdBy : null,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findAttachment(int $attachmentId): ?array
    {
        if (!$this->attachmentsTableExists() || $attachmentId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT a.*, p.payable_number, p.description AS payable_description
            FROM payable_attachments a
            INNER JOIN payables p ON p.id = a.payable_id AND p.company_id = a.company_id
            WHERE a.company_id = :company_id
              AND a.id = :id
            LIMIT 1');
        $stmt->execute([
            ':company_id' => $this->companyId(),
            ':id' => $attachmentId,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function deleteAttachment(int $attachmentId): bool
    {
        if (!$this->attachmentsTableExists() || $attachmentId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare('DELETE FROM payable_attachments
            WHERE company_id = :company_id
              AND id = :id');
        $stmt->execute([
            ':company_id' => $this->companyId(),
            ':id' => $attachmentId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function registerPayment(int $payableId, float $amount, string $paidAt, string $notes, ?int $paymentMethodId, int $createdBy): array
    {
        if (!$this->tableExists() || !$this->paymentsTableExists()) {
            return ['ok' => false, 'message' => 'Estrutura de contas a pagar indisponivel no banco.'];
        }

        $payable = $this->find($payableId);
        if (!$payable) {
            return ['ok' => false, 'message' => 'Conta a pagar nao encontrada.'];
        }

        if (in_array((string) ($payable['status'] ?? ''), ['paid', 'cancelled'], true)) {
            return ['ok' => false, 'message' => 'Esta conta nao aceita novas baixas.'];
        }

        $outstanding = max(0, (float) ($payable['amount'] ?? 0) - (float) ($payable['paid_amount'] ?? 0));
        if ($amount <= 0 || $amount > $outstanding + 0.0001) {
            return ['ok' => false, 'message' => 'Valor de baixa invalido para o saldo atual.'];
        }

        $companyId = $this->companyId();
        $now = now();
        $this->db->beginTransaction();

        try {
            $insert = $this->db->prepare('INSERT INTO payable_payments (
                company_id, payable_id, payment_method_id, amount, paid_at, notes, created_by, created_at, updated_at
            ) VALUES (
                :company_id, :payable_id, :payment_method_id, :amount, :paid_at, :notes, :created_by, :created_at, :updated_at
            )');
            $insert->execute([
                ':company_id' => $companyId,
                ':payable_id' => $payableId,
                ':payment_method_id' => $paymentMethodId,
                ':amount' => $amount,
                ':paid_at' => $paidAt,
                ':notes' => $notes !== '' ? $notes : null,
                ':created_by' => $createdBy > 0 ? $createdBy : null,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);

            $newPaidAmount = round((float) ($payable['paid_amount'] ?? 0) + $amount, 2);
            $newStatus = $newPaidAmount >= (float) ($payable['amount'] ?? 0) ? 'paid' : 'partial';
            $update = $this->db->prepare('UPDATE payables
                SET paid_amount = :paid_amount,
                    paid_at = :paid_at,
                    status = :status,
                    payment_method_id = COALESCE(:payment_method_id, payment_method_id),
                    updated_by = :updated_by,
                    updated_at = :updated_at
                WHERE company_id = :company_id
                  AND id = :id');
            $update->execute([
                ':paid_amount' => $newPaidAmount,
                ':paid_at' => $paidAt,
                ':status' => $newStatus,
                ':payment_method_id' => $paymentMethodId,
                ':updated_by' => $createdBy > 0 ? $createdBy : null,
                ':updated_at' => $now,
                ':company_id' => $companyId,
                ':id' => $payableId,
            ]);

            $this->db->commit();
            return ['ok' => true, 'message' => $newStatus === 'paid' ? 'Conta baixada totalmente.' : 'Baixa parcial registrada.'];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['ok' => false, 'message' => 'Nao foi possivel registrar a baixa.'];
        }
    }

    protected function scalar(string $sql, array $params = [])
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    private function resolveStatusForUpdate(string $requestedStatus, float $amount, float $paidAmount, string $dueDate): string
    {
        if ($paidAmount >= $amount && $amount > 0) {
            return 'paid';
        }

        if ($paidAmount > 0) {
            return $this->isPastDue($dueDate) ? 'overdue' : 'partial';
        }

        if ($requestedStatus === 'draft') {
            return 'draft';
        }

        return $this->isPastDue($dueDate) ? 'overdue' : 'open';
    }

    private function isPastDue(string $dueDate): bool
    {
        $dueDate = trim($dueDate);
        return $dueDate !== '' && strtotime($dueDate) < strtotime(date('Y-m-d'));
    }

    private function normalizeRecurrenceInterval(string $interval): string
    {
        $interval = strtolower(trim($interval));
        return in_array($interval, ['monthly', 'quarterly', 'yearly'], true) ? $interval : 'monthly';
    }

    private function recurrenceIntervalMonths(string $interval): int
    {
        return match ($this->normalizeRecurrenceInterval($interval)) {
            'quarterly' => 3,
            'yearly' => 12,
            default => 1,
        };
    }

    private function recurringPayableExists(int $templateId, string $dueDate): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*)
            FROM payables
            WHERE company_id = :company_id
              AND recurrence_parent_id = :template_id
              AND due_date = :due_date');
        $stmt->execute([
            ':company_id' => $this->companyId(),
            ':template_id' => $templateId,
            ':due_date' => $dueDate,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function buildRecurringPayableNumber(string $baseNumber, string $dueDate): string
    {
        $suffix = date('Ym', strtotime($dueDate));
        $base = trim($baseNumber) !== '' ? trim($baseNumber) : 'PAGAR';
        $base = substr($base, 0, 53);
        return $base . '-' . $suffix;
    }

    private function addMonthsPreservingDay(string $date, int $months): string
    {
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return date('Y-m-d');
        }

        $year = (int) date('Y', $timestamp);
        $month = (int) date('n', $timestamp);
        $day = (int) date('j', $timestamp);
        $month += $months;

        while ($month > 12) {
            $year++;
            $month -= 12;
        }

        while ($month < 1) {
            $year--;
            $month += 12;
        }

        $lastDay = (int) date('t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
        $day = min($day, $lastDay);

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
}
