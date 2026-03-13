<?php
$uploadsByCourse = [];
foreach ($uploads as $file) {
    $courseId = (int) $file['course_id'];
    if (!isset($uploadsByCourse[$courseId])) {
        $uploadsByCourse[$courseId] = [];
    }
    $uploadsByCourse[$courseId][] = $file;
}
?>
<section class="space-y-6">
    <div>
        <h2 class="text-2xl font-semibold">Materiais</h2>
        <p class="text-sm text-slate-500">Conteudo e anexos liberados nos cursos matriculados.</p>
    </div>

    <div class="space-y-4">
        <?php foreach ($courses as $course): ?>
            <?php $files = $uploadsByCourse[(int) $course['id']] ?? []; ?>
            <article class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-lg font-semibold text-slate-900"><?= e($course['name']); ?></h3>
                    <span class="text-xs text-slate-500">Atualizado em <?= e(date('d/m/Y H:i', strtotime((string) $course['updated_at']))); ?></span>
                </div>

                <div class="mt-3 rounded-lg border border-slate-100 bg-slate-50 p-3 text-sm text-slate-700">
                    <?php if (!empty($course['materials'])): ?>
                        <?= nl2br(e($course['materials'])); ?>
                    <?php else: ?>
                        <span class="text-slate-500">Sem texto de material cadastrado para este curso.</span>
                    <?php endif; ?>
                </div>

                <div class="mt-4">
                    <p class="mb-2 text-sm font-medium text-slate-700">Arquivos anexados</p>
                    <?php if ($files === []): ?>
                        <p class="text-sm text-slate-500">Nenhum arquivo anexado.</p>
                    <?php else: ?>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($files as $file): ?>
                                <a href="<?= e($file['file_path']); ?>" target="_blank" rel="noopener" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">
                                    <?= e($file['file_name']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>

        <?php if ($courses === []): ?>
            <article class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
                Nenhum material disponivel, pois voce ainda nao possui cursos publicados matriculados.
            </article>
        <?php endif; ?>
    </div>
</section>
