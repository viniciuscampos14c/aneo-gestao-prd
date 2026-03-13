<?php
$bi = $metrics['bi'] ?? [
    'overview' => [],
    'monthly_series' => [],
    'courses_performance' => [],
];
$biOverview = $bi['overview'] ?? [];
$monthlySeries = $bi['monthly_series'] ?? [];
$coursesPerformance = $bi['courses_performance'] ?? [];

$maxMonthlyValue = 1.0;
foreach ($monthlySeries as $row) {
    $maxMonthlyValue = max($maxMonthlyValue, (float) ($row['invoiced'] ?? 0), (float) ($row['received'] ?? 0));
}
?>
<section class="space-y-6">
    <div>
        <h2 class="text-2xl font-semibold">Visao Geral</h2>
        <p class="text-sm text-slate-500">Resumo operacional, comercial e financeiro.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-500">Total de Alunos</p>
            <p class="mt-2 text-3xl font-semibold"><?= (int) $metrics['students_total']; ?></p>
            <p class="mt-1 text-xs text-slate-500">Ativos: <?= (int) $metrics['students_active']; ?> | Inativos: <?= (int) $metrics['students_inactive']; ?></p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-500">Leads</p>
            <p class="mt-2 text-3xl font-semibold"><?= (int) $metrics['leads_total']; ?></p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-500">Faturas em aberto</p>
            <p class="mt-2 text-3xl font-semibold"><?= (int) $metrics['invoices_open']; ?></p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-500">Receber</p>
            <p class="mt-2 text-3xl font-semibold"><?= e(format_currency($metrics['receivable'])); ?></p>
            <p class="mt-1 text-xs text-slate-500">Faturas pagas: <?= (int) $metrics['invoices_paid']; ?></p>
        </article>
    </div>

    <section class="rounded-xl border border-indigo-200 bg-gradient-to-r from-indigo-50 to-cyan-50 p-4 shadow-sm">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold text-slate-900">BI Gerencial</h3>
                <p class="text-sm text-slate-600">Indicadores executivos para decisao rapida no perfil administrativo.</p>
            </div>
            <?php if (has_permission('finance')): ?>
                <a href="<?= route('finance/reports'); ?>" class="rounded-lg border border-indigo-200 bg-white px-3 py-2 text-sm text-indigo-700 hover:bg-indigo-50">Abrir Relatorios Financeiros</a>
            <?php endif; ?>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
            <article class="rounded-lg border border-white/70 bg-white/80 p-3">
                <p class="text-xs uppercase text-slate-500">Conversao Leads</p>
                <p class="mt-1 text-2xl font-semibold text-indigo-700"><?= e(number_format((float) ($biOverview['leads_conversion_rate'] ?? 0), 2, ',', '.')); ?>%</p>
                <p class="text-xs text-slate-500"><?= (int) ($biOverview['leads_converted'] ?? 0); ?> de <?= (int) ($biOverview['leads_total'] ?? 0); ?> leads</p>
            </article>
            <article class="rounded-lg border border-white/70 bg-white/80 p-3">
                <p class="text-xs uppercase text-slate-500">Recebido 30 dias</p>
                <p class="mt-1 text-2xl font-semibold text-emerald-700"><?= e(format_currency((float) ($biOverview['revenue_received_30d'] ?? 0))); ?></p>
            </article>
            <article class="rounded-lg border border-white/70 bg-white/80 p-3">
                <p class="text-xs uppercase text-slate-500">Previsto 30 dias</p>
                <p class="mt-1 text-2xl font-semibold text-cyan-700"><?= e(format_currency((float) ($biOverview['revenue_forecast_30d'] ?? 0))); ?></p>
            </article>
            <article class="rounded-lg border border-white/70 bg-white/80 p-3">
                <p class="text-xs uppercase text-slate-500">Inadimplencia</p>
                <p class="mt-1 text-2xl font-semibold text-rose-700"><?= e(number_format((float) ($biOverview['delinquency_rate'] ?? 0), 2, ',', '.')); ?>%</p>
                <p class="text-xs text-slate-500">Vencido: <?= e(format_currency((float) ($biOverview['overdue_amount'] ?? 0))); ?></p>
            </article>
            <article class="rounded-lg border border-white/70 bg-white/80 p-3">
                <p class="text-xs uppercase text-slate-500">Progresso Medio</p>
                <p class="mt-1 text-2xl font-semibold text-slate-900"><?= e(number_format((float) ($biOverview['enrollments_avg_progress'] ?? 0), 1, ',', '.')); ?>%</p>
                <p class="text-xs text-slate-500">Matriculas</p>
            </article>
            <article class="rounded-lg border border-white/70 bg-white/80 p-3">
                <p class="text-xs uppercase text-slate-500">Aprovacao Provas</p>
                <p class="mt-1 text-2xl font-semibold text-amber-700"><?= e(number_format((float) ($biOverview['exam_approval_rate'] ?? 0), 2, ',', '.')); ?>%</p>
                <p class="text-xs text-slate-500">Resultados: <?= (int) ($biOverview['exam_results_total'] ?? 0); ?></p>
            </article>
        </div>

        <div class="mt-5 grid gap-4 xl:grid-cols-2">
            <section class="rounded-lg border border-slate-200 bg-white p-4">
                <h4 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-600">Faturado x Recebido (ultimos 6 meses)</h4>
                <div class="space-y-3">
                    <?php foreach ($monthlySeries as $month): ?>
                        <?php
                        $invoiced = (float) ($month['invoiced'] ?? 0);
                        $received = (float) ($month['received'] ?? 0);
                        $invoicedWidth = min(100, ($invoiced / $maxMonthlyValue) * 100);
                        $receivedWidth = min(100, ($received / $maxMonthlyValue) * 100);
                        ?>
                        <div>
                            <div class="mb-1 flex items-center justify-between text-xs text-slate-600">
                                <span><?= e((string) ($month['label'] ?? '')); ?></span>
                                <span>F: <?= e(format_currency($invoiced)); ?> | R: <?= e(format_currency($received)); ?></span>
                            </div>
                            <div class="space-y-1">
                                <div class="h-2 rounded-full bg-slate-100">
                                    <div class="h-2 rounded-full bg-indigo-500" style="width: <?= $invoicedWidth; ?>%"></div>
                                </div>
                                <div class="h-2 rounded-full bg-slate-100">
                                    <div class="h-2 rounded-full bg-emerald-500" style="width: <?= $receivedWidth; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($monthlySeries === []): ?>
                        <p class="text-sm text-slate-500">Sem dados mensais suficientes para exibir a serie.</p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-4">
                <h4 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-600">Desempenho por Curso</h4>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                                <th class="px-2 py-2">Curso</th>
                                <th class="px-2 py-2">Matriculas</th>
                                <th class="px-2 py-2">Progresso medio</th>
                                <th class="px-2 py-2">Aprovacao</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($coursesPerformance as $course): ?>
                                <tr class="border-b border-slate-100 hover:bg-slate-50">
                                    <td class="px-2 py-2 font-medium"><?= e((string) ($course['name'] ?? '')); ?></td>
                                    <td class="px-2 py-2"><?= (int) ($course['enrollments_total'] ?? 0); ?></td>
                                    <td class="px-2 py-2"><?= e(number_format((float) ($course['avg_progress'] ?? 0), 1, ',', '.')); ?>%</td>
                                    <td class="px-2 py-2"><?= e(number_format((float) ($course['exam_approval_rate'] ?? 0), 1, ',', '.')); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($coursesPerformance === []): ?>
                                <tr><td colspan="4" class="px-2 py-4 text-center text-slate-500">Sem dados de cursos para BI.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="mb-4 text-lg font-semibold">Pipeline de Leads</h3>
            <div class="space-y-3">
                <?php foreach ($metrics['lead_pipeline'] as $row): ?>
                    <div>
                        <div class="mb-1 flex items-center justify-between text-sm">
                            <span><?= e($row['name']); ?></span>
                            <span class="font-semibold"><?= (int) $row['qty']; ?></span>
                        </div>
                        <div class="h-2 rounded-full bg-slate-100">
                            <div class="h-full rounded-full" style="background-color: <?= e($row['color'] ?: '#0ea5e9'); ?>; width: <?= min(100, (int) $row['qty'] * 8); ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="mb-4 text-lg font-semibold">Kanban Financeiro de Alunos</h3>
            <div class="space-y-3">
                <?php foreach ($metrics['kanban'] as $row): ?>
                    <div class="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2">
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full" style="background-color: <?= e($row['color'] ?: '#14b8a6'); ?>"></span>
                            <span class="text-sm"><?= e($row['name']); ?></span>
                        </div>
                        <span class="text-sm font-semibold"><?= (int) $row['qty']; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</section>
