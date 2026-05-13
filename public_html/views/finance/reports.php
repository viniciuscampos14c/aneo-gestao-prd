<?php
$canExportReports = has_permission('finance.reports.export');
$baseParams = [
    'period' => $filters['period'],
    'start_date' => $filters['start_date'],
    'end_date' => $filters['end_date'],
    'student_id' => $filters['student_id'],
    'status' => $filters['status'],
    'method' => $filters['method'],
    'per_page' => request('per_page', $paginationOptions[0]),
];

$tabs = [
    'overview' => 'Visao Geral',
    'receipts' => 'Recebimentos',
    'receivables' => 'Contas a Receber',
    'aging' => 'Inadimplencia',
    'fiscal' => 'NF-e',
];
$paymentMethodOptions = is_array($paymentMethodOptions ?? null) ? $paymentMethodOptions : ['PIX', 'Boleto', 'Cartao de credito', 'Transferencia', 'Dinheiro'];
?>

<section class="finance-reports-shell space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold">Relatorios Financeiros</h2>
            <p class="text-sm text-slate-500">Analise por periodo com foco em recebimentos, pendencias e inadimplencia.</p>
        </div>
        <div class="flex gap-2">
            <a href="<?= route('finance/invoices'); ?>" class="finance-reports-nav-btn rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Faturas</a>
            <a href="<?= route('finance/payments'); ?>" class="finance-reports-nav-btn rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Pagamentos</a>
            <?php if ($canExportReports): ?>
                <a href="index.php?<?= http_build_query(array_merge($baseParams, ['route' => 'finance/reports/export', 'tab' => $tab])); ?>" class="finance-reports-export-btn rounded-lg bg-cyan-600 px-3 py-2 text-sm font-semibold text-white hover:bg-cyan-700">Exportar CSV</a>
            <?php endif; ?>
        </div>
    </div>

    <form method="get" action="index.php" class="finance-reports-filter grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-4 xl:grid-cols-8">
        <input type="hidden" name="route" value="finance/reports">
        <input type="hidden" name="tab" value="<?= e($tab); ?>">

        <select name="period" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option value="today" <?= $filters['period'] === 'today' ? 'selected' : ''; ?>>Hoje</option>
            <option value="last_7" <?= $filters['period'] === 'last_7' ? 'selected' : ''; ?>>Ultimos 7 dias</option>
            <option value="last_30" <?= $filters['period'] === 'last_30' ? 'selected' : ''; ?>>Ultimos 30 dias</option>
            <option value="month_current" <?= $filters['period'] === 'month_current' ? 'selected' : ''; ?>>Mes atual</option>
            <option value="month_previous" <?= $filters['period'] === 'month_previous' ? 'selected' : ''; ?>>Mes anterior</option>
            <option value="custom" <?= $filters['period'] === 'custom' ? 'selected' : ''; ?>>Personalizado</option>
        </select>

        <input type="date" name="start_date" value="<?= e($filters['start_date']); ?>" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <input type="date" name="end_date" value="<?= e($filters['end_date']); ?>" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">

        <select name="student_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option value="">Todos os alunos</option>
            <?php foreach ($students as $student): ?>
                <option value="<?= (int) $student['id']; ?>" <?= (string) $filters['student_id'] === (string) $student['id'] ? 'selected' : ''; ?>>
                    <?= e($student['full_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="status" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option value="">Todos os status</option>
            <option value="draft" <?= $filters['status'] === 'draft' ? 'selected' : ''; ?>>Rascunho</option>
            <option value="open" <?= $filters['status'] === 'open' ? 'selected' : ''; ?>>Em aberto</option>
            <option value="partial" <?= $filters['status'] === 'partial' ? 'selected' : ''; ?>>Parcial</option>
            <option value="paid" <?= $filters['status'] === 'paid' ? 'selected' : ''; ?>>Pago</option>
            <option value="overdue" <?= $filters['status'] === 'overdue' ? 'selected' : ''; ?>>Vencido</option>
        </select>

        <select name="method" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option value="">Todos os metodos</option>
            <?php foreach ($paymentMethodOptions as $method): ?>
                <option value="<?= e($method); ?>" <?= $filters['method'] === $method ? 'selected' : ''; ?>><?= e($method); ?></option>
            <?php endforeach; ?>
        </select>

        <select name="per_page" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <?php foreach ($paginationOptions as $opt): ?>
                <option value="<?= (int) $opt; ?>" <?= (string) request('per_page', $paginationOptions[0]) === (string) $opt ? 'selected' : ''; ?>>
                    <?= (int) $opt; ?>/pagina
                </option>
            <?php endforeach; ?>
        </select>

        <div class="flex gap-2 xl:col-span-8">
            <button class="finance-reports-apply-btn rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Aplicar</button>
            <a href="index.php?<?= http_build_query(['route' => 'finance/reports', 'tab' => $tab]); ?>" class="finance-reports-clear-btn rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm hover:bg-slate-50">Limpar</a>
        </div>
    </form>

    <div class="finance-reports-tabs flex flex-wrap gap-2 rounded-xl border border-slate-200 bg-white p-3">
        <?php foreach ($tabs as $tabKey => $tabLabel): ?>
            <?php $tabQuery = http_build_query(array_merge($baseParams, ['route' => 'finance/reports', 'tab' => $tabKey, 'page' => 1])); ?>
            <a href="index.php?<?= $tabQuery; ?>" class="finance-reports-tab-btn rounded-lg px-3 py-2 text-sm font-medium <?= $tab === $tabKey ? 'finance-reports-tab-active bg-slate-900 text-white' : 'finance-reports-tab-inactive border border-slate-200 bg-white hover:bg-slate-50'; ?>">
                <?= e($tabLabel); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($tab === 'overview' && $overview): ?>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
            <article class="finance-reports-kpi finance-reports-kpi-invoiced rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs uppercase text-slate-500">Faturado</p><p class="mt-2 text-xl font-semibold"><?= e(format_currency($overview['cards']['total_invoiced'])); ?></p></article>
            <article class="finance-reports-kpi finance-reports-kpi-received rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs uppercase text-slate-500">Recebido</p><p class="mt-2 text-xl font-semibold"><?= e(format_currency($overview['cards']['total_received'])); ?></p></article>
            <article class="finance-reports-kpi finance-reports-kpi-pending rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs uppercase text-slate-500">Pendente</p><p class="mt-2 text-xl font-semibold"><?= e(format_currency($overview['cards']['pending_value'])); ?></p></article>
            <article class="finance-reports-kpi finance-reports-kpi-overdue rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs uppercase text-slate-500">Vencido</p><p class="mt-2 text-xl font-semibold"><?= e(format_currency($overview['cards']['overdue_value'])); ?></p></article>
            <article class="finance-reports-kpi finance-reports-kpi-settled rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs uppercase text-slate-500">Baixas no periodo</p><p class="mt-2 text-xl font-semibold"><?= (int) $overview['cards']['settled_count']; ?></p></article>
            <article class="finance-reports-kpi finance-reports-kpi-default rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs uppercase text-slate-500">Inadimplencia</p><p class="mt-2 text-xl font-semibold"><?= number_format((float) $overview['cards']['inadimplencia_percent'], 2, ',', '.'); ?>%</p></article>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <article class="finance-reports-nfe finance-reports-nfe-issued rounded-xl border border-indigo-200 bg-indigo-50 p-4"><p class="text-xs uppercase text-indigo-700">NF-e emitidas</p><p class="mt-2 text-xl font-semibold text-indigo-700"><?= (int) $overview['nfe']['issued']; ?></p></article>
            <article class="finance-reports-nfe finance-reports-nfe-pending rounded-xl border border-amber-200 bg-amber-50 p-4"><p class="text-xs uppercase text-amber-700">NF-e pendentes</p><p class="mt-2 text-xl font-semibold text-amber-700"><?= (int) $overview['nfe']['pending']; ?></p></article>
            <article class="finance-reports-nfe finance-reports-nfe-failed rounded-xl border border-rose-200 bg-rose-50 p-4"><p class="text-xs uppercase text-rose-700">NF-e falha</p><p class="mt-2 text-xl font-semibold text-rose-700"><?= (int) $overview['nfe']['failed']; ?></p></article>
        </div>

        <?php
        $labels = $overview['chart']['labels'];
        $invoicedSeries = $overview['chart']['invoiced'];
        $receivedSeries = $overview['chart']['received'];
        $lastIndexes = array_slice(array_keys($labels), -31);
        $maxValue = max(1, max(array_merge($invoicedSeries, $receivedSeries)));
        ?>

        <section class="finance-reports-chart rounded-xl border border-slate-200 bg-white p-4">
            <h3 class="mb-3 text-lg font-semibold">Evolucao diaria (ultimos 31 dias do periodo)</h3>
            <div class="space-y-2">
                <?php foreach ($lastIndexes as $idx): ?>
                    <?php
                    $label = $labels[$idx];
                    $invoiced = (float) $invoicedSeries[$idx];
                    $received = (float) $receivedSeries[$idx];
                    $invWidth = min(100, ($invoiced / $maxValue) * 100);
                    $recWidth = min(100, ($received / $maxValue) * 100);
                    ?>
                    <div class="finance-reports-chart-row rounded-lg border border-slate-100 p-2">
                        <div class="mb-1 flex items-center justify-between text-xs text-slate-600">
                            <span><?= e($label); ?></span>
                            <span>Faturado: <?= e(format_currency($invoiced)); ?> | Recebido: <?= e(format_currency($received)); ?></span>
                        </div>
                        <div class="space-y-1">
                            <div class="finance-reports-bar-track h-2 rounded-full bg-slate-100">
                                <div class="finance-reports-bar-invoiced h-2 rounded-full bg-cyan-500" style="width: <?= $invWidth; ?>%"></div>
                            </div>
                            <div class="finance-reports-bar-track h-2 rounded-full bg-slate-100">
                                <div class="finance-reports-bar-received h-2 rounded-full bg-emerald-500" style="width: <?= $recWidth; ?>%"></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($tab === 'receipts'): ?>
        <?php $rows = $receipts['rows']; $meta = $receipts['meta']; ?>
        <div class="finance-reports-table-wrap overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="finance-reports-table min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-3 py-3">Referencia</th>
                        <th class="px-3 py-3">Data</th>
                        <th class="px-3 py-3">Metodo</th>
                        <th class="px-3 py-3">Valor</th>
                        <th class="px-3 py-3">Aplicado</th>
                        <th class="px-3 py-3">Faturas</th>
                        <th class="px-3 py-3">Alunos</th>
                        <th class="px-3 py-3">Obs.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr class="finance-reports-row border-b border-slate-100 hover:bg-slate-50">
                            <td class="px-3 py-3 font-medium"><?= e($row['payment_ref']); ?></td>
                            <td class="px-3 py-3"><?= e($row['paid_at']); ?></td>
                            <td class="px-3 py-3"><?= e($row['method']); ?></td>
                            <td class="px-3 py-3"><?= e(format_currency($row['amount'])); ?></td>
                            <td class="px-3 py-3"><?= e(format_currency($row['applied_amount'])); ?></td>
                            <td class="px-3 py-3"><?= e($row['invoices']); ?></td>
                            <td class="px-3 py-3"><?= e($row['students']); ?></td>
                            <td class="px-3 py-3"><?= e($row['notes']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="8" class="px-3 py-6 text-center text-slate-500">Nenhum recebimento no periodo.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php include __DIR__ . '/reports_pagination.php'; ?>
    <?php endif; ?>

    <?php if ($tab === 'receivables'): ?>
        <?php $rows = $receivables['rows']; $meta = $receivables['meta']; ?>
        <div class="finance-reports-table-wrap overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="finance-reports-table min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-3 py-3">Fatura</th>
                        <th class="px-3 py-3">Parcela</th>
                        <th class="px-3 py-3">Aluno</th>
                        <th class="px-3 py-3">Vencimento</th>
                        <th class="px-3 py-3">Valor</th>
                        <th class="px-3 py-3">Pago</th>
                        <th class="px-3 py-3">Saldo</th>
                        <th class="px-3 py-3">Status</th>
                        <th class="px-3 py-3">Dias atraso</th>
                        <th class="px-3 py-3">NF-e</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr class="finance-reports-row border-b border-slate-100 hover:bg-slate-50">
                            <td class="px-3 py-3 font-medium"><?= e($row['invoice_number']); ?></td>
                            <td class="px-3 py-3">
                                <?php if (!empty($row['installment_label'])): ?>
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700"><?= e($row['installment_label']); ?></span>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400">Avulsa</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-3"><?= e($row['student_name']); ?></td>
                            <td class="px-3 py-3"><?= e($row['due_date']); ?></td>
                            <td class="px-3 py-3"><?= e(format_currency($row['amount'])); ?></td>
                            <td class="px-3 py-3"><?= e(format_currency($row['paid_amount'])); ?></td>
                            <td class="px-3 py-3"><?= e(format_currency($row['outstanding_amount'])); ?></td>
                            <td class="px-3 py-3"><?= e(invoice_status_label((string) $row['status'])); ?></td>
                            <td class="px-3 py-3"><?= (int) $row['days_overdue']; ?></td>
                            <td class="px-3 py-3"><?= e($row['fiscal_status'] ?: '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="10" class="px-3 py-6 text-center text-slate-500">Nenhuma conta a receber encontrada.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php include __DIR__ . '/reports_pagination.php'; ?>
    <?php endif; ?>

    <?php if ($tab === 'aging' && $aging): ?>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <article class="finance-reports-aging finance-reports-aging-current rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs uppercase text-slate-500">Atual/A vencer</p><p class="mt-2 text-xl font-semibold"><?= e(format_currency($aging['buckets']['current']['amount'])); ?></p><p class="text-xs text-slate-500"><?= (int) $aging['buckets']['current']['qty']; ?> faturas</p></article>
            <article class="finance-reports-aging finance-reports-aging-1-30 rounded-xl border border-amber-200 bg-amber-50 p-4"><p class="text-xs uppercase text-amber-700">1-30 dias</p><p class="mt-2 text-xl font-semibold text-amber-700"><?= e(format_currency($aging['buckets']['1_30']['amount'])); ?></p><p class="text-xs text-amber-700"><?= (int) $aging['buckets']['1_30']['qty']; ?> faturas</p></article>
            <article class="finance-reports-aging finance-reports-aging-31-60 rounded-xl border border-orange-200 bg-orange-50 p-4"><p class="text-xs uppercase text-orange-700">31-60 dias</p><p class="mt-2 text-xl font-semibold text-orange-700"><?= e(format_currency($aging['buckets']['31_60']['amount'])); ?></p><p class="text-xs text-orange-700"><?= (int) $aging['buckets']['31_60']['qty']; ?> faturas</p></article>
            <article class="finance-reports-aging finance-reports-aging-61-90 rounded-xl border border-rose-200 bg-rose-50 p-4"><p class="text-xs uppercase text-rose-700">61-90 dias</p><p class="mt-2 text-xl font-semibold text-rose-700"><?= e(format_currency($aging['buckets']['61_90']['amount'])); ?></p><p class="text-xs text-rose-700"><?= (int) $aging['buckets']['61_90']['qty']; ?> faturas</p></article>
            <article class="finance-reports-aging finance-reports-aging-90-plus rounded-xl border border-red-200 bg-red-50 p-4"><p class="text-xs uppercase text-red-700">90+ dias</p><p class="mt-2 text-xl font-semibold text-red-700"><?= e(format_currency($aging['buckets']['90_plus']['amount'])); ?></p><p class="text-xs text-red-700"><?= (int) $aging['buckets']['90_plus']['qty']; ?> faturas</p></article>
        </div>

        <section class="finance-reports-debtors rounded-xl border border-slate-200 bg-white p-4">
            <h3 class="mb-3 text-lg font-semibold">Top Devedores</h3>
            <div class="overflow-x-auto">
                <table class="finance-reports-table min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                            <th class="px-3 py-3">Aluno</th>
                            <th class="px-3 py-3">Faturas</th>
                            <th class="px-3 py-3">Saldo em aberto</th>
                            <th class="px-3 py-3">Maior atraso</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($aging['top_debtors'] as $row): ?>
                            <tr class="finance-reports-row border-b border-slate-100 hover:bg-slate-50">
                                <td class="px-3 py-3 font-medium"><?= e($row['full_name']); ?></td>
                                <td class="px-3 py-3"><?= (int) $row['invoices_qty']; ?></td>
                                <td class="px-3 py-3"><?= e(format_currency($row['outstanding_amount'])); ?></td>
                                <td class="px-3 py-3"><?= (int) $row['max_days_overdue']; ?> dias</td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($aging['top_debtors'] === []): ?>
                            <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500">Sem devedores no periodo.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($tab === 'fiscal'): ?>
        <?php if (!$fiscalAvailable): ?>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                Tabela fiscal ainda nao disponivel no banco atual. Execute a atualizacao SQL (tabela `fiscal_invoices`) para habilitar esta guia.
            </div>
        <?php else: ?>
            <?php $rows = $fiscal['rows']; $meta = $fiscal['meta']; ?>
            <div class="finance-reports-table-wrap overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="finance-reports-table min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                            <th class="px-3 py-3">Fatura</th>
                            <th class="px-3 py-3">Aluno</th>
                            <th class="px-3 py-3">Valor</th>
                            <th class="px-3 py-3">Status Fatura</th>
                            <th class="px-3 py-3">Provider</th>
                            <th class="px-3 py-3">Status NF-e</th>
                            <th class="px-3 py-3">Numero</th>
                            <th class="px-3 py-3">Ultima tentativa</th>
                            <th class="px-3 py-3">Erro</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr class="finance-reports-row border-b border-slate-100 hover:bg-slate-50">
                                <td class="px-3 py-3 font-medium"><?= e($row['invoice_number']); ?></td>
                                <td class="px-3 py-3"><?= e($row['student_name']); ?></td>
                                <td class="px-3 py-3"><?= e(format_currency($row['amount'])); ?></td>
                                <td class="px-3 py-3"><?= e(invoice_status_label((string) $row['invoice_status'])); ?></td>
                                <td class="px-3 py-3"><?= e($row['provider']); ?></td>
                                <td class="px-3 py-3"><?= e($row['fiscal_status']); ?></td>
                                <td class="px-3 py-3"><?= e($row['fiscal_number'] ?: '-'); ?></td>
                                <td class="px-3 py-3"><?= e($row['last_attempt_at'] ?: '-'); ?></td>
                                <td class="px-3 py-3"><?= e($row['error_message'] ?: '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($rows === []): ?>
                            <tr><td colspan="9" class="px-3 py-6 text-center text-slate-500">Nenhum registro fiscal encontrado.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php include __DIR__ . '/reports_pagination.php'; ?>
        <?php endif; ?>
    <?php endif; ?>
</section>
