<?php
$canPaymentCreate = has_permission('finance.payment.create');
$paymentMethods = is_array($paymentMethods ?? null) ? $paymentMethods : [];
$paymentMethodsAvailable = !empty($paymentMethodsAvailable);
$filters = is_array($filters ?? null) ? $filters : [];
$students = is_array($students ?? null) ? $students : [];
$paginationBase = [
    'route' => 'finance/payments',
    'period' => $filters['period'] ?? 'month_current',
    'start_date' => $filters['start_date'] ?? '',
    'end_date' => $filters['end_date'] ?? '',
    'q' => $filters['q'] ?? '',
    'student_id' => $filters['student_id'] ?? '',
    'per_page' => $meta['per_page'] ?? 50,
];
?>
<section class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-semibold">Pagamentos</h2>
            <p class="text-sm text-slate-500">Registre pagamento total, parcial ou em lote para varias faturas.</p>
        </div>
        <div class="flex gap-2">
            <a href="<?= route('finance/invoices'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Ver Faturas</a>
            <a href="<?= route('finance/reports'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Relatorios</a>
            <a href="<?= route('finance/payment-methods'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Formas de pagamento</a>
        </div>
    </div>

    <form method="get" action="index.php" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-4 xl:grid-cols-7">
        <input type="hidden" name="route" value="finance/payments">

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
        <input type="text" name="q" value="<?= e((string) ($filters['q'] ?? '')); ?>" placeholder="Buscar referencia ou observacao..." class="rounded-lg border border-slate-200 px-3 py-2 text-sm">

        <select name="student_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option value="">Todos os alunos</option>
            <?php foreach ($students as $student): ?>
                <option value="<?= (int) $student['id']; ?>" <?= (string) ($filters['student_id'] ?? '') === (string) $student['id'] ? 'selected' : ''; ?>><?= e($student['full_name']); ?></option>
            <?php endforeach; ?>
        </select>

        <select name="per_page" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <?php foreach ($paginationOptions as $opt): ?>
                <option value="<?= (int) $opt; ?>" <?= (int) $meta['per_page'] === (int) $opt ? 'selected' : ''; ?>><?= (int) $opt; ?>/pagina</option>
            <?php endforeach; ?>
        </select>

        <div class="flex gap-2 xl:col-span-7">
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Aplicar</button>
            <a href="index.php?<?= http_build_query(['route' => 'finance/payments']); ?>" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm hover:bg-slate-50">Limpar</a>
        </div>
    </form>

    <?php if ($canPaymentCreate): ?>
        <form method="post" action="<?= route('finance/payments/store'); ?>" class="space-y-4 rounded-xl border border-slate-200 bg-white p-4">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">

            <h3 class="text-lg font-semibold">Novo pagamento</h3>
            <p class="text-sm text-slate-500">A selecao abaixo respeita o filtro aplicado nesta tela. Por padrao, mostramos apenas faturas do mes atual.</p>
            <div class="grid gap-3 lg:grid-cols-4">
                <label class="block lg:col-span-2">
                    <span class="mb-1 block text-sm">Selecionar faturas (lote)</span>
                    <select name="invoice_ids[]" multiple size="6" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <?php foreach ($invoicesPool as $invoice): ?>
                            <option value="<?= (int) $invoice['id']; ?>">
                                <?= e($invoice['invoice_number']); ?> - <?= e($invoice['student_name']); ?><?= !empty($invoice['installment_label']) ? ' - Parcela ' . e($invoice['installment_label']) : ''; ?> - Venc. <?= e((string) $invoice['due_date']); ?> - Saldo <?= e(format_currency($invoice['amount'] - $invoice['paid_amount'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-xs text-slate-500">Use Ctrl/Cmd para selecionar varias. Total no filtro atual: <?= (int) count($invoicesPool); ?> faturas.</small>
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm">Valor pago *</span>
                    <input type="text" name="amount" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="0,00">
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm">Metodo</span>
                    <?php if ($paymentMethodsAvailable): ?>
                        <select name="payment_method_id" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <option value="">Selecione...</option>
                            <?php foreach ($paymentMethods as $method): ?>
                                <?php
                                $methodId = (int) ($method['id'] ?? 0);
                                $methodName = trim((string) ($method['name'] ?? ''));
                                if ($methodId <= 0 || $methodName === '') {
                                    continue;
                                }
                                ?>
                                <option value="<?= $methodId; ?>"><?= e($methodName); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <select name="method" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <option>PIX</option>
                            <option>Boleto</option>
                            <option>Cartao de credito</option>
                            <option>Transferencia</option>
                            <option>Dinheiro</option>
                        </select>
                    <?php endif; ?>
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm">Data pagamento</span>
                    <input type="date" name="paid_at" value="<?= date('Y-m-d'); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>

                <label class="block lg:col-span-3">
                    <span class="mb-1 block text-sm">Observacoes</span>
                    <input type="text" name="notes" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>
            </div>

            <button class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700">Registrar Pagamento</button>
        </form>
    <?php endif; ?>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-3">Referencia</th>
                    <th class="px-3 py-3">Metodo</th>
                    <th class="px-3 py-3">Valor</th>
                    <th class="px-3 py-3">Data pagamento</th>
                    <th class="px-3 py-3">Qtd. faturas</th>
                    <th class="px-3 py-3">Notas</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php $methodLabel = trim((string) ($row['payment_method_name'] ?? '')) ?: trim((string) ($row['method'] ?? '')); ?>
                    <tr class="border-b border-slate-100 hover:bg-slate-50">
                        <td class="px-3 py-3 font-medium"><?= e($row['payment_ref']); ?></td>
                        <td class="px-3 py-3"><?= e($methodLabel); ?></td>
                        <td class="px-3 py-3"><?= e(format_currency($row['amount'])); ?></td>
                        <td class="px-3 py-3"><?= e($row['paid_at']); ?></td>
                        <td class="px-3 py-3"><?= (int) $row['invoices_qty']; ?></td>
                        <td class="px-3 py-3"><?= e($row['notes']); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?>
                    <tr>
                        <td colspan="6" class="px-3 py-6 text-center text-slate-500">Nenhum pagamento encontrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
        <p>Total: <?= (int) $meta['total']; ?> registros | Pagina <?= (int) $meta['page']; ?>/<?= (int) $meta['pages']; ?></p>
        <div class="flex gap-2">
            <?php for ($p = 1; $p <= (int) $meta['pages']; $p++): ?>
                <a href="index.php?<?= http_build_query($paginationBase + ['page' => $p]); ?>" class="rounded px-3 py-1 <?= $p === (int) $meta['page'] ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white hover:bg-slate-50'; ?>"><?= $p; ?></a>
            <?php endfor; ?>
        </div>
    </div>
</section>
