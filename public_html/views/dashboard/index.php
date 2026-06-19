<?php
$bi = $metrics['bi'] ?? [
    'overview' => [],
    'monthly_series' => [],
    'courses_performance' => [],
];
$biOverview = $bi['overview'] ?? [];
$monthlySeries = $bi['monthly_series'] ?? [];
$coursesPerformance = $bi['courses_performance'] ?? [];
$dueTodayAlerts = $metrics['due_today_alerts'] ?? [];
$dueTodayCount = (int) ($metrics['due_today_count'] ?? 0);
$leadPipeline = $metrics['lead_pipeline'] ?? [];
$kanbanRows = $metrics['kanban'] ?? [];

$maxMonthlyValue = 1.0;
foreach ($monthlySeries as $row) {
    $maxMonthlyValue = max($maxMonthlyValue, (float) ($row['invoiced'] ?? 0), (float) ($row['received'] ?? 0));
}

$maxPipelineQty = 1;
foreach ($leadPipeline as $row) {
    $maxPipelineQty = max($maxPipelineQty, (int) ($row['qty'] ?? 0));
}
?>

<section class="dashboard-preview-shell">
    <div class="dashboard-preview-content space-y-6">
        <div>
            <p class="text-xs uppercase tracking-[0.24em] text-cyan-300">Painel executivo</p>
            <h2 class="dashboard-preview-title mt-2 text-4xl font-semibold">Visão Geral</h2>
            <p class="dashboard-preview-subtitle mt-2 text-sm">Resumo operacional, comercial e financeiro.</p>
        </div>

        <?php if ($dueTodayCount > 0): ?>
            <div id="due-today-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/65 p-4">
                <div class="w-full max-w-2xl rounded-2xl border border-rose-300/35 bg-slate-900/95 shadow-2xl">
                    <div class="flex items-center justify-between border-b border-slate-700 px-4 py-3">
                        <div>
                            <h3 class="text-lg font-semibold text-rose-300">Alertas de vencimento de hoje</h3>
                            <p class="text-xs text-slate-300"><?= (int) $dueTodayCount; ?> fatura(s) vence(m) hoje.</p>
                        </div>
                        <button type="button" data-due-modal-close class="rounded-lg border border-slate-600 px-3 py-1 text-xs text-slate-200 hover:bg-slate-800">Fechar</button>
                    </div>
                    <div class="max-h-[60vh] overflow-y-auto p-4">
                        <div class="space-y-2">
                            <?php foreach ($dueTodayAlerts as $alert): ?>
                                <article class="rounded-lg border border-rose-400/25 bg-rose-900/15 px-3 py-2 text-sm">
                                    <p class="font-semibold text-slate-100"><?= e((string) ($alert['invoice_number'] ?? 'Fatura')); ?> - <?= e((string) ($alert['student_name'] ?? 'Aluno')); ?></p>
                                    <p class="text-xs text-slate-300">Vencimento: <?= e(date('d/m/Y', strtotime((string) ($alert['due_date'] ?? '')))); ?> | Em aberto: <?= e(format_currency((float) ($alert['outstanding_amount'] ?? 0))); ?></p>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="border-t border-slate-700 px-4 py-3 text-right">
                        <button type="button" data-due-modal-close class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Entendi</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="dashboard-preview-kpi p-4">
                <p class="dashboard-preview-kpi-title text-xs uppercase tracking-[0.18em]">Total de Alunos</p>
                <p class="dashboard-preview-kpi-value mt-2 text-4xl font-semibold"><?= (int) ($metrics['students_total'] ?? 0); ?></p>
                <p class="dashboard-preview-subtitle mt-1 text-xs">Ativos: <?= (int) ($metrics['students_active'] ?? 0); ?> | Inativos: <?= (int) ($metrics['students_inactive'] ?? 0); ?></p>
            </article>
            <article class="dashboard-preview-kpi p-4">
                <p class="dashboard-preview-kpi-title text-xs uppercase tracking-[0.18em]">Leads</p>
                <p class="dashboard-preview-kpi-value mt-2 text-4xl font-semibold"><?= (int) ($metrics['leads_total'] ?? 0); ?></p>
            </article>
            <article class="dashboard-preview-kpi p-4">
                <p class="dashboard-preview-kpi-title text-xs uppercase tracking-[0.18em]">Faturas em aberto</p>
                <p class="dashboard-preview-kpi-value mt-2 text-4xl font-semibold"><?= (int) ($metrics['invoices_open'] ?? 0); ?></p>
            </article>
            <article class="dashboard-preview-kpi p-4">
                <p class="dashboard-preview-kpi-title text-xs uppercase tracking-[0.18em]">Receber</p>
                <p class="dashboard-preview-kpi-value mt-2 text-4xl font-semibold"><?= e(format_currency((float) ($metrics['receivable'] ?? 0))); ?></p>
                <p class="dashboard-preview-subtitle mt-1 text-xs">Faturas pagas: <?= (int) ($metrics['invoices_paid'] ?? 0); ?></p>
            </article>
        </div>

        <section class="dashboard-preview-bi p-4">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-slate-100">BI Gerencial</h3>
                    <p class="dashboard-preview-bi-muted text-sm">Indicadores executivos para decisão rápida no perfil administrativo.</p>
                </div>
                <?php if (has_permission('finance')): ?>
                    <a href="<?= route('finance/reports'); ?>" class="dashboard-preview-btn-link rounded-lg px-3 py-2 text-sm transition">Abrir Relatórios Financeiros</a>
                <?php endif; ?>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                <article class="dashboard-preview-bi-card p-3">
                    <p class="dashboard-preview-bi-title text-xs uppercase">Conversão de Leads</p>
                    <p class="mt-1 text-2xl font-semibold text-indigo-300"><?= e(number_format((float) ($biOverview['leads_conversion_rate'] ?? 0), 2, ',', '.')); ?>%</p>
                    <p class="dashboard-preview-bi-muted text-xs"><?= (int) ($biOverview['leads_converted'] ?? 0); ?> de <?= (int) ($biOverview['leads_total'] ?? 0); ?> leads</p>
                </article>
                <article class="dashboard-preview-bi-card p-3">
                    <p class="dashboard-preview-bi-title text-xs uppercase">Recebido 30 dias</p>
                    <p class="mt-1 text-2xl font-semibold text-emerald-300"><?= e(format_currency((float) ($biOverview['revenue_received_30d'] ?? 0))); ?></p>
                </article>
                <article class="dashboard-preview-bi-card p-3">
                    <p class="dashboard-preview-bi-title text-xs uppercase">Previsto 30 dias</p>
                    <p class="mt-1 text-2xl font-semibold text-cyan-300"><?= e(format_currency((float) ($biOverview['revenue_forecast_30d'] ?? 0))); ?></p>
                </article>
                <article class="dashboard-preview-bi-card p-3">
                    <p class="dashboard-preview-bi-title text-xs uppercase">Inadimplência</p>
                    <p class="mt-1 text-2xl font-semibold text-rose-300"><?= e(number_format((float) ($biOverview['delinquency_rate'] ?? 0), 2, ',', '.')); ?>%</p>
                    <p class="dashboard-preview-bi-muted text-xs">Vencido: <?= e(format_currency((float) ($biOverview['overdue_amount'] ?? 0))); ?></p>
                </article>
                <article class="dashboard-preview-bi-card p-3">
                    <p class="dashboard-preview-bi-title text-xs uppercase">Progresso Médio</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-100"><?= e(number_format((float) ($biOverview['enrollments_avg_progress'] ?? 0), 1, ',', '.')); ?>%</p>
                    <p class="dashboard-preview-bi-muted text-xs">Matrículas</p>
                </article>
                <article class="dashboard-preview-bi-card p-3">
                    <p class="dashboard-preview-bi-title text-xs uppercase">Aprovação em Provas</p>
                    <p class="mt-1 text-2xl font-semibold text-amber-300"><?= e(number_format((float) ($biOverview['exam_approval_rate'] ?? 0), 2, ',', '.')); ?>%</p>
                    <p class="dashboard-preview-bi-muted text-xs">Resultados: <?= (int) ($biOverview['exam_results_total'] ?? 0); ?></p>
                </article>
            </div>

            <div class="mt-5 grid gap-4 xl:grid-cols-2">
                <section class="dashboard-preview-section p-4">
                    <h4 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-200">Faturado x Recebido (últimos 6 meses)</h4>
                    <div class="space-y-3">
                        <?php foreach ($monthlySeries as $month): ?>
                            <?php
                            $invoiced = (float) ($month['invoiced'] ?? 0);
                            $received = (float) ($month['received'] ?? 0);
                            $invoicedWidth = min(100, ($invoiced / $maxMonthlyValue) * 100);
                            $receivedWidth = min(100, ($received / $maxMonthlyValue) * 100);
                            ?>
                            <div>
                                <div class="mb-1 flex items-center justify-between text-xs text-slate-300">
                                    <span><?= e((string) ($month['label'] ?? '')); ?></span>
                                    <span>F: <?= e(format_currency($invoiced)); ?> | R: <?= e(format_currency($received)); ?></span>
                                </div>
                                <div class="space-y-1">
                                    <div class="dashboard-preview-track h-2 rounded-full">
                                        <div class="h-2 rounded-full bg-indigo-400" style="width: <?= $invoicedWidth; ?>%"></div>
                                    </div>
                                    <div class="dashboard-preview-track h-2 rounded-full">
                                        <div class="h-2 rounded-full bg-emerald-400" style="width: <?= $receivedWidth; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($monthlySeries === []): ?>
                            <p class="dashboard-preview-bi-muted text-sm">Sem dados mensais suficientes para exibir a série.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="dashboard-preview-section p-4">
                    <h4 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-200">Desempenho por Curso</h4>
                    <div class="overflow-x-auto">
                        <table class="dashboard-preview-table min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide">
                                    <th class="px-2 py-2">Curso</th>
                                    <th class="px-2 py-2">Matrículas</th>
                                    <th class="px-2 py-2">Progresso médio</th>
                                    <th class="px-2 py-2">Aprovação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($coursesPerformance as $course): ?>
                                    <tr class="dashboard-preview-table-row">
                                        <td class="px-2 py-2 font-medium"><?= e((string) ($course['name'] ?? '')); ?></td>
                                        <td class="px-2 py-2"><?= (int) ($course['enrollments_total'] ?? 0); ?></td>
                                        <td class="px-2 py-2"><?= e(number_format((float) ($course['avg_progress'] ?? 0), 1, ',', '.')); ?>%</td>
                                        <td class="px-2 py-2"><?= e(number_format((float) ($course['exam_approval_rate'] ?? 0), 1, ',', '.')); ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($coursesPerformance === []): ?>
                                    <tr class="dashboard-preview-table-row">
                                        <td colspan="4" class="px-2 py-4 text-center text-slate-400">Sem dados de cursos para BI.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="dashboard-preview-section p-4">
                <h3 class="mb-4 text-lg font-semibold text-slate-100">Pipeline de Leads</h3>
                <div class="space-y-3">
                    <?php foreach ($leadPipeline as $row): ?>
                        <?php $width = min(100, ((int) ($row['qty'] ?? 0) / $maxPipelineQty) * 100); ?>
                        <div>
                            <div class="mb-1 flex items-center justify-between text-sm text-slate-200">
                                <span><?= e((string) ($row['name'] ?? '')); ?></span>
                                <span class="font-semibold"><?= (int) ($row['qty'] ?? 0); ?></span>
                            </div>
                            <div class="dashboard-preview-track h-2 rounded-full">
                                <div class="h-full rounded-full" style="background-color: <?= e((string) (($row['color'] ?? '') ?: '#38bdf8')); ?>; width: <?= $width; ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($leadPipeline === []): ?>
                        <p class="dashboard-preview-bi-muted text-sm">Sem dados de pipeline.</p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="dashboard-preview-section p-4">
                <h3 class="mb-4 text-lg font-semibold text-slate-100">Kanban Financeiro de Alunos</h3>
                <div class="space-y-3">
                    <?php foreach ($kanbanRows as $row): ?>
                        <div class="flex items-center justify-between rounded-lg border border-slate-600/35 bg-slate-900/32 px-3 py-2">
                            <div class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full" style="background-color: <?= e((string) (($row['color'] ?? '') ?: '#14b8a6')); ?>"></span>
                                <span class="text-sm text-slate-200"><?= e((string) ($row['name'] ?? '')); ?></span>
                            </div>
                            <span class="text-sm font-semibold text-slate-100"><?= (int) ($row['qty'] ?? 0); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($kanbanRows === []): ?>
                        <p class="dashboard-preview-bi-muted text-sm">Sem dados de kanban.</p>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>
</section>

<?php if ($dueTodayCount > 0): ?>
    <script>
        (function () {
            const modal = document.getElementById('due-today-modal');
            if (!modal) return;

            const todayKey = 'aneo_due_today_popup_' + new Date().toISOString().slice(0, 10);
            if (sessionStorage.getItem(todayKey)) {
                return;
            }

            const close = function () {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                sessionStorage.setItem(todayKey, '1');
            };

            modal.classList.remove('hidden');
            modal.classList.add('flex');

            modal.querySelectorAll('[data-due-modal-close]').forEach((btn) => {
                btn.addEventListener('click', close);
            });

            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    close();
                }
            });
        })();
    </script>
<?php endif; ?>
