<?php
/** @var array  $summary      totais financeiros do aluno */
/** @var array  $invoices     lista de faturas */
/** @var string $statusFilter filtro de status ativo */
/** @var int    $page         página atual */
/** @var bool   $hasNextPage  existe próxima página */

$statusLabels = [
    'open'    => 'Em aberto',
    'partial' => 'Parcial',
    'overdue' => 'Vencida',
    'paid'    => 'Pago',
    'renegotiated' => 'Renegociada',
    'draft'   => 'Rascunho',
];

$statusBadge = [
    'open'    => 'bg-blue-100 text-blue-700',
    'partial' => 'bg-amber-100 text-amber-700',
    'overdue' => 'bg-rose-100 text-rose-700',
    'paid'    => 'bg-emerald-100 text-emerald-700',
    'renegotiated' => 'bg-violet-100 text-violet-700',
    'draft'   => 'bg-slate-100 text-slate-600',
];

$filterTabs = [
    ''        => 'Todas',
    'pending' => 'Em aberto',
    'overdue' => 'Vencidas',
    'paid'    => 'Pagas',
];

function finance_url(string $status, int $page = 1): string {
    $params = ['route' => 'student/finances'];
    if ($status !== '') { $params['status'] = $status; }
    if ($page > 1)      { $params['page']   = $page; }
    return 'index.php?' . http_build_query($params);
}
?>
<section class="space-y-6">

    <!-- Título -->
    <div>
        <h2 class="text-2xl font-semibold text-slate-800">Financeiro</h2>
        <p class="text-sm text-slate-500">Acompanhe suas faturas e situação financeira junto à ANEO.</p>
    </div>

    <!-- Cards de resumo -->
    <div class="grid gap-4 sm:grid-cols-3">

        <!-- Vencidas -->
        <article class="rounded-xl border border-rose-200 bg-rose-50/80 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-rose-600">Vencidas</p>
            <p class="mt-2 text-2xl font-bold text-rose-700"><?= format_currency((float) ($summary['total_overdue'] ?? 0)); ?></p>
            <p class="mt-1 text-xs text-rose-500"><?= (int) ($summary['count_overdue'] ?? 0); ?> fatura(s)</p>
        </article>

        <!-- Em aberto -->
        <article class="rounded-xl border border-amber-200 bg-amber-50/80 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-600">Em aberto</p>
            <p class="mt-2 text-2xl font-bold text-amber-700"><?= format_currency((float) ($summary['total_open'] ?? 0)); ?></p>
            <p class="mt-1 text-xs text-amber-500">
                <?= (int) ($summary['count_open'] ?? 0); ?> fatura(s)
                <?php if (!empty($summary['next_due_date'])): ?>
                    &mdash; próx. <?= date('d/m/Y', strtotime($summary['next_due_date'])); ?>
                <?php endif; ?>
            </p>
        </article>

        <!-- Pagas -->
        <article class="rounded-xl border border-emerald-200 bg-emerald-50/80 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Pagas</p>
            <p class="mt-2 text-2xl font-bold text-emerald-700"><?= format_currency((float) ($summary['total_paid'] ?? 0)); ?></p>
            <p class="mt-1 text-xs text-emerald-500"><?= (int) ($summary['count_paid'] ?? 0); ?> fatura(s)</p>
        </article>

    </div>

    <!-- Filtros de status -->
    <div class="flex flex-wrap gap-2">
        <?php foreach ($filterTabs as $val => $label): ?>
            <?php $isActive = $statusFilter === $val; ?>
            <a href="<?= finance_url($val); ?>"
               class="rounded-lg px-4 py-2 text-sm font-medium transition
                      <?= $isActive
                            ? 'bg-sky-600 text-white shadow-sm'
                            : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50'; ?>">
                <?= e($label); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Tabela de faturas -->
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <?php if ($invoices === []): ?>
            <div class="px-6 py-12 text-center text-sm text-slate-400">
                Nenhuma fatura encontrada para o filtro selecionado.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3 text-left">Fatura</th>
                            <th class="px-4 py-3 text-left">Vencimento</th>
                            <th class="px-4 py-3 text-right">Valor</th>
                            <th class="px-4 py-3 text-right">Pago</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-center">Boleto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($invoices as $inv): ?>
                            <?php
                            $status     = (string) ($inv['status'] ?? 'open');
                            $badge      = $statusBadge[$status] ?? 'bg-slate-100 text-slate-600';
                            $badgeTone  = 'student-finance-status student-finance-status-' . preg_replace('/[^a-z0-9_-]+/i', '-', $status);
                            $label      = $statusLabels[$status] ?? $status;
                            $isOverdue  = $status === 'overdue';
                            $dueDate    = (string) ($inv['due_date'] ?? '');
                            $dueFmt     = $dueDate !== '' ? date('d/m/Y', strtotime($dueDate)) : '—';
                            $subtitle   = trim((string) ($inv['project_name'] ?? $inv['tags'] ?? ''));
                            $boletoUrl  = trim((string) ($inv['boleto_url'] ?? ''));
                            $hasBoleto  = $boletoUrl !== '';
                            ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-800"><?= e($inv['invoice_number'] ?? '—'); ?></p>
                                    <?php if ($subtitle !== ''): ?>
                                        <p class="text-xs text-slate-400"><?= e($subtitle); ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 <?= $isOverdue ? 'font-semibold text-rose-600' : 'text-slate-600'; ?>">
                                    <?= e($dueFmt); ?>
                                    <?php if ($isOverdue): ?>
                                        <span class="student-finance-overdue-chip ml-1 inline-block rounded px-1.5 py-0.5 text-[10px] font-bold">VENCIDA</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-slate-800">
                                    <?= format_currency((float) ($inv['amount'] ?? 0)); ?>
                                </td>
                                <td class="px-4 py-3 text-right text-slate-500">
                                    <?= format_currency((float) ($inv['paid_amount'] ?? 0)); ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-block rounded-full px-3 py-0.5 text-xs font-semibold <?= $badge; ?> <?= $badgeTone; ?>">
                                        <?= e($label); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <?php if ($hasBoleto): ?>
                                        <a href="<?= e($boletoUrl); ?>" target="_blank" rel="noopener"
                                           class="inline-block rounded-lg border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-100">
                                            Ver boleto
                                        </a>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-300">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Paginação -->
    <?php if ($page > 1 || $hasNextPage): ?>
        <div class="flex items-center justify-between">
            <?php if ($page > 1): ?>
                <a href="<?= finance_url($statusFilter, $page - 1); ?>"
                   class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                    ← Anterior
                </a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>

            <span class="text-xs text-slate-400">Página <?= $page; ?></span>

            <?php if ($hasNextPage): ?>
                <a href="<?= finance_url($statusFilter, $page + 1); ?>"
                   class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                    Próxima →
                </a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</section>
