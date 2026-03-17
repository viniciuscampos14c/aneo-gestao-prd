<section class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-semibold">Meus Cursos</h2>
            <p class="text-sm text-slate-500">Cursos publicados vinculados a sua matricula.</p>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <?php foreach ($rows as $course): ?>
            <article class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="flex items-start gap-3">
                    <?php $coverImage = trim((string) ($course['cover_image'] ?? '')); ?>
                    <?php if ($coverImage !== '' && media_path_available($coverImage)): ?>
                        <img src="<?= e($coverImage); ?>" alt="Capa" class="h-16 w-24 rounded-lg object-cover">
                    <?php else: ?>
                        <div class="flex h-16 w-24 items-center justify-center rounded-lg bg-slate-100 text-xs text-slate-500">Sem capa</div>
                    <?php endif; ?>

                    <div class="flex-1">
                        <h3 class="font-semibold text-slate-900"><?= e($course['name']); ?></h3>
                        <p class="text-xs text-slate-500"><?= e($course['category_name'] ?: 'Sem categoria'); ?><?= !empty($course['workload_hours']) ? ' | ' . (int) $course['workload_hours'] . 'h' : ''; ?></p>
                        <p class="mt-2 text-sm text-slate-600"><?= e($course['description']); ?></p>
                    </div>
                </div>

                <div class="mt-4 space-y-2">
                    <div class="flex items-center justify-between text-xs text-slate-500">
                        <span>Status da matricula: <?= e($course['enrollment_status']); ?></span>
                        <span><?= (int) $course['progress_percent']; ?>%</span>
                    </div>
                    <div class="h-2 rounded-full bg-slate-200">
                        <div class="h-2 rounded-full bg-cyan-600" style="width: <?= (int) $course['progress_percent']; ?>%"></div>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-2 text-sm">
                    <?php if ((int) ($course['modules_total'] ?? 0) > 0): ?>
                        <a href="<?= route('student/course&course_id=' . (int) $course['course_id']); ?>" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 font-medium text-emerald-700 hover:bg-emerald-100">Continuar curso</a>
                    <?php else: ?>
                        <span class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-500">Sem trilha modular configurada</span>
                    <?php endif; ?>
                    <?php if (!empty($course['workload_hours'])): ?>
                        <span class="text-xs text-slate-500">Carga horaria: <?= (int) $course['workload_hours']; ?>h</span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($course['live_link'])): ?>
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-2 text-sm">
                        <p class="text-slate-500">Aula ao vivo: <?= !empty($course['live_datetime']) ? e(date('d/m/Y H:i', strtotime((string) $course['live_datetime']))) : 'sem horario'; ?></p>
                        <a href="<?= e($course['live_link']); ?>" target="_blank" rel="noopener" class="rounded-lg border border-cyan-200 bg-cyan-50 px-3 py-2 font-medium text-cyan-700 hover:bg-cyan-100">Entrar na aula</a>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>

        <?php if ($rows === []): ?>
            <article class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500 lg:col-span-2">
                Nenhum curso matriculado no momento.
            </article>
        <?php endif; ?>
    </div>
</section>
