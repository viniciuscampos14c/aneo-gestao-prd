<?php

class FinanceController extends BaseController
{
    private FinanceModel $finance;
    private SupplierModel $suppliers;
    private PayableModel $payables;
    private PaymentMethodModel $paymentMethods;
    private AuditLogService $audit;

    public function __construct()
    {
        $this->finance = new FinanceModel();
        $this->suppliers = new SupplierModel();
        $this->payables = new PayableModel();
        $this->paymentMethods = new PaymentMethodModel();
        $this->audit = new AuditLogService();
    }

    public function invoices(): void
    {
        require_auth();
        require_permission('finance');

        $filters = $this->collectFinanceDateFilters();
        $filters['q'] = trim((string) request('q', ''));
        $filters['status'] = trim((string) request('status', ''));
        $filters['student_id'] = request('student_id', '');

        $perPage = (int) request('per_page', config('app.default_pagination', 50));
        if (!in_array($perPage, config('app.pagination_options', [50, 100, 200]), true)) {
            $perPage = 50;
        }
        $page = max(1, (int) request('page', 1));

        $result = $this->finance->listInvoices($filters, $perPage, $page);

        $this->render('finance/invoices', [
            'title' => 'Faturas',
            'filters' => $filters,
            'stats' => $this->finance->invoiceStats($filters),
            'rows' => $result['rows'],
            'meta' => $result['meta'],
            'students' => $this->finance->listStudents(),
            'paginationOptions' => config('app.pagination_options', [50, 100, 200]),
            'boletosAvailable' => $this->finance->bankSlipAvailable(),
            'invoicePaymentMethodsAvailable' => $this->finance->invoicePaymentMethodsAvailable(),
        ]);
    }

    public function reports(): void
    {
        require_auth();
        require_permission('finance');

        $filters = $this->collectReportFilters();
        $tab = $this->reportTab();

        $perPage = (int) request('per_page', config('app.default_pagination', 50));
        if (!in_array($perPage, config('app.pagination_options', [50, 100, 200]), true)) {
            $perPage = 50;
        }
        $page = max(1, (int) request('page', 1));

        $overview = null;
        $receipts = ['rows' => [], 'meta' => pagination_meta(0, $perPage, 1)];
        $receivables = ['rows' => [], 'meta' => pagination_meta(0, $perPage, 1)];
        $payablesReport = ['rows' => [], 'meta' => pagination_meta(0, $perPage, 1)];
        $aging = null;
        $cashflow = null;
        $fiscal = ['rows' => [], 'meta' => pagination_meta(0, $perPage, 1)];

        if ($tab === 'overview') {
            $overview = $this->finance->reportOverview($filters);
        } elseif ($tab === 'receipts') {
            $receipts = $this->finance->reportReceipts($filters, $perPage, $page);
        } elseif ($tab === 'receivables') {
            $receivables = $this->finance->reportReceivables($filters, $perPage, $page);
        } elseif ($tab === 'payables') {
            $payablesReport = $this->finance->reportPayables($filters, $perPage, $page);
        } elseif ($tab === 'aging') {
            $aging = $this->finance->reportAging($filters);
        } elseif ($tab === 'cashflow') {
            $cashflow = $this->finance->reportCashflow($filters);
        } elseif ($tab === 'fiscal') {
            $fiscal = $this->finance->reportFiscal($filters, $perPage, $page);
        }

        $this->render('finance/reports', [
            'title' => 'Relatorios Financeiros',
            'tab' => $tab,
            'filters' => $filters,
            'overview' => $overview,
            'receipts' => $receipts,
            'receivables' => $receivables,
            'payablesReport' => $payablesReport,
            'aging' => $aging,
            'cashflow' => $cashflow,
            'fiscal' => $fiscal,
            'students' => $this->finance->listStudents(),
            'suppliers' => $this->suppliers->tableExists() ? $this->suppliers->activeByCompany((int) (current_company_id() ?? 0)) : [],
            'paymentMethodOptions' => $this->finance->paymentMethodFilterOptions(),
            'paginationOptions' => config('app.pagination_options', [50, 100, 200]),
            'fiscalAvailable' => $this->finance->fiscalAvailable(),
        ]);
    }

    public function exportReports(): void
    {
        require_auth();
        require_permission('finance.reports.export');

        $filters = $this->collectReportFilters();
        $tab = $this->reportTab();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=relatorio_financeiro_' . $tab . '_' . date('Ymd_His') . '.csv');

        $out = fopen('php://output', 'w');

        if ($tab === 'overview') {
            $overview = $this->finance->reportOverview($filters);
            fputcsv($out, ['Metrica', 'Valor'], ';');
            fputcsv($out, ['Periodo', $filters['start_date'] . ' ate ' . $filters['end_date']], ';');
            fputcsv($out, ['Faturado', $overview['cards']['total_invoiced']], ';');
            fputcsv($out, ['Recebido', $overview['cards']['total_received']], ';');
            fputcsv($out, ['Pendente', $overview['cards']['pending_value']], ';');
            fputcsv($out, ['Vencido', $overview['cards']['overdue_value']], ';');
            fputcsv($out, ['Baixadas no periodo', $overview['cards']['settled_count']], ';');
            fputcsv($out, ['Inadimplencia %', $overview['cards']['inadimplencia_percent']], ';');
            fputcsv($out, ['NF-e emitidas', $overview['nfe']['issued']], ';');
            fputcsv($out, ['NF-e pendentes', $overview['nfe']['pending']], ';');
            fputcsv($out, ['NF-e com falha', $overview['nfe']['failed']], ';');
        } elseif ($tab === 'receipts') {
            $rows = $this->finance->reportReceipts($filters, 100000, 1)['rows'];
            fputcsv($out, ['ID', 'Referencia', 'Data', 'Metodo', 'Valor', 'Aplicado', 'Faturas', 'Alunos', 'Observacoes'], ';');
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['id'],
                    $row['payment_ref'],
                    $row['paid_at'],
                    $row['method'],
                    $row['amount'],
                    $row['applied_amount'],
                    $row['invoices'],
                    $row['students'],
                    $row['notes'],
                ], ';');
            }
        } elseif ($tab === 'receivables') {
            $rows = $this->finance->reportReceivables($filters, 100000, 1)['rows'];
            fputcsv($out, ['ID', 'Fatura', 'Parcela', 'Aluno', 'Vencimento', 'Valor', 'Pago', 'Saldo', 'Status', 'Dias em atraso', 'Data baixa', 'NF status'], ';');
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['id'],
                    $row['invoice_number'],
                    $row['installment_label'] ?? '',
                    $row['student_name'],
                    $row['due_date'],
                    $row['amount'],
                    $row['paid_amount'],
                    $row['outstanding_amount'],
                    $row['status'],
                    $row['days_overdue'],
                    $row['paid_at'],
                    $row['fiscal_status'],
                ], ';');
            }
        } elseif ($tab === 'payables') {
            $rows = $this->finance->reportPayables($filters, 100000, 1)['rows'];
            fputcsv($out, ['ID', 'Numero', 'Fornecedor', 'Descricao', 'Categoria', 'Competencia', 'Vencimento', 'Valor', 'Pago', 'Saldo', 'Status', 'Data baixa', 'Metodo'], ';');
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['id'],
                    $row['payable_number'],
                    $row['supplier_name'],
                    $row['description'],
                    $row['category'],
                    $row['competence_date'],
                    $row['due_date'],
                    $row['amount'],
                    $row['paid_amount'],
                    $row['outstanding_amount'],
                    $row['status'],
                    $row['paid_at'],
                    $row['payment_method_name'],
                ], ';');
            }
        } elseif ($tab === 'aging') {
            $aging = $this->finance->reportAging($filters);
            fputcsv($out, ['Faixa', 'Quantidade', 'Valor'], ';');
            fputcsv($out, ['A vencer/Atual', $aging['buckets']['current']['qty'], $aging['buckets']['current']['amount']], ';');
            fputcsv($out, ['1-30 dias', $aging['buckets']['1_30']['qty'], $aging['buckets']['1_30']['amount']], ';');
            fputcsv($out, ['31-60 dias', $aging['buckets']['31_60']['qty'], $aging['buckets']['31_60']['amount']], ';');
            fputcsv($out, ['61-90 dias', $aging['buckets']['61_90']['qty'], $aging['buckets']['61_90']['amount']], ';');
            fputcsv($out, ['90+ dias', $aging['buckets']['90_plus']['qty'], $aging['buckets']['90_plus']['amount']], ';');
            fputcsv($out, [], ';');
            fputcsv($out, ['Top Devedores'], ';');
            fputcsv($out, ['Aluno', 'Faturas', 'Saldo em aberto', 'Maior atraso (dias)'], ';');
            foreach ($aging['top_debtors'] as $row) {
                fputcsv($out, [
                    $row['full_name'],
                    $row['invoices_qty'],
                    $row['outstanding_amount'],
                    $row['max_days_overdue'],
                ], ';');
            }
        } elseif ($tab === 'cashflow') {
            $cashflow = $this->finance->reportCashflow($filters);
            fputcsv($out, ['Data', 'Entradas', 'Saidas', 'Saldo Liquido'], ';');
            foreach ($cashflow['rows'] as $row) {
                fputcsv($out, [
                    $row['date'],
                    $row['incoming'],
                    $row['outgoing'],
                    $row['net'],
                ], ';');
            }
        } elseif ($tab === 'fiscal') {
            $rows = $this->finance->reportFiscal($filters, 100000, 1)['rows'];
            fputcsv($out, ['ID', 'Fatura', 'Aluno', 'Valor', 'Status fatura', 'Data baixa', 'Provider', 'Status NF', 'Numero NF', 'ID Externo', 'Ultima tentativa', 'Emitida em', 'Erro'], ';');
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['id'],
                    $row['invoice_number'],
                    $row['student_name'],
                    $row['amount'],
                    $row['invoice_status'],
                    $row['paid_at'],
                    $row['provider'],
                    $row['fiscal_status'],
                    $row['fiscal_number'],
                    $row['external_id'],
                    $row['last_attempt_at'],
                    $row['issued_at'],
                    $row['error_message'],
                ], ';');
            }
        }

        fclose($out);
        exit;
    }

    public function generateBankSlip(): void
    {
        require_auth();
        require_permission('finance.invoice.boleto.generate');
        csrf_validate();

        $invoiceId = (int) post('invoice_id');
        if ($this->finance->invoicePaymentMethodsAvailable()) {
            $invoice = $this->finance->findInvoice($invoiceId);
            if ($invoice && (int) ($invoice['payment_method_id'] ?? 0) > 0) {
                $method = $this->finance->findPaymentMethod((int) $invoice['payment_method_id']);
                if ($method && (string) ($method['mode'] ?? 'manual') !== 'integrated') {
                    $this->error('Esta fatura usa forma de pagamento manual. A geracao automatica de boleto exige forma integrada (contrato).');
                    $this->redirect('finance/invoices');
                }
            }
        }

        $before = $this->invoiceSnapshotById($invoiceId);
        $result = $this->finance->generateBankSlip($invoiceId, (int) current_user()['id']);

        if (!$result['ok']) {
            $this->error($result['message']);
            $this->redirect('finance/invoices');
        }

        $after = $this->invoiceSnapshotById($invoiceId);
        $this->auditInvoiceEvent('generate_boleto', $invoiceId, $before, $after, (string) ($result['message'] ?? 'Boleto gerado.'));

        $this->success($result['message']);
        $this->redirect('finance/invoices');
    }

    public function syncBankSlip(): void
    {
        require_auth();
        require_permission('finance.invoice.boleto.sync');
        csrf_validate();

        $invoiceId = (int) post('invoice_id');
        if ($this->finance->invoicePaymentMethodsAvailable()) {
            $invoice = $this->finance->findInvoice($invoiceId);
            if ($invoice && (int) ($invoice['payment_method_id'] ?? 0) > 0) {
                $method = $this->finance->findPaymentMethod((int) $invoice['payment_method_id']);
                if ($method && (string) ($method['mode'] ?? 'manual') !== 'integrated') {
                    $this->error('Esta fatura usa forma de pagamento manual. A sincronizacao automatica exige forma integrada (contrato).');
                    $this->redirect('finance/invoices');
                }
            }
        }

        $before = $this->invoiceSnapshotById($invoiceId);
        $result = $this->finance->syncBankSlipStatus($invoiceId, (int) current_user()['id']);

        if (!$result['ok']) {
            $this->error($result['message']);
            $this->redirect('finance/invoices');
        }

        $after = $this->invoiceSnapshotById($invoiceId);
        $this->auditInvoiceEvent('sync_boleto', $invoiceId, $before, $after, (string) ($result['message'] ?? 'Boleto sincronizado.'));

        $this->success($result['message']);
        $this->redirect('finance/invoices');
    }

    public function itauWebhook(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->json(['ok' => false, 'message' => 'Metodo invalido.'], 405);
        }

        $rawBody = (string) file_get_contents('php://input');
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            $this->json(['ok' => false, 'message' => 'Payload JSON invalido.'], 400);
        }

        $providedToken = $this->itauWebhookTokenFromRequest($payload);
        if ($providedToken === '') {
            $this->json(['ok' => false, 'message' => 'Token do webhook Itau obrigatorio.'], 401);
        }

        $companyIds = (new CompanyIntegrationModel())->findCompanyIdsByToken('itau', 'webhook_token', $providedToken);
        if ($companyIds === []) {
            $this->json(['ok' => false, 'message' => 'Token do webhook Itau invalido.'], 401);
        }

        $lastResult = null;
        foreach ($companyIds as $companyId) {
            $result = (new FinanceModel())->useCompany($companyId)->processItauWebhook($payload, $companyId);
            if (!empty($result['ok'])) {
                $this->json($result, 200);
            }

            $lastResult = $result;
            if (($result['message'] ?? '') !== 'Boleto nao encontrado para o webhook Itau.') {
                break;
            }
        }

        $this->json(
            $lastResult ?: ['ok' => false, 'message' => 'Boleto nao encontrado para o webhook Itau.'],
            422
        );
    }

    public function issueDueBankSlips(): void
    {
        require_auth();
        require_permission('finance.invoice.boleto.generate');
        csrf_validate();

        $before = [
            'company_id' => (int) (current_company_id() ?? 0),
            'executed_at' => now(),
        ];

        $result = $this->finance->issueDueBankSlips((int) current_user()['id'], 10);

        $this->audit->log([
            'module' => 'finance.faturas',
            'action' => 'issue_due_bank_slips',
            'entity_type' => 'invoice_batch',
            'entity_id' => null,
            'entity_label' => 'Emissao automatica de boletos Itau',
            'description' => (string) ($result['message'] ?? 'Processamento da fila de boletos.'),
            'before' => $before,
            'after' => $result,
            'metadata' => ['days_before' => 10],
            'company_id' => (int) (current_company_id() ?? 0),
        ]);

        if (!($result['ok'] ?? false)) {
            $this->error((string) ($result['message'] ?? 'Nao foi possivel emitir os boletos da janela.'));
            $this->redirect('finance/invoices');
        }

        $this->success((string) ($result['message'] ?? 'Fila de boletos processada com sucesso.'));
        $this->redirect('finance/invoices');
    }

    private function itauWebhookTokenFromRequest(array $payload): string
    {
        $token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ($payload['token'] ?? '')));
        if ($token !== '') {
            return $token;
        }

        $headers = function_exists('getallheaders') ? getallheaders() : [];
        if (!is_array($headers)) {
            return '';
        }

        foreach ($headers as $name => $value) {
            $normalized = strtolower(str_replace(['_', '-'], '', (string) $name));
            if (in_array($normalized, ['xitauwebhooktoken', 'xwebhooktoken'], true)) {
                return trim((string) $value);
            }
        }

        return '';
    }

    public function createInvoice(): void
    {
        require_auth();
        require_permission('finance.invoice.create');

        $this->render('finance/invoice_form', [
            'title' => 'Nova Fatura',
            'invoice' => null,
            'students' => $this->finance->listStudents(),
            'paymentMethods' => $this->finance->paymentMethodsForInvoiceSelection(),
            'paymentMethodsAvailable' => $this->finance->invoicePaymentMethodsAvailable(),
            'action' => route('finance/invoices/store'),
            'actionLabel' => 'Salvar Fatura',
            'isEdit' => false,
        ]);
    }

    public function editInvoice(): void
    {
        require_admin();

        $invoiceId = (int) request('id');
        $invoice = $this->finance->findInvoice($invoiceId);
        if (!$invoice) {
            $this->error('Fatura nao encontrada para edicao.');
            $this->redirect('finance/invoices');
        }

        $this->render('finance/invoice_form', [
            'title' => 'Editar Fatura',
            'invoice' => $invoice,
            'students' => $this->finance->listStudents(),
            'paymentMethods' => $this->finance->paymentMethodsForInvoiceSelection(),
            'paymentMethodsAvailable' => $this->finance->invoicePaymentMethodsAvailable(),
            'action' => route('finance/invoices/update&id=' . $invoiceId),
            'actionLabel' => 'Salvar Alteracoes',
            'isEdit' => true,
        ]);
    }

    public function storeInvoice(): void
    {
        require_auth();
        require_permission('finance.invoice.create');
        csrf_validate();

        $data = [
            'student_id' => (int) post('student_id'),
            'payment_method_id' => (int) post('payment_method_id'),
            'due_date' => trim((string) post('due_date')),
            'amount' => parse_decimal((string) post('amount', '0')),
            'tax_amount' => parse_decimal((string) post('tax_amount', '0')),
            'status' => trim((string) post('status', 'open')),
            'tags' => trim((string) post('tags')),
            'project_name' => trim((string) post('project_name')),
            'boleto_url' => trim((string) post('boleto_url')),
            'is_recurring' => post('is_recurring') ? 1 : 0,
            'recurrence_interval' => trim((string) post('recurrence_interval', 'monthly')),
        ];

        if ($data['student_id'] <= 0 || $data['due_date'] === '' || $data['amount'] <= 0) {
            $this->error('Preencha aluno, vencimento e quantia.');
            $this->redirect('finance/invoices/create');
        }

        if ($this->finance->invoicePaymentMethodsAvailable()) {
            if ($data['payment_method_id'] <= 0) {
                $this->error('Selecione a forma de pagamento da fatura.');
                $this->redirect('finance/invoices/create');
            }

            $method = $this->finance->findPaymentMethod($data['payment_method_id']);
            if (!$method || (int) ($method['is_active'] ?? 0) !== 1) {
                $this->error('Forma de pagamento invalida ou inativa.');
                $this->redirect('finance/invoices/create');
            }
        } else {
            $data['payment_method_id'] = 0;
        }

        if ($data['boleto_url'] !== '' && !filter_var($data['boleto_url'], FILTER_VALIDATE_URL)) {
            $this->error('Informe um link de boleto valido (URL completa).');
            $this->redirect('finance/invoices/create');
        }

        $id = $this->finance->createInvoice($data, (int) current_user()['id']);
        $this->finance->syncStudentFinanceKanban((int) $data['student_id'], (int) current_user()['id']);

        $after = $this->invoiceSnapshotById($id);
        $this->auditInvoiceEvent('create', $id, [], $after, 'Fatura criada.');

        $this->success('Fatura criada #' . $id . '.');
        $this->redirect('finance/invoices');
    }

    public function updateInvoice(): void
    {
        require_admin();
        csrf_validate();

        $invoiceId = (int) request('id');
        $invoice = $this->finance->findInvoice($invoiceId);
        if (!$invoice) {
            $this->error('Fatura nao encontrada.');
            $this->redirect('finance/invoices');
        }

        $data = [
            'student_id' => (int) post('student_id'),
            'payment_method_id' => (int) post('payment_method_id'),
            'due_date' => trim((string) post('due_date')),
            'amount' => parse_decimal((string) post('amount', '0')),
            'tax_amount' => parse_decimal((string) post('tax_amount', '0')),
            'status' => trim((string) post('status', 'open')),
            'tags' => trim((string) post('tags')),
            'project_name' => trim((string) post('project_name')),
            'boleto_url' => trim((string) post('boleto_url')),
            'is_recurring' => post('is_recurring') ? 1 : 0,
            'recurrence_interval' => trim((string) post('recurrence_interval', 'monthly')),
        ];

        if ($data['student_id'] <= 0 || $data['due_date'] === '' || $data['amount'] <= 0) {
            $this->error('Preencha aluno, vencimento e quantia.');
            $this->redirect('finance/invoices/edit&id=' . $invoiceId);
        }

        if ($this->finance->invoicePaymentMethodsAvailable()) {
            if ($data['payment_method_id'] <= 0) {
                $this->error('Selecione a forma de pagamento da fatura.');
                $this->redirect('finance/invoices/edit&id=' . $invoiceId);
            }

            $method = $this->finance->findPaymentMethod($data['payment_method_id']);
            if (!$method || (int) ($method['is_active'] ?? 0) !== 1) {
                $this->error('Forma de pagamento invalida ou inativa.');
                $this->redirect('finance/invoices/edit&id=' . $invoiceId);
            }
        } else {
            $data['payment_method_id'] = 0;
        }

        if ($data['boleto_url'] !== '' && !filter_var($data['boleto_url'], FILTER_VALIDATE_URL)) {
            $this->error('Informe um link de boleto valido (URL completa).');
            $this->redirect('finance/invoices/edit&id=' . $invoiceId);
        }

        $before = $this->invoiceSnapshot($invoice);
        $result = $this->finance->updateInvoice($invoiceId, $data, (int) current_user()['id']);
        if (!($result['ok'] ?? false)) {
            $this->error((string) ($result['message'] ?? 'Nao foi possivel atualizar a fatura.'));
            $this->redirect('finance/invoices/edit&id=' . $invoiceId);
        }

        $after = $this->invoiceSnapshotById($invoiceId);
        $this->auditInvoiceEvent('update', $invoiceId, $before, $after, 'Fatura editada por administrador.');

        $this->success((string) ($result['message'] ?? 'Fatura atualizada com sucesso.'));
        $this->redirect('finance/invoices');
    }

    public function deleteInvoice(): void
    {
        require_auth();
        require_permission('finance.invoice.delete');
        csrf_validate();

        $id = (int) post('id');
        $invoice = $this->finance->findInvoice($id);
        $before = $this->invoiceSnapshot($invoice);

        if ($invoice) {
            $this->finance->deleteInvoice($id);
            $this->finance->syncStudentFinanceKanban((int) $invoice['student_id'], (int) current_user()['id']);
            $this->auditInvoiceEvent('delete', $id, $before, [], 'Fatura removida.');
            $this->success('Fatura removida.');
        }

        $this->redirect('finance/invoices');
    }

    public function settleInvoice(): void
    {
        require_auth();
        require_permission('finance.invoice.settle');
        csrf_validate();

        $invoiceId = (int) post('invoice_id');
        $method = trim((string) post('method', 'PIX'));
        $paymentMethodId = (int) post('payment_method_id');
        $paidAt = trim((string) post('paid_at', date('Y-m-d')));
        $notes = trim((string) post('notes'));
        $before = $this->invoiceSnapshotById($invoiceId);

        if ($this->finance->invoicePaymentMethodsAvailable()) {
            $method = $this->finance->resolvePaymentMethodName($paymentMethodId, $method);
        }

        $result = $this->finance->settleInvoice(
            $invoiceId,
            $method,
            $paidAt,
            $notes,
            (int) current_user()['id'],
            $paymentMethodId > 0 ? $paymentMethodId : null
        );

        if (!$result['ok']) {
            $this->error($result['message']);
            $this->redirect('finance/invoices');
        }

        $after = $this->invoiceSnapshotById($invoiceId);
        $this->auditInvoiceEvent('settle', $invoiceId, $before, $after, 'Baixa de fatura registrada.', [
            'payment_id' => (int) ($result['payment_id'] ?? 0),
            'method' => $method,
            'payment_method_id' => $paymentMethodId > 0 ? $paymentMethodId : null,
            'paid_at' => $paidAt,
        ]);

        $this->success('Baixa efetuada com sucesso. Pagamento #' . (int) $result['payment_id'] . ' registrado.');
        $this->redirect('finance/invoices');
    }

    public function generateFiscalInvoice(): void
    {
        require_auth();
        require_permission('finance.invoice.nfe');
        csrf_validate();

        $invoiceId = (int) post('invoice_id');
        $before = $this->invoiceSnapshotById($invoiceId);
        $result = $this->finance->generateFiscalInvoice($invoiceId, (int) current_user()['id']);

        if (!$result['ok']) {
            $this->error($result['message']);
            $this->redirect('finance/invoices');
        }

        $after = $this->invoiceSnapshotById($invoiceId);
        $this->auditInvoiceEvent('generate_nfe', $invoiceId, $before, $after, (string) ($result['message'] ?? 'Emissao fiscal solicitada.'));

        $this->success($result['message']);
        $this->redirect('finance/invoices');
    }

    public function exportInvoices(): void
    {
        require_auth();
        require_permission('finance.invoice.export');

        $filters = $this->collectFinanceDateFilters();
        $filters['q'] = trim((string) request('q', ''));
        $filters['status'] = trim((string) request('status', ''));
        $filters['student_id'] = request('student_id', '');

        $result = $this->finance->listInvoices($filters, 10000, 1);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=faturas_' . date('Ymd_His') . '.csv');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Numero', 'Parcela', 'Aluno', 'Vencimento', 'Quantia', 'Pago', 'Status', 'Forma de Pagamento', 'Data Baixa', 'Boleto Status', 'Boleto ID Externo', 'Link Boleto', 'NF Status', 'Imposto', 'Projeto', 'Tags'], ';');

        foreach ($result['rows'] as $row) {
            fputcsv($out, [
                $row['id'],
                $row['invoice_number'],
                $row['installment_label'] ?? '',
                $row['student_name'],
                $row['due_date'],
                $row['amount'],
                $row['paid_amount'],
                $row['status'],
                $row['payment_method_name'] ?? '',
                $row['paid_at'] ?? '',
                $row['boleto_status'] ?? '',
                $row['boleto_external_id'] ?? '',
                ($row['boleto_url'] ?? '') ?: ($row['bank_slip_url'] ?? '') ?: ($row['boleto_pdf_url'] ?? ''),
                $row['fiscal_status'] ?? '',
                $row['tax_amount'],
                $row['project_name'],
                $row['tags'],
            ], ';');
        }

        fclose($out);
        exit;
    }

    public function generateRecurring(): void
    {
        require_auth();
        require_permission('finance.invoice.recurrence');
        csrf_validate();

        $ref = trim((string) post('reference_month'));
        if ($ref === '') {
            $ref = date('Y-m-01');
        } else {
            $ref .= '-01';
        }

        $qty = $this->finance->generateRecurringInvoices($ref, (int) current_user()['id']);

        $students = $this->finance->listStudents();
        foreach ($students as $student) {
            $this->finance->syncStudentFinanceKanban((int) $student['id'], (int) current_user()['id']);
        }

        $this->audit->log([
            'module' => 'finance.faturas',
            'action' => 'generate_recurring',
            'entity_type' => 'invoice_batch',
            'entity_id' => null,
            'entity_label' => 'Recorrencia ' . date('m/Y', strtotime($ref)),
            'description' => $qty . ' fatura(s) recorrente(s) gerada(s).',
            'before' => [],
            'after' => ['generated_qty' => $qty],
            'metadata' => ['reference_month' => date('Y-m', strtotime($ref))],
            'company_id' => (int) (current_company_id() ?? 0),
        ]);

        $this->success($qty . ' fatura(s) recorrente(s) gerada(s).');
        $this->redirect('finance/invoices');
    }

    public function payments(): void
    {
        require_auth();
        require_permission('finance');

        $filters = $this->collectFinanceDateFilters();
        $filters['q'] = trim((string) request('q', ''));
        $filters['student_id'] = request('student_id', '');

        $perPage = (int) request('per_page', config('app.default_pagination', 50));
        if (!in_array($perPage, config('app.pagination_options', [50, 100, 200]), true)) {
            $perPage = 50;
        }
        $page = max(1, (int) request('page', 1));

        $result = $this->finance->listPayments($filters, $perPage, $page);

        $poolBaseFilters = [
            'period' => $filters['period'],
            'start_date' => $filters['start_date'],
            'end_date' => $filters['end_date'],
            'q' => $filters['q'],
            'student_id' => $filters['student_id'],
        ];
        $openInvoices = $this->finance->listInvoices($poolBaseFilters + ['status' => 'open'], 300, 1);
        $partialInvoices = $this->finance->listInvoices($poolBaseFilters + ['status' => 'partial'], 300, 1);
        $overdueInvoices = $this->finance->listInvoices($poolBaseFilters + ['status' => 'overdue'], 300, 1);

        $invoicePool = [];
        foreach (array_merge($openInvoices['rows'], $partialInvoices['rows'], $overdueInvoices['rows']) as $row) {
            $invoicePool[(int) ($row['id'] ?? 0)] = $row;
        }
        $invoicePool = array_values($invoicePool);

        $this->render('finance/payments', [
            'title' => 'Pagamentos',
            'filters' => $filters,
            'rows' => $result['rows'],
            'meta' => $result['meta'],
            'invoicesPool' => $invoicePool,
            'students' => $this->finance->listStudents(),
            'paymentMethods' => $this->finance->paymentMethodsForInvoiceSelection(),
            'paymentMethodsAvailable' => $this->finance->paymentsPaymentMethodsAvailable(),
            'paginationOptions' => config('app.pagination_options', [50, 100, 200]),
        ]);
    }

    public function storePayment(): void
    {
        require_auth();
        require_permission('finance.payment.create');
        csrf_validate();

        $invoiceIds = (array) post('invoice_ids', []);
        $amount = parse_decimal((string) post('amount', '0'));
        $paymentMethodId = (int) post('payment_method_id');
        $method = trim((string) post('method', 'PIX'));
        $paidAt = trim((string) post('paid_at', date('Y-m-d')));
        $notes = trim((string) post('notes'));

        if ($amount <= 0 || $invoiceIds === []) {
            $this->error('Selecione faturas e informe um valor.');
            $this->redirect('finance/payments');
        }

        if ($this->finance->paymentsPaymentMethodsAvailable()) {
            if ($paymentMethodId <= 0) {
                $this->error('Selecione a forma de pagamento.');
                $this->redirect('finance/payments');
            }

            $selectedMethod = $this->finance->findPaymentMethod($paymentMethodId);
            if (!$selectedMethod || (int) ($selectedMethod['is_active'] ?? 0) !== 1) {
                $this->error('Forma de pagamento invalida ou inativa.');
                $this->redirect('finance/payments');
            }

            $method = trim((string) ($selectedMethod['name'] ?? 'PIX'));
        } else {
            $paymentMethodId = 0;
        }

        $invoiceIds = array_values(array_unique(array_filter(array_map('intval', $invoiceIds), fn ($id) => $id > 0)));
        $before = [];
        foreach ($invoiceIds as $invoiceId) {
            $snap = $this->invoiceSnapshotById($invoiceId);
            if ($snap) {
                $before[(string) $invoiceId] = $snap;
            }
        }

        $paymentId = $this->finance->recordBatchPayment(
            $invoiceIds,
            $amount,
            $method,
            $paidAt,
            $notes,
            (int) current_user()['id'],
            $paymentMethodId > 0 ? $paymentMethodId : null
        );

        if ($paymentId <= 0) {
            $this->error('Nao foi possivel registrar pagamento.');
            $this->redirect('finance/payments');
        }

        $after = [];
        foreach ($invoiceIds as $invoiceId) {
            $snap = $this->invoiceSnapshotById($invoiceId);
            if ($snap) {
                $after[(string) $invoiceId] = $snap;
            }
        }

        $this->audit->log([
            'module' => 'finance.pagamentos',
            'action' => 'create',
            'entity_type' => 'payment_batch',
            'entity_id' => $paymentId,
            'entity_label' => 'Pagamento #' . $paymentId,
            'description' => 'Pagamento em lote registrado.',
            'before' => ['invoices' => $before],
            'after' => ['invoices' => $after],
            'metadata' => [
                'invoice_ids' => $invoiceIds,
                'amount' => $amount,
                'method' => $method,
                'payment_method_id' => $paymentMethodId > 0 ? $paymentMethodId : null,
                'paid_at' => $paidAt,
            ],
            'company_id' => (int) (current_company_id() ?? 0),
        ]);

        $this->success('Pagamento registrado #' . $paymentId . '.');
        $this->redirect('finance/payments');
    }

    public function paymentMethods(): void
    {
        require_auth();
        require_permission('finance');

        $available = $this->finance->paymentMethodsTableAvailable();
        $rows = $available ? $this->finance->paymentMethodsForManagement() : [];

        $this->render('finance/payment_methods', [
            'title' => 'Formas de Pagamento',
            'available' => $available,
            'rows' => $rows,
        ]);
    }

    public function storePaymentMethod(): void
    {
        require_auth();
        require_permission('finance');
        csrf_validate();

        if (!$this->finance->paymentMethodsTableAvailable()) {
            $this->error('Estrutura de formas de pagamento indisponivel no banco. Execute a migration.');
            $this->redirect('finance/payment-methods');
        }

        $name = trim((string) post('name'));
        $channel = strtolower(trim((string) post('channel', 'other')));
        if ($name === '') {
            $this->error('Informe o nome da forma de pagamento.');
            $this->redirect('finance/payment-methods');
        }

        $allowedChannels = ['pix', 'card', 'transfer', 'cash', 'boleto', 'other'];
        if (!in_array($channel, $allowedChannels, true)) {
            $channel = 'other';
        }

        $companyId = (int) (current_company_id() ?? 0);
        $createdBy = (int) (current_user()['id'] ?? 0);
        $id = $this->paymentMethods->createManual($companyId, $name, $channel, $createdBy);
        if ($id <= 0) {
            $this->error('Nao foi possivel salvar a forma de pagamento.');
            $this->redirect('finance/payment-methods');
        }

        $after = $this->paymentMethods->find($companyId, $id);
        $this->audit->log([
            'module' => 'finance.formas_pagamento',
            'action' => 'create',
            'entity_type' => 'payment_method',
            'entity_id' => $id,
            'entity_label' => (string) ($after['name'] ?? $name),
            'description' => 'Forma de pagamento manual criada.',
            'before' => [],
            'after' => $after ?: [],
            'company_id' => $companyId,
        ]);

        $this->success('Forma de pagamento salva com sucesso.');
        $this->redirect('finance/payment-methods');
    }

    public function togglePaymentMethod(): void
    {
        require_auth();
        require_permission('finance');
        csrf_validate();

        if (!$this->finance->paymentMethodsTableAvailable()) {
            $this->error('Estrutura de formas de pagamento indisponivel no banco. Execute a migration.');
            $this->redirect('finance/payment-methods');
        }

        $id = (int) post('id');
        $setActive = (int) post('set_active', 1) === 1;
        $companyId = (int) (current_company_id() ?? 0);
        $changedBy = (int) (current_user()['id'] ?? 0);

        $before = $this->paymentMethods->find($companyId, $id);
        if (!$before) {
            $this->error('Forma de pagamento nao encontrada.');
            $this->redirect('finance/payment-methods');
        }

        $ok = $this->paymentMethods->setActive($companyId, $id, $setActive, $changedBy);
        if (!$ok) {
            $this->error('Nao foi possivel atualizar o status da forma de pagamento.');
            $this->redirect('finance/payment-methods');
        }

        $after = $this->paymentMethods->find($companyId, $id);
        $this->audit->log([
            'module' => 'finance.formas_pagamento',
            'action' => 'toggle',
            'entity_type' => 'payment_method',
            'entity_id' => $id,
            'entity_label' => (string) ($after['name'] ?? $before['name'] ?? ('Forma #' . $id)),
            'description' => $setActive ? 'Forma de pagamento ativada.' : 'Forma de pagamento inativada.',
            'before' => $before,
            'after' => $after ?: [],
            'company_id' => $companyId,
        ]);

        $this->success($setActive ? 'Forma de pagamento ativada.' : 'Forma de pagamento inativada.');
        $this->redirect('finance/payment-methods');
    }

    public function suppliers(): void
    {
        require_auth();
        require_permission('finance');

        $perPage = (int) request('per_page', config('app.default_pagination', 50));
        if (!in_array($perPage, config('app.pagination_options', [50, 100, 200]), true)) {
            $perPage = 50;
        }
        $page = max(1, (int) request('page', 1));
        $filters = [
            'q' => trim((string) request('q', '')),
            'status' => trim((string) request('status', '')),
        ];

        $companyId = (int) (current_company_id() ?? 0);
        $available = $this->suppliers->tableExists();
        $result = $available
            ? $this->suppliers->allByCompany($companyId, $filters, $perPage, $page)
            : ['rows' => [], 'meta' => pagination_meta(0, $perPage, 1)];

        $this->render('finance/suppliers', [
            'title' => 'Fornecedores',
            'available' => $available,
            'filters' => $filters,
            'rows' => $result['rows'],
            'meta' => $result['meta'],
            'paginationOptions' => config('app.pagination_options', [50, 100, 200]),
        ]);
    }

    public function storeSupplier(): void
    {
        require_auth();
        require_permission('finance');
        csrf_validate();

        if (!$this->suppliers->tableExists()) {
            $this->error('Estrutura de fornecedores indisponivel no banco. Execute a migration.');
            $this->redirect('finance/suppliers');
        }

        $data = [
            'name' => trim((string) post('name')),
            'document' => trim((string) post('document')),
            'contact_name' => trim((string) post('contact_name')),
            'email' => trim((string) post('email')),
            'phone' => trim((string) post('phone')),
            'whatsapp' => trim((string) post('whatsapp')),
            'pix_key' => trim((string) post('pix_key')),
            'bank_name' => trim((string) post('bank_name')),
            'bank_agency' => trim((string) post('bank_agency')),
            'bank_account' => trim((string) post('bank_account')),
            'notes' => trim((string) post('notes')),
            'is_active' => (int) post('is_active', 1) === 1,
        ];

        if ($data['name'] === '') {
            $this->error('Informe o nome do fornecedor.');
            $this->redirect('finance/suppliers');
        }

        $companyId = (int) (current_company_id() ?? 0);
        $createdBy = (int) (current_user()['id'] ?? 0);
        $id = $this->suppliers->create($companyId, $data, $createdBy);
        if ($id <= 0) {
            $this->error('Nao foi possivel salvar o fornecedor.');
            $this->redirect('finance/suppliers');
        }

        $after = $this->suppliers->find($companyId, $id);
        $this->audit->log([
            'module' => 'finance.fornecedores',
            'action' => 'create',
            'entity_type' => 'supplier',
            'entity_id' => $id,
            'entity_label' => (string) ($after['name'] ?? $data['name']),
            'description' => 'Fornecedor criado.',
            'before' => [],
            'after' => $after ?: [],
            'company_id' => $companyId,
        ]);

        $this->success('Fornecedor cadastrado com sucesso.');
        $this->redirect('finance/suppliers');
    }

    public function toggleSupplier(): void
    {
        require_auth();
        require_permission('finance');
        csrf_validate();

        if (!$this->suppliers->tableExists()) {
            $this->error('Estrutura de fornecedores indisponivel no banco. Execute a migration.');
            $this->redirect('finance/suppliers');
        }

        $id = (int) post('id');
        $setActive = (int) post('set_active', 1) === 1;
        $companyId = (int) (current_company_id() ?? 0);
        $changedBy = (int) (current_user()['id'] ?? 0);

        $before = $this->suppliers->find($companyId, $id);
        if (!$before) {
            $this->error('Fornecedor nao encontrado.');
            $this->redirect('finance/suppliers');
        }

        $ok = $this->suppliers->setActive($companyId, $id, $setActive, $changedBy);
        if (!$ok) {
            $this->error('Nao foi possivel atualizar o fornecedor.');
            $this->redirect('finance/suppliers');
        }

        $after = $this->suppliers->find($companyId, $id);
        $this->audit->log([
            'module' => 'finance.fornecedores',
            'action' => 'toggle',
            'entity_type' => 'supplier',
            'entity_id' => $id,
            'entity_label' => (string) ($after['name'] ?? $before['name'] ?? ('Fornecedor #' . $id)),
            'description' => $setActive ? 'Fornecedor ativado.' : 'Fornecedor inativado.',
            'before' => $before,
            'after' => $after ?: [],
            'company_id' => $companyId,
        ]);

        $this->success($setActive ? 'Fornecedor ativado.' : 'Fornecedor inativado.');
        $this->redirect('finance/suppliers');
    }

    public function payables(): void
    {
        require_auth();
        require_permission('finance');

        $filters = $this->collectPayableFilters();
        $perPage = (int) request('per_page', config('app.default_pagination', 50));
        if (!in_array($perPage, config('app.pagination_options', [50, 100, 200]), true)) {
            $perPage = 50;
        }
        $page = max(1, (int) request('page', 1));

        $available = $this->payables->tableExists() && $this->suppliers->tableExists();
        $result = $available
            ? $this->payables->list($filters, $perPage, $page)
            : ['rows' => [], 'meta' => pagination_meta(0, $perPage, 1)];
        $payableIds = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $result['rows']);

        $companyId = (int) (current_company_id() ?? 0);
        $supplierOptions = $this->suppliers->tableExists() ? $this->suppliers->activeByCompany($companyId) : [];
        $paymentMethods = $this->finance->paymentMethodsForInvoiceSelection();

        $this->render('finance/payables', [
            'title' => 'Contas a Pagar',
            'available' => $available,
            'filters' => $filters,
            'stats' => $available ? $this->payables->stats($filters) : [],
            'rows' => $result['rows'],
            'meta' => $result['meta'],
            'suppliers' => $supplierOptions,
            'paymentMethods' => $paymentMethods,
            'paymentMethodsAvailable' => $this->finance->paymentMethodsTableAvailable(),
            'attachmentsAvailable' => $this->payables->attachmentsTableExists(),
            'attachmentsByPayable' => $this->payables->attachmentsByPayables($payableIds),
            'recurrenceAvailable' => $this->payables->recurrenceColumnsAvailable(),
            'nextPayableNumber' => $available ? $this->payables->nextNumber() : 'PAGAR-' . date('YmdHis'),
            'paginationOptions' => config('app.pagination_options', [50, 100, 200]),
        ]);
    }

    public function storePayable(): void
    {
        require_auth();
        require_permission('finance');
        csrf_validate();

        if (!$this->payables->tableExists() || !$this->suppliers->tableExists()) {
            $this->error('Estrutura de contas a pagar indisponivel no banco. Execute a migration.');
            $this->redirect('finance/payables');
        }

        $data = $this->collectPayablePayload();

        if ($data['supplier_id'] <= 0 || $data['description'] === '' || $data['payable_number'] === '' || $data['due_date'] === '' || $data['amount'] <= 0) {
            $this->error('Preencha fornecedor, numero, descricao, vencimento e valor.');
            $this->redirect('finance/payables');
        }

        $dateError = $this->validatePayableDates($data);
        if ($dateError !== '') {
            $this->error($dateError);
            $this->redirect('finance/payables');
        }

        $supplier = $this->suppliers->find((int) (current_company_id() ?? 0), $data['supplier_id']);
        if (!$supplier || (int) ($supplier['is_active'] ?? 0) !== 1) {
            $this->error('Fornecedor invalido ou inativo.');
            $this->redirect('finance/payables');
        }

        $id = $this->payables->create($data, (int) (current_user()['id'] ?? 0));
        if ($id <= 0) {
            $this->error('Nao foi possivel salvar a conta a pagar.');
            $this->redirect('finance/payables');
        }

        $after = $this->payableSnapshotById($id);
        $this->audit->log([
            'module' => 'finance.contas_pagar',
            'action' => 'create',
            'entity_type' => 'payable',
            'entity_id' => $id,
            'entity_label' => 'Conta ' . (string) ($after['payable_number'] ?? $data['payable_number']),
            'description' => 'Conta a pagar criada.',
            'before' => [],
            'after' => $after ?: [],
            'company_id' => (int) (current_company_id() ?? 0),
        ]);

        $message = 'Conta a pagar cadastrada com sucesso.';
        if (!empty($data['is_recurring']) && $data['recurrence_until'] !== '') {
            $recurrence = $this->payables->generateRecurringPayablesForTemplate($id, $data['recurrence_until'], (int) (current_user()['id'] ?? 0));
            if (!($recurrence['ok'] ?? false)) {
                $this->error((string) ($recurrence['message'] ?? 'Conta salva, mas nao foi possivel gerar as recorrencias.'));
                $this->redirect('finance/payables');
            }

            $created = (int) ($recurrence['created'] ?? 0);
            $existing = (int) ($recurrence['existing'] ?? 0);
            $message .= ' ' . $created . ' recorrencia(s) futura(s) gerada(s).';
            if ($existing > 0) {
                $message .= ' ' . $existing . ' ja existia(m).';
            }
        }

        $this->success($message);
        $this->redirect('finance/payables');
    }

    public function updatePayable(): void
    {
        require_auth();
        require_permission('finance');
        csrf_validate();

        if (!$this->payables->tableExists() || !$this->suppliers->tableExists()) {
            $this->error('Estrutura de contas a pagar indisponivel no banco. Execute a migration.');
            $this->redirect('finance/payables');
        }

        $payableId = (int) post('payable_id');
        $before = $this->payableSnapshotById($payableId);
        if (!$before) {
            $this->error('Conta a pagar nao encontrada.');
            $this->redirect('finance/payables');
        }

        $data = $this->collectPayablePayload();
        if ($data['supplier_id'] <= 0 || $data['description'] === '' || $data['payable_number'] === '' || $data['due_date'] === '' || $data['amount'] <= 0) {
            $this->error('Preencha fornecedor, numero, descricao, vencimento e valor.');
            $this->redirect('finance/payables');
        }

        $dateError = $this->validatePayableDates($data);
        if ($dateError !== '') {
            $this->error($dateError);
            $this->redirect('finance/payables');
        }

        $supplier = $this->suppliers->find((int) (current_company_id() ?? 0), $data['supplier_id']);
        if (!$supplier || (int) ($supplier['is_active'] ?? 0) !== 1) {
            $this->error('Fornecedor invalido ou inativo.');
            $this->redirect('finance/payables');
        }

        $result = $this->payables->update($payableId, $data, (int) (current_user()['id'] ?? 0));
        if (!($result['ok'] ?? false)) {
            $this->error((string) ($result['message'] ?? 'Nao foi possivel atualizar a conta a pagar.'));
            $this->redirect('finance/payables');
        }

        $after = $this->payableSnapshotById($payableId);
        $this->audit->log([
            'module' => 'finance.contas_pagar',
            'action' => 'update',
            'entity_type' => 'payable',
            'entity_id' => $payableId,
            'entity_label' => 'Conta ' . (string) ($after['payable_number'] ?? $before['payable_number'] ?? ('#' . $payableId)),
            'description' => 'Conta a pagar atualizada.',
            'before' => $before,
            'after' => $after ?: [],
            'company_id' => (int) (current_company_id() ?? 0),
        ]);

        $this->success((string) ($result['message'] ?? 'Conta a pagar atualizada com sucesso.'));
        $this->redirect('finance/payables');
    }

    public function settlePayable(): void
    {
        require_auth();
        require_permission('finance');
        csrf_validate();

        if (!$this->payables->tableExists()) {
            $this->error('Estrutura de contas a pagar indisponivel no banco. Execute a migration.');
            $this->redirect('finance/payables');
        }

        $payableId = (int) post('payable_id');
        $amount = parse_decimal((string) post('amount', '0'));
        $paidAt = trim((string) post('paid_at', date('Y-m-d')));
        $notes = trim((string) post('notes'));
        $paymentMethodId = (int) post('payment_method_id');

        $before = $this->payableSnapshotById($payableId);
        $result = $this->payables->registerPayment(
            $payableId,
            $amount,
            $paidAt,
            $notes,
            $paymentMethodId > 0 ? $paymentMethodId : null,
            (int) (current_user()['id'] ?? 0)
        );

        if (!$result['ok']) {
            $this->error((string) $result['message']);
            $this->redirect('finance/payables');
        }

        $after = $this->payableSnapshotById($payableId);
        $this->audit->log([
            'module' => 'finance.contas_pagar',
            'action' => 'settle',
            'entity_type' => 'payable',
            'entity_id' => $payableId,
            'entity_label' => 'Conta ' . (string) ($after['payable_number'] ?? $before['payable_number'] ?? ('#' . $payableId)),
            'description' => 'Baixa de conta a pagar registrada.',
            'before' => $before,
            'after' => $after ?: [],
            'metadata' => [
                'amount' => $amount,
                'paid_at' => $paidAt,
                'payment_method_id' => $paymentMethodId > 0 ? $paymentMethodId : null,
            ],
            'company_id' => (int) (current_company_id() ?? 0),
        ]);

        $this->success((string) $result['message']);
        $this->redirect('finance/payables');
    }

    public function cancelPayable(): void
    {
        require_auth();
        require_permission('finance');
        csrf_validate();

        if (!$this->payables->tableExists()) {
            $this->error('Estrutura de contas a pagar indisponivel no banco. Execute a migration.');
            $this->redirect('finance/payables');
        }

        $payableId = (int) post('payable_id');
        $before = $this->payableSnapshotById($payableId);
        if (!$before) {
            $this->error('Conta a pagar nao encontrada.');
            $this->redirect('finance/payables');
        }

        $result = $this->payables->cancel($payableId, (int) (current_user()['id'] ?? 0));
        if (!($result['ok'] ?? false)) {
            $this->error((string) ($result['message'] ?? 'Nao foi possivel cancelar a conta a pagar.'));
            $this->redirect('finance/payables');
        }

        $after = $this->payableSnapshotById($payableId);
        $this->audit->log([
            'module' => 'finance.contas_pagar',
            'action' => 'cancel',
            'entity_type' => 'payable',
            'entity_id' => $payableId,
            'entity_label' => 'Conta ' . (string) ($after['payable_number'] ?? $before['payable_number'] ?? ('#' . $payableId)),
            'description' => 'Conta a pagar cancelada.',
            'before' => $before,
            'after' => $after ?: [],
            'company_id' => (int) (current_company_id() ?? 0),
        ]);

        $this->success((string) ($result['message'] ?? 'Conta a pagar cancelada com sucesso.'));
        $this->redirect('finance/payables');
    }

    public function generateRecurringPayables(): void
    {
        require_auth();
        require_permission('finance');
        csrf_validate();

        if (!$this->payables->recurrenceColumnsAvailable()) {
            $this->error('Estrutura de recorrencia do contas a pagar indisponivel no banco. Execute a migration.');
            $this->redirect('finance/payables');
        }

        $referenceMonth = trim((string) post('reference_month', date('Y-m')));
        if (!preg_match('/^\d{4}-\d{2}$/', $referenceMonth)) {
            $this->error('Informe um mes de referencia valido.');
            $this->redirect('finance/payables');
        }

        $referenceDate = date('Y-m-t', strtotime($referenceMonth . '-01'));
        $before = [
            'reference_month' => $referenceMonth,
            'reference_date' => $referenceDate,
            'company_id' => (int) (current_company_id() ?? 0),
        ];

        $result = $this->payables->generateRecurringPayables($referenceDate, (int) (current_user()['id'] ?? 0));
        $this->audit->log([
            'module' => 'finance.contas_pagar',
            'action' => 'generate_recurring',
            'entity_type' => 'payable_batch',
            'entity_id' => null,
            'entity_label' => 'Geracao de despesas fixas',
            'description' => (string) ($result['message'] ?? 'Geracao de despesas fixas processada.'),
            'before' => $before,
            'after' => $result,
            'company_id' => (int) (current_company_id() ?? 0),
        ]);

        if (!($result['ok'] ?? false)) {
            $this->error((string) ($result['message'] ?? 'Nao foi possivel gerar as despesas fixas.'));
            $this->redirect('finance/payables');
        }

        $created = (int) ($result['created'] ?? 0);
        $existing = (int) ($result['existing'] ?? 0);
        $this->success($created . ' conta(s) recorrente(s) gerada(s).' . ($existing > 0 ? ' ' . $existing . ' ja existia(m).' : ''));
        $this->redirect('finance/payables');
    }

    public function uploadPayableAttachment(): void
    {
        require_auth();
        require_permission('finance');
        csrf_validate();

        if (!$this->payables->tableExists() || !$this->payables->attachmentsTableExists()) {
            $this->error('Estrutura de anexos do contas a pagar indisponivel no banco. Execute a migration.');
            $this->redirect('finance/payables');
        }

        $payableId = (int) post('payable_id');
        $before = $this->payableSnapshotById($payableId);
        if (!$before) {
            $this->error('Conta a pagar nao encontrada.');
            $this->redirect('finance/payables');
        }

        $attachmentType = $this->normalizePayableAttachmentType((string) post('attachment_type', 'outro'));
        $upload = $this->handlePayableAttachmentUpload($_FILES['attachment_file'] ?? null);
        if (!($upload['ok'] ?? false)) {
            $this->error((string) ($upload['message'] ?? 'Nao foi possivel anexar o arquivo.'));
            $this->redirect('finance/payables');
        }

        $attachmentId = $this->payables->addAttachment($payableId, [
            'attachment_type' => $attachmentType,
            'original_file_name' => (string) ($upload['original_file_name'] ?? ''),
            'stored_file_name' => (string) ($upload['stored_file_name'] ?? ''),
            'file_path' => (string) ($upload['file_path'] ?? ''),
            'file_type' => (string) ($upload['file_type'] ?? ''),
            'file_size' => (int) ($upload['file_size'] ?? 0),
            'notes' => trim((string) post('attachment_notes')),
        ], (int) (current_user()['id'] ?? 0));

        if ($attachmentId <= 0) {
            $this->safeRemovePayableAttachmentFile((string) ($upload['file_path'] ?? ''));
            $this->error('Nao foi possivel registrar o anexo da conta.');
            $this->redirect('finance/payables');
        }

        $after = $this->payables->findAttachment($attachmentId);
        $this->audit->log([
            'module' => 'finance.contas_pagar',
            'action' => 'attachment_upload',
            'entity_type' => 'payable_attachment',
            'entity_id' => $attachmentId,
            'entity_label' => 'Anexo ' . (string) ($after['original_file_name'] ?? ('#' . $attachmentId)),
            'description' => 'Anexo de conta a pagar enviado.',
            'before' => [],
            'after' => $after ?: [],
            'metadata' => ['payable' => $before],
            'company_id' => (int) (current_company_id() ?? 0),
        ]);

        $this->success('Anexo enviado com sucesso.');
        $this->redirect('finance/payables');
    }

    public function deletePayableAttachment(): void
    {
        require_auth();
        require_permission('finance');
        csrf_validate();

        if (!$this->payables->attachmentsTableExists()) {
            $this->error('Estrutura de anexos do contas a pagar indisponivel no banco. Execute a migration.');
            $this->redirect('finance/payables');
        }

        $attachmentId = (int) post('attachment_id');
        $before = $this->payables->findAttachment($attachmentId);
        if (!$before) {
            $this->error('Anexo nao encontrado.');
            $this->redirect('finance/payables');
        }

        $ok = $this->payables->deleteAttachment($attachmentId);
        if (!$ok) {
            $this->error('Nao foi possivel remover o anexo.');
            $this->redirect('finance/payables');
        }

        $this->safeRemovePayableAttachmentFile((string) ($before['file_path'] ?? ''));
        $this->audit->log([
            'module' => 'finance.contas_pagar',
            'action' => 'attachment_delete',
            'entity_type' => 'payable_attachment',
            'entity_id' => $attachmentId,
            'entity_label' => 'Anexo ' . (string) ($before['original_file_name'] ?? ('#' . $attachmentId)),
            'description' => 'Anexo de conta a pagar removido.',
            'before' => $before,
            'after' => [],
            'company_id' => (int) (current_company_id() ?? 0),
        ]);

        $this->success('Anexo removido com sucesso.');
        $this->redirect('finance/payables');
    }

    public function downloadPayableAttachment(): void
    {
        require_auth();
        require_permission('finance');

        $attachmentId = (int) request('id');
        $attachment = $this->payables->findAttachment($attachmentId);
        if (!$attachment) {
            http_response_code(404);
            echo 'Anexo nao encontrado.';
            return;
        }

        $fullPath = $this->resolvePayableAttachmentPath((string) ($attachment['file_path'] ?? ''));
        if (!$fullPath || !is_file($fullPath)) {
            http_response_code(404);
            echo 'Arquivo nao encontrado.';
            return;
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $downloadName = basename((string) ($attachment['original_file_name'] ?? 'anexo'));
        header('Content-Type: ' . $this->detectPayableAttachmentMime($fullPath));
        header('Content-Length: ' . filesize($fullPath));
        header('Content-Disposition: attachment; filename="' . addslashes($downloadName) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($fullPath);
        exit;
    }

    private function invoiceSnapshotById(int $invoiceId): ?array
    {
        return $this->invoiceSnapshot($this->finance->findInvoice($invoiceId));
    }

    private function payableSnapshotById(int $payableId): ?array
    {
        $payable = $this->payables->find($payableId);
        if (!$payable) {
            return null;
        }

        return [
            'id' => (int) ($payable['id'] ?? 0),
            'payable_number' => (string) ($payable['payable_number'] ?? ''),
            'supplier_id' => (int) ($payable['supplier_id'] ?? 0),
            'supplier_name' => (string) ($payable['supplier_name'] ?? ''),
            'payment_method_id' => (int) ($payable['payment_method_id'] ?? 0),
            'description' => (string) ($payable['description'] ?? ''),
            'category' => (string) ($payable['category'] ?? ''),
            'competence_date' => (string) ($payable['competence_date'] ?? ''),
            'due_date' => (string) ($payable['due_date'] ?? ''),
            'amount' => (float) ($payable['amount'] ?? 0),
            'paid_amount' => (float) ($payable['paid_amount'] ?? 0),
            'outstanding_amount' => (float) ($payable['outstanding_amount'] ?? 0),
            'status' => (string) ($payable['status'] ?? ''),
            'paid_at' => (string) ($payable['paid_at'] ?? ''),
            'notes' => (string) ($payable['notes'] ?? ''),
            'is_recurring' => (int) ($payable['is_recurring'] ?? 0),
            'recurrence_interval' => (string) ($payable['recurrence_interval'] ?? ''),
            'recurrence_until' => (string) ($payable['recurrence_until'] ?? ''),
            'recurrence_parent_id' => (int) ($payable['recurrence_parent_id'] ?? 0),
        ];
    }

    private function invoiceSnapshot(?array $invoice): ?array
    {
        if (!$invoice) {
            return null;
        }

        return [
            'id' => (int) ($invoice['id'] ?? 0),
            'invoice_number' => (string) ($invoice['invoice_number'] ?? ''),
            'student_id' => (int) ($invoice['student_id'] ?? 0),
            'payment_method_id' => (int) ($invoice['payment_method_id'] ?? 0),
            'payment_method_name' => (string) ($invoice['payment_method_name'] ?? ''),
            'due_date' => (string) ($invoice['due_date'] ?? ''),
            'amount' => (float) ($invoice['amount'] ?? 0),
            'paid_amount' => (float) ($invoice['paid_amount'] ?? 0),
            'status' => (string) ($invoice['status'] ?? ''),
            'paid_at' => (string) ($invoice['paid_at'] ?? ''),
            'tax_amount' => (float) ($invoice['tax_amount'] ?? 0),
            'project_name' => (string) ($invoice['project_name'] ?? ''),
            'tags' => (string) ($invoice['tags'] ?? ''),
            'boleto_url' => (string) ($invoice['boleto_url'] ?? ''),
            'is_recurring' => (int) ($invoice['is_recurring'] ?? 0),
            'recurrence_interval' => (string) ($invoice['recurrence_interval'] ?? ''),
        ];
    }

    private function auditInvoiceEvent(string $action, int $invoiceId, $before, $after, string $description, array $metadata = []): void
    {
        $label = 'Fatura #' . $invoiceId;
        if (is_array($after) && trim((string) ($after['invoice_number'] ?? '')) !== '') {
            $label = 'Fatura ' . (string) $after['invoice_number'];
        } elseif (is_array($before) && trim((string) ($before['invoice_number'] ?? '')) !== '') {
            $label = 'Fatura ' . (string) $before['invoice_number'];
        }

        $this->audit->log([
            'module' => 'finance.faturas',
            'action' => $action,
            'entity_type' => 'invoice',
            'entity_id' => $invoiceId,
            'entity_label' => $label,
            'description' => $description,
            'before' => $before,
            'after' => $after,
            'metadata' => $metadata,
            'company_id' => (int) (current_company_id() ?? 0),
        ]);
    }

    private function collectReportFilters(): array
    {
        $period = trim((string) request('period', 'month_current'));
        [$startDate, $endDate] = $this->resolvePeriod($period);

        $customStart = trim((string) request('start_date', ''));
        $customEnd = trim((string) request('end_date', ''));
        if ($customStart !== '' && $customEnd !== '') {
            $startDate = $customStart;
            $endDate = $customEnd;
        }

        if ($startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        return [
            'period' => $period,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'student_id' => request('student_id', ''),
            'supplier_id' => request('supplier_id', ''),
            'status' => trim((string) request('status', '')),
            'method' => trim((string) request('method', '')),
        ];
    }

    private function collectFinanceDateFilters(): array
    {
        $period = trim((string) request('period', 'month_current'));
        [$startDate, $endDate] = $this->resolvePeriod($period);

        $customStart = trim((string) request('start_date', ''));
        $customEnd = trim((string) request('end_date', ''));
        if ($customStart !== '' && $customEnd !== '') {
            $startDate = $customStart;
            $endDate = $customEnd;
        }

        if ($startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        return [
            'period' => $period,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }

    private function collectPayableFilters(): array
    {
        $filters = $this->collectFinanceDateFilters();
        $filters['q'] = trim((string) request('q', ''));
        $filters['supplier_id'] = request('supplier_id', '');
        $filters['status'] = trim((string) request('status', ''));
        return $filters;
    }

    private function collectPayablePayload(): array
    {
        return [
            'supplier_id' => (int) post('supplier_id'),
            'payment_method_id' => (int) post('payment_method_id'),
            'payable_number' => trim((string) post('payable_number')),
            'description' => trim((string) post('description')),
            'category' => trim((string) post('category')),
            'competence_date' => $this->normalizePayableDate((string) post('competence_date')),
            'due_date' => $this->normalizePayableDate((string) post('due_date')),
            'amount' => parse_decimal((string) post('amount', '0')),
            'status' => trim((string) post('status', 'open')),
            'notes' => trim((string) post('notes')),
            'paid_at' => $this->normalizePayableDate((string) post('paid_at')),
            'is_recurring' => post('is_recurring') ? 1 : 0,
            'recurrence_interval' => trim((string) post('recurrence_interval', 'monthly')),
            'recurrence_until' => $this->normalizePayableDate((string) post('recurrence_until')),
            'recurrence_until_raw' => trim((string) post('recurrence_until')),
        ];
    }

    private function normalizePayableDate(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return '';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return '';
        }

        [$year, $month, $day] = array_map('intval', explode('-', $date));
        if ($year < 2000 || $year > 2100 || !checkdate($month, $day, $year)) {
            return '';
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function validatePayableDates(array $data): string
    {
        if ((string) ($data['due_date'] ?? '') === '') {
            return 'Informe uma data de vencimento valida.';
        }

        if (!empty($data['is_recurring']) && (string) ($data['recurrence_until'] ?? '') !== '') {
            if (strtotime((string) $data['recurrence_until']) < strtotime((string) $data['due_date'])) {
                return 'A data final da recorrencia precisa ser igual ou posterior ao vencimento.';
            }
        }

        if (!empty($data['is_recurring']) && (string) ($data['recurrence_until_raw'] ?? '') !== '' && (string) ($data['recurrence_until'] ?? '') === '') {
            return 'Informe uma data final de recorrencia valida.';
        }

        return '';
    }

    private function normalizePayableAttachmentType(string $type): string
    {
        $type = strtolower(trim($type));
        $allowed = ['boleto', 'nota_fiscal', 'contrato', 'comprovante', 'outro'];
        return in_array($type, $allowed, true) ? $type : 'outro';
    }

    private function handlePayableAttachmentUpload($file): array
    {
        if (!$file || !isset($file['name']) || trim((string) ($file['name'] ?? '')) === '') {
            return ['ok' => false, 'message' => 'Selecione um arquivo para anexar.'];
        }

        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'message' => 'Falha no upload do arquivo.'];
        }

        if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            return ['ok' => false, 'message' => 'Arquivo invalido para upload.'];
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            return ['ok' => false, 'message' => 'Arquivo vazio ou invalido.'];
        }

        if ($size > (20 * 1024 * 1024)) {
            return ['ok' => false, 'message' => 'Arquivo acima do limite de 20MB.'];
        }

        $originalName = basename((string) ($file['name'] ?? ''));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'txt'];
        if (!in_array($extension, $allowed, true)) {
            return ['ok' => false, 'message' => 'Extensao nao permitida para anexos financeiros.'];
        }

        $targetDir = __DIR__ . '/../uploads/payables';
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true)) {
            return ['ok' => false, 'message' => 'Nao foi possivel criar a pasta de anexos.'];
        }

        if (!is_writable($targetDir)) {
            return ['ok' => false, 'message' => 'A pasta de anexos nao esta com permissao de escrita.'];
        }

        $safeOriginal = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        $storedName = 'payable_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '_' . $safeOriginal;
        $finalPath = $targetDir . '/' . $storedName;

        if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), $finalPath)) {
            return ['ok' => false, 'message' => 'Nao foi possivel salvar o arquivo no servidor.'];
        }

        return [
            'ok' => true,
            'message' => 'Arquivo enviado com sucesso.',
            'original_file_name' => $originalName,
            'stored_file_name' => $storedName,
            'file_path' => 'uploads/payables/' . $storedName,
            'file_type' => $extension,
            'file_size' => $size,
        ];
    }

    private function safeRemovePayableAttachmentFile(string $relativePath): void
    {
        $fullPath = $this->resolvePayableAttachmentPath($relativePath);
        if ($fullPath && is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    private function resolvePayableAttachmentPath(string $relativePath): ?string
    {
        $relativePath = trim($relativePath);
        if ($relativePath === '') {
            return null;
        }

        $uploadsBase = realpath(__DIR__ . '/../uploads');
        if (!$uploadsBase) {
            return null;
        }

        $fullPath = realpath(__DIR__ . '/../' . ltrim($relativePath, '/\\'));
        if (!$fullPath || !str_starts_with($fullPath, $uploadsBase)) {
            return null;
        }

        return $fullPath;
    }

    private function detectPayableAttachmentMime(string $filePath): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $filePath);
                finfo_close($finfo);
                if (is_string($mime) && trim($mime) !== '') {
                    return $mime;
                }
            }
        }

        return 'application/octet-stream';
    }

    private function reportTab(): string
    {
        $tab = trim((string) request('tab', 'overview'));
        $allowed = ['overview', 'receipts', 'receivables', 'payables', 'aging', 'cashflow', 'fiscal'];
        return in_array($tab, $allowed, true) ? $tab : 'overview';
    }

    private function resolvePeriod(string $period): array
    {
        $today = date('Y-m-d');

        return match ($period) {
            'today' => [$today, $today],
            'last_7' => [date('Y-m-d', strtotime('-6 days')), $today],
            'last_30' => [date('Y-m-d', strtotime('-29 days')), $today],
            'month_previous' => [date('Y-m-01', strtotime('first day of previous month')), date('Y-m-t', strtotime('last day of previous month'))],
            'custom' => [$today, $today],
            default => [date('Y-m-01'), date('Y-m-t')],
        };
    }
}
