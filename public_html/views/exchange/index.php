<?php
/** @var array $rows lista de solicitacoes */
/** @var array $meta paginacao */
/** @var array $filters filtros ativos */
/** @var array $counts contagens por status */
/** @var bool  $featureAvailable tabela existe */

$statusLabels = [
    'pending'  => 'Aguardando',
    'viewed'   => 'Visualizado',
    'approved' => 'Aprovado',
    'rejected' => 'Recusado',
];

$statusBadge = [
    'pending'  => 'bg-amber-100 text-amber-700',
    'viewed'   => 'bg-sky-100 text-sky-700',
    'approved' => 'bg-emerald-100 text-emerald-700',
    'rejected' => 'bg-rose-100 text-rose-700',
];

function exchange_url(array $filters, int $page = 1): string
{
    $params = ['route' => 'exchange'];
    if (($filters['status'] ?? '') !== '') {
        $params['status'] = $filters['status'];
    }
    if (($filters['q'] ?? '') !== '') {
        $params['q'] = $filters['q'];
    }
    if ($page > 1) {
        $params['page'] = $page;
    }

    return 'index.php?' . http_build_query($params);
}
?>
<div class="exchange-index space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold text-slate-800">Interc&acirc;mbio Aluno</h2>
            <p class="text-sm text-slate-500">Solicita&ccedil;&otilde;es de interc&acirc;mbio entre unidades enviadas pelos alunos.</p>
        </div>
    </div>

    <?php if (!$featureAvailable): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            A tabela <code>student_exchange_requests</code> n&atilde;o existe no banco. Execute a migration para ativar este m&oacute;dulo.
        </div>
    <?php else: ?>

    <div class="grid gap-4 sm:grid-cols-4">
        <article class="exchange-kpi exchange-kpi-pending rounded-xl border border-amber-200 bg-amber-50/80 p-4 text-center">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-600">Aguardando</p>
            <p class="mt-1 text-3xl font-bold text-amber-700"><?= (int) ($counts['pending'] ?? 0); ?></p>
        </article>
        <article class="exchange-kpi exchange-kpi-viewed rounded-xl border border-sky-200 bg-sky-50/80 p-4 text-center">
            <p class="text-xs font-semibold uppercase tracking-wide text-sky-600">Visualizados</p>
            <p class="mt-1 text-3xl font-bold text-sky-700"><?= (int) ($counts['viewed'] ?? 0); ?></p>
        </article>
        <article class="exchange-kpi exchange-kpi-approved rounded-xl border border-emerald-200 bg-emerald-50/80 p-4 text-center">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Aprovados</p>
            <p class="mt-1 text-3xl font-bold text-emerald-700"><?= (int) ($counts['approved'] ?? 0); ?></p>
        </article>
        <article class="exchange-kpi exchange-kpi-rejected rounded-xl border border-rose-200 bg-rose-50/80 p-4 text-center">
            <p class="text-xs font-semibold uppercase tracking-wide text-rose-600">Recusados</p>
            <p class="mt-1 text-3xl font-bold text-rose-700"><?= (int) ($counts['rejected'] ?? 0); ?></p>
        </article>
    </div>

    <div class="exchange-filter-wrap flex flex-wrap items-center gap-3">
        <form method="GET" action="index.php" class="exchange-filter-form flex flex-wrap items-center gap-2">
            <input type="hidden" name="route" value="exchange">
            <input type="text" name="q" value="<?= e((string) ($filters['q'] ?? '')); ?>"
                   placeholder="Buscar aluno ou unidade..."
                   class="exchange-filter-input rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-100 w-64">
            <select name="status"
                    class="exchange-filter-select rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-100">
                <option value="">Todos os status</option>
                <?php foreach ($statusLabels as $val => $lbl): ?>
                    <option value="<?= e($val); ?>" <?= (($filters['status'] ?? '') === $val) ? 'selected' : ''; ?>><?= e($lbl); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit"
                    class="exchange-filter-button rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700 transition">
                Filtrar
            </button>
            <?php if (($filters['q'] ?? '') !== '' || ($filters['status'] ?? '') !== ''): ?>
                <a href="<?= route('exchange'); ?>" class="exchange-filter-clear text-sm text-slate-500 hover:text-slate-700">Limpar</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="exchange-table-wrap rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <?php if ($rows === []): ?>
            <div class="px-6 py-12 text-center text-sm text-slate-400">
                Nenhuma solicita&ccedil;&atilde;o encontrada.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3 text-left">#</th>
                            <th class="px-4 py-3 text-left">Aluno</th>
                            <th class="px-4 py-3 text-left">Unidade atual</th>
                            <th class="px-4 py-3 text-left">Destino</th>
                            <th class="px-4 py-3 text-left">M&ecirc;s desejado</th>
                            <th class="px-4 py-3 text-center">Meses cursando</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-left">Enviado em</th>
                            <th class="px-4 py-3 text-center">A&ccedil;&atilde;o</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $st = (string) ($row['status'] ?? 'pending');
                            $badge = $statusBadge[$st] ?? 'bg-slate-100 text-slate-600';
                            $slabel = $statusLabels[$st] ?? $st;
                            $dm = (string) ($row['desired_month'] ?? '');
                            $dmFmt = '';
                            if ($dm !== '' && preg_match('/^(\d{4})-(\d{2})$/', $dm, $m)) {
                                $months = ['', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
                                $dmFmt = ($months[(int) $m[2]] ?? $m[2]) . '/' . $m[1];
                            }
                            $createdAt = (string) ($row['created_at'] ?? '');
                            $dateFmt = $createdAt !== '' ? date('d/m/Y H:i', strtotime($createdAt)) : '-';
                            $isPending = $st === 'pending';
                            ?>
                            <tr class="exchange-row hover:bg-slate-50 transition <?= $isPending ? 'font-medium' : ''; ?>">
                                <td class="px-4 py-3 text-slate-400 text-xs"><?= (int) ($row['id'] ?? 0); ?></td>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-800"><?= e((string) ($row['student_name'] ?? '-')); ?></p>
                                    <p class="text-xs text-slate-400"><?= e((string) ($row['student_login'] ?? '')); ?></p>
                                </td>
                                <td class="px-4 py-3 text-slate-600"><?= e((string) ($row['current_unit'] ?? '-')); ?></td>
                                <td class="px-4 py-3 font-medium text-slate-800"><?= e((string) ($row['target_unit'] ?? '-')); ?></td>
                                <td class="px-4 py-3 text-slate-600"><?= e($dmFmt !== '' ? $dmFmt : $dm); ?></td>
                                <td class="px-4 py-3 text-center text-slate-600"><?= (int) ($row['months_enrolled'] ?? 0); ?></td>
                                <td class="px-4 py-3 text-center">
                                    <span class="exchange-status-pill inline-block rounded-full px-3 py-0.5 text-xs font-semibold <?= $badge; ?>">
                                        <?= e($slabel); ?>
                                        <?php if ($isPending): ?>
                                            <span class="ml-1 inline-block h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-400"><?= e($dateFmt); ?></td>
                                <td class="px-4 py-3 text-center">
                                    <a href="<?= route('exchange/show') . '&id=' . (int) ($row['id'] ?? 0); ?>"
                                       class="exchange-open-btn inline-block rounded-lg border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-100 transition">
                                        Abrir
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if ((int) ($meta['last_page'] ?? 1) > 1): ?>
        <div class="flex items-center justify-between">
            <?php if ((int) ($meta['page'] ?? 1) > 1): ?>
                <a href="<?= exchange_url($filters, (int) $meta['page'] - 1); ?>"
                   class="exchange-page-btn rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                    &larr; Anterior
                </a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>

            <span class="text-xs text-slate-400">P&aacute;gina <?= (int) ($meta['page'] ?? 1); ?> de <?= (int) ($meta['last_page'] ?? 1); ?> &mdash; <?= (int) ($meta['total'] ?? 0); ?> registro(s)</span>

            <?php if ((int) ($meta['page'] ?? 1) < (int) ($meta['last_page'] ?? 1)): ?>
                <a href="<?= exchange_url($filters, (int) $meta['page'] + 1); ?>"
                   class="exchange-page-btn rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                    Pr&oacute;xima &rarr;
                </a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php endif; ?>
</div>