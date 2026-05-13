<?php

class FinanceController extends BaseController
{
    private FinanceModel $finance;
    private PaymentMethodModel $paymentMethods;
    private AuditLogService $audit;

    public function __construct()
    {
        $this->finance = new FinanceModel();
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
            'stats' => $this->finance->invoiceStats(),
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
        $aging = null;
        $fiscal = ['rows' => [], 'meta' => pagination_meta(0, $perPage, 1)];

        if ($tab === 'overview') {
            $overview = $this->finance->reportOverview($filters);
        } elseif ($tab === 'receipts') {
            $receipts = $this->finance->reportReceipts($filters, $perPage, $page);
        } elseif ($tab === 'receivables') {
            $receivables = $this->finance->reportReceivables($filters, $perPage, $page);
        } elseif ($tab === 'aging') {
            $aging = $this->finance->reportAging($filters);
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
            'aging' => $aging,
            'fiscal' => $fiscal,
            'students' => $this->finance->listStudents(),
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

    private function invoiceSnapshotById(int $invoiceId): ?array
    {
        return $this->invoiceSnapshot($this->finance->findInvoice($invoiceId));
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

    private function reportTab(): string
    {
        $tab = trim((string) request('tab', 'overview'));
        $allowed = ['overview', 'receipts', 'receivables', 'aging', 'fiscal'];
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
