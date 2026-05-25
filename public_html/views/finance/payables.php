<?php
$available = !empty($available);
$filters = is_array($filters ?? null) ? $filters : [];
$rows = is_array($rows ?? null) ? $rows : [];
$stats = is_array($stats ?? null) ? $stats : [];
$suppliers = is_array($suppliers ?? null) ? $suppliers : [];
$paymentMethods = is_array($paymentMethods ?? null) ? $paymentMethods : [];
$paymentMethodsAvailable = !empty($paymentMethodsAvailable);
$attachmentsAvailable = !empty($attachmentsAvailable);
$recurrenceAvailable = !empty($recurrenceAvailable);
$attachmentsByPayable = is_array($attachmentsByPayable ?? null) ? $attachmentsByPayable : [];
$attachmentTypeLabels = [
    'boleto' => 'Boleto',
    'nota_fiscal' => 'Nota fiscal',
    'contrato' => 'Contrato',
    'comprovante' => 'Comprovante',
    'outro' => 'Outro',
];
$recurrenceIntervalLabels = [
    'monthly' => 'Mensal',
    'quarterly' => 'Trimestral',
    'yearly' => 'Anual',
];
$meta = is_array($meta ?? null) ? $meta : pagination_meta(0, 50, 1);
$paginationOptions = is_array($paginationOptions ?? null) ? $paginationOptions : [50, 100, 200];
$paginationBase = [
    'route' => 'finance/payables',
    'period' => $filters['period'] ?? 'month_current',
    'start_date' => $filters['start_date'] ?? '',
    'end_date' => $filters['end_date'] ?? '',
    'q' => $filters['q'] ?? '',
    'supplier_id' => $filters['supplier_id'] ?? '',
    'status' => $filters['status'] ?? '',
    'per_page' => $meta['per_page'] ?? 50,
];
?>
<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold">Contas a Pagar</h2>
            <p class="text-sm text-slate-500">Controle despesas, fornecedores, vencimentos e baixas totais ou parciais.</p>
        </div>
        <div class="flex gap-2">
            <a href="<?= route('finance/suppliers'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Fornecedores</a>
        </div>
    </div>

    <?php if (!$available): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
            Estrutura indisponivel no banco. Execute a migration <code>migrations/20260523_finance_payables.sql</code>.
        </div>
    <?php else: ?>
        <?php if (!$attachmentsAvailable): ?>
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                Anexos de contas a pagar ainda indisponiveis no banco. Execute a migration <code>migrations/20260524_payable_attachments.sql</code>.
            </div>
        <?php endif; ?>
        <?php if (!$recurrenceAvailable): ?>
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                Recorrencia de despesas fixas ainda indisponivel no banco. Execute a migration <code>migrations/20260524_payable_recurrence.sql</code>.
            </div>
        <?php endif; ?>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <article class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs uppercase text-slate-500">Em aberto</p><p class="mt-2 text-2xl font-semibold"><?= (int) ($stats['open_count'] ?? 0); ?></p></article>
            <article class="rounded-xl border border-amber-200 bg-amber-50 p-4"><p class="text-xs uppercase text-amber-700">Parcial</p><p class="mt-2 text-2xl font-semibold text-amber-700"><?= (int) ($stats['partial_count'] ?? 0); ?></p></article>
            <article class="rounded-xl border border-rose-200 bg-rose-50 p-4"><p class="text-xs uppercase text-rose-700">Vencido</p><p class="mt-2 text-2xl font-semibold text-rose-700"><?= (int) ($stats['overdue_count'] ?? 0); ?></p></article>
            <article class="rounded-xl border border-emerald-200 bg-emerald-50 p-4"><p class="text-xs uppercase text-emerald-700">Pago</p><p class="mt-2 text-2xl font-semibold text-emerald-700"><?= (int) ($stats['paid_count'] ?? 0); ?></p></article>
            <article class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs uppercase text-slate-500">Rascunho</p><p class="mt-2 text-2xl font-semibold"><?= (int) ($stats['draft_count'] ?? 0); ?></p></article>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <article class="rounded-xl border border-amber-200 bg-amber-50 p-4"><p class="text-xs uppercase text-amber-700">Saldo em aberto</p><p class="mt-2 text-xl font-semibold text-amber-700"><?= e(format_currency((float) ($stats['open_amount'] ?? 0))); ?></p></article>
            <article class="rounded-xl border border-rose-200 bg-rose-50 p-4"><p class="text-xs uppercase text-rose-700">Saldo vencido</p><p class="mt-2 text-xl font-semibold text-rose-700"><?= e(format_currency((float) ($stats['overdue_amount'] ?? 0))); ?></p></article>
            <article class="rounded-xl border border-cyan-200 bg-cyan-50 p-4"><p class="text-xs uppercase text-cyan-700">Pago no periodo</p><p class="mt-2 text-xl font-semibold text-cyan-700"><?= e(format_currency((float) ($stats['paid_period_amount'] ?? 0))); ?></p></article>
        </div>

        <form method="get" action="index.php" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-4 xl:grid-cols-7">
            <input type="hidden" name="route" value="finance/payables">
            <select name="period" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="today" <?= ($filters['period'] ?? '') === 'today' ? 'selected' : ''; ?>>Hoje</option>
                <option value="last_7" <?= ($filters['period'] ?? '') === 'last_7' ? 'selected' : ''; ?>>Ultimos 7 dias</option>
                <option value="last_30" <?= ($filters['period'] ?? '') === 'last_30' ? 'selected' : ''; ?>>Ultimos 30 dias</option>
                <option value="month_current" <?= ($filters['period'] ?? 'month_current') === 'month_current' ? 'selected' : ''; ?>>Mes atual</option>
                <option value="month_previous" <?= ($filters['period'] ?? '') === 'month_previous' ? 'selected' : ''; ?>>Mes anterior</option>
                <option value="custom" <?= ($filters['period'] ?? '') === 'custom' ? 'selected' : ''; ?>>Personalizado</option>
            </select>
            <input type="date" name="start_date" value="<?= e((string) ($filters['start_date'] ?? '')); ?>" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <input type="date" name="end_date" value="<?= e((string) ($filters['end_date'] ?? '')); ?>" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <input type="text" name="q" value="<?= e((string) ($filters['q'] ?? '')); ?>" placeholder="Buscar numero, descricao ou categoria..." class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <select name="supplier_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Todos os fornecedores</option>
                <?php foreach ($suppliers as $supplier): ?>
                    <option value="<?= (int) $supplier['id']; ?>" <?= (string) ($filters['supplier_id'] ?? '') === (string) $supplier['id'] ? 'selected' : ''; ?>><?= e((string) $supplier['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Todos os status</option>
                <?php foreach (['draft' => 'Rascunho', 'open' => 'Em aberto', 'partial' => 'Parcial', 'paid' => 'Pago', 'overdue' => 'Vencido', 'cancelled' => 'Cancelado'] as $k => $v): ?>
                    <option value="<?= $k; ?>" <?= ($filters['status'] ?? '') === $k ? 'selected' : ''; ?>><?= $v; ?></option>
                <?php endforeach; ?>
            </select>
            <select name="per_page" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <?php foreach ($paginationOptions as $opt): ?>
                    <option value="<?= (int) $opt; ?>" <?= (int) ($meta['per_page'] ?? 50) === (int) $opt ? 'selected' : ''; ?>><?= (int) $opt; ?>/pagina</option>
                <?php endforeach; ?>
            </select>
            <div class="flex gap-2 xl:col-span-7">
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Aplicar</button>
                <a href="index.php?<?= http_build_query(['route' => 'finance/payables']); ?>" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm hover:bg-slate-50">Limpar</a>
            </div>
        </form>

        <?php if ($recurrenceAvailable): ?>
            <form method="post" action="<?= route('finance/payables/recurring'); ?>" class="flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white p-4">
                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                <span class="text-sm font-semibold text-slate-700">Despesas fixas</span>
                <input type="month" name="reference_month" value="<?= date('Y-m'); ?>" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <button class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold hover:bg-slate-50">Gerar recorrentes</button>
            </form>
        <?php endif; ?>

        <form method="post" action="<?= route('finance/payables/store'); ?>" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-2 xl:grid-cols-4">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">

            <label class="block">
                <span class="mb-1 block text-sm font-medium">Numero *</span>
                <input type="text" name="payable_number" required value="<?= e((string) ($nextPayableNumber ?? '')); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </label>
            <label class="block">
                <span class="mb-1 block text-sm font-medium">Fornecedor *</span>
                <select name="supplier_id" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <option value="">Selecione...</option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?= (int) $supplier['id']; ?>"><?= e((string) $supplier['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="block xl:col-span-2">
                <span class="mb-1 block text-sm font-medium">Descricao *</span>
                <input type="text" name="description" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Ex.: Licenca Zoom, hospedagem, consultoria, aluguel...">
            </label>
            <label class="block">
                <span class="mb-1 block text-sm font-medium">Categoria</span>
                <input type="text" name="category" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Software, Operacional, Fiscal...">
            </label>
            <label class="block">
                <span class="mb-1 block text-sm font-medium">Competencia</span>
                <input type="date" name="competence_date" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </label>
            <label class="block">
                <span class="mb-1 block text-sm font-medium">Vencimento *</span>
                <input type="date" name="due_date" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </label>
            <label class="block">
                <span class="mb-1 block text-sm font-medium">Valor *</span>
                <input type="text" name="amount" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="0,00">
            </label>
            <label class="block">
                <span class="mb-1 block text-sm font-medium">Forma de pagamento</span>
                <?php if ($paymentMethodsAvailable): ?>
                    <select name="payment_method_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option value="">Selecione...</option>
                        <?php foreach ($paymentMethods as $method): ?>
                            <?php if ((int) ($method['id'] ?? 0) <= 0 || trim((string) ($method['name'] ?? '')) === '') { continue; } ?>
                            <option value="<?= (int) $method['id']; ?>"><?= e((string) $method['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input type="text" disabled value="Execute a migration de formas de pagamento." class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-400">
                <?php endif; ?>
            </label>
            <label class="block">
                <span class="mb-1 block text-sm font-medium">Status inicial</span>
                <select name="status" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <option value="open">Em aberto</option>
                    <option value="draft">Rascunho</option>
                </select>
            </label>
            <label class="block xl:col-span-2">
                <span class="mb-1 block text-sm font-medium">Observacoes</span>
                <input type="text" name="notes" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </label>
            <?php if ($recurrenceAvailable): ?>
                <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                    <input type="checkbox" name="is_recurring" value="1" class="rounded border-slate-300">
                    <span>Despesa fixa</span>
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm font-medium">Recorrencia</span>
                    <select name="recurrence_interval" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <?php foreach ($recurrenceIntervalLabels as $intervalValue => $intervalLabel): ?>
                            <option value="<?= e($intervalValue); ?>"><?= e($intervalLabel); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block xl:col-span-2">
                    <span class="mb-1 block text-sm font-medium">Gerar ate</span>
                    <input type="date" name="recurrence_until" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>
            <?php endif; ?>
            <div class="xl:col-span-4">
                <button class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700">Salvar conta a pagar</button>
            </div>
        </form>

        <div class="finance-scroll-shell space-y-2">
            <div class="finance-scroll-proxy-wrap" data-horizontal-scroll-proxy-wrap>
                <div class="finance-scroll-proxy" data-horizontal-scroll-proxy>
                    <div data-horizontal-scroll-proxy-content></div>
                </div>
            </div>
            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white" data-horizontal-scroll-area>
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-3 py-3">Numero</th>
                        <th class="px-3 py-3">Fornecedor</th>
                        <th class="px-3 py-3">Descricao</th>
                        <th class="px-3 py-3">Vencimento</th>
                        <th class="px-3 py-3">Valor</th>
                        <th class="px-3 py-3">Pago</th>
                        <th class="px-3 py-3">Saldo</th>
                        <th class="px-3 py-3">Status</th>
                        <th class="px-3 py-3">Operacoes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $payableStatus = (string) ($row['status'] ?? '');
                        $canSettle = in_array($payableStatus, ['open', 'partial', 'overdue'], true);
                        $canCancel = !in_array($payableStatus, ['paid', 'cancelled'], true) && (float) ($row['paid_amount'] ?? 0) <= 0.0001;
                        $canEdit = $payableStatus !== 'cancelled';
                        $rowAttachments = $attachmentsByPayable[(int) ($row['id'] ?? 0)] ?? [];
                        $isRecurringTemplate = (int) ($row['is_recurring'] ?? 0) === 1 && (int) ($row['recurrence_parent_id'] ?? 0) <= 0;
                        $isRecurringGenerated = (int) ($row['recurrence_parent_id'] ?? 0) > 0;
                        $editStatusOptions = [
                            'draft' => 'Rascunho',
                            'open' => 'Em aberto',
                            'partial' => 'Parcial',
                            'paid' => 'Pago',
                            'overdue' => 'Vencido',
                        ];
                        $statusClass = match ($payableStatus) {
                            'paid' => 'bg-emerald-100 text-emerald-700',
                            'partial' => 'bg-amber-100 text-amber-700',
                            'overdue' => 'bg-rose-100 text-rose-700',
                            'cancelled' => 'bg-slate-200 text-slate-700',
                            'draft' => 'bg-slate-100 text-slate-700',
                            default => 'bg-cyan-100 text-cyan-700',
                        };
                        ?>
                        <tr class="border-b border-slate-100 align-top">
                            <td class="px-3 py-3 font-medium">
                                <p><?= e((string) ($row['payable_number'] ?? '')); ?></p>
                                <p class="text-xs text-slate-500"><?= e((string) ($row['category'] ?? '')); ?></p>
                                <?php if ($isRecurringTemplate): ?>
                                    <span class="mt-1 inline-flex rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-700">Despesa fixa</span>
                                <?php elseif ($isRecurringGenerated): ?>
                                    <span class="mt-1 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">Gerada</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-3">
                                <p><?= e((string) ($row['supplier_name'] ?? '')); ?></p>
                                <p class="text-xs text-slate-500"><?= e((string) ($row['payment_method_name'] ?? '-')); ?></p>
                            </td>
                            <td class="px-3 py-3">
                                <p><?= e((string) ($row['description'] ?? '')); ?></p>
                                <p class="text-xs text-slate-500"><?= e((string) ($row['notes'] ?? '')); ?></p>
                            </td>
                            <td class="px-3 py-3">
                                <p><?= e((string) ($row['due_date'] ?? '')); ?></p>
                                <p class="text-xs text-slate-500"><?= !empty($row['days_overdue']) ? e((string) ((int) $row['days_overdue'] . ' dias')) : ''; ?></p>
                            </td>
                            <td class="px-3 py-3"><?= e(format_currency((float) ($row['amount'] ?? 0))); ?></td>
                            <td class="px-3 py-3"><?= e(format_currency((float) ($row['paid_amount'] ?? 0))); ?></td>
                            <td class="px-3 py-3"><?= e(format_currency((float) ($row['outstanding_amount'] ?? 0))); ?></td>
                            <td class="px-3 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-semibold <?= $statusClass; ?>">
                                    <?= e(invoice_status_label($payableStatus)); ?>
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <div class="space-y-2">
                                    <?php if ($canSettle): ?>
                                        <form method="post" action="<?= route('finance/payables/settle'); ?>" class="space-y-2">
                                            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                            <input type="hidden" name="payable_id" value="<?= (int) $row['id']; ?>">
                                            <input type="text" name="amount" value="<?= e(number_format((float) ($row['outstanding_amount'] ?? 0), 2, ',', '.')); ?>" class="w-28 rounded border border-slate-200 px-2 py-1 text-xs">
                                            <input type="date" name="paid_at" value="<?= date('Y-m-d'); ?>" class="w-32 rounded border border-slate-200 px-2 py-1 text-xs">
                                            <?php if ($paymentMethodsAvailable): ?>
                                                <select name="payment_method_id" class="w-36 rounded border border-slate-200 px-2 py-1 text-xs">
                                                    <option value="">Forma...</option>
                                                    <?php foreach ($paymentMethods as $method): ?>
                                                        <?php if ((int) ($method['id'] ?? 0) <= 0 || trim((string) ($method['name'] ?? '')) === '') { continue; } ?>
                                                        <option value="<?= (int) $method['id']; ?>"><?= e((string) $method['name']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php endif; ?>
                                            <input type="text" name="notes" placeholder="Observacao" class="w-40 rounded border border-slate-200 px-2 py-1 text-xs">
                                            <button class="rounded border border-cyan-200 bg-cyan-50 px-2 py-1 text-xs text-cyan-700 hover:bg-cyan-100">Registrar baixa</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-400">Sem baixa disponivel</span>
                                    <?php endif; ?>

                                    <?php if ($canEdit): ?>
                                        <details class="rounded-lg border border-slate-200 bg-slate-50 p-2 text-xs text-slate-600">
                                            <summary class="cursor-pointer select-none font-semibold text-slate-700">Editar conta</summary>
                                            <div class="mt-3 space-y-3">
                                                <form method="post" action="<?= route('finance/payables/update'); ?>" class="grid gap-3 md:grid-cols-2">
                                                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                                    <input type="hidden" name="payable_id" value="<?= (int) $row['id']; ?>">

                                                    <label class="block">
                                                        <span class="mb-1 block font-medium">Numero</span>
                                                        <input type="text" name="payable_number" required value="<?= e((string) ($row['payable_number'] ?? '')); ?>" class="w-full rounded border border-slate-200 bg-white px-2 py-1.5">
                                                    </label>
                                                    <label class="block">
                                                        <span class="mb-1 block font-medium">Fornecedor</span>
                                                        <select name="supplier_id" required class="w-full rounded border border-slate-200 bg-white px-2 py-1.5">
                                                            <?php foreach ($suppliers as $supplier): ?>
                                                                <option value="<?= (int) $supplier['id']; ?>" <?= (string) ($row['supplier_id'] ?? '') === (string) $supplier['id'] ? 'selected' : ''; ?>><?= e((string) $supplier['name']); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </label>
                                                    <label class="block md:col-span-2">
                                                        <span class="mb-1 block font-medium">Descricao</span>
                                                        <input type="text" name="description" required value="<?= e((string) ($row['description'] ?? '')); ?>" class="w-full rounded border border-slate-200 bg-white px-2 py-1.5">
                                                    </label>
                                                    <label class="block">
                                                        <span class="mb-1 block font-medium">Categoria</span>
                                                        <input type="text" name="category" value="<?= e((string) ($row['category'] ?? '')); ?>" class="w-full rounded border border-slate-200 bg-white px-2 py-1.5">
                                                    </label>
                                                    <label class="block">
                                                        <span class="mb-1 block font-medium">Competencia</span>
                                                        <input type="date" name="competence_date" value="<?= e((string) ($row['competence_date'] ?? '')); ?>" class="w-full rounded border border-slate-200 bg-white px-2 py-1.5">
                                                    </label>
                                                    <label class="block">
                                                        <span class="mb-1 block font-medium">Vencimento</span>
                                                        <input type="date" name="due_date" required value="<?= e((string) ($row['due_date'] ?? '')); ?>" class="w-full rounded border border-slate-200 bg-white px-2 py-1.5">
                                                    </label>
                                                    <label class="block">
                                                        <span class="mb-1 block font-medium">Valor</span>
                                                        <input type="text" name="amount" required value="<?= e(number_format((float) ($row['amount'] ?? 0), 2, ',', '.')); ?>" class="w-full rounded border border-slate-200 bg-white px-2 py-1.5">
                                                    </label>
                                                    <label class="block">
                                                        <span class="mb-1 block font-medium">Status</span>
                                                        <select name="status" class="w-full rounded border border-slate-200 bg-white px-2 py-1.5">
                                                            <?php foreach ($editStatusOptions as $statusValue => $statusLabel): ?>
                                                                <option value="<?= e($statusValue); ?>" <?= $payableStatus === $statusValue ? 'selected' : ''; ?>><?= e($statusLabel); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </label>
                                                    <label class="block">
                                                        <span class="mb-1 block font-medium">Forma de pagamento</span>
                                                        <select name="payment_method_id" class="w-full rounded border border-slate-200 bg-white px-2 py-1.5">
                                                            <option value="">Selecione...</option>
                                                            <?php foreach ($paymentMethods as $method): ?>
                                                                <?php if ((int) ($method['id'] ?? 0) <= 0 || trim((string) ($method['name'] ?? '')) === '') { continue; } ?>
                                                                <option value="<?= (int) $method['id']; ?>" <?= (string) ($row['payment_method_id'] ?? '') === (string) $method['id'] ? 'selected' : ''; ?>><?= e((string) $method['name']); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </label>
                                                    <label class="block md:col-span-2">
                                                        <span class="mb-1 block font-medium">Observacoes</span>
                                                        <input type="text" name="notes" value="<?= e((string) ($row['notes'] ?? '')); ?>" class="w-full rounded border border-slate-200 bg-white px-2 py-1.5">
                                                    </label>
                                                    <?php if ($recurrenceAvailable && !$isRecurringGenerated): ?>
                                                        <label class="flex items-center gap-2 rounded border border-slate-200 bg-white px-2 py-1.5">
                                                            <input type="checkbox" name="is_recurring" value="1" class="rounded border-slate-300" <?= $isRecurringTemplate ? 'checked' : ''; ?>>
                                                            <span>Despesa fixa</span>
                                                        </label>
                                                        <label class="block">
                                                            <span class="mb-1 block font-medium">Recorrencia</span>
                                                            <select name="recurrence_interval" class="w-full rounded border border-slate-200 bg-white px-2 py-1.5">
                                                                <?php foreach ($recurrenceIntervalLabels as $intervalValue => $intervalLabel): ?>
                                                                    <option value="<?= e($intervalValue); ?>" <?= (string) ($row['recurrence_interval'] ?? 'monthly') === $intervalValue ? 'selected' : ''; ?>><?= e($intervalLabel); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </label>
                                                        <label class="block md:col-span-2">
                                                            <span class="mb-1 block font-medium">Gerar ate</span>
                                                            <input type="date" name="recurrence_until" value="<?= e((string) ($row['recurrence_until'] ?? '')); ?>" class="w-full rounded border border-slate-200 bg-white px-2 py-1.5">
                                                        </label>
                                                    <?php endif; ?>
                                                    <div class="md:col-span-2 flex flex-wrap gap-2">
                                                        <button class="rounded border border-sky-200 bg-sky-50 px-3 py-1.5 font-semibold text-sky-700 hover:bg-sky-100">Salvar alteracoes</button>
                                                        <?php if (!empty($row['paid_amount'])): ?>
                                                            <span class="self-center text-slate-500">Conta com pagamento registrado: o status final sera recalculado automaticamente.</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </form>

                                                <?php if ($canCancel): ?>
                                                    <form method="post" action="<?= route('finance/payables/cancel'); ?>" onsubmit="return confirm('Cancelar esta conta a pagar?');">
                                                        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                                        <input type="hidden" name="payable_id" value="<?= (int) $row['id']; ?>">
                                                        <button class="rounded border border-rose-200 bg-rose-50 px-3 py-1.5 font-semibold text-rose-700 hover:bg-rose-100">Cancelar conta</button>
                                                    </form>
                                                <?php else: ?>
                                                    <p class="text-slate-500">Cancelamento disponivel apenas para contas sem pagamento registrado.</p>
                                                <?php endif; ?>
                                            </div>
                                        </details>
                                    <?php endif; ?>

                                    <?php if ($attachmentsAvailable): ?>
                                        <details class="rounded-lg border border-slate-200 bg-slate-50 p-2 text-xs text-slate-600">
                                            <summary class="cursor-pointer select-none font-semibold text-slate-700">
                                                Anexos<?= $rowAttachments !== [] ? ' (' . count($rowAttachments) . ')' : ''; ?>
                                            </summary>
                                            <div class="mt-3 space-y-3">
                                                <form method="post" action="<?= route('finance/payables/attachments/store'); ?>" enctype="multipart/form-data" class="grid gap-2 md:grid-cols-2">
                                                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                                    <input type="hidden" name="payable_id" value="<?= (int) $row['id']; ?>">
                                                    <label class="block">
                                                        <span class="mb-1 block font-medium">Tipo</span>
                                                        <select name="attachment_type" class="w-full rounded border border-slate-200 bg-white px-2 py-1.5">
                                                            <?php foreach ($attachmentTypeLabels as $typeValue => $typeLabel): ?>
                                                                <option value="<?= e($typeValue); ?>"><?= e($typeLabel); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </label>
                                                    <label class="block">
                                                        <span class="mb-1 block font-medium">Arquivo</span>
                                                        <input type="file" name="attachment_file" required accept=".pdf,.png,.jpg,.jpeg,.webp,.doc,.docx,.xls,.xlsx,.txt" class="w-full rounded border border-slate-200 bg-white px-2 py-1.5">
                                                    </label>
                                                    <label class="block md:col-span-2">
                                                        <span class="mb-1 block font-medium">Observacao</span>
                                                        <input type="text" name="attachment_notes" class="w-full rounded border border-slate-200 bg-white px-2 py-1.5" placeholder="Ex.: comprovante da baixa, boleto original...">
                                                    </label>
                                                    <div class="md:col-span-2">
                                                        <button class="rounded border border-cyan-200 bg-cyan-50 px-3 py-1.5 font-semibold text-cyan-700 hover:bg-cyan-100">Anexar arquivo</button>
                                                    </div>
                                                </form>

                                                <div class="space-y-2">
                                                    <?php if ($rowAttachments === []): ?>
                                                        <p class="text-slate-500">Nenhum anexo registrado.</p>
                                                    <?php else: ?>
                                                        <?php foreach ($rowAttachments as $attachment): ?>
                                                            <?php
                                                            $attachmentType = (string) ($attachment['attachment_type'] ?? 'outro');
                                                            $attachmentLabel = $attachmentTypeLabels[$attachmentType] ?? 'Outro';
                                                            $attachmentSize = (int) ($attachment['file_size'] ?? 0);
                                                            ?>
                                                            <div class="flex flex-wrap items-center justify-between gap-2 rounded border border-slate-200 bg-white px-2 py-2">
                                                                <div>
                                                                    <p class="font-semibold text-slate-700"><?= e($attachmentLabel); ?>: <?= e((string) ($attachment['original_file_name'] ?? 'arquivo')); ?></p>
                                                                    <p class="text-slate-500">
                                                                        <?= e((string) ($attachment['notes'] ?? '')); ?>
                                                                        <?= $attachmentSize > 0 ? e(' | ' . number_format($attachmentSize / 1024, 1, ',', '.') . ' KB') : ''; ?>
                                                                    </p>
                                                                </div>
                                                                <div class="flex flex-wrap gap-2">
                                                                    <a href="<?= route('finance/payables/attachments/download&id=' . (int) $attachment['id']); ?>" class="rounded border border-slate-200 bg-slate-50 px-2 py-1 font-semibold text-slate-700 hover:bg-slate-100">Baixar</a>
                                                                    <form method="post" action="<?= route('finance/payables/attachments/delete'); ?>" onsubmit="return confirm('Remover este anexo?');">
                                                                        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                                                        <input type="hidden" name="attachment_id" value="<?= (int) $attachment['id']; ?>">
                                                                        <button class="rounded border border-rose-200 bg-rose-50 px-2 py-1 font-semibold text-rose-700 hover:bg-rose-100">Remover</button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </details>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="9" class="px-3 py-6 text-center text-slate-500">Nenhuma conta a pagar encontrada.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
            <p>Total: <?= (int) ($meta['total'] ?? 0); ?> registros | Pagina <?= (int) ($meta['page'] ?? 1); ?>/<?= (int) ($meta['pages'] ?? 1); ?></p>
            <div class="flex gap-2">
                <?php for ($p = 1; $p <= (int) ($meta['pages'] ?? 1); $p++): ?>
                    <a href="index.php?<?= http_build_query($paginationBase + ['page' => $p]); ?>" class="rounded px-3 py-1 <?= $p === (int) ($meta['page'] ?? 1) ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white hover:bg-slate-50'; ?>"><?= $p; ?></a>
                <?php endfor; ?>
            </div>
        </div>
    <?php endif; ?>
</section>
