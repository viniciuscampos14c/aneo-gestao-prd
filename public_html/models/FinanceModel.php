<?php

class FinanceModel extends BaseModel
{
    private StudentModel $students;
    private FiscalInvoiceService $fiscalService;
    private BoletoService $boletoService;
    private PaymentMethodModel $paymentMethods;
    private ?bool $bankSlipNossoNumeroColumnExists = null;

    public function __construct(?int $companyId = null)
    {
        parent::__construct();
        if ($companyId !== null && $companyId > 0) {
            $this->useCompany($companyId);
        }
        $resolvedCompanyId = $this->companyId();
        $this->students = (new StudentModel())->useCompany($resolvedCompanyId);
        $this->fiscalService = new FiscalInvoiceService($resolvedCompanyId);
        $this->boletoService = new BoletoService($resolvedCompanyId);
        $this->paymentMethods = new PaymentMethodModel();
    }

    public function useCompany(?int $companyId): static
    {
        parent::useCompany($companyId);

        if (isset($this->students)) {
            $this->students->useCompany($this->companyId());
        }

        return $this;
    }

    public function listStudents(): array
    {
        $stmt = $this->db->prepare('SELECT id, full_name
            FROM students
            WHERE company_id = :company_id
              AND is_active = 1
            ORDER BY full_name ASC');
        $stmt->execute([':company_id' => $this->companyId()]);

        return $stmt->fetchAll();
    }

    public function refreshOverdueInvoices(): void
    {
        $stmt = $this->db->prepare("UPDATE invoices
            SET status = 'overdue', updated_at = :updated_at
            WHERE company_id = :company_id
              AND due_date < CURDATE()
              AND status IN ('open', 'partial')");
        $stmt->execute([
            ':updated_at' => now(),
            ':company_id' => $this->companyId(),
        ]);
    }

    public function invoiceStats(array $filters = []): array
    {
        $this->refreshOverdueInvoices();

        $counts = [
            'open' => 0,
            'paid' => 0,
            'partial' => 0,
            'overdue' => 0,
            'draft' => 0,
        ];

        [$whereSql, $params] = $this->invoiceFilterSql($filters, 'i', 's', 'stats_');

        $stmt = $this->db->prepare("SELECT i.status, COUNT(*) AS qty
            FROM invoices i
            LEFT JOIN students s ON s.id = i.student_id
            WHERE {$whereSql}
            GROUP BY i.status");
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $counts[$row['status']] = (int) $row['qty'];
        }

        $totals = [
            'paid_value' => (float) $this->scalar("SELECT COALESCE(SUM(i.paid_amount),0)
                FROM invoices i
                LEFT JOIN students s ON s.id = i.student_id
                WHERE {$whereSql}
                  AND i.status = 'paid'", $params),
            'overdue_value' => (float) $this->scalar("SELECT COALESCE(SUM(i.amount - i.paid_amount),0)
                FROM invoices i
                LEFT JOIN students s ON s.id = i.student_id
                WHERE {$whereSql}
                  AND i.status = 'overdue'", $params),
            'pending_value' => (float) $this->scalar("SELECT COALESCE(SUM(i.amount - i.paid_amount),0)
                FROM invoices i
                LEFT JOIN students s ON s.id = i.student_id
                WHERE {$whereSql}
                  AND i.status IN ('open','partial')", $params),
            'settled_today' => (int) $this->scalar("SELECT COUNT(*)
                FROM invoices i
                LEFT JOIN students s ON s.id = i.student_id
                WHERE {$whereSql}
                  AND i.status = 'paid'
                  AND i.paid_at = CURDATE()", $params),
            'nfe_issued' => 0,
            'nfe_pending' => 0,
            'boletos_issued' => 0,
            'boletos_pending' => 0,
        ];

        if ($this->hasFiscalTable()) {
            $totals['nfe_issued'] = (int) $this->scalar("SELECT COUNT(*)
                FROM fiscal_invoices fi
                INNER JOIN invoices i ON i.id = fi.invoice_id
                LEFT JOIN students s ON s.id = i.student_id
                WHERE {$whereSql}
                  AND fi.status = 'issued'", $params);
            $totals['nfe_pending'] = (int) $this->scalar("SELECT COUNT(*)
                FROM fiscal_invoices fi
                INNER JOIN invoices i ON i.id = fi.invoice_id
                LEFT JOIN students s ON s.id = i.student_id
                WHERE {$whereSql}
                  AND fi.status IN ('pending','processing')", $params);
        }

        if ($this->hasBankSlipTable()) {
            $totals['boletos_issued'] = (int) $this->scalar("SELECT COUNT(*)
                FROM bank_slips bs
                INNER JOIN invoices i ON i.id = bs.invoice_id
                LEFT JOIN students s ON s.id = i.student_id
                WHERE {$whereSql}
                  AND bs.status IN ('issued','registered')", $params);
            $totals['boletos_pending'] = (int) $this->scalar("SELECT COUNT(*)
                FROM bank_slips bs
                INNER JOIN invoices i ON i.id = bs.invoice_id
                LEFT JOIN students s ON s.id = i.student_id
                WHERE {$whereSql}
                  AND bs.status IN ('pending','processing')", $params);
        }

        return array_merge($counts, $totals);
    }

    public function fiscalAvailable(): bool
    {
        return $this->hasFiscalTable();
    }

    public function bankSlipAvailable(): bool
    {
        return $this->hasBankSlipTable();
    }

    public function paymentMethodsTableAvailable(): bool
    {
        return $this->paymentMethods->tableExists();
    }

    public function invoicePaymentMethodsAvailable(): bool
    {
        return $this->paymentMethods->tableExists() && $this->paymentMethods->invoiceColumnExists();
    }

    public function paymentsPaymentMethodsAvailable(): bool
    {
        return $this->paymentMethods->tableExists() && $this->paymentMethods->paymentsColumnExists();
    }

    public function paymentMethodsForInvoiceSelection(): array
    {
        $fallback = $this->fallbackPaymentMethods();
        if (!$this->invoicePaymentMethodsAvailable()) {
            return $fallback;
        }

        $companyId = $this->companyId();
        $this->paymentMethods->seedManualDefaults($companyId, (int) (current_user()['id'] ?? 0));
        $rows = $this->paymentMethods->activeByCompany($companyId);
        if ($rows === []) {
            return $fallback;
        }

        return $rows;
    }

    public function studentFinancialPlanFeatureAvailable(): bool
    {
        return $this->students->financialPlanFeatureAvailable();
    }

    public function paymentMethodsForManagement(): array
    {
        if (!$this->paymentMethodsTableAvailable()) {
            return [];
        }

        $companyId = $this->companyId();
        $this->paymentMethods->seedManualDefaults($companyId, (int) (current_user()['id'] ?? 0));
        return $this->paymentMethods->allByCompany($companyId);
    }

    public function findPaymentMethod(int $paymentMethodId): ?array
    {
        if (!$this->paymentMethodsTableAvailable() || $paymentMethodId <= 0) {
            return null;
        }

        return $this->paymentMethods->find($this->companyId(), $paymentMethodId);
    }

    public function paymentMethodFilterOptions(): array
    {
        $fallback = array_map(
            fn (array $item) => (string) ($item['name'] ?? ''),
            $this->fallbackPaymentMethods()
        );

        $options = [];
        foreach ($fallback as $name) {
            $name = trim($name);
            if ($name !== '') {
                $options[$name] = $name;
            }
        }

        if ($this->paymentMethodsTableAvailable()) {
            foreach ($this->paymentMethods->allByCompany($this->companyId()) as $method) {
                $name = trim((string) ($method['name'] ?? ''));
                if ($name !== '') {
                    $options[$name] = $name;
                }
            }
        }

        foreach ($this->paymentMethods->methodNamesFromPayments($this->companyId()) as $name) {
            $name = trim((string) $name);
            if ($name !== '') {
                $options[$name] = $name;
            }
        }

        ksort($options);
        return array_values($options);
    }

    public function resolvePaymentMethodName(?int $paymentMethodId, string $fallback = 'PIX'): string
    {
        $fallback = trim($fallback) !== '' ? trim($fallback) : 'PIX';
        if (!$this->paymentMethodsTableAvailable() || $paymentMethodId === null || $paymentMethodId <= 0) {
            return $fallback;
        }

        $row = $this->paymentMethods->findActive($this->companyId(), $paymentMethodId);
        if (!$row) {
            return $fallback;
        }

        $name = trim((string) ($row['name'] ?? ''));
        return $name !== '' ? $name : $fallback;
    }

    public function listInvoices(array $filters, int $perPage, int $page): array
    {
        $this->refreshOverdueInvoices();
        $hasFiscalTable = $this->hasFiscalTable();
        $hasBankSlipTable = $this->hasBankSlipTable();
        $hasInvoicePaymentMethod = $this->invoicePaymentMethodsAvailable();
        $hasStudentFinancialPlan = $this->studentFinancialPlanFeatureAvailable();

        $where = ['i.company_id = :company_id'];
        $params = [':company_id' => $this->companyId()];

        if (!empty($filters['q'])) {
            $where[] = '(i.invoice_number LIKE :q OR s.full_name LIKE :q OR i.tags LIKE :q OR i.project_name LIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        if (!empty($filters['status'])) {
            $where[] = 'i.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['student_id'])) {
            $where[] = 'i.student_id = :student_id';
            $params[':student_id'] = (int) $filters['student_id'];
        }

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $where[] = 'i.due_date BETWEEN :start_date AND :end_date';
            $params[':start_date'] = (string) $filters['start_date'];
            $params[':end_date'] = (string) $filters['end_date'];
        }

        $whereSql = implode(' AND ', $where);

        $bankJoin = $hasBankSlipTable ? "LEFT JOIN bank_slips bs ON bs.invoice_id = i.id" : "";
        $fiscalJoin = $hasFiscalTable ? "LEFT JOIN fiscal_invoices fi ON fi.invoice_id = i.id" : "";
        $paymentJoin = $hasInvoicePaymentMethod
            ? "LEFT JOIN payment_methods pm ON pm.id = i.payment_method_id AND pm.company_id = i.company_id"
            : "";

        $countSql = "SELECT COUNT(*)
            FROM invoices i
            LEFT JOIN students s ON s.id = i.student_id
            {$bankJoin}
            {$fiscalJoin}
            {$paymentJoin}
            WHERE {$whereSql}";

        $bankFields = $hasBankSlipTable
            ? "bs.id AS boleto_id, bs.provider AS boleto_provider, bs.status AS boleto_status, bs.external_id AS boleto_external_id, bs.digitable_line AS boleto_digitable_line, bs.barcode AS boleto_barcode, bs.pix_copy_paste AS boleto_pix_copy_paste, bs.boleto_url AS bank_slip_url, bs.pdf_url AS boleto_pdf_url, bs.error_message AS boleto_error_message, bs.last_attempt_at AS boleto_last_attempt_at"
            : "NULL AS boleto_id, NULL AS boleto_provider, NULL AS boleto_status, NULL AS boleto_external_id, NULL AS boleto_digitable_line, NULL AS boleto_barcode, NULL AS boleto_pix_copy_paste, NULL AS bank_slip_url, NULL AS boleto_pdf_url, NULL AS boleto_error_message, NULL AS boleto_last_attempt_at";

        $fiscalFields = $hasFiscalTable
            ? "fi.id AS fiscal_id, fi.status AS fiscal_status, fi.number AS fiscal_number, fi.provider AS fiscal_provider, fi.error_message AS fiscal_error_message, fi.last_attempt_at AS fiscal_last_attempt_at"
            : "NULL AS fiscal_id, NULL AS fiscal_status, NULL AS fiscal_number, NULL AS fiscal_provider, NULL AS fiscal_error_message, NULL AS fiscal_last_attempt_at";

        $paymentFields = $hasInvoicePaymentMethod
            ? "pm.id AS payment_method_id, pm.name AS payment_method_name, pm.mode AS payment_method_mode, pm.provider_key AS payment_method_provider_key, pm.channel AS payment_method_channel"
            : "NULL AS payment_method_id, NULL AS payment_method_name, NULL AS payment_method_mode, NULL AS payment_method_provider_key, NULL AS payment_method_channel";
        $studentPlanFields = $hasStudentFinancialPlan
            ? "s.financial_plan_boleto_days_before AS student_boleto_days_before, s.financial_plan_generated_at AS student_financial_plan_generated_at"
            : "NULL AS student_boleto_days_before, NULL AS student_financial_plan_generated_at";

        $dataSql = "SELECT i.*, s.full_name AS student_name, s.phone AS student_phone, s.primary_contact AS student_contact, s.email_primary AS student_email, {$bankFields}, {$fiscalFields}, {$paymentFields}, {$studentPlanFields}
            FROM invoices i
            LEFT JOIN students s ON s.id = i.student_id
            {$bankJoin}
            {$fiscalJoin}
            {$paymentJoin}
            WHERE {$whereSql}
            ORDER BY i.due_date ASC, i.id DESC";

        $result = $this->paginate($countSql, $dataSql, $params, $perPage, $page);
        $result['rows'] = $this->decorateInvoiceRows($result['rows']);
        return $result;
    }

    public function findInvoice(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM invoices WHERE id = :id AND company_id = :company_id LIMIT 1');
        $stmt->execute([
            ':id' => $id,
            ':company_id' => $this->companyId(),
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updateInvoice(int $id, array $data, int $updatedBy): array
    {
        $invoice = $this->findInvoice($id);
        if (!$invoice) {
            return ['ok' => false, 'message' => 'Fatura não encontrada.'];
        }

        $now = now();
        $params = [
            ':id' => $id,
            ':company_id' => $this->companyId(),
            ':student_id' => (int) $data['student_id'],
            ':due_date' => $data['due_date'],
            ':amount' => (float) $data['amount'],
            ':tax_amount' => (float) ($data['tax_amount'] ?? 0),
            ':status' => $data['status'] ?: 'open',
            ':tags' => $data['tags'],
            ':project_name' => $data['project_name'],
            ':boleto_url' => $data['boleto_url'] ?: null,
            ':is_recurring' => !empty($data['is_recurring']) ? 1 : 0,
            ':recurrence_interval' => $data['recurrence_interval'] ?: 'monthly',
            ':updated_at' => $now,
        ];

        if ($this->invoicePaymentMethodsAvailable()) {
            $stmt = $this->db->prepare('UPDATE invoices
                SET student_id = :student_id,
                    payment_method_id = :payment_method_id,
                    due_date = :due_date,
                    amount = :amount,
                    tax_amount = :tax_amount,
                    status = :status,
                    tags = :tags,
                    project_name = :project_name,
                    boleto_url = :boleto_url,
                    is_recurring = :is_recurring,
                    recurrence_interval = :recurrence_interval,
                    updated_at = :updated_at
                WHERE id = :id
                  AND company_id = :company_id');
            $params[':payment_method_id'] = !empty($data['payment_method_id']) ? (int) $data['payment_method_id'] : null;
        } else {
            $stmt = $this->db->prepare('UPDATE invoices
                SET student_id = :student_id,
                    due_date = :due_date,
                    amount = :amount,
                    tax_amount = :tax_amount,
                    status = :status,
                    tags = :tags,
                    project_name = :project_name,
                    boleto_url = :boleto_url,
                    is_recurring = :is_recurring,
                    recurrence_interval = :recurrence_interval,
                    updated_at = :updated_at
                WHERE id = :id
                  AND company_id = :company_id');
        }

        $stmt->execute($params);
        $this->syncLinkedBankSlipAfterInvoiceEdit($invoice, $data, $updatedBy);
        $this->syncStudentFinanceKanban((int) $data['student_id'], $updatedBy);

        if ((int) $invoice['student_id'] !== (int) $data['student_id']) {
            $this->syncStudentFinanceKanban((int) $invoice['student_id'], $updatedBy);
        }

        return ['ok' => true, 'message' => 'Fatura atualizada com sucesso.'];
    }

    public function createInvoice(array $data, int $createdBy): int
    {
        $now = now();

        $params = [
            ':invoice_number' => 'DRAFT',
            ':company_id' => $this->companyId(),
            ':student_id' => (int) $data['student_id'],
            ':due_date' => $data['due_date'],
            ':amount' => (float) $data['amount'],
            ':tax_amount' => (float) ($data['tax_amount'] ?? 0),
            ':paid_at' => null,
            ':status' => $data['status'] ?: 'open',
            ':tags' => $data['tags'],
            ':project_name' => $data['project_name'],
            ':boleto_url' => $data['boleto_url'] ?: null,
            ':is_recurring' => !empty($data['is_recurring']) ? 1 : 0,
            ':recurrence_interval' => $data['recurrence_interval'] ?: 'monthly',
            ':created_by' => $createdBy,
            ':created_at' => $now,
            ':updated_at' => $now,
        ];

        if ($this->invoicePaymentMethodsAvailable()) {
            $stmt = $this->db->prepare('INSERT INTO invoices (
                invoice_number, company_id, student_id, payment_method_id, due_date, amount, tax_amount, paid_amount,
                paid_at,
                status, tags, project_name, boleto_url, is_recurring, recurrence_interval,
                created_by, created_at, updated_at
            ) VALUES (
                :invoice_number, :company_id, :student_id, :payment_method_id, :due_date, :amount, :tax_amount, 0,
                :paid_at,
                :status, :tags, :project_name, :boleto_url, :is_recurring, :recurrence_interval,
                :created_by, :created_at, :updated_at
            )');
            $params[':payment_method_id'] = !empty($data['payment_method_id']) ? (int) $data['payment_method_id'] : null;
        } else {
            $stmt = $this->db->prepare('INSERT INTO invoices (
                invoice_number, company_id, student_id, due_date, amount, tax_amount, paid_amount,
                paid_at,
                status, tags, project_name, boleto_url, is_recurring, recurrence_interval,
                created_by, created_at, updated_at
            ) VALUES (
                :invoice_number, :company_id, :student_id, :due_date, :amount, :tax_amount, 0,
                :paid_at,
                :status, :tags, :project_name, :boleto_url, :is_recurring, :recurrence_interval,
                :created_by, :created_at, :updated_at
            )');
        }

        $stmt->execute($params);

        $id = (int) $this->db->lastInsertId();
        $number = $this->generateInvoiceNumber($id);

        $update = $this->db->prepare('UPDATE invoices
            SET invoice_number = :invoice_number
            WHERE id = :id AND company_id = :company_id');
        $update->execute([
            ':invoice_number' => $number,
            ':id' => $id,
            ':company_id' => $this->companyId(),
        ]);

        return $id;
    }

    public function deleteInvoice(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM invoices WHERE id = :id AND company_id = :company_id');
        $stmt->execute([
            ':id' => $id,
            ':company_id' => $this->companyId(),
        ]);
    }

    public function settleInvoice(int $invoiceId, string $method, string $paidAt, string $notes, int $createdBy, ?int $paymentMethodId = null): array
    {
        $invoice = $this->findInvoice($invoiceId);
        if (!$invoice) {
            return ['ok' => false, 'message' => 'Fatura não encontrada.'];
        }

        $outstanding = max(0, (float) $invoice['amount'] - (float) $invoice['paid_amount']);
        if ($outstanding <= 0) {
            return ['ok' => false, 'message' => 'Esta fatura já esta quitada.'];
        }

        if (($paymentMethodId === null || $paymentMethodId <= 0) && $this->invoicePaymentMethodsAvailable()) {
            $paymentMethodId = (int) ($invoice['payment_method_id'] ?? 0);
        }

        $method = $this->resolvePaymentMethodName($paymentMethodId, $method);

        $description = $notes !== '' ? $notes : 'Baixa manual da fatura ' . $invoice['invoice_number'];
        $paymentId = $this->recordBatchPayment([$invoiceId], $outstanding, $method, $paidAt, $description, $createdBy, $paymentMethodId);

        if ($paymentId <= 0) {
            return ['ok' => false, 'message' => 'Não foi possível efetuar a baixa da fatura.'];
        }

        $this->syncStudentFinanceKanban((int) $invoice['student_id'], $createdBy);
        (new FinanceNotificationModel())
            ->useCompany($this->companyId())
            ->dispatchInvoiceEvent($invoiceId, 'invoice_paid');

        return [
            'ok' => true,
            'payment_id' => $paymentId,
            'message' => 'Baixa realizada com sucesso.',
        ];
    }

    public function applyMobileNegotiationApproval(
        int $studentId,
        float $negotiatedTotal,
        int $installments,
        string $firstDueDate,
        ?int $createdBy,
        array $context = []
    ): array {
        $studentId = (int) $studentId;
        $negotiatedTotal = round((float) $negotiatedTotal, 2);
        $installments = max(1, min(60, (int) $installments));
        $firstDueDate = trim($firstDueDate);

        if ($studentId <= 0) {
            return ['ok' => false, 'message' => 'Aluno inválido para aplicar a negociacao.'];
        }

        if ($negotiatedTotal <= 0) {
            return ['ok' => false, 'message' => 'Valor negociado inválido.'];
        }

        $dueDate = DateTimeImmutable::createFromFormat('Y-m-d', $firstDueDate);
        if (!$dueDate || $dueDate->format('Y-m-d') !== $firstDueDate) {
            return ['ok' => false, 'message' => 'Primeiro vencimento inválido para gerar as parcelas.'];
        }

        $student = $this->students->find($studentId);
        if (!$student || (int) ($student['company_id'] ?? 0) !== $this->companyId()) {
            return ['ok' => false, 'message' => 'Aluno da negociacao não encontrado nesta empresa.'];
        }

        $scope = trim((string) ($context['scope'] ?? 'total'));
        $scope = in_array($scope, ['total', 'overdue'], true) ? $scope : 'total';
        $selectedInvoiceNumbers = array_values(array_unique(array_filter(array_map(
            static fn ($number): string => strtoupper(trim((string) $number)),
            (array) ($context['selected_invoice_numbers'] ?? [])
        ), static fn (string $number): bool => $number !== '')));

        $where = [
            'company_id = :company_id',
            'student_id = :student_id',
            "status IN ('open', 'partial', 'overdue')",
        ];
        $params = [
            ':company_id' => $this->companyId(),
            ':student_id' => $studentId,
        ];

        if ($scope === 'overdue') {
            if ($selectedInvoiceNumbers !== []) {
                $numberPlaceholders = [];
                foreach ($selectedInvoiceNumbers as $idx => $invoiceNumber) {
                    $key = ':invoice_number_' . $idx;
                    $numberPlaceholders[] = $key;
                    $params[$key] = $invoiceNumber;
                }
                $where[] = 'invoice_number IN (' . implode(', ', $numberPlaceholders) . ')';
            } else {
                $where[] = "(status = 'overdue' OR due_date < CURDATE())";
            }
        }

        $openStmt = $this->db->prepare("SELECT id, invoice_number, due_date, amount, paid_amount
            FROM invoices
            WHERE " . implode(' AND ', $where) . "
            ORDER BY due_date ASC, id ASC");
        $openStmt->execute($params);
        $openRows = $openStmt->fetchAll();

        if ($scope === 'overdue' && $selectedInvoiceNumbers !== []) {
            $foundNumbers = array_values(array_unique(array_map(
                static fn (array $row): string => strtoupper(trim((string) ($row['invoice_number'] ?? ''))),
                $openRows
            )));
            $missingNumbers = array_values(array_diff($selectedInvoiceNumbers, $foundNumbers));
            if ($missingNumbers !== []) {
                return [
                    'ok' => false,
                    'message' => 'Não foi possível localizar todas as faturas selecionadas para a negociacao: ' . implode(', ', $missingNumbers) . '.',
                ];
            }
        }

        $openInvoiceIds = [];
        $outstandingTotal = 0.0;
        foreach ($openRows as $row) {
            $remaining = max(0, (float) $row['amount'] - (float) $row['paid_amount']);
            if ($remaining <= 0) {
                continue;
            }
            $openInvoiceIds[] = (int) $row['id'];
            $outstandingTotal += $remaining;
        }
        $outstandingTotal = round($outstandingTotal, 2);

        if ($openInvoiceIds === [] || $outstandingTotal <= 0) {
            return ['ok' => false, 'message' => 'Não existem titulos elegiveis para aplicar esta negociacao.'];
        }

        if ($negotiatedTotal > ($outstandingTotal + 0.01)) {
            return [
                'ok' => false,
                'message' => 'Valor negociado maior que o saldo atual em aberto. Atualize os dados antes de aprovar.',
            ];
        }

        $ticketCode = trim((string) ($context['ticket_code'] ?? ''));
        $ticketId = (int) ($context['ticket_id'] ?? 0);
        $mode = trim((string) ($context['mode'] ?? 'negociacao'));
        $modeLabel = $mode === 'aditivo' ? 'Aditivo' : 'Negociacao';
        $paymentMethodId = $this->invoicePaymentMethodsAvailable()
            ? max(0, (int) ($context['payment_method_id'] ?? 0))
            : 0;

        $renegotiationNotes = trim(implode(' | ', array_filter([
            'Titulos substituidos por renegociacao aprovada no fluxo mobile',
            $ticketCode !== '' ? ('Ticket ' . $ticketCode) : ($ticketId > 0 ? ('Ticket #' . $ticketId) : ''),
            'Aluno: ' . (string) ($student['full_name'] ?? ('ID ' . $studentId)),
            $scope === 'overdue' ? 'Escopo: parcelas selecionadas/vencidas' : 'Escopo: saldo total',
        ])));

        $newInvoiceIds = [];
        $newInvoiceNumbers = [];
        $amounts = $this->splitAmount($negotiatedTotal, $installments);

        try {
            $this->db->beginTransaction();

            $renegotiatedCount = $this->markInvoicesAsRenegotiated(
                $openInvoiceIds,
                $renegotiationNotes,
                $createdBy
            );

            if ($renegotiatedCount !== count($openInvoiceIds)) {
                throw new RuntimeException('Falha ao marcar os titulos antigos como renegociados.');
            }

            foreach ($amounts as $idx => $amount) {
                $due = $dueDate->modify('+' . $idx . ' month')->format('Y-m-d');
                $newId = $this->createInvoice([
                    'student_id' => $studentId,
                    'payment_method_id' => $paymentMethodId > 0 ? $paymentMethodId : null,
                    'due_date' => $due,
                    'amount' => $amount,
                    'tax_amount' => 0,
                    'status' => 'open',
                    'tags' => 'Acordo mobile',
                    'project_name' => $modeLabel . ' financeiro (App Diretoria)'
                        . ($ticketCode !== '' ? (' - ' . $ticketCode) : ($ticketId > 0 ? (' - Ticket #' . $ticketId) : '')),
                    'boleto_url' => '',
                    'is_recurring' => 0,
                    'recurrence_interval' => 'monthly',
                ], $createdBy);

                if ($newId <= 0) {
                    throw new RuntimeException('Falha ao criar parcela da negociacao.');
                }

                $newInvoiceIds[] = $newId;
                $invoice = $this->findInvoice($newId);
                $newInvoiceNumbers[] = (string) ($invoice['invoice_number'] ?? ('#' . $newId));
            }

            $this->syncStudentFinanceKanban($studentId, $createdBy);

            $this->db->commit();

            return [
                'ok' => true,
                'renegotiated_invoices_count' => count($openInvoiceIds),
                'renegotiated_total' => $outstandingTotal,
                'new_invoice_ids' => $newInvoiceIds,
                'new_invoice_numbers' => $newInvoiceNumbers,
                'new_total' => $negotiatedTotal,
                'installments' => $installments,
                'first_due_date' => $firstDueDate,
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[MOBILE_NEGOTIATION_APPROVAL_ERROR] ' . $e->getMessage());

            return ['ok' => false, 'message' => 'Não foi possível aplicar a negociacao no financeiro.'];
        }
    }

    public function generateFiscalInvoice(int $invoiceId, int $createdBy): array
    {
        if (!$this->hasFiscalTable()) {
            return ['ok' => false, 'message' => 'Estrutura fiscal indisponivel no banco. Execute a atualização SQL.'];
        }

        $invoice = $this->findInvoice($invoiceId);
        if (!$invoice) {
            return ['ok' => false, 'message' => 'Fatura não encontrada.'];
        }

        if ($invoice['status'] !== 'paid') {
            return ['ok' => false, 'message' => 'Somente faturas pagas podem gerar nota fiscal.'];
        }

        $student = $this->students->find((int) $invoice['student_id']);
        if (!$student) {
            return ['ok' => false, 'message' => 'Aluno vinculado não encontrado.'];
        }

        $payload = $this->fiscalService->buildPayload($invoice, $student);
        $serviceResult = $this->fiscalService->requestEmission($payload);

        $existing = $this->fiscalRecordByInvoice($invoiceId);
        $status = (string) ($serviceResult['status'] ?? 'pending');
        $provider = $this->fiscalService->provider();
        $now = now();

        if ($existing) {
            $stmt = $this->db->prepare('UPDATE fiscal_invoices SET
                provider = :provider,
                status = :status,
                external_id = :external_id,
                number = :number,
                request_payload = :request_payload,
                response_payload = :response_payload,
                error_message = :error_message,
                last_attempt_at = :last_attempt_at,
                issued_at = :issued_at,
                updated_at = :updated_at
                WHERE id = :id');

            $params = [
                ':provider' => $provider,
                ':status' => $status,
                ':external_id' => $serviceResult['external_id'] ?? null,
                ':number' => $serviceResult['number'] ?? null,
                ':request_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                ':response_payload' => isset($serviceResult['response_payload']) ? json_encode($serviceResult['response_payload'], JSON_UNESCAPED_UNICODE) : null,
                ':error_message' => ($serviceResult['message'] ?? null),
                ':last_attempt_at' => $now,
                ':issued_at' => $status === 'issued' ? $now : null,
                ':updated_at' => $now,
                ':id' => (int) $existing['id'],
            ];
            $stmt->execute($params);
        } else {
            $stmt = $this->db->prepare('INSERT INTO fiscal_invoices (
                invoice_id, provider, status, external_id, number,
                request_payload, response_payload, error_message, last_attempt_at,
                issued_at, created_by, created_at, updated_at
            ) VALUES (
                :invoice_id, :provider, :status, :external_id, :number,
                :request_payload, :response_payload, :error_message, :last_attempt_at,
                :issued_at, :created_by, :created_at, :updated_at
            )');

            $stmt->execute([
                ':invoice_id' => $invoiceId,
                ':provider' => $provider,
                ':status' => $status,
                ':external_id' => $serviceResult['external_id'] ?? null,
                ':number' => $serviceResult['number'] ?? null,
                ':request_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                ':response_payload' => isset($serviceResult['response_payload']) ? json_encode($serviceResult['response_payload'], JSON_UNESCAPED_UNICODE) : null,
                ':error_message' => ($serviceResult['message'] ?? null),
                ':last_attempt_at' => $now,
                ':issued_at' => $status === 'issued' ? $now : null,
                ':created_by' => $createdBy > 0 ? $createdBy : null,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        }

        return [
            'ok' => true,
            'status' => $status,
            'message' => $serviceResult['message'] ?? 'Solicitacao de nota fiscal registrada.',
        ];
    }

    public function generateBankSlip(int $invoiceId, int $createdBy): array
    {
        if (!$this->hasBankSlipTable()) {
            return ['ok' => false, 'message' => 'Estrutura de boleto indisponivel no banco. Execute a atualização SQL.'];
        }

        $invoice = $this->findInvoice($invoiceId);
        if (!$invoice) {
            return ['ok' => false, 'message' => 'Fatura não encontrada.'];
        }

        if ($invoice['status'] === 'paid') {
            return ['ok' => false, 'message' => 'Fatura paga não permite gerar novo boleto.'];
        }

        $student = $this->students->find((int) $invoice['student_id']);
        if (!$student) {
            return ['ok' => false, 'message' => 'Aluno vinculado não encontrado.'];
        }

        $paymentMethod = $this->resolveInvoicePaymentMethod($invoice);
        $service = $this->resolveBankSlipService($paymentMethod);
        $existing = $this->bankSlipByInvoice($invoiceId);
        $payload = $service->buildPayload($invoice, $student, $existing);
        $serviceResult = $service->requestGeneration($payload, $existing);

        $status = (string) ($serviceResult['status'] ?? 'pending');
        $provider = $service->provider();
        $now = now();
        $hasNossoNumero = $this->bankSlipNossoNumeroColumnAvailable();

        $url = (string) ($serviceResult['boleto_url'] ?? '');
        $pdf = (string) ($serviceResult['pdf_url'] ?? '');
        $chosenUrl = $url !== '' ? $url : ($pdf !== '' ? $pdf : ((string) ($invoice['boleto_url'] ?? '')));
        $chosenUrl = $chosenUrl !== '' ? $chosenUrl : null;

        if ($existing) {
            $stmt = $this->db->prepare('UPDATE bank_slips SET
                provider = :provider,
                status = :status,
                external_id = :external_id,
                ' . ($hasNossoNumero ? 'nosso_numero = :nosso_numero,' : '') . '
                digitable_line = :digitable_line,
                barcode = :barcode,
                pix_qr_code = :pix_qr_code,
                pix_copy_paste = :pix_copy_paste,
                boleto_url = :boleto_url,
                pdf_url = :pdf_url,
                amount = :amount,
                due_date = :due_date,
                request_payload = :request_payload,
                response_payload = :response_payload,
                error_message = :error_message,
                last_attempt_at = :last_attempt_at,
                issued_at = :issued_at,
                expires_at = :expires_at,
                updated_at = :updated_at
                WHERE id = :id');

            $params = [
                ':provider' => $provider,
                ':status' => $status,
                ':external_id' => $serviceResult['external_id'] ?? null,
                ':digitable_line' => $serviceResult['digitable_line'] ?? null,
                ':barcode' => $serviceResult['barcode'] ?? null,
                ':pix_qr_code' => $serviceResult['pix_qr_code'] ?? null,
                ':pix_copy_paste' => $serviceResult['pix_copy_paste'] ?? null,
                ':boleto_url' => $url !== '' ? $url : ($existing['boleto_url'] ?? null),
                ':pdf_url' => $pdf !== '' ? $pdf : ($existing['pdf_url'] ?? null),
                ':amount' => (float) $invoice['amount'],
                ':due_date' => $invoice['due_date'],
                ':request_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                ':response_payload' => isset($serviceResult['response_payload']) ? json_encode($serviceResult['response_payload'], JSON_UNESCAPED_UNICODE) : null,
                ':error_message' => ($serviceResult['message'] ?? null),
                ':last_attempt_at' => $now,
                ':issued_at' => in_array($status, ['issued', 'registered'], true) ? $now : ($existing['issued_at'] ?? null),
                ':expires_at' => $serviceResult['expires_at'] ?? null,
                ':updated_at' => $now,
                ':id' => (int) $existing['id'],
            ];
            if ($hasNossoNumero) {
                $params[':nosso_numero'] = $serviceResult['nosso_numero'] ?? ($existing['nosso_numero'] ?? null);
            }
            $stmt->execute($params);
        } else {
            $stmt = $this->db->prepare('INSERT INTO bank_slips (
                invoice_id, provider, status, external_id, ' . ($hasNossoNumero ? 'nosso_numero, ' : '') . 'digitable_line, barcode,
                pix_qr_code, pix_copy_paste, boleto_url, pdf_url, amount, due_date,
                request_payload, response_payload, error_message, last_attempt_at,
                issued_at, expires_at, created_by, created_at, updated_at
            ) VALUES (
                :invoice_id, :provider, :status, :external_id, ' . ($hasNossoNumero ? ':nosso_numero, ' : '') . ':digitable_line, :barcode,
                :pix_qr_code, :pix_copy_paste, :boleto_url, :pdf_url, :amount, :due_date,
                :request_payload, :response_payload, :error_message, :last_attempt_at,
                :issued_at, :expires_at, :created_by, :created_at, :updated_at
            )');

            $params = [
                ':invoice_id' => $invoiceId,
                ':provider' => $provider,
                ':status' => $status,
                ':external_id' => $serviceResult['external_id'] ?? null,
                ':digitable_line' => $serviceResult['digitable_line'] ?? null,
                ':barcode' => $serviceResult['barcode'] ?? null,
                ':pix_qr_code' => $serviceResult['pix_qr_code'] ?? null,
                ':pix_copy_paste' => $serviceResult['pix_copy_paste'] ?? null,
                ':boleto_url' => $url !== '' ? $url : null,
                ':pdf_url' => $pdf !== '' ? $pdf : null,
                ':amount' => (float) $invoice['amount'],
                ':due_date' => $invoice['due_date'],
                ':request_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                ':response_payload' => isset($serviceResult['response_payload']) ? json_encode($serviceResult['response_payload'], JSON_UNESCAPED_UNICODE) : null,
                ':error_message' => ($serviceResult['message'] ?? null),
                ':last_attempt_at' => $now,
                ':issued_at' => in_array($status, ['issued', 'registered'], true) ? $now : null,
                ':expires_at' => $serviceResult['expires_at'] ?? null,
                ':created_by' => $createdBy > 0 ? $createdBy : null,
                ':created_at' => $now,
                ':updated_at' => $now,
            ];
            if ($hasNossoNumero) {
                $params[':nosso_numero'] = $serviceResult['nosso_numero'] ?? null;
            }
            $stmt->execute($params);
        }

        if ($chosenUrl) {
            $upInvoice = $this->db->prepare('UPDATE invoices
                SET boleto_url = :boleto_url, updated_at = :updated_at
                WHERE id = :id AND company_id = :company_id');
            $upInvoice->execute([
                ':boleto_url' => $chosenUrl,
                ':updated_at' => now(),
                ':id' => $invoiceId,
                ':company_id' => $this->companyId(),
            ]);
        }

        if (in_array($status, ['issued', 'registered'], true)) {
            (new FinanceNotificationModel())
                ->useCompany($this->companyId())
                ->dispatchInvoiceEvent($invoiceId, 'invoice_issued');
        }

        return [
            'ok' => true,
            'status' => $status,
            'message' => $serviceResult['message'] ?? 'Solicitacao de boleto registrada.',
        ];
    }

    public function syncBankSlipStatus(int $invoiceId, int $changedBy): array
    {
        if (!$this->hasBankSlipTable()) {
            return ['ok' => false, 'message' => 'Estrutura de boleto indisponivel no banco. Execute a atualização SQL.'];
        }

        $record = $this->bankSlipByInvoice($invoiceId);
        if (!$record) {
            return ['ok' => false, 'message' => 'Boleto ainda não foi gerado para esta fatura.'];
        }

        $invoice = $this->findInvoice($invoiceId);
        if (!$invoice) {
            return ['ok' => false, 'message' => 'Fatura não encontrada para sincronizar boleto.'];
        }

        $paymentMethod = $this->resolveInvoicePaymentMethod($invoice);
        $service = $this->resolveBankSlipService($paymentMethod, $record);
        $serviceResult = $service->requestStatus($record);
        $status = (string) ($serviceResult['status'] ?? $record['status']);
        $now = now();
        $hasNossoNumero = $this->bankSlipNossoNumeroColumnAvailable();

        $stmt = $this->db->prepare('UPDATE bank_slips SET
            status = :status,
            external_id = :external_id,
            ' . ($hasNossoNumero ? 'nosso_numero = :nosso_numero,' : '') . '
            digitable_line = :digitable_line,
            barcode = :barcode,
            pix_qr_code = :pix_qr_code,
            pix_copy_paste = :pix_copy_paste,
            boleto_url = :boleto_url,
            pdf_url = :pdf_url,
            response_payload = :response_payload,
            error_message = :error_message,
            last_attempt_at = :last_attempt_at,
            issued_at = :issued_at,
            paid_at = :paid_at,
            expires_at = :expires_at,
            updated_at = :updated_at
            WHERE id = :id');

        $params = [
            ':status' => $status,
            ':external_id' => $serviceResult['external_id'] ?? $record['external_id'],
            ':digitable_line' => $serviceResult['digitable_line'] ?? $record['digitable_line'],
            ':barcode' => $serviceResult['barcode'] ?? $record['barcode'],
            ':pix_qr_code' => $serviceResult['pix_qr_code'] ?? $record['pix_qr_code'],
            ':pix_copy_paste' => $serviceResult['pix_copy_paste'] ?? $record['pix_copy_paste'],
            ':boleto_url' => $serviceResult['boleto_url'] ?? $record['boleto_url'],
            ':pdf_url' => $serviceResult['pdf_url'] ?? $record['pdf_url'],
            ':response_payload' => isset($serviceResult['response_payload']) ? json_encode($serviceResult['response_payload'], JSON_UNESCAPED_UNICODE) : null,
            ':error_message' => ($serviceResult['message'] ?? $record['error_message']),
            ':last_attempt_at' => $now,
            ':issued_at' => in_array($status, ['issued', 'registered'], true) ? ($record['issued_at'] ?: $now) : $record['issued_at'],
            ':paid_at' => in_array($status, ['paid', 'received'], true) ? ($serviceResult['paid_at'] ?? date('Y-m-d')) : $record['paid_at'],
            ':expires_at' => $serviceResult['expires_at'] ?? $record['expires_at'],
            ':updated_at' => $now,
            ':id' => (int) $record['id'],
        ];
        if ($hasNossoNumero) {
            $params[':nosso_numero'] = $serviceResult['nosso_numero'] ?? ($record['nosso_numero'] ?? null);
        }
        $stmt->execute($params);

        $paymentApplied = false;
        if ($invoice && in_array($status, ['paid', 'received'], true) && (float) $invoice['paid_amount'] < (float) $invoice['amount']) {
            $outstanding = max(0, (float) $invoice['amount'] - (float) $invoice['paid_amount']);
            $paidAmount = isset($serviceResult['paid_amount']) ? (float) $serviceResult['paid_amount'] : $outstanding;
            $paidAmount = min(max(0, $paidAmount), $outstanding);

            if ($paidAmount > 0) {
                $paymentMethodId = $this->invoicePaymentMethodsAvailable()
                    ? (int) ($invoice['payment_method_id'] ?? 0)
                    : null;
                $methodName = $this->resolvePaymentMethodName($paymentMethodId, 'Boleto');
                $this->recordBatchPayment(
                    [$invoiceId],
                    $paidAmount,
                    $methodName,
                    (string) ($serviceResult['paid_at'] ?? date('Y-m-d')),
                    'Baixa automatica por sincronizacao de boleto API.',
                    $changedBy,
                    $paymentMethodId
                );
                $paymentApplied = true;
            }
        }

        if (in_array($status, ['paid', 'received'], true)) {
            $refreshedInvoice = $paymentApplied ? $this->findInvoice($invoiceId) : $invoice;
            if ($refreshedInvoice && (float) $refreshedInvoice['paid_amount'] >= (float) $refreshedInvoice['amount']) {
                (new FinanceNotificationModel())
                    ->useCompany($this->companyId())
                    ->dispatchInvoiceEvent($invoiceId, 'invoice_paid');
            }
        }

        $message = $serviceResult['message'] ?? ('Status do boleto atualizado para ' . $status . '.');
        return [
            'ok' => true,
            'status' => $status,
            'message' => $message,
        ];
    }

    public function processItauWebhook(array $payload, ?int $companyId = null): array
    {
        if (!$this->hasBankSlipTable()) {
            return ['ok' => false, 'message' => 'Estrutura de boleto indisponivel no banco.'];
        }

        if ($companyId !== null && $companyId > 0) {
            $this->useCompany($companyId);
        }

        $identifiers = $this->itauWebhookIdentifiers($payload);
        if ($identifiers === []) {
            return ['ok' => false, 'message' => 'Webhook Itau sem identificador de boleto reconhecido.'];
        }

        $record = $this->bankSlipByItauIdentifiers($identifiers, $companyId);
        if (!$record) {
            return ['ok' => false, 'message' => 'Boleto não encontrado para o webhook Itau.'];
        }

        $companyId = (int) ($record['company_id'] ?? $companyId ?? 0);
        if ($companyId > 0) {
            $this->useCompany($companyId);
        }

        $invoiceId = (int) ($record['invoice_id'] ?? 0);
        $invoice = $this->findInvoice($invoiceId);
        if (!$invoice) {
            return ['ok' => false, 'message' => 'Fatura do boleto não encontrada.'];
        }

        $status = $this->normalizeItauWebhookStatus($payload, (string) ($record['status'] ?? 'pending'));
        $paidAt = $this->normalizeItauWebhookDate($this->extractFromPayload($payload, [
            'data_pagamento',
            'dataPagamento',
            'data_liquidacao',
            'dataLiquidacao',
            'paid_at',
        ]));
        $paidAmount = $this->normalizeItauWebhookAmount($this->extractFromPayload($payload, [
            'valor_pago',
            'valorPago',
            'valor_liquidado',
            'valorLiquidado',
            'valor_titulo',
            'valorTitulo',
            'amount',
        ]));

        $now = now();
        $hasNossoNumero = $this->bankSlipNossoNumeroColumnAvailable();
        $stmt = $this->db->prepare('UPDATE bank_slips SET
            status = :status,
            external_id = :external_id,
            ' . ($hasNossoNumero ? 'nosso_numero = :nosso_numero,' : '') . '
            response_payload = :response_payload,
            error_message = :error_message,
            last_attempt_at = :last_attempt_at,
            issued_at = :issued_at,
            paid_at = :paid_at,
            updated_at = :updated_at
            WHERE id = :id');

        $params = [
            ':status' => $status,
            ':external_id' => $identifiers['external_id'] ?? $record['external_id'],
            ':response_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':error_message' => 'Status atualizado por webhook Itau.',
            ':last_attempt_at' => $now,
            ':issued_at' => in_array($status, ['issued', 'registered'], true) ? ($record['issued_at'] ?: $now) : $record['issued_at'],
            ':paid_at' => in_array($status, ['paid', 'received'], true) ? ($paidAt ?: date('Y-m-d')) : $record['paid_at'],
            ':updated_at' => $now,
            ':id' => (int) $record['id'],
        ];
        if ($hasNossoNumero) {
            $params[':nosso_numero'] = $identifiers['nosso_numero'] ?? ($record['nosso_numero'] ?? null);
        }
        $stmt->execute($params);

        $paymentApplied = false;
        if (in_array($status, ['paid', 'received'], true) && (float) $invoice['paid_amount'] < (float) $invoice['amount']) {
            $outstanding = max(0, (float) $invoice['amount'] - (float) $invoice['paid_amount']);
            $amountToApply = $paidAmount !== null ? $paidAmount : $outstanding;
            $amountToApply = min(max(0, $amountToApply), $outstanding);

            if ($amountToApply > 0) {
                $paymentMethodId = $this->invoicePaymentMethodsAvailable()
                    ? (int) ($invoice['payment_method_id'] ?? 0)
                    : null;
                $methodName = $this->resolvePaymentMethodName($paymentMethodId, 'Boleto Itau');
                $this->recordBatchPayment(
                    [$invoiceId],
                    $amountToApply,
                    $methodName,
                    $paidAt ?: date('Y-m-d'),
                    'Baixa automatica por webhook Itau.',
                    0,
                    $paymentMethodId
                );
                $paymentApplied = true;
            }
        }

        if (in_array($status, ['paid', 'received'], true)) {
            $refreshedInvoice = $paymentApplied ? $this->findInvoice($invoiceId) : $invoice;
            if ($refreshedInvoice && (float) $refreshedInvoice['paid_amount'] >= (float) $refreshedInvoice['amount']) {
                (new FinanceNotificationModel())
                    ->useCompany($this->companyId())
                    ->dispatchInvoiceEvent($invoiceId, 'invoice_paid');
            }
        }

        return [
            'ok' => true,
            'message' => 'Webhook Itau processado com sucesso.',
            'status' => $status,
            'invoice_id' => $invoiceId,
            'payment_applied' => $paymentApplied,
        ];
    }

    public function reportOverview(array $filters): array
    {
        $startDate = $filters['start_date'];
        $endDate = $filters['end_date'];
        $companyId = $this->companyId();
        $hasPayables = $this->schemaTableExists('payables') && $this->schemaTableExists('payable_payments');

        $invoiceWhere = [
            'i.company_id = :invoice_company_id',
            'i.due_date BETWEEN :start_date AND :end_date',
        ];
        $invoiceParams = [
            ':invoice_company_id' => $companyId,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
        ];
        $invoiceJoinSql = '';

        if (!empty($filters['student_id'])) {
            $invoiceWhere[] = 'i.student_id = :student_id';
            $invoiceParams[':student_id'] = (int) $filters['student_id'];
        }

        if (!empty($filters['status'])) {
            $invoiceWhere[] = 'i.status = :invoice_status';
            $invoiceParams[':invoice_status'] = $filters['status'];
        }

        if (!empty($filters['method']) && $this->invoicePaymentMethodsAvailable()) {
            $invoiceJoinSql = 'LEFT JOIN payment_methods pm_invoice ON pm_invoice.id = i.payment_method_id AND pm_invoice.company_id = i.company_id';
            $invoiceWhere[] = 'COALESCE(pm_invoice.name, "") = :invoice_method';
            $invoiceParams[':invoice_method'] = $filters['method'];
        }

        $invoiceWhereSql = implode(' AND ', $invoiceWhere);

        $totalInvoiced = (float) $this->scalar(
            "SELECT COALESCE(SUM(i.amount), 0)
             FROM invoices i
             {$invoiceJoinSql}
             WHERE {$invoiceWhereSql}",
            $invoiceParams
        );

        $pendingValue = (float) $this->scalar(
            "SELECT COALESCE(SUM(i.amount - i.paid_amount), 0)
             FROM invoices i
             {$invoiceJoinSql}
             WHERE {$invoiceWhereSql} AND i.status IN ('open','partial','overdue')",
            $invoiceParams
        );

        $overdueValue = (float) $this->scalar(
            "SELECT COALESCE(SUM(i.amount - i.paid_amount), 0)
             FROM invoices i
             {$invoiceJoinSql}
             WHERE {$invoiceWhereSql} AND i.status = 'overdue'",
            $invoiceParams
        );

        $settledWhere = [
            'i.company_id = :settled_company_id',
            'i.paid_at BETWEEN :settled_start_date AND :settled_end_date',
        ];
        $settledParams = [
            ':settled_company_id' => $companyId,
            ':settled_start_date' => $startDate,
            ':settled_end_date' => $endDate,
        ];
        $settledJoinSql = '';

        if (!empty($filters['student_id'])) {
            $settledWhere[] = 'i.student_id = :settled_student_id';
            $settledParams[':settled_student_id'] = (int) $filters['student_id'];
        }

        if (!empty($filters['status'])) {
            $settledWhere[] = 'i.status = :settled_status';
            $settledParams[':settled_status'] = $filters['status'];
        } else {
            $settledWhere[] = "i.status = 'paid'";
        }

        if (!empty($filters['method']) && $this->invoicePaymentMethodsAvailable()) {
            $settledJoinSql = 'LEFT JOIN payment_methods pm_settled ON pm_settled.id = i.payment_method_id AND pm_settled.company_id = i.company_id';
            $settledWhere[] = 'COALESCE(pm_settled.name, "") = :settled_method';
            $settledParams[':settled_method'] = $filters['method'];
        }

        $settledCount = (int) $this->scalar(
            "SELECT COUNT(*)
             FROM invoices i
             {$settledJoinSql}
             WHERE " . implode(' AND ', $settledWhere),
            $settledParams
        );

        $receivedWhere = [
            'i.company_id = :rc_company_id',
            'p.paid_at BETWEEN :rc_start_date AND :rc_end_date',
        ];
        $receivedParams = [
            ':rc_company_id' => $companyId,
            ':rc_start_date' => $startDate,
            ':rc_end_date' => $endDate,
        ];

        if (!empty($filters['method'])) {
            $receivedWhere[] = 'p.method = :payment_method';
            $receivedParams[':payment_method'] = $filters['method'];
        }

        if (!empty($filters['student_id'])) {
            $receivedWhere[] = 'i.student_id = :rc_student_id';
            $receivedParams[':rc_student_id'] = (int) $filters['student_id'];
        }

        if (!empty($filters['status'])) {
            $receivedWhere[] = 'i.status = :rc_invoice_status';
            $receivedParams[':rc_invoice_status'] = $filters['status'];
        }

        $totalReceived = (float) $this->scalar(
            "SELECT COALESCE(SUM(pi.amount), 0)
             FROM payments p
             INNER JOIN payment_items pi ON pi.payment_id = p.id
             INNER JOIN invoices i ON i.id = pi.invoice_id
             WHERE " . implode(' AND ', $receivedWhere),
            $receivedParams
        );

        $labels = $this->buildDateLabels($startDate, $endDate);
        $invoicedSeries = array_fill(0, count($labels), 0.0);
        $receivedSeries = array_fill(0, count($labels), 0.0);
        $labelIndex = array_flip($labels);

        $dailyInvoicedStmt = $this->db->prepare(
            "SELECT i.due_date AS ref_date, COALESCE(SUM(i.amount), 0) AS total
             FROM invoices i
             {$invoiceJoinSql}
             WHERE {$invoiceWhereSql}
             GROUP BY i.due_date
             ORDER BY i.due_date ASC"
        );
        $dailyInvoicedStmt->execute($invoiceParams);
        foreach ($dailyInvoicedStmt->fetchAll() as $row) {
            $date = (string) $row['ref_date'];
            if (isset($labelIndex[$date])) {
                $invoicedSeries[$labelIndex[$date]] = (float) $row['total'];
            }
        }

        $dailyReceivedStmt = $this->db->prepare(
            "SELECT p.paid_at AS ref_date, COALESCE(SUM(pi.amount), 0) AS total
             FROM payments p
             INNER JOIN payment_items pi ON pi.payment_id = p.id
             INNER JOIN invoices i ON i.id = pi.invoice_id
             WHERE " . implode(' AND ', $receivedWhere) . "
             GROUP BY p.paid_at
             ORDER BY p.paid_at ASC"
        );
        $dailyReceivedStmt->execute($receivedParams);
        foreach ($dailyReceivedStmt->fetchAll() as $row) {
            $date = (string) $row['ref_date'];
            if (isset($labelIndex[$date])) {
                $receivedSeries[$labelIndex[$date]] = (float) $row['total'];
            }
        }

        $nfe = [
            'issued' => 0,
            'pending' => 0,
            'failed' => 0,
        ];

        if ($this->hasFiscalTable()) {
            $nfeWhere = [
                'i.company_id = :nfe_company_id',
                "DATE(COALESCE(fi.last_attempt_at, fi.created_at)) BETWEEN :nfe_start_date AND :nfe_end_date",
            ];
            $nfeParams = [
                ':nfe_company_id' => $companyId,
                ':nfe_start_date' => $startDate,
                ':nfe_end_date' => $endDate,
            ];

            if (!empty($filters['student_id'])) {
                $nfeWhere[] = 'i.student_id = :nfe_student_id';
                $nfeParams[':nfe_student_id'] = (int) $filters['student_id'];
            }

            if (!empty($filters['status'])) {
                $nfeWhere[] = 'i.status = :nfe_invoice_status';
                $nfeParams[':nfe_invoice_status'] = $filters['status'];
            }

            if (!empty($filters['method']) && $this->invoicePaymentMethodsAvailable()) {
                $nfeWhere[] = 'COALESCE(pm_invoice.name, "") = :nfe_method';
                $nfeParams[':nfe_method'] = $filters['method'];
            }

            $stmt = $this->db->prepare(
                "SELECT fi.status, COUNT(*) AS qty
                 FROM fiscal_invoices fi
                 INNER JOIN invoices i ON i.id = fi.invoice_id
                 {$invoiceJoinSql}
                 WHERE " . implode(' AND ', $nfeWhere) . "
                 GROUP BY fi.status"
            );
            $stmt->execute($nfeParams);

            foreach ($stmt->fetchAll() as $row) {
                $status = (string) $row['status'];
                if ($status === 'issued') {
                    $nfe['issued'] = (int) $row['qty'];
                } elseif (in_array($status, ['pending', 'processing'], true)) {
                    $nfe['pending'] += (int) $row['qty'];
                } elseif ($status === 'failed') {
                    $nfe['failed'] = (int) $row['qty'];
                }
            }
        }

        $inadimplenciaPercent = $totalInvoiced > 0 ? (($overdueValue / $totalInvoiced) * 100) : 0.0;
        $totalOutgoing = 0.0;
        $payablesOpenValue = 0.0;
        $payablesOverdueValue = 0.0;

        if ($hasPayables) {
            $outgoingWhere = [
                'pp.company_id = :pp_company_id',
                'pp.paid_at BETWEEN :pp_start_date AND :pp_end_date',
            ];
            $outgoingParams = [
                ':pp_company_id' => $companyId,
                ':pp_start_date' => $startDate,
                ':pp_end_date' => $endDate,
            ];

            if (!empty($filters['method'])) {
                $outgoingWhere[] = 'COALESCE(pm.name, "") = :pp_method';
                $outgoingParams[':pp_method'] = $filters['method'];
            }

            if (!empty($filters['status'])) {
                $outgoingWhere[] = 'p.status = :pp_status';
                $outgoingParams[':pp_status'] = $filters['status'];
            }

            if (!empty($filters['supplier_id'])) {
                $outgoingWhere[] = 'p.supplier_id = :pp_supplier_id';
                $outgoingParams[':pp_supplier_id'] = (int) $filters['supplier_id'];
            }

            $totalOutgoing = (float) $this->scalar(
                "SELECT COALESCE(SUM(pp.amount), 0)
                 FROM payable_payments pp
                 INNER JOIN payables p ON p.id = pp.payable_id
                 LEFT JOIN payment_methods pm ON pm.id = pp.payment_method_id
                 WHERE " . implode(' AND ', $outgoingWhere),
                $outgoingParams
            );

            $payableWhere = [
                'p.company_id = :pay_company_id',
                'p.due_date BETWEEN :pay_start_date AND :pay_end_date',
            ];
            $payableParams = [
                ':pay_company_id' => $companyId,
                ':pay_start_date' => $startDate,
                ':pay_end_date' => $endDate,
            ];

            if (!empty($filters['status'])) {
                $payableWhere[] = 'p.status = :pay_status';
                $payableParams[':pay_status'] = $filters['status'];
            }

            if (!empty($filters['supplier_id'])) {
                $payableWhere[] = 'p.supplier_id = :pay_supplier_id';
                $payableParams[':pay_supplier_id'] = (int) $filters['supplier_id'];
            }

            $payablesOpenValue = (float) $this->scalar(
                "SELECT COALESCE(SUM(p.amount - p.paid_amount), 0)
                 FROM payables p
                 WHERE " . implode(' AND ', array_merge($payableWhere, ["p.status IN ('open','partial')"])),
                $payableParams
            );

            $payablesOverdueValue = (float) $this->scalar(
                "SELECT COALESCE(SUM(p.amount - p.paid_amount), 0)
                 FROM payables p
                 WHERE " . implode(' AND ', array_merge($payableWhere, ["p.status = 'overdue'"])),
                $payableParams
            );
        }

        return [
            'cards' => [
                'total_invoiced' => $totalInvoiced,
                'total_received' => $totalReceived,
                'pending_value' => $pendingValue,
                'overdue_value' => $overdueValue,
                'settled_count' => $settledCount,
                'inadimplencia_percent' => $inadimplenciaPercent,
                'total_outgoing' => $totalOutgoing,
                'net_cash' => $totalReceived - $totalOutgoing,
                'payables_open_value' => $payablesOpenValue,
                'payables_overdue_value' => $payablesOverdueValue,
            ],
            'nfe' => $nfe,
            'chart' => [
                'labels' => $labels,
                'invoiced' => $invoicedSeries,
                'received' => $receivedSeries,
            ],
        ];
    }

    public function reportReceipts(array $filters, int $perPage, int $page): array
    {
        $where = [
            'i.company_id = :company_id',
            'p.paid_at BETWEEN :start_date AND :end_date',
        ];
        $params = [
            ':company_id' => $this->companyId(),
            ':start_date' => $filters['start_date'],
            ':end_date' => $filters['end_date'],
        ];

        if (!empty($filters['method'])) {
            $where[] = 'p.method = :method';
            $params[':method'] = $filters['method'];
        }

        if (!empty($filters['student_id'])) {
            $where[] = 'i.student_id = :student_id';
            $params[':student_id'] = (int) $filters['student_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'i.status = :invoice_status';
            $params[':invoice_status'] = $filters['status'];
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) FROM (
                SELECT p.id
                FROM payments p
                INNER JOIN payment_items pi ON pi.payment_id = p.id
                INNER JOIN invoices i ON i.id = pi.invoice_id
                WHERE {$whereSql}
                GROUP BY p.id
            ) t";

        $dataSql = "SELECT
                p.id,
                p.payment_ref,
                p.method,
                p.paid_at,
                p.amount,
                p.notes,
                COALESCE(SUM(pi.amount), 0) AS applied_amount,
                GROUP_CONCAT(DISTINCT i.invoice_number ORDER BY i.invoice_number SEPARATOR ', ') AS invoices,
                GROUP_CONCAT(DISTINCT s.full_name ORDER BY s.full_name SEPARATOR ', ') AS students
            FROM payments p
            INNER JOIN payment_items pi ON pi.payment_id = p.id
            INNER JOIN invoices i ON i.id = pi.invoice_id
            LEFT JOIN students s ON s.id = i.student_id
            WHERE {$whereSql}
            GROUP BY p.id
            ORDER BY p.paid_at DESC, p.id DESC";

        return $this->paginate($countSql, $dataSql, $params, $perPage, $page);
    }

    public function reportReceivables(array $filters, int $perPage, int $page): array
    {
        $hasFiscalTable = $this->hasFiscalTable();
        $hasInvoicePaymentMethod = $this->invoicePaymentMethodsAvailable();
        $where = [
            'i.company_id = :company_id',
            'i.due_date BETWEEN :start_date AND :end_date',
        ];
        $params = [
            ':company_id' => $this->companyId(),
            ':start_date' => $filters['start_date'],
            ':end_date' => $filters['end_date'],
        ];

        if (!empty($filters['student_id'])) {
            $where[] = 'i.student_id = :student_id';
            $params[':student_id'] = (int) $filters['student_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'i.status = :invoice_status';
            $params[':invoice_status'] = $filters['status'];
        }

        if (!empty($filters['method']) && $hasInvoicePaymentMethod) {
            $where[] = 'COALESCE(pm.name, "") = :method';
            $params[':method'] = $filters['method'];
        }

        $whereSql = implode(' AND ', $where);

        $fiscalJoin = $hasFiscalTable ? "LEFT JOIN fiscal_invoices fi ON fi.invoice_id = i.id" : "";
        $fiscalFields = $hasFiscalTable
            ? "fi.status AS fiscal_status, fi.number AS fiscal_number"
            : "NULL AS fiscal_status, NULL AS fiscal_number";
        $paymentJoin = $hasInvoicePaymentMethod
            ? "LEFT JOIN payment_methods pm ON pm.id = i.payment_method_id AND pm.company_id = i.company_id"
            : "";

        $countSql = "SELECT COUNT(*)
            FROM invoices i
            INNER JOIN students s ON s.id = i.student_id
            {$fiscalJoin}
            {$paymentJoin}
            WHERE {$whereSql}";

        $dataSql = "SELECT
                i.id,
                i.invoice_number,
                i.due_date,
                i.amount,
                i.paid_amount,
                i.paid_at,
                i.status,
                i.tags,
                (i.amount - i.paid_amount) AS outstanding_amount,
                GREATEST(DATEDIFF(CURDATE(), i.due_date), 0) AS days_overdue,
                s.full_name AS student_name,
                {$fiscalFields}
            FROM invoices i
            INNER JOIN students s ON s.id = i.student_id
            {$fiscalJoin}
            {$paymentJoin}
            WHERE {$whereSql}
            ORDER BY i.due_date ASC, i.id DESC";

        $result = $this->paginate($countSql, $dataSql, $params, $perPage, $page);
        $result['rows'] = $this->decorateInvoiceRows($result['rows']);
        return $result;
    }

    public function reportAging(array $filters): array
    {
        $where = [
            'i.company_id = :company_id',
            '(i.amount - i.paid_amount) > 0',
            'i.due_date BETWEEN :start_date AND :end_date',
        ];
        $params = [
            ':company_id' => $this->companyId(),
            ':start_date' => $filters['start_date'],
            ':end_date' => $filters['end_date'],
        ];

        if (!empty($filters['student_id'])) {
            $where[] = 'i.student_id = :student_id';
            $params[':student_id'] = (int) $filters['student_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'i.status = :invoice_status';
            $params[':invoice_status'] = $filters['status'];
        } else {
            $where[] = "i.status IN ('open','partial','overdue')";
        }

        $whereSql = implode(' AND ', $where);

        $bucketStmt = $this->db->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), i.due_date) <= 0 THEN (i.amount - i.paid_amount) ELSE 0 END), 0) AS current_amount,
                COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), i.due_date) BETWEEN 1 AND 30 THEN (i.amount - i.paid_amount) ELSE 0 END), 0) AS bucket_1_30,
                COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), i.due_date) BETWEEN 31 AND 60 THEN (i.amount - i.paid_amount) ELSE 0 END), 0) AS bucket_31_60,
                COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), i.due_date) BETWEEN 61 AND 90 THEN (i.amount - i.paid_amount) ELSE 0 END), 0) AS bucket_61_90,
                COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), i.due_date) > 90 THEN (i.amount - i.paid_amount) ELSE 0 END), 0) AS bucket_90_plus,
                SUM(CASE WHEN DATEDIFF(CURDATE(), i.due_date) <= 0 THEN 1 ELSE 0 END) AS current_qty,
                SUM(CASE WHEN DATEDIFF(CURDATE(), i.due_date) BETWEEN 1 AND 30 THEN 1 ELSE 0 END) AS qty_1_30,
                SUM(CASE WHEN DATEDIFF(CURDATE(), i.due_date) BETWEEN 31 AND 60 THEN 1 ELSE 0 END) AS qty_31_60,
                SUM(CASE WHEN DATEDIFF(CURDATE(), i.due_date) BETWEEN 61 AND 90 THEN 1 ELSE 0 END) AS qty_61_90,
                SUM(CASE WHEN DATEDIFF(CURDATE(), i.due_date) > 90 THEN 1 ELSE 0 END) AS qty_90_plus
            FROM invoices i
            WHERE {$whereSql}"
        );
        $bucketStmt->execute($params);
        $buckets = $bucketStmt->fetch() ?: [];

        $topWhere = $where;
        $topWhere[] = 'i.due_date < CURDATE()';

        $topSql = "SELECT
                s.id,
                s.full_name,
                COUNT(i.id) AS invoices_qty,
                COALESCE(SUM(i.amount - i.paid_amount), 0) AS outstanding_amount,
                MAX(DATEDIFF(CURDATE(), i.due_date)) AS max_days_overdue
            FROM invoices i
            INNER JOIN students s ON s.id = i.student_id
            WHERE " . implode(' AND ', $topWhere) . "
            GROUP BY s.id
            ORDER BY outstanding_amount DESC
            LIMIT 20";

        $topStmt = $this->db->prepare($topSql);
        $topStmt->execute($params);
        $topDebtors = $topStmt->fetchAll();

        return [
            'buckets' => [
                'current' => ['amount' => (float) ($buckets['current_amount'] ?? 0), 'qty' => (int) ($buckets['current_qty'] ?? 0)],
                '1_30' => ['amount' => (float) ($buckets['bucket_1_30'] ?? 0), 'qty' => (int) ($buckets['qty_1_30'] ?? 0)],
                '31_60' => ['amount' => (float) ($buckets['bucket_31_60'] ?? 0), 'qty' => (int) ($buckets['qty_31_60'] ?? 0)],
                '61_90' => ['amount' => (float) ($buckets['bucket_61_90'] ?? 0), 'qty' => (int) ($buckets['qty_61_90'] ?? 0)],
                '90_plus' => ['amount' => (float) ($buckets['bucket_90_plus'] ?? 0), 'qty' => (int) ($buckets['qty_90_plus'] ?? 0)],
            ],
            'top_debtors' => $topDebtors,
        ];
    }

    public function reportPayables(array $filters, int $perPage, int $page): array
    {
        if (!$this->schemaTableExists('payables')) {
            return [
                'rows' => [],
                'meta' => pagination_meta(0, $perPage, $page),
            ];
        }

        $where = [
            'p.company_id = :company_id',
            'p.due_date BETWEEN :start_date AND :end_date',
        ];
        $params = [
            ':company_id' => $this->companyId(),
            ':start_date' => $filters['start_date'],
            ':end_date' => $filters['end_date'],
        ];

        if (!empty($filters['status'])) {
            $where[] = 'p.status = :payable_status';
            $params[':payable_status'] = $filters['status'];
        }

        if (!empty($filters['method'])) {
            $where[] = 'COALESCE(pm.name, "") = :payable_method';
            $params[':payable_method'] = $filters['method'];
        }

        if (!empty($filters['supplier_id'])) {
            $where[] = 'p.supplier_id = :supplier_id';
            $params[':supplier_id'] = (int) $filters['supplier_id'];
        }

        $whereSql = implode(' AND ', $where);
        $countSql = "SELECT COUNT(*)
            FROM payables p
            INNER JOIN suppliers s ON s.id = p.supplier_id
            LEFT JOIN payment_methods pm ON pm.id = p.payment_method_id
            WHERE {$whereSql}";

        $dataSql = "SELECT
                p.id,
                p.payable_number,
                p.description,
                p.category,
                p.competence_date,
                p.due_date,
                p.amount,
                p.paid_amount,
                p.paid_at,
                p.status,
                p.notes,
                (p.amount - p.paid_amount) AS outstanding_amount,
                GREATEST(DATEDIFF(CURDATE(), p.due_date), 0) AS days_overdue,
                s.name AS supplier_name,
                COALESCE(pm.name, '-') AS payment_method_name
            FROM payables p
            INNER JOIN suppliers s ON s.id = p.supplier_id
            LEFT JOIN payment_methods pm ON pm.id = p.payment_method_id
            WHERE {$whereSql}
            ORDER BY p.due_date ASC, p.id DESC";

        return $this->paginate($countSql, $dataSql, $params, $perPage, $page);
    }

    public function reportCashflow(array $filters): array
    {
        $companyId = $this->companyId();
        $startDate = (string) $filters['start_date'];
        $endDate = (string) $filters['end_date'];
        $labels = $this->buildDateLabels($startDate, $endDate);
        $incomingByDate = array_fill_keys($labels, 0.0);
        $outgoingByDate = array_fill_keys($labels, 0.0);

        $incomingWhere = [
            'i.company_id = :in_company_id',
            'p.paid_at BETWEEN :in_start_date AND :in_end_date',
        ];
        $incomingParams = [
            ':in_company_id' => $companyId,
            ':in_start_date' => $startDate,
            ':in_end_date' => $endDate,
        ];

        if (!empty($filters['method'])) {
            $incomingWhere[] = 'p.method = :in_method';
            $incomingParams[':in_method'] = $filters['method'];
        }

        if (!empty($filters['student_id'])) {
            $incomingWhere[] = 'i.student_id = :in_student_id';
            $incomingParams[':in_student_id'] = (int) $filters['student_id'];
        }

        $incomingStmt = $this->db->prepare(
            "SELECT p.paid_at AS ref_date, COALESCE(SUM(pi.amount), 0) AS total
             FROM payments p
             INNER JOIN payment_items pi ON pi.payment_id = p.id
             INNER JOIN invoices i ON i.id = pi.invoice_id
             WHERE " . implode(' AND ', $incomingWhere) . "
             GROUP BY p.paid_at
             ORDER BY p.paid_at ASC"
        );
        $incomingStmt->execute($incomingParams);
        foreach ($incomingStmt->fetchAll() as $row) {
            $date = (string) $row['ref_date'];
            if (isset($incomingByDate[$date])) {
                $incomingByDate[$date] = (float) $row['total'];
            }
        }

        if ($this->schemaTableExists('payable_payments')) {
            $outgoingWhere = [
                'pp.company_id = :out_company_id',
                'pp.paid_at BETWEEN :out_start_date AND :out_end_date',
            ];
            $outgoingParams = [
                ':out_company_id' => $companyId,
                ':out_start_date' => $startDate,
                ':out_end_date' => $endDate,
            ];

            if (!empty($filters['method'])) {
                $outgoingWhere[] = 'COALESCE(pm.name, "") = :out_method';
                $outgoingParams[':out_method'] = $filters['method'];
            }

            if (!empty($filters['status'])) {
                $outgoingWhere[] = 'p.status = :out_status';
                $outgoingParams[':out_status'] = $filters['status'];
            }

            if (!empty($filters['supplier_id'])) {
                $outgoingWhere[] = 'p.supplier_id = :out_supplier_id';
                $outgoingParams[':out_supplier_id'] = (int) $filters['supplier_id'];
            }

            $outgoingStmt = $this->db->prepare(
                "SELECT pp.paid_at AS ref_date, COALESCE(SUM(pp.amount), 0) AS total
                 FROM payable_payments pp
                 INNER JOIN payables p ON p.id = pp.payable_id
                 LEFT JOIN payment_methods pm ON pm.id = pp.payment_method_id
                 WHERE " . implode(' AND ', $outgoingWhere) . "
                 GROUP BY pp.paid_at
                 ORDER BY pp.paid_at ASC"
            );
            $outgoingStmt->execute($outgoingParams);
            foreach ($outgoingStmt->fetchAll() as $row) {
                $date = (string) $row['ref_date'];
                if (isset($outgoingByDate[$date])) {
                    $outgoingByDate[$date] = (float) $row['total'];
                }
            }
        }

        $rows = [];
        foreach ($labels as $date) {
            $incoming = (float) ($incomingByDate[$date] ?? 0);
            $outgoing = (float) ($outgoingByDate[$date] ?? 0);
            $rows[] = [
                'date' => $date,
                'incoming' => $incoming,
                'outgoing' => $outgoing,
                'net' => $incoming - $outgoing,
            ];
        }

        return [
            'summary' => [
                'incoming_total' => array_sum($incomingByDate),
                'outgoing_total' => array_sum($outgoingByDate),
                'net_total' => array_sum($incomingByDate) - array_sum($outgoingByDate),
            ],
            'rows' => $rows,
        ];
    }

    public function reportFiscal(array $filters, int $perPage, int $page): array
    {
        if (!$this->hasFiscalTable()) {
            return [
                'rows' => [],
                'meta' => pagination_meta(0, $perPage, $page),
            ];
        }

        $where = [
            'i.company_id = :company_id',
            "DATE(COALESCE(fi.last_attempt_at, fi.created_at)) BETWEEN :start_date AND :end_date",
        ];
        $params = [
            ':company_id' => $this->companyId(),
            ':start_date' => $filters['start_date'],
            ':end_date' => $filters['end_date'],
        ];

        if (!empty($filters['student_id'])) {
            $where[] = 'i.student_id = :student_id';
            $params[':student_id'] = (int) $filters['student_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'i.status = :invoice_status';
            $params[':invoice_status'] = $filters['status'];
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*)
            FROM fiscal_invoices fi
            INNER JOIN invoices i ON i.id = fi.invoice_id
            LEFT JOIN students s ON s.id = i.student_id
            WHERE {$whereSql}";

        $dataSql = "SELECT
                fi.id,
                fi.provider,
                fi.status AS fiscal_status,
                fi.external_id,
                fi.number AS fiscal_number,
                fi.error_message,
                fi.last_attempt_at,
                fi.issued_at,
                i.id AS invoice_id,
                i.invoice_number,
                i.amount,
                i.status AS invoice_status,
                i.paid_at,
                s.full_name AS student_name
            FROM fiscal_invoices fi
            INNER JOIN invoices i ON i.id = fi.invoice_id
            LEFT JOIN students s ON s.id = i.student_id
            WHERE {$whereSql}
            ORDER BY fi.updated_at DESC, fi.id DESC";

        return $this->paginate($countSql, $dataSql, $params, $perPage, $page);
    }

    public function listPayments(array $filters, int $perPage, int $page): array
    {
        $hasPaymentsPaymentMethod = $this->paymentsPaymentMethodsAvailable();
        $where = ['EXISTS (
            SELECT 1
            FROM payment_items pi_filter
            INNER JOIN invoices i_filter ON i_filter.id = pi_filter.invoice_id
            WHERE pi_filter.payment_id = p.id
              AND i_filter.company_id = :filter_company_id
        )'];
        $params = [
            ':filter_company_id' => $this->companyId(),
        ];

        if (!empty($filters['q'])) {
            $where[] = '(p.payment_ref LIKE :q OR p.method LIKE :q OR p.notes LIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        if (!empty($filters['student_id'])) {
            $where[] = 'EXISTS (
                SELECT 1
                FROM payment_items pi_student
                INNER JOIN invoices i_student ON i_student.id = pi_student.invoice_id
                WHERE pi_student.payment_id = p.id
                  AND i_student.company_id = :student_company_id
                  AND i_student.student_id = :student_id
            )';
            $params[':student_company_id'] = $this->companyId();
            $params[':student_id'] = (int) $filters['student_id'];
        }

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $where[] = 'p.paid_at BETWEEN :start_date AND :end_date';
            $params[':start_date'] = (string) $filters['start_date'];
            $params[':end_date'] = (string) $filters['end_date'];
        }

        $whereSql = implode(' AND ', $where);
        $paymentMethodJoin = $hasPaymentsPaymentMethod
            ? 'LEFT JOIN payment_methods pm ON pm.id = p.payment_method_id AND pm.company_id = p.company_id'
            : '';
        $paymentMethodSelect = $hasPaymentsPaymentMethod
            ? 'MAX(pm.name) AS payment_method_name, MAX(pm.mode) AS payment_method_mode, MAX(pm.provider_key) AS payment_method_provider_key'
            : 'NULL AS payment_method_name, NULL AS payment_method_mode, NULL AS payment_method_provider_key';

        $countSql = "SELECT COUNT(*) FROM payments p WHERE {$whereSql}";

        $dataSql = "SELECT p.*, {$paymentMethodSelect}, COUNT(i.id) AS invoices_qty
            FROM payments p
            LEFT JOIN payment_items pi ON pi.payment_id = p.id
            LEFT JOIN invoices i ON i.id = pi.invoice_id AND i.company_id = :filter_company_id
            {$paymentMethodJoin}
            WHERE {$whereSql}
            GROUP BY p.id
            ORDER BY p.id DESC";

        return $this->paginate($countSql, $dataSql, $params, $perPage, $page);
    }

    public function recordBatchPayment(
        array $invoiceIds,
        float $amount,
        string $method,
        string $paidAt,
        string $notes,
        int $createdBy,
        ?int $paymentMethodId = null
    ): int
    {
        $invoiceIds = array_values(array_filter(array_map('intval', $invoiceIds), fn ($id) => $id > 0));
        if ($invoiceIds === [] || $amount <= 0) {
            return 0;
        }

        $companyId = $this->companyId();
        $method = $this->resolvePaymentMethodName($paymentMethodId, $method);

        if ($this->paymentsPaymentMethodsAvailable()) {
            $stmt = $this->db->prepare('INSERT INTO payments (
                payment_ref, company_id, payment_method_id, method, amount, paid_at, notes, created_by, created_at, updated_at
            ) VALUES (
                :payment_ref, :company_id, :payment_method_id, :method, :amount, :paid_at, :notes, :created_by, :created_at, :updated_at
            )');
        } else {
            $stmt = $this->db->prepare('INSERT INTO payments (
                payment_ref, company_id, method, amount, paid_at, notes, created_by, created_at, updated_at
            ) VALUES (
                :payment_ref, :company_id, :method, :amount, :paid_at, :notes, :created_by, :created_at, :updated_at
            )');
        }

        $now = now();
        $params = [
            ':payment_ref' => 'PG-' . date('YmdHis'),
            ':company_id' => $companyId,
            ':method' => $method,
            ':amount' => $amount,
            ':paid_at' => $paidAt,
            ':notes' => $notes,
            ':created_by' => ($createdBy !== null && $createdBy > 0) ? $createdBy : null,
            ':created_at' => $now,
            ':updated_at' => $now,
        ];
        if ($this->paymentsPaymentMethodsAvailable()) {
            $params[':payment_method_id'] = ($paymentMethodId !== null && $paymentMethodId > 0) ? $paymentMethodId : null;
        }
        $stmt->execute($params);

        $paymentId = (int) $this->db->lastInsertId();

        $placeholders = implode(',', array_fill(0, count($invoiceIds), '?'));
        $q = $this->db->prepare("SELECT * FROM invoices WHERE id IN ({$placeholders}) AND company_id = ? ORDER BY due_date ASC, id ASC");
        $q->execute(array_merge($invoiceIds, [$companyId]));
        $invoices = $q->fetchAll();

        $remaining = $amount;
        foreach ($invoices as $invoice) {
            if ($remaining <= 0) {
                break;
            }

            $outstanding = max(0, (float) $invoice['amount'] - (float) $invoice['paid_amount']);
            if ($outstanding <= 0) {
                continue;
            }

            $toApply = min($remaining, $outstanding);
            $this->attachPaymentItem($paymentId, (int) $invoice['id'], $toApply);

            $up = $this->db->prepare('UPDATE invoices
                SET paid_amount = paid_amount + :value, updated_at = :updated_at
                WHERE id = :id AND company_id = :company_id');
            $up->execute([
                ':value' => $toApply,
                ':updated_at' => now(),
                ':id' => (int) $invoice['id'],
                ':company_id' => $companyId,
            ]);

            $this->recalculateInvoiceStatus((int) $invoice['id'], $paidAt);
            $this->syncStudentFinanceKanban((int) $invoice['student_id'], $createdBy);

            $remaining -= $toApply;
        }

        return $paymentId;
    }

    public function generateRecurringInvoices(string $referenceDate, int $createdBy): int
    {
        $companyId = $this->companyId();
        $studentsStmt = $this->db->prepare('SELECT id, monthly_fee, billing_day
            FROM students
            WHERE company_id = :company_id
              AND is_active = 1
              AND monthly_fee > 0');
        $studentsStmt->execute([':company_id' => $companyId]);
        $students = $studentsStmt->fetchAll();
        $created = 0;

        foreach ($students as $student) {
            $day = (int) ($student['billing_day'] ?: 10);
            $date = new DateTime($referenceDate);
            $lastDay = (int) $date->format('t');
            $day = min(max(1, $day), $lastDay);
            $dueDate = $date->format('Y-m-') . str_pad((string) $day, 2, '0', STR_PAD_LEFT);

            $check = $this->db->prepare('SELECT COUNT(*)
                FROM invoices
                WHERE company_id = :company_id
                  AND student_id = :student_id
                  AND due_date = :due_date
                  AND is_recurring = 1');
            $check->execute([
                ':company_id' => $companyId,
                ':student_id' => (int) $student['id'],
                ':due_date' => $dueDate,
            ]);

            if ((int) $check->fetchColumn() > 0) {
                continue;
            }

            $this->createInvoice([
                'student_id' => (int) $student['id'],
                'due_date' => $dueDate,
                'amount' => (float) $student['monthly_fee'],
                'tax_amount' => 0,
                'status' => 'open',
                'tags' => 'Mensalidade',
                'project_name' => 'Plano recorrente',
                'is_recurring' => 1,
                'recurrence_interval' => 'monthly',
            ], $createdBy);

            $created++;
        }

        return $created;
    }

    public function generateStudentFinancialPlan(int $studentId, int $createdBy): array
    {
        $studentId = (int) $studentId;
        if ($studentId <= 0) {
            return ['ok' => false, 'message' => 'Aluno inválido para gerar plano financeiro.'];
        }

        if (!$this->studentFinancialPlanFeatureAvailable()) {
            return ['ok' => false, 'message' => 'Plano financeiro do aluno indisponível no banco. Execute a migração correspondente.'];
        }

        $student = $this->students->find($studentId);
        if (!$student || (int) ($student['company_id'] ?? 0) !== $this->companyId()) {
            return ['ok' => false, 'message' => 'Aluno não encontrado para gerar o plano financeiro.'];
        }

        $qty = max(0, (int) ($student['financial_plan_installments'] ?? 0));
        $amount = round((float) ($student['monthly_fee'] ?? 0), 2);
        $firstDueDate = trim((string) ($student['financial_plan_first_due_date'] ?? ''));
        $billingDay = max(1, min(31, (int) ($student['billing_day'] ?? 0)));
        $paymentMethodId = $this->invoicePaymentMethodsAvailable()
            ? (int) ($student['financial_plan_payment_method_id'] ?? 0)
            : 0;

        if ($qty <= 0 || $amount <= 0 || !$this->isValidDate($firstDueDate)) {
            return ['ok' => false, 'message' => 'O aluno não possui um plano financeiro completo para gerar as parcelas.'];
        }

        if ($billingDay <= 0) {
            $billingDay = (int) date('d', strtotime($firstDueDate));
        }

        $projectName = 'Plano Financeiro do aluno';
        $created = 0;
        $existing = 0;
        $failed = 0;

        $this->db->beginTransaction();
        try {
            for ($installment = 1; $installment <= $qty; $installment++) {
                $dueDate = $this->buildInstallmentDueDate($firstDueDate, $billingDay, $installment - 1);
                if ($dueDate === null) {
                    $failed++;
                    continue;
                }

                $existingInvoiceId = $this->findStudentPlanInstallmentInvoice($studentId, $installment);
                if ($existingInvoiceId > 0) {
                    $existing++;
                    continue;
                }

                $tags = $this->buildStudentPlanTags($studentId, $installment, $qty, (string) ($student['financial_plan_profile'] ?? 'custom'));
                $invoiceId = $this->createInvoice([
                    'student_id' => $studentId,
                    'payment_method_id' => $paymentMethodId > 0 ? $paymentMethodId : null,
                    'due_date' => $dueDate,
                    'amount' => $amount,
                    'tax_amount' => 0,
                    'status' => 'open',
                    'tags' => $tags,
                    'project_name' => $projectName,
                    'boleto_url' => '',
                    'is_recurring' => 1,
                    'recurrence_interval' => 'monthly',
                ], $createdBy);

                if ($invoiceId > 0) {
                    $created++;
                } else {
                    $failed++;
                }
            }

            if ($failed > 0) {
                $this->db->rollBack();
                return [
                    'ok' => false,
                    'message' => 'Não foi possível gerar o plano financeiro completo do aluno.',
                    'created' => 0,
                    'existing' => $existing,
                    'failed' => $failed,
                ];
            }

            $generatedAt = ($created > 0 || $existing > 0) ? now() : null;
            $stmt = $this->db->prepare('UPDATE students
                SET financial_plan_generated_at = :generated_at,
                    updated_at = :updated_at
                WHERE id = :id
                  AND company_id = :company_id');
            $stmt->execute([
                ':generated_at' => $generatedAt,
                ':updated_at' => now(),
                ':id' => $studentId,
                ':company_id' => $this->companyId(),
            ]);

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return ['ok' => false, 'message' => 'Falha ao gerar plano financeiro: ' . $e->getMessage()];
        }

        return [
            'ok' => true,
            'message' => 'Plano financeiro processado com sucesso.',
            'created' => $created,
            'existing' => $existing,
            'failed' => $failed,
        ];
    }

    public function studentGeneratedPlanInvoiceCount(int $studentId): int
    {
        $studentId = (int) $studentId;
        if ($studentId <= 0) {
            return 0;
        }

        $stmt = $this->db->prepare('SELECT COUNT(*)
            FROM invoices
            WHERE company_id = :company_id
              AND student_id = :student_id
              AND tags LIKE :marker');
        $stmt->execute([
            ':company_id' => $this->companyId(),
            ':student_id' => $studentId,
            ':marker' => '%PlanoAluno#' . $studentId . ' Parcela#%',
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function issueDueBankSlips(int $createdBy, int $defaultDaysBefore = 10, int $limit = 100): array
    {
        if (!$this->hasBankSlipTable()) {
            return ['ok' => false, 'message' => 'Estrutura de boleto indisponivel no banco.'];
        }

        if (!$this->invoicePaymentMethodsAvailable()) {
            return ['ok' => false, 'message' => 'Formas de pagamento ainda não disponíveis para emitir boletos automaticamente.'];
        }

        $studentWindowSql = $this->studentFinancialPlanFeatureAvailable()
            ? 'COALESCE(NULLIF(s.financial_plan_boleto_days_before, 0), :default_days_before)'
            : ':default_days_before';

        $sql = "SELECT i.id
            FROM invoices i
            INNER JOIN students s ON s.id = i.student_id
            INNER JOIN payment_methods pm ON pm.id = i.payment_method_id AND pm.company_id = i.company_id
            LEFT JOIN bank_slips bs ON bs.invoice_id = i.id
            WHERE i.company_id = :company_id
              AND i.status IN ('open', 'partial', 'overdue')
              AND pm.mode = 'integrated'
              AND pm.provider_key = 'itau'
              AND DATEDIFF(i.due_date, CURDATE()) <= {$studentWindowSql}
              AND (
                    bs.id IS NULL
                    OR bs.status IN ('pending', 'error', 'failed', 'cancelled')
                  )
            ORDER BY i.due_date ASC, i.id ASC
            LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':company_id', $this->companyId(), PDO::PARAM_INT);
        $stmt->bindValue(':default_days_before', max(0, $defaultDaysBefore), PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll() ?: [];

        if ($rows === []) {
            return [
                'ok' => true,
                'message' => 'Nenhuma fatura entrou na janela de emissao para o Itau.',
                'processed' => 0,
                'issued' => 0,
                'pending' => 0,
                'errors' => 0,
            ];
        }

        $processed = 0;
        $issued = 0;
        $pending = 0;
        $errors = 0;

        foreach ($rows as $row) {
            $result = $this->generateBankSlip((int) $row['id'], $createdBy);
            $processed++;

            if (!($result['ok'] ?? false)) {
                $errors++;
                continue;
            }

            $status = (string) ($result['status'] ?? 'pending');
            if (in_array($status, ['issued', 'registered'], true)) {
                $issued++;
            } elseif (in_array($status, ['pending', 'processing'], true)) {
                $pending++;
            } else {
                $errors++;
            }
        }

        $message = sprintf(
            'Faturas processadas: %d. Emitidos: %d. Pendentes: %d. Erros: %d.',
            $processed,
            $issued,
            $pending,
            $errors
        );

        return [
            'ok' => $errors === 0,
            'message' => $message,
            'processed' => $processed,
            'issued' => $issued,
            'pending' => $pending,
            'errors' => $errors,
        ];
    }

    public function syncStudentFinanceKanban(int $studentId, int $changedBy): void
    {
        $companyId = $this->companyId();
        $this->students->useCompany($companyId);
        $student = $this->students->find($studentId);
        if (!$student || (int) ($student['company_id'] ?? 0) !== $companyId) {
            return;
        }

        $this->refreshOverdueInvoices();

        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM invoices
            WHERE company_id = :company_id
              AND student_id = :student_id
              AND status = 'overdue'");
        $stmt->execute([
            ':company_id' => $companyId,
            ':student_id' => $studentId,
        ]);
        $overdueCount = (int) $stmt->fetchColumn();

        $hasActiveAgreement = $this->studentHasActiveAgreementInvoices($studentId, $companyId);

        $targetStatusId = null;
        if ($hasActiveAgreement) {
            $targetStatusId = $this->statusIdBySlug('acordo-ativo');
        } elseif ($overdueCount >= 2) {
            $targetStatusId = $this->statusIdBySlug('inadimplente');
        } else {
            $targetStatusId = $this->statusIdBySlug('sem-pendencias') ?: $this->students->defaultKanbanStatusId();
        }

        if (!$targetStatusId || (int) $student['kanban_status_id'] === (int) $targetStatusId) {
            return;
        }

        $up = $this->db->prepare('UPDATE students
            SET kanban_status_id = :status_id, updated_at = :updated_at
            WHERE id = :id AND company_id = :company_id');
        $up->execute([
            ':status_id' => $targetStatusId,
            ':updated_at' => now(),
            ':id' => $studentId,
            ':company_id' => $companyId,
        ]);

        if ($hasActiveAgreement) {
            $reason = 'Atualizacao automatica por acordo ativo';
        } elseif ($overdueCount >= 2) {
            $reason = 'Atualizacao automatica por inadimplencia';
        } else {
            $reason = 'Atualizacao automatica por regularizacao';
        }

        $fromStatusId = !empty($student['kanban_status_id']) ? (int) $student['kanban_status_id'] : null;
        $this->students->registerKanbanHistory($studentId, $fromStatusId, (int) $targetStatusId, $changedBy, $reason);
    }

    public function syncFinanceBoardStatuses(int $changedBy): void
    {
        $companyId = $this->companyId();
        $this->students->useCompany($companyId);
        $this->refreshOverdueInvoices();

        $stmt = $this->db->prepare("SELECT s.id
            FROM students s
            WHERE s.company_id = :company_id
              AND s.is_active = 1");
        $stmt->execute([':company_id' => $companyId]);

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $studentId) {
            $this->syncStudentFinanceKanban((int) $studentId, $changedBy);
        }
    }

    public function enforceMobileAgreementAccessGracePeriod(int $graceDays = 3): array
    {
        if (!$this->schemaTableExists('student_portal_accounts')) {
            return [
                'ok' => true,
                'checked' => 0,
                'blocked' => 0,
                'message' => 'Portal do aluno indisponivel nesta empresa.',
            ];
        }

        $companyId = $this->companyId();
        $graceDays = max(1, min(30, $graceDays));
        $agreementKeySql = "COALESCE(NULLIF(i.project_name, ''), CONCAT('Acordo mobile #', i.student_id, ' - ', DATE(i.created_at)))";

        $sql = "SELECT DISTINCT pending.account_id, pending.student_id
            FROM (
                SELECT
                    spa.id AS account_id,
                    spa.student_id,
                    {$agreementKeySql} AS agreement_key,
                    MIN(i.created_at) AS agreement_created_at,
                    SUM(CASE
                        WHEN i.status IN ('open', 'partial', 'overdue')
                         AND GREATEST(i.amount - COALESCE(i.paid_amount, 0), 0) > 0.009
                        THEN 1 ELSE 0
                    END) AS open_count,
                    SUM(CASE
                        WHEN i.status = 'paid'
                          OR COALESCE(i.paid_amount, 0) > 0.009
                          OR i.paid_at IS NOT NULL
                        THEN 1 ELSE 0
                    END) AS paid_count
                FROM invoices i
                INNER JOIN students s ON s.id = i.student_id AND s.company_id = i.company_id
                INNER JOIN student_portal_accounts spa ON spa.student_id = s.id
                WHERE i.company_id = :company_id
                  AND s.is_active = 1
                  AND spa.is_active = 1
                  AND (
                        i.tags LIKE :agreement_tag
                        OR i.project_name LIKE :negotiation_project
                        OR i.project_name LIKE :addendum_project
                      )
                GROUP BY spa.id, spa.student_id, {$agreementKeySql}
                HAVING agreement_created_at <= DATE_SUB(NOW(), INTERVAL {$graceDays} DAY)
                   AND open_count > 0
                   AND paid_count = 0
            ) pending";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':company_id' => $companyId,
            ':agreement_tag' => '%Acordo mobile%',
            ':negotiation_project' => 'Negociacao financeiro (App Diretoria)%',
            ':addendum_project' => 'Aditivo financeiro (App Diretoria)%',
        ]);

        $rows = $stmt->fetchAll() ?: [];
        if ($rows === []) {
            return [
                'ok' => true,
                'checked' => 0,
                'blocked' => 0,
                'message' => 'Nenhum acordo mobile fora do prazo sem baixa.',
            ];
        }

        $blocked = 0;
        $update = $this->db->prepare('UPDATE student_portal_accounts
            SET is_active = 0,
                updated_at = :updated_at
            WHERE id = :account_id
              AND student_id = :student_id
              AND is_active = 1');

        foreach ($rows as $row) {
            $update->execute([
                ':updated_at' => now(),
                ':account_id' => (int) ($row['account_id'] ?? 0),
                ':student_id' => (int) ($row['student_id'] ?? 0),
            ]);
            if ($update->rowCount() > 0) {
                $blocked++;
            }
        }

        return [
            'ok' => true,
            'checked' => count($rows),
            'blocked' => $blocked,
            'message' => "Acordos mobile avaliados: " . count($rows) . ". Acessos bloqueados: {$blocked}.",
        ];
    }

    private function studentHasActiveAgreementInvoices(int $studentId, int $companyId): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM invoices
            WHERE company_id = :company_id
              AND student_id = :student_id
              AND status IN ('open', 'partial', 'overdue')
              AND (
                    tags LIKE :agreement_tag
                    OR project_name LIKE :negotiation_project
                    OR project_name LIKE :addendum_project
                  )");
        $stmt->execute([
            ':company_id' => $companyId,
            ':student_id' => $studentId,
            ':agreement_tag' => '%Acordo mobile%',
            ':negotiation_project' => 'Negociacao financeiro (App Diretoria)%',
            ':addendum_project' => 'Aditivo financeiro (App Diretoria)%',
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function resolveInvoicePaymentMethod(array $invoice): ?array
    {
        if (!$this->invoicePaymentMethodsAvailable()) {
            return null;
        }

        $paymentMethodId = (int) ($invoice['payment_method_id'] ?? 0);
        if ($paymentMethodId <= 0) {
            return null;
        }

        return $this->findPaymentMethod($paymentMethodId);
    }

    private function resolveBankSlipService(?array $paymentMethod = null, ?array $record = null)
    {
        $providerKey = strtolower(trim((string) ($paymentMethod['provider_key'] ?? $record['provider'] ?? '')));
        if ($providerKey === 'itau') {
            return new ItauService($this->companyId());
        }

        return new BoletoService($this->companyId());
    }

    private function buildInstallmentDueDate(string $firstDueDate, int $billingDay, int $monthOffset): ?string
    {
        try {
            $baseDate = new DateTimeImmutable($firstDueDate);
            if ($monthOffset <= 0) {
                return $baseDate->format('Y-m-d');
            }

            $targetMonth = $baseDate->modify('first day of +' . $monthOffset . ' month');
            $lastDay = (int) $targetMonth->format('t');
            $day = max(1, min($billingDay, $lastDay));

            return $targetMonth->format('Y-m-') . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
        } catch (Throwable $e) {
            return null;
        }
    }

    private function isValidDate(string $date): bool
    {
        $date = trim($date);
        if ($date === '') {
            return false;
        }

        $parsed = DateTime::createFromFormat('Y-m-d', $date);
        return $parsed && $parsed->format('Y-m-d') === $date;
    }

    private function findStudentPlanInstallmentInvoice(int $studentId, int $installment): int
    {
        $marker = sprintf('PlanoAluno#%d Parcela#%02d', $studentId, $installment);
        $stmt = $this->db->prepare('SELECT id
            FROM invoices
            WHERE company_id = :company_id
              AND student_id = :student_id
              AND tags LIKE :marker
            LIMIT 1');
        $stmt->execute([
            ':company_id' => $this->companyId(),
            ':student_id' => $studentId,
            ':marker' => '%' . $marker . '%',
        ]);

        $value = $stmt->fetchColumn();
        return $value !== false ? (int) $value : 0;
    }

    private function buildStudentPlanTags(int $studentId, int $installment, int $qty, string $profile): string
    {
        $profile = trim($profile) !== '' ? trim($profile) : 'custom';
        return sprintf(
            'Plano financeiro,Perfil:%s,PlanoAluno#%d,PlanoAluno#%d Parcela#%02d,Parcela %02d/%02d',
            $profile,
            $studentId,
            $studentId,
            $installment,
            $installment,
            $qty
        );
    }

    private function decorateInvoiceRows(array $rows): array
    {
        foreach ($rows as &$row) {
            $meta = $this->extractInstallmentMeta((string) ($row['tags'] ?? ''));
            $row['installment_number'] = $meta['number'];
            $row['installment_total'] = $meta['total'];
            $row['installment_label'] = $meta['label'];
        }
        unset($row);

        return $rows;
    }

    private function extractInstallmentMeta(string $tags): array
    {
        $tags = trim($tags);
        if ($tags !== '' && preg_match('/Parcela\s+(\d{1,3})\/(\d{1,3})/i', $tags, $matches)) {
            $number = (int) $matches[1];
            $total = (int) $matches[2];
            return [
                'number' => $number,
                'total' => $total,
                'label' => $number . '/' . $total,
            ];
        }

        return [
            'number' => null,
            'total' => null,
            'label' => '',
        ];
    }

    private function syncLinkedBankSlipAfterInvoiceEdit(array $previousInvoice, array $newData, int $updatedBy): void
    {
        if (!$this->hasBankSlipTable()) {
            return;
        }

        $record = $this->bankSlipByInvoice((int) $previousInvoice['id']);
        if (!$record) {
            return;
        }

        $newPaymentMethodId = $this->invoicePaymentMethodsAvailable()
            ? (int) ($newData['payment_method_id'] ?? 0)
            : 0;
        $oldPaymentMethodId = $this->invoicePaymentMethodsAvailable()
            ? (int) ($previousInvoice['payment_method_id'] ?? 0)
            : 0;

        $materialChange = false;
        if ((string) ($previousInvoice['due_date'] ?? '') !== (string) ($newData['due_date'] ?? '')) {
            $materialChange = true;
        }
        if (round((float) ($previousInvoice['amount'] ?? 0), 2) !== round((float) ($newData['amount'] ?? 0), 2)) {
            $materialChange = true;
        }
        if ($newPaymentMethodId !== $oldPaymentMethodId) {
            $materialChange = true;
        }

        if (!$materialChange) {
            return;
        }

        $paymentMethod = $newPaymentMethodId > 0 ? $this->findPaymentMethod($newPaymentMethodId) : null;
        $shouldKeepIntegrated = $paymentMethod
            && (string) ($paymentMethod['mode'] ?? '') === 'integrated'
            && (string) ($paymentMethod['provider_key'] ?? '') === 'itau';

        $status = $shouldKeepIntegrated ? 'pending' : 'cancelled';
        $errorMessage = $shouldKeepIntegrated
            ? 'Boleto marcado para reemissão após alteração administrativa da fatura.'
            : 'Boleto cancelado após mudança para forma de pagamento sem integração.';

        $stmt = $this->db->prepare('UPDATE bank_slips
            SET status = :status,
                external_id = NULL,
                ' . ($this->bankSlipNossoNumeroColumnAvailable() ? 'nosso_numero = NULL,' : '') . '
                amount = :amount,
                due_date = :due_date,
                digitable_line = NULL,
                barcode = NULL,
                pix_qr_code = NULL,
                pix_copy_paste = NULL,
                boleto_url = NULL,
                pdf_url = NULL,
                expires_at = NULL,
                issued_at = NULL,
                error_message = :error_message,
                updated_at = :updated_at
            WHERE id = :id');
        $stmt->execute([
            ':status' => $status,
            ':amount' => (float) ($newData['amount'] ?? 0),
            ':due_date' => (string) ($newData['due_date'] ?? ''),
            ':error_message' => $errorMessage,
            ':updated_at' => now(),
            ':id' => (int) $record['id'],
        ]);

        $invoiceStmt = $this->db->prepare('UPDATE invoices
            SET boleto_url = :boleto_url,
                updated_at = :updated_at
            WHERE id = :id
              AND company_id = :company_id');
        $invoiceStmt->execute([
            ':boleto_url' => null,
            ':updated_at' => now(),
            ':id' => (int) $previousInvoice['id'],
            ':company_id' => $this->companyId(),
        ]);
    }

    private function scalar(string $sql, array $params = [])
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    private function invoiceFilterSql(array $filters, string $invoiceAlias = 'i', string $studentAlias = 's', string $prefix = ''): array
    {
        $where = ["{$invoiceAlias}.company_id = :{$prefix}company_id"];
        $params = [":{$prefix}company_id" => $this->companyId()];

        if (!empty($filters['q'])) {
            $where[] = "({$invoiceAlias}.invoice_number LIKE :{$prefix}q OR {$studentAlias}.full_name LIKE :{$prefix}q OR {$invoiceAlias}.tags LIKE :{$prefix}q OR {$invoiceAlias}.project_name LIKE :{$prefix}q)";
            $params[":{$prefix}q"] = '%' . $filters['q'] . '%';
        }

        if (!empty($filters['status'])) {
            $where[] = "{$invoiceAlias}.status = :{$prefix}status";
            $params[":{$prefix}status"] = $filters['status'];
        }

        if (!empty($filters['student_id'])) {
            $where[] = "{$invoiceAlias}.student_id = :{$prefix}student_id";
            $params[":{$prefix}student_id"] = (int) $filters['student_id'];
        }

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $where[] = "{$invoiceAlias}.due_date BETWEEN :{$prefix}start_date AND :{$prefix}end_date";
            $params[":{$prefix}start_date"] = (string) $filters['start_date'];
            $params[":{$prefix}end_date"] = (string) $filters['end_date'];
        }

        return [implode(' AND ', $where), $params];
    }

    private function buildDateLabels(string $startDate, string $endDate): array
    {
        $labels = [];
        $current = new DateTime($startDate);
        $end = new DateTime($endDate);

        while ($current <= $end) {
            $labels[] = $current->format('Y-m-d');
            $current->modify('+1 day');
        }

        return $labels;
    }

    private function generateInvoiceNumber(int $id): string
    {
        return sprintf('FATURA-%06d-%s', $id, date('y'));
    }

    private function splitAmount(float $total, int $parts): array
    {
        $parts = max(1, $parts);
        $totalCents = (int) round($total * 100);
        $base = intdiv($totalCents, $parts);
        $remainder = $totalCents - ($base * $parts);

        $values = [];
        for ($i = 0; $i < $parts; $i++) {
            $cents = $base + ($i < $remainder ? 1 : 0);
            $values[] = $cents / 100;
        }

        return $values;
    }

    private function attachPaymentItem(int $paymentId, int $invoiceId, float $amount): void
    {
        $companyId = $this->companyId();
        $stmt = $this->db->prepare('INSERT INTO payment_items (payment_id, invoice_id, amount, created_at)
            SELECT p.id, i.id, :amount, :created_at
            FROM payments p
            INNER JOIN invoices i ON i.id = :invoice_id AND i.company_id = :invoice_company_id
            WHERE p.id = :payment_id
              AND p.company_id = :payment_company_id');

        $stmt->execute([
            ':payment_id' => $paymentId,
            ':invoice_id' => $invoiceId,
            ':invoice_company_id' => $companyId,
            ':payment_company_id' => $companyId,
            ':amount' => $amount,
            ':created_at' => now(),
        ]);
    }

    private function recalculateInvoiceStatus(int $invoiceId, ?string $paidAt = null): void
    {
        $invoice = $this->findInvoice($invoiceId);
        if (!$invoice) {
            return;
        }

        $amount = (float) $invoice['amount'];
        $paid = (float) $invoice['paid_amount'];
        $status = 'open';

        if ($paid <= 0 && $invoice['status'] === 'draft') {
            $status = 'draft';
        } elseif ($paid >= $amount && $amount > 0) {
            $status = 'paid';
        } elseif ($paid > 0 && $paid < $amount) {
            $status = 'partial';
        } elseif ($invoice['due_date'] < date('Y-m-d')) {
            $status = 'overdue';
        }

        $paidAtValue = $status === 'paid'
            ? ($paidAt ?: ($invoice['paid_at'] ?? date('Y-m-d')))
            : null;

        $stmt = $this->db->prepare('UPDATE invoices
            SET status = :status, paid_at = :paid_at, updated_at = :updated_at
            WHERE id = :id AND company_id = :company_id');
        $stmt->execute([
            ':status' => $status,
            ':paid_at' => $paidAtValue,
            ':updated_at' => now(),
            ':id' => $invoiceId,
            ':company_id' => $this->companyId(),
        ]);
    }

    private function markInvoicesAsRenegotiated(array $invoiceIds, string $notes, int $changedBy): int
    {
        $invoiceIds = array_values(array_filter(array_map('intval', $invoiceIds), fn ($id) => $id > 0));
        if ($invoiceIds === []) {
            return 0;
        }

        $companyId = $this->companyId();
        $placeholders = implode(',', array_fill(0, count($invoiceIds), '?'));
        $stmt = $this->db->prepare("SELECT id, student_id, tags, project_name
            FROM invoices
            WHERE id IN ({$placeholders}) AND company_id = ?");
        $stmt->execute(array_merge($invoiceIds, [$companyId]));
        $rows = $stmt->fetchAll() ?: [];

        if ($rows === []) {
            return 0;
        }

        $updated = 0;
        $studentsToSync = [];
        $stamp = 'Renegociada via app em ' . date('d/m/Y');

        foreach ($rows as $row) {
            $existingTags = trim((string) ($row['tags'] ?? ''));
            $existingProject = trim((string) ($row['project_name'] ?? ''));

            $tags = $existingTags;
            if (!str_contains(strtolower($existingTags), 'renegociad')) {
                $tags = trim(implode(' | ', array_filter([$existingTags, 'Renegociada'])));
            }

            $project = $existingProject;
            if (!str_contains(strtolower($existingProject), 'renegociad')) {
                $project = trim(implode(' | ', array_filter([$existingProject, $stamp])));
            }

            if ($notes !== '' && !str_contains($project, $notes)) {
                $project = trim(implode(' | ', array_filter([$project, $notes])));
            }

            $update = $this->db->prepare('UPDATE invoices
                SET status = :status,
                    paid_at = NULL,
                    tags = :tags,
                    project_name = :project_name,
                    updated_at = :updated_at
                WHERE id = :id
                  AND company_id = :company_id');
            $update->execute([
                ':status' => 'renegotiated',
                ':tags' => $tags !== '' ? $tags : null,
                ':project_name' => $project !== '' ? $project : null,
                ':updated_at' => now(),
                ':id' => (int) $row['id'],
                ':company_id' => $companyId,
            ]);

            if ($update->rowCount() > 0) {
                $updated++;
            }

            $studentsToSync[(int) ($row['student_id'] ?? 0)] = true;
        }

        foreach (array_keys($studentsToSync) as $studentId) {
            if ($studentId > 0) {
                $this->syncStudentFinanceKanban($studentId, $changedBy);
            }
        }

        return $updated;
    }

    private function fiscalRecordByInvoice(int $invoiceId): ?array
    {
        if (!$this->hasFiscalTable()) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT fi.*
            FROM fiscal_invoices fi
            INNER JOIN invoices i ON i.id = fi.invoice_id
            WHERE fi.invoice_id = :invoice_id
              AND i.company_id = :company_id
            LIMIT 1');
        $stmt->execute([
            ':invoice_id' => $invoiceId,
            ':company_id' => $this->companyId(),
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function bankSlipByInvoice(int $invoiceId): ?array
    {
        if (!$this->hasBankSlipTable()) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT bs.*
            FROM bank_slips bs
            INNER JOIN invoices i ON i.id = bs.invoice_id
            WHERE bs.invoice_id = :invoice_id
              AND i.company_id = :company_id
            LIMIT 1');
        $stmt->execute([
            ':invoice_id' => $invoiceId,
            ':company_id' => $this->companyId(),
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function bankSlipByItauIdentifiers(array $identifiers, ?int $companyId = null): ?array
    {
        $values = array_values(array_unique(array_filter(array_map('strval', $identifiers), fn ($value) => trim($value) !== '')));
        if ($values === []) {
            return null;
        }

        $hasNossoNumero = $this->bankSlipNossoNumeroColumnAvailable();
        $conditions = ['bs.external_id IN (' . implode(',', array_fill(0, count($values), '?')) . ')'];
        $params = $values;

        if ($hasNossoNumero) {
            $conditions[] = 'bs.nosso_numero IN (' . implode(',', array_fill(0, count($values), '?')) . ')';
            $params = array_merge($params, $values);
        }

        $where = '(' . implode(' OR ', $conditions) . ')';
        if ($companyId !== null && $companyId > 0) {
            $where .= ' AND i.company_id = ?';
            $params[] = $companyId;
        }

        $stmt = $this->db->prepare("SELECT bs.*, i.company_id
            FROM bank_slips bs
            INNER JOIN invoices i ON i.id = bs.invoice_id
            WHERE {$where}
            ORDER BY bs.id DESC
            LIMIT 1");
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function itauWebhookIdentifiers(array $payload): array
    {
        $externalId = $this->extractFromPayload($payload, [
            'id_boleto',
            'idBoleto',
            'id_boleto_individual',
            'idBoletoIndividual',
            'external_id',
            'boleto_id',
        ]);
        $nossoNumero = $this->extractFromPayload($payload, [
            'nosso_numero',
            'nossoNumero',
            'numero_nosso_numero',
            'numeroNossoNumero',
            'numero_nosso_numero_boleto',
        ]);

        return array_filter([
            'external_id' => trim((string) $externalId),
            'nosso_numero' => trim((string) $nossoNumero),
        ], fn ($value) => $value !== '');
    }

    private function normalizeItauWebhookStatus(array $payload, string $fallback): string
    {
        $raw = strtolower(trim((string) $this->extractFromPayload($payload, [
            'status',
            'situacao',
            'situacao_boleto',
            'situacaoBoleto',
            'situacao_geral_boleto',
            'situacaoGeralBoleto',
            'codigo_situacao',
        ])));

        if ($raw === '') {
            return $fallback !== '' ? $fallback : 'pending';
        }

        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $raw);
        $normalized = is_string($normalized) ? $normalized : $raw;

        if (str_contains($normalized, 'pag') || str_contains($normalized, 'liquid') || str_contains($normalized, 'receb') || str_contains($normalized, 'baixad')) {
            return 'paid';
        }
        if (str_contains($normalized, 'cancel') || str_contains($normalized, 'expir')) {
            return 'cancelled';
        }
        if (str_contains($normalized, 'venc')) {
            return 'overdue';
        }
        if (str_contains($normalized, 'registr') || str_contains($normalized, 'emit') || str_contains($normalized, 'abert') || str_contains($normalized, 'ativo')) {
            return 'issued';
        }

        return $fallback !== '' ? $fallback : 'pending';
    }

    private function normalizeItauWebhookDate($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $formats = ['Y-m-d', 'd/m/Y', 'Y-m-d H:i:s', DateTimeInterface::ATOM];
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $value);
            if ($date instanceof DateTime) {
                return $date->format('Y-m-d');
            }
        }

        $timestamp = strtotime($value);
        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    private function normalizeItauWebhookAmount($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $clean = preg_replace('/[^\d,.-]/', '', (string) $value);
        if ($clean === null || $clean === '') {
            return null;
        }

        if (str_contains($clean, ',') && str_contains($clean, '.')) {
            $clean = str_replace('.', '', $clean);
        }
        $clean = str_replace(',', '.', $clean);

        return is_numeric($clean) ? (float) $clean : null;
    }

    private function extractFromPayload(array $payload, array $keys)
    {
        $keysMap = array_fill_keys($keys, true);
        $stack = [$payload];

        while ($stack !== []) {
            $item = array_pop($stack);
            if (!is_array($item)) {
                continue;
            }

            foreach ($item as $key => $value) {
                if (is_string($key) && isset($keysMap[$key])) {
                    return $value;
                }

                if (is_array($value)) {
                    $stack[] = $value;
                }
            }
        }

        return null;
    }

    private function hasFiscalTable(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'fiscal_invoices'");
        $stmt->execute();
        $cached = ((int) $stmt->fetchColumn()) > 0;

        return $cached;
    }

    private function hasBankSlipTable(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'bank_slips'");
        $stmt->execute();
        $cached = ((int) $stmt->fetchColumn()) > 0;

        return $cached;
    }

    private function bankSlipNossoNumeroColumnAvailable(): bool
    {
        if ($this->bankSlipNossoNumeroColumnExists !== null) {
            return $this->bankSlipNossoNumeroColumnExists;
        }

        return $this->bankSlipNossoNumeroColumnExists = $this->schemaColumnExists('bank_slips', 'nosso_numero');
    }

    private function fallbackPaymentMethods(): array
    {
        return [
            ['id' => null, 'name' => 'PIX', 'mode' => 'manual', 'channel' => 'pix', 'provider_key' => null, 'auto_created' => 0, 'is_active' => 1],
            ['id' => null, 'name' => 'Cartao de credito', 'mode' => 'manual', 'channel' => 'card', 'provider_key' => null, 'auto_created' => 0, 'is_active' => 1],
            ['id' => null, 'name' => 'Transferencia', 'mode' => 'manual', 'channel' => 'transfer', 'provider_key' => null, 'auto_created' => 0, 'is_active' => 1],
            ['id' => null, 'name' => 'Dinheiro', 'mode' => 'manual', 'channel' => 'cash', 'provider_key' => null, 'auto_created' => 0, 'is_active' => 1],
            ['id' => null, 'name' => 'Boleto', 'mode' => 'manual', 'channel' => 'boleto', 'provider_key' => null, 'auto_created' => 0, 'is_active' => 1],
        ];
    }

    private function statusIdBySlug(string $slug): ?int
    {
        $stmt = $this->db->prepare('SELECT id FROM kanban_status WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        $value = $stmt->fetchColumn();
        return $value ? (int) $value : null;
    }
}
