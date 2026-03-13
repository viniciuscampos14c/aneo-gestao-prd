<?php

class FinanceController extends BaseController
{
    private FinanceModel $finance;

    public function __construct()
    {
        $this->finance = new FinanceModel();
    }

    public function invoices(): void
    {
        require_auth();
        require_permission('finance');

        $filters = [
            'q' => trim((string) request('q', '')),
            'status' => trim((string) request('status', '')),
            'student_id' => request('student_id', ''),
        ];

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
            fputcsv($out, ['ID', 'Fatura', 'Aluno', 'Vencimento', 'Valor', 'Pago', 'Saldo', 'Status', 'Dias em atraso', 'Data baixa', 'NF status'], ';');
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['id'],
                    $row['invoice_number'],
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
        $result = $this->finance->generateBankSlip($invoiceId, (int) current_user()['id']);

        if (!$result['ok']) {
            $this->error($result['message']);
            $this->redirect('finance/invoices');
        }

        $this->success($result['message']);
        $this->redirect('finance/invoices');
    }

    public function syncBankSlip(): void
    {
        require_auth();
        require_permission('finance.invoice.boleto.sync');
        csrf_validate();

        $invoiceId = (int) post('invoice_id');
        $result = $this->finance->syncBankSlipStatus($invoiceId, (int) current_user()['id']);

        if (!$result['ok']) {
            $this->error($result['message']);
            $this->redirect('finance/invoices');
        }

        $this->success($result['message']);
        $this->redirect('finance/invoices');
    }

    public function createInvoice(): void
    {
        require_auth();
        require_permission('finance.invoice.create');

        $this->render('finance/invoice_form', [
            'title' => 'Nova Fatura',
            'students' => $this->finance->listStudents(),
            'action' => route('finance/invoices/store'),
        ]);
    }

    public function storeInvoice(): void
    {
        require_auth();
        require_permission('finance.invoice.create');
        csrf_validate();

        $data = [
            'student_id' => (int) post('student_id'),
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

        if ($data['boleto_url'] !== '' && !filter_var($data['boleto_url'], FILTER_VALIDATE_URL)) {
            $this->error('Informe um link de boleto valido (URL completa).');
            $this->redirect('finance/invoices/create');
        }

        $id = $this->finance->createInvoice($data, (int) current_user()['id']);
        $this->finance->syncStudentFinanceKanban((int) $data['student_id'], (int) current_user()['id']);

        $this->success('Fatura criada #' . $id . '.');
        $this->redirect('finance/invoices');
    }

    public function deleteInvoice(): void
    {
        require_auth();
        require_permission('finance.invoice.delete');
        csrf_validate();

        $id = (int) post('id');
        $invoice = $this->finance->findInvoice($id);

        if ($invoice) {
            $this->finance->deleteInvoice($id);
            $this->finance->syncStudentFinanceKanban((int) $invoice['student_id'], (int) current_user()['id']);
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
        $paidAt = trim((string) post('paid_at', date('Y-m-d')));
        $notes = trim((string) post('notes'));

        $result = $this->finance->settleInvoice($invoiceId, $method, $paidAt, $notes, (int) current_user()['id']);

        if (!$result['ok']) {
            $this->error($result['message']);
            $this->redirect('finance/invoices');
        }

        $this->success('Baixa efetuada com sucesso. Pagamento #' . (int) $result['payment_id'] . ' registrado.');
        $this->redirect('finance/invoices');
    }

    public function generateFiscalInvoice(): void
    {
        require_auth();
        require_permission('finance.invoice.nfe');
        csrf_validate();

        $invoiceId = (int) post('invoice_id');
        $result = $this->finance->generateFiscalInvoice($invoiceId, (int) current_user()['id']);

        if (!$result['ok']) {
            $this->error($result['message']);
            $this->redirect('finance/invoices');
        }

        $this->success($result['message']);
        $this->redirect('finance/invoices');
    }

    public function exportInvoices(): void
    {
        require_auth();
        require_permission('finance.invoice.export');

        $result = $this->finance->listInvoices(['q' => trim((string) request('q', ''))], 10000, 1);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=faturas_' . date('Ymd_His') . '.csv');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Numero', 'Aluno', 'Vencimento', 'Quantia', 'Pago', 'Status', 'Data Baixa', 'Boleto Status', 'Boleto ID Externo', 'Link Boleto', 'NF Status', 'Imposto', 'Projeto', 'Tags'], ';');

        foreach ($result['rows'] as $row) {
            fputcsv($out, [
                $row['id'],
                $row['invoice_number'],
                $row['student_name'],
                $row['due_date'],
                $row['amount'],
                $row['paid_amount'],
                $row['status'],
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

        $this->success($qty . ' fatura(s) recorrente(s) gerada(s).');
        $this->redirect('finance/invoices');
    }

    public function payments(): void
    {
        require_auth();
        require_permission('finance');

        $filters = [
            'q' => trim((string) request('q', '')),
        ];

        $perPage = (int) request('per_page', config('app.default_pagination', 50));
        if (!in_array($perPage, config('app.pagination_options', [50, 100, 200]), true)) {
            $perPage = 50;
        }
        $page = max(1, (int) request('page', 1));

        $result = $this->finance->listPayments($filters, $perPage, $page);

        $openInvoices = $this->finance->listInvoices(['status' => 'open'], 1000, 1);
        $partialInvoices = $this->finance->listInvoices(['status' => 'partial'], 1000, 1);

        $invoicePool = array_merge($openInvoices['rows'], $partialInvoices['rows']);

        $this->render('finance/payments', [
            'title' => 'Pagamentos',
            'rows' => $result['rows'],
            'meta' => $result['meta'],
            'invoicesPool' => $invoicePool,
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
        $method = trim((string) post('method', 'PIX'));
        $paidAt = trim((string) post('paid_at', date('Y-m-d')));
        $notes = trim((string) post('notes'));

        if ($amount <= 0 || $invoiceIds === []) {
            $this->error('Selecione faturas e informe um valor.');
            $this->redirect('finance/payments');
        }

        $paymentId = $this->finance->recordBatchPayment($invoiceIds, $amount, $method, $paidAt, $notes, (int) current_user()['id']);

        if ($paymentId <= 0) {
            $this->error('Nao foi possivel registrar pagamento.');
            $this->redirect('finance/payments');
        }

        $this->success('Pagamento registrado #' . $paymentId . '.');
        $this->redirect('finance/payments');
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
            default => [date('Y-m-01'), $today],
        };
    }
}
