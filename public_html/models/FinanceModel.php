<?php

class FinanceModel extends BaseModel
{
    private StudentModel $students;
    private FiscalInvoiceService $fiscalService;
    private BoletoService $boletoService;
    private PaymentMethodModel $paymentMethods;

    public function __construct()
    {
        parent::__construct();
        $this->students = new StudentModel();
        $this->fiscalService = new FiscalInvoiceService();
        $this->boletoService = new BoletoService();
        $this->paymentMethods = new PaymentMethodModel();
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

    public function invoiceStats(): array
    {
        $this->refreshOverdueInvoices();
        $companyId = $this->companyId();

        $counts = [
            'open' => 0,
            'paid' => 0,
            'partial' => 0,
            'overdue' => 0,
            'draft' => 0,
        ];

        $stmt = $this->db->prepare('SELECT status, COUNT(*) AS qty FROM invoices WHERE company_id = :company_id GROUP BY status');
        $stmt->execute([':company_id' => $companyId]);
        foreach ($stmt->fetchAll() as $row) {
            $counts[$row['status']] = (int) $row['qty'];
        }

        $totals = [
            'paid_value' => (float) $this->scalar("SELECT COALESCE(SUM(paid_amount),0) FROM invoices WHERE company_id = :company_id AND status = 'paid'", [':company_id' => $companyId]),
            'overdue_value' => (float) $this->scalar("SELECT COALESCE(SUM(amount - paid_amount),0) FROM invoices WHERE company_id = :company_id AND status = 'overdue'", [':company_id' => $companyId]),
            'pending_value' => (float) $this->scalar("SELECT COALESCE(SUM(amount - paid_amount),0) FROM invoices WHERE company_id = :company_id AND status IN ('open','partial')", [':company_id' => $companyId]),
            'settled_today' => (int) $this->scalar("SELECT COUNT(*) FROM invoices WHERE company_id = :company_id AND status = 'paid' AND paid_at = CURDATE()", [':company_id' => $companyId]),
            'nfe_issued' => 0,
            'nfe_pending' => 0,
            'boletos_issued' => 0,
            'boletos_pending' => 0,
        ];

        if ($this->hasFiscalTable()) {
            $totals['nfe_issued'] = (int) $this->scalar("SELECT COUNT(*)
                FROM fiscal_invoices fi
                INNER JOIN invoices i ON i.id = fi.invoice_id
                WHERE i.company_id = :company_id
                  AND fi.status = 'issued'", [':company_id' => $companyId]);
            $totals['nfe_pending'] = (int) $this->scalar("SELECT COUNT(*)
                FROM fiscal_invoices fi
                INNER JOIN invoices i ON i.id = fi.invoice_id
                WHERE i.company_id = :company_id
                  AND fi.status IN ('pending','processing')", [':company_id' => $companyId]);
        }

        if ($this->hasBankSlipTable()) {
            $totals['boletos_issued'] = (int) $this->scalar("SELECT COUNT(*)
                FROM bank_slips bs
                INNER JOIN invoices i ON i.id = bs.invoice_id
                WHERE i.company_id = :company_id
                  AND bs.status IN ('issued','registered')", [':company_id' => $companyId]);
            $totals['boletos_pending'] = (int) $this->scalar("SELECT COUNT(*)
                FROM bank_slips bs
                INNER JOIN invoices i ON i.id = bs.invoice_id
                WHERE i.company_id = :company_id
                  AND bs.status IN ('pending','processing')", [':company_id' => $companyId]);
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
            ? "bs.id AS boleto_id, bs.provider AS boleto_provider, bs.status AS boleto_status, bs.external_id AS boleto_external_id, bs.digitable_line AS boleto_digitable_line, bs.barcode AS boleto_barcode, bs.boleto_url AS bank_slip_url, bs.pdf_url AS boleto_pdf_url, bs.error_message AS boleto_error_message, bs.last_attempt_at AS boleto_last_attempt_at"
            : "NULL AS boleto_id, NULL AS boleto_provider, NULL AS boleto_status, NULL AS boleto_external_id, NULL AS boleto_digitable_line, NULL AS boleto_barcode, NULL AS bank_slip_url, NULL AS boleto_pdf_url, NULL AS boleto_error_message, NULL AS boleto_last_attempt_at";

        $fiscalFields = $hasFiscalTable
            ? "fi.id AS fiscal_id, fi.status AS fiscal_status, fi.number AS fiscal_number, fi.provider AS fiscal_provider, fi.error_message AS fiscal_error_message, fi.last_attempt_at AS fiscal_last_attempt_at"
            : "NULL AS fiscal_id, NULL AS fiscal_status, NULL AS fiscal_number, NULL AS fiscal_provider, NULL AS fiscal_error_message, NULL AS fiscal_last_attempt_at";

        $paymentFields = $hasInvoicePaymentMethod
            ? "pm.id AS payment_method_id, pm.name AS payment_method_name, pm.mode AS payment_method_mode, pm.provider_key AS payment_method_provider_key, pm.channel AS payment_method_channel"
            : "NULL AS payment_method_id, NULL AS payment_method_name, NULL AS payment_method_mode, NULL AS payment_method_provider_key, NULL AS payment_method_channel";

        $dataSql = "SELECT i.*, s.full_name AS student_name, s.phone AS student_phone, s.primary_contact AS student_contact, s.email_primary AS student_email, {$bankFields}, {$fiscalFields}, {$paymentFields}
            FROM invoices i
            LEFT JOIN students s ON s.id = i.student_id
            {$bankJoin}
            {$fiscalJoin}
            {$paymentJoin}
            WHERE {$whereSql}
            ORDER BY i.id DESC";

        return $this->paginate($countSql, $dataSql, $params, $perPage, $page);
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
            return ['ok' => false, 'message' => 'Fatura nao encontrada.'];
        }

        $outstanding = max(0, (float) $invoice['amount'] - (float) $invoice['paid_amount']);
        if ($outstanding <= 0) {
            return ['ok' => false, 'message' => 'Esta fatura ja esta quitada.'];
        }

        if (($paymentMethodId === null || $paymentMethodId <= 0) && $this->invoicePaymentMethodsAvailable()) {
            $paymentMethodId = (int) ($invoice['payment_method_id'] ?? 0);
        }

        $method = $this->resolvePaymentMethodName($paymentMethodId, $method);

        $description = $notes !== '' ? $notes : 'Baixa manual da fatura ' . $invoice['invoice_number'];
        $paymentId = $this->recordBatchPayment([$invoiceId], $outstanding, $method, $paidAt, $description, $createdBy, $paymentMethodId);

        if ($paymentId <= 0) {
            return ['ok' => false, 'message' => 'Nao foi possivel efetuar a baixa da fatura.'];
        }

        $this->syncStudentFinanceKanban((int) $invoice['student_id'], $createdBy);

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
        int $createdBy,
        array $context = []
    ): array {
        $studentId = (int) $studentId;
        $negotiatedTotal = round((float) $negotiatedTotal, 2);
        $installments = max(1, min(60, (int) $installments));
        $firstDueDate = trim($firstDueDate);

        if ($studentId <= 0) {
            return ['ok' => false, 'message' => 'Aluno invalido para aplicar a negociacao.'];
        }

        if ($negotiatedTotal <= 0) {
            return ['ok' => false, 'message' => 'Valor negociado invalido.'];
        }

        $dueDate = DateTimeImmutable::createFromFormat('Y-m-d', $firstDueDate);
        if (!$dueDate || $dueDate->format('Y-m-d') !== $firstDueDate) {
            return ['ok' => false, 'message' => 'Primeiro vencimento invalido para gerar as parcelas.'];
        }

        $student = $this->students->find($studentId);
        if (!$student || (int) ($student['company_id'] ?? 0) !== $this->companyId()) {
            return ['ok' => false, 'message' => 'Aluno da negociacao nao encontrado nesta empresa.'];
        }

        $openStmt = $this->db->prepare("SELECT id, invoice_number, due_date, amount, paid_amount
            FROM invoices
            WHERE company_id = :company_id
              AND student_id = :student_id
              AND status IN ('open', 'partial', 'overdue')
            ORDER BY due_date ASC, id ASC");
        $openStmt->execute([
            ':company_id' => $this->companyId(),
            ':student_id' => $studentId,
        ]);
        $openRows = $openStmt->fetchAll();

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
            return ['ok' => false, 'message' => 'Nao existem titulos em aberto para aplicar esta negociacao.'];
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

        $paymentNotes = trim(implode(' | ', array_filter([
            'Compensacao automatica de titulos por aprovacao de fluxo mobile',
            $ticketCode !== '' ? ('Ticket ' . $ticketCode) : ($ticketId > 0 ? ('Ticket #' . $ticketId) : ''),
            'Aluno: ' . (string) ($student['full_name'] ?? ('ID ' . $studentId)),
        ])));

        $newInvoiceIds = [];
        $newInvoiceNumbers = [];
        $amounts = $this->splitAmount($negotiatedTotal, $installments);
        $paidAt = date('Y-m-d');

        try {
            $this->db->beginTransaction();

            $paymentId = $this->recordBatchPayment(
                $openInvoiceIds,
                $outstandingTotal,
                'NEGOCIACAO_APP',
                $paidAt,
                $paymentNotes,
                $createdBy
            );

            if ($paymentId <= 0) {
                throw new RuntimeException('Falha ao registrar pagamento de compensacao dos titulos antigos.');
            }

            foreach ($amounts as $idx => $amount) {
                $due = $dueDate->modify('+' . $idx . ' month')->format('Y-m-d');
                $newId = $this->createInvoice([
                    'student_id' => $studentId,
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
                'payment_id' => $paymentId,
                'closed_invoices_count' => count($openInvoiceIds),
                'closed_total' => $outstandingTotal,
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

            return ['ok' => false, 'message' => 'Nao foi possivel aplicar a negociacao no financeiro.'];
        }
    }

    public function generateFiscalInvoice(int $invoiceId, int $createdBy): array
    {
        if (!$this->hasFiscalTable()) {
            return ['ok' => false, 'message' => 'Estrutura fiscal indisponivel no banco. Execute a atualizacao SQL.'];
        }

        $invoice = $this->findInvoice($invoiceId);
        if (!$invoice) {
            return ['ok' => false, 'message' => 'Fatura nao encontrada.'];
        }

        if ($invoice['status'] !== 'paid') {
            return ['ok' => false, 'message' => 'Somente faturas pagas podem gerar nota fiscal.'];
        }

        $student = $this->students->find((int) $invoice['student_id']);
        if (!$student) {
            return ['ok' => false, 'message' => 'Aluno vinculado nao encontrado.'];
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

            $stmt->execute([
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
            ]);
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
                ':created_by' => $createdBy,
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
            return ['ok' => false, 'message' => 'Estrutura de boleto indisponivel no banco. Execute a atualizacao SQL.'];
        }

        $invoice = $this->findInvoice($invoiceId);
        if (!$invoice) {
            return ['ok' => false, 'message' => 'Fatura nao encontrada.'];
        }

        if ($invoice['status'] === 'paid') {
            return ['ok' => false, 'message' => 'Fatura paga nao permite gerar novo boleto.'];
        }

        $student = $this->students->find((int) $invoice['student_id']);
        if (!$student) {
            return ['ok' => false, 'message' => 'Aluno vinculado nao encontrado.'];
        }

        $existing = $this->bankSlipByInvoice($invoiceId);
        $payload = $this->boletoService->buildPayload($invoice, $student, $existing);
        $serviceResult = $this->boletoService->requestGeneration($payload, $existing);

        $status = (string) ($serviceResult['status'] ?? 'pending');
        $provider = $this->boletoService->provider();
        $now = now();

        $url = (string) ($serviceResult['boleto_url'] ?? '');
        $pdf = (string) ($serviceResult['pdf_url'] ?? '');
        $chosenUrl = $url !== '' ? $url : ($pdf !== '' ? $pdf : ((string) ($invoice['boleto_url'] ?? '')));
        $chosenUrl = $chosenUrl !== '' ? $chosenUrl : null;

        if ($existing) {
            $stmt = $this->db->prepare('UPDATE bank_slips SET
                provider = :provider,
                status = :status,
                external_id = :external_id,
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

            $stmt->execute([
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
            ]);
        } else {
            $stmt = $this->db->prepare('INSERT INTO bank_slips (
                invoice_id, provider, status, external_id, digitable_line, barcode,
                pix_qr_code, pix_copy_paste, boleto_url, pdf_url, amount, due_date,
                request_payload, response_payload, error_message, last_attempt_at,
                issued_at, expires_at, created_by, created_at, updated_at
            ) VALUES (
                :invoice_id, :provider, :status, :external_id, :digitable_line, :barcode,
                :pix_qr_code, :pix_copy_paste, :boleto_url, :pdf_url, :amount, :due_date,
                :request_payload, :response_payload, :error_message, :last_attempt_at,
                :issued_at, :expires_at, :created_by, :created_at, :updated_at
            )');

            $stmt->execute([
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
                ':created_by' => $createdBy,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
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

        return [
            'ok' => true,
            'status' => $status,
            'message' => $serviceResult['message'] ?? 'Solicitacao de boleto registrada.',
        ];
    }

    public function syncBankSlipStatus(int $invoiceId, int $changedBy): array
    {
        if (!$this->hasBankSlipTable()) {
            return ['ok' => false, 'message' => 'Estrutura de boleto indisponivel no banco. Execute a atualizacao SQL.'];
        }

        $record = $this->bankSlipByInvoice($invoiceId);
        if (!$record) {
            return ['ok' => false, 'message' => 'Boleto ainda nao foi gerado para esta fatura.'];
        }

        $serviceResult = $this->boletoService->requestStatus($record);
        $status = (string) ($serviceResult['status'] ?? $record['status']);
        $now = now();

        $stmt = $this->db->prepare('UPDATE bank_slips SET
            status = :status,
            external_id = :external_id,
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

        $stmt->execute([
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
        ]);

        $invoice = $this->findInvoice($invoiceId);
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
            }
        }

        $message = $serviceResult['message'] ?? ('Status do boleto atualizado para ' . $status . '.');
        return [
            'ok' => true,
            'status' => $status,
            'message' => $message,
        ];
    }

    public function reportOverview(array $filters): array
    {
        $startDate = $filters['start_date'];
        $endDate = $filters['end_date'];
        $companyId = $this->companyId();

        $invoiceWhere = [
            'i.company_id = :invoice_company_id',
            'i.due_date BETWEEN :start_date AND :end_date',
        ];
        $invoiceParams = [
            ':invoice_company_id' => $companyId,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
        ];

        if (!empty($filters['student_id'])) {
            $invoiceWhere[] = 'i.student_id = :student_id';
            $invoiceParams[':student_id'] = (int) $filters['student_id'];
        }

        if (!empty($filters['status'])) {
            $invoiceWhere[] = 'i.status = :invoice_status';
            $invoiceParams[':invoice_status'] = $filters['status'];
        }

        $invoiceWhereSql = implode(' AND ', $invoiceWhere);

        $totalInvoiced = (float) $this->scalar(
            "SELECT COALESCE(SUM(i.amount), 0) FROM invoices i WHERE {$invoiceWhereSql}",
            $invoiceParams
        );

        $pendingValue = (float) $this->scalar(
            "SELECT COALESCE(SUM(i.amount - i.paid_amount), 0)
             FROM invoices i
             WHERE {$invoiceWhereSql} AND i.status IN ('open','partial','overdue')",
            $invoiceParams
        );

        $overdueValue = (float) $this->scalar(
            "SELECT COALESCE(SUM(i.amount - i.paid_amount), 0)
             FROM invoices i
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

        $settledCount = (int) $this->scalar(
            "SELECT COUNT(*) FROM invoices i WHERE " . implode(' AND ', $settledWhere),
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

            $stmt = $this->db->prepare(
                "SELECT fi.status, COUNT(*) AS qty
                 FROM fiscal_invoices fi
                 INNER JOIN invoices i ON i.id = fi.invoice_id
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

        return [
            'cards' => [
                'total_invoiced' => $totalInvoiced,
                'total_received' => $totalReceived,
                'pending_value' => $pendingValue,
                'overdue_value' => $overdueValue,
                'settled_count' => $settledCount,
                'inadimplencia_percent' => $inadimplenciaPercent,
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
        } else {
            $where[] = "i.status IN ('open','partial','overdue')";
        }

        $whereSql = implode(' AND ', $where);

        $fiscalJoin = $hasFiscalTable ? "LEFT JOIN fiscal_invoices fi ON fi.invoice_id = i.id" : "";
        $fiscalFields = $hasFiscalTable
            ? "fi.status AS fiscal_status, fi.number AS fiscal_number"
            : "NULL AS fiscal_status, NULL AS fiscal_number";

        $countSql = "SELECT COUNT(*)
            FROM invoices i
            INNER JOIN students s ON s.id = i.student_id
            {$fiscalJoin}
            WHERE {$whereSql}";

        $dataSql = "SELECT
                i.id,
                i.invoice_number,
                i.due_date,
                i.amount,
                i.paid_amount,
                i.paid_at,
                i.status,
                (i.amount - i.paid_amount) AS outstanding_amount,
                GREATEST(DATEDIFF(CURDATE(), i.due_date), 0) AS days_overdue,
                s.full_name AS student_name,
                {$fiscalFields}
            FROM invoices i
            INNER JOIN students s ON s.id = i.student_id
            {$fiscalJoin}
            WHERE {$whereSql}
            ORDER BY i.due_date ASC, i.id DESC";

        return $this->paginate($countSql, $dataSql, $params, $perPage, $page);
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
            ':created_by' => $createdBy,
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

    public function syncStudentFinanceKanban(int $studentId, int $changedBy): void
    {
        $companyId = $this->companyId();
        $student = $this->students->find($studentId);
        if (!$student || (int) ($student['company_id'] ?? 0) !== $companyId) {
            return;
        }

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

        $openStmt = $this->db->prepare("SELECT COUNT(*)
            FROM invoices
            WHERE company_id = :company_id
              AND student_id = :student_id
              AND status IN ('open','partial','overdue')");
        $openStmt->execute([
            ':company_id' => $companyId,
            ':student_id' => $studentId,
        ]);
        $openCount = (int) $openStmt->fetchColumn();

        $targetStatusId = null;
        if ($overdueCount > 0) {
            $targetStatusId = $this->statusIdBySlug('inadimplente');
        } elseif ($openCount === 0) {
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

        $reason = $overdueCount > 0 ? 'Atualizacao automatica por inadimplencia' : 'Atualizacao automatica por regularizacao';
        $this->students->registerKanbanHistory($studentId, (int) $student['kanban_status_id'], (int) $targetStatusId, $changedBy, $reason);
    }

    private function scalar(string $sql, array $params = [])
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
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
