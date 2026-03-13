<section class="space-y-6">
    <div>
        <h2 class="text-2xl font-semibold">Progresso</h2>
        <p class="text-sm text-slate-500">Acompanhamento de andamento por curso.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <article class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase text-slate-500">Total</p>
            <p class="mt-2 text-2xl font-semibold"><?= (int) ($summary['total'] ?? 0); ?></p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase text-slate-500">Ativos</p>
            <p class="mt-2 text-2xl font-semibold text-cyan-700"><?= (int) ($summary['active'] ?? 0); ?></p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase text-slate-500">Concluidos</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-700"><?= (int) ($summary['completed'] ?? 0); ?></p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase text-slate-500">Cancelados</p>
            <p class="mt-2 text-2xl font-semibold text-rose-700"><?= (int) ($summary['cancelled'] ?? 0); ?></p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase text-slate-500">Media</p>
            <p class="mt-2 text-2xl font-semibold"><?= (int) round((float) ($summary['avg_progress'] ?? 0)); ?>%</p>
        </article>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-3">Curso</th>
                    <th class="px-3 py-3">Status</th>
                    <th class="px-3 py-3">Inicio</th>
                    <th class="px-3 py-3">Conclusao</th>
                    <th class="px-3 py-3">Progresso</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($courses as $course): ?>
                    <tr class="border-b border-slate-100 hover:bg-slate-50">
                        <td class="px-3 py-3 font-medium"><?= e($course['name']); ?></td>
                        <td class="px-3 py-3"><?= e($course['status']); ?></td>
                        <td class="px-3 py-3"><?= e($course['started_at'] ?: '-'); ?></td>
                        <td class="px-3 py-3"><?= e($course['completed_at'] ?: '-'); ?></td>
                        <td class="px-3 py-3">
                            <div class="w-44 rounded-full bg-slate-200">
                                <div class="rounded-full bg-cyan-600 px-2 text-right text-xs text-white" style="width: <?= max(5, (int) $course['progress_percent']); ?>%">
                                    <?= (int) $course['progress_percent']; ?>%
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($courses === []): ?>
                    <tr>
                        <td colspan="5" class="px-3 py-8 text-center text-slate-500">Sem cursos para acompanhamento.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
