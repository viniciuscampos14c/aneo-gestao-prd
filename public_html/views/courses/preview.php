<?php
$coverImage = trim((string) ($course['cover_image'] ?? ''));
$hasCover = $coverImage !== '' && media_path_available($coverImage);
?>
<section class="course-preview-shell space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold">Preview do Curso</h2>
            <p class="text-sm text-slate-500">Visao rapida do curso no formato de consumo do aluno.</p>
        </div>
        <div class="flex gap-2">
            <a href="<?= route('courses'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Voltar</a>
            <a href="<?= route('courses/edit&id=' . (int) ($course['id'] ?? 0)); ?>" class="rounded-lg bg-cyan-600 px-3 py-2 text-sm font-semibold text-white hover:bg-cyan-700">Editar curso</a>
        </div>
    </div>

    <article class="course-preview-card overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="h-48 bg-slate-900">
            <?php if ($hasCover): ?>
                <img src="<?= e($coverImage); ?>" alt="Capa do curso" class="h-full w-full object-cover">
            <?php else: ?>
                <div class="flex h-full items-center justify-center bg-gradient-to-br from-slate-900 via-slate-800 to-cyan-900 text-sm text-slate-300">Sem capa configurada</div>
            <?php endif; ?>
        </div>
        <div class="space-y-5 p-6">
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">#<?= (int) ($course['id'] ?? 0); ?></span>
                <span class="rounded-full px-3 py-1 text-xs font-semibold <?= match ((string) ($course['status'] ?? 'draft')) { 'published' => 'bg-emerald-100 text-emerald-700', 'archived' => 'bg-slate-200 text-slate-700', default => 'bg-amber-100 text-amber-700', }; ?>">
                    <?= e(match ((string) ($course['status'] ?? 'draft')) { 'published' => 'Publicado', 'archived' => 'Arquivado', default => 'Rascunho', }); ?>
                </span>
                <span class="rounded-full bg-cyan-50 px-3 py-1 text-xs font-semibold text-cyan-700"><?= e((string) (($course['category_name'] ?? '') ?: 'Sem categoria')); ?></span>
            </div>

            <div>
                <h3 class="text-3xl font-semibold text-slate-900"><?= e((string) ($course['name'] ?? '')); ?></h3>
                <p class="mt-3 max-w-4xl text-sm leading-6 text-slate-600"><?= nl2br(e((string) ($course['description'] ?? ''))); ?></p>
            </div>

            <div class="grid gap-3 md:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Módulos</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900"><?= count($courseModules); ?></p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Aulas</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900"><?= array_sum(array_map(static fn (array $module): int => count($module['lessons'] ?? []), $courseModules)); ?></p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Carga horaria</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900"><?= (int) ($course['workload_hours'] ?? 0); ?>h</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Atualizado</p>
                    <p class="mt-2 text-sm font-semibold text-slate-900"><?= e((string) ($course['updated_at'] ?? '')); ?></p>
                </div>
            </div>

            <div class="space-y-3">
                <h4 class="text-lg font-semibold text-slate-900">Estrutura do curso</h4>
                <?php foreach ($courseModules as $module): ?>
                    <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h5 class="font-semibold text-slate-900"><?= e((string) ($module['title'] ?? 'Módulo')); ?></h5>
                                <p class="text-sm text-slate-500"><?= e((string) ($module['description'] ?? '')); ?></p>
                            </div>
                            <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-700"><?= count($module['lessons'] ?? []); ?> aula(s)</span>
                        </div>
                        <?php if (($module['lessons'] ?? []) !== []): ?>
                            <ul class="mt-4 space-y-2 text-sm text-slate-600">
                                <?php foreach (($module['lessons'] ?? []) as $lesson): ?>
                                    <li class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                                        <strong class="text-slate-800"><?= e((string) ($lesson['title'] ?? 'Aula')); ?></strong>
                                        <span class="text-slate-500"> | minimo <?= (int) ($lesson['min_progress_percent'] ?? 70); ?>%</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>

                <?php if ($courseModules === []): ?>
                    <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                        Este curso ainda não possui módulos configurados.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </article>
</section>
