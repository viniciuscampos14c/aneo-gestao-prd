<?php
$courseId = (int) ($row['id'] ?? 0);
$coverImage = trim((string) ($row['cover_image'] ?? ''));
$hasCover = $coverImage !== '' && media_path_available($coverImage);
$courseStatus = (string) ($row['status'] ?? 'draft');
$statusLabel = match ($courseStatus) {
    'published' => 'Publicado',
    'archived' => 'Arquivado',
    default => 'Rascunho',
};
$menuId = 'course-menu-' . $courseId;
$commentsNew = (int) ($row['comments_new_total'] ?? 0);
$updatedAgo = $formatRelativeTime((string) ($row['updated_at'] ?? ''));
$showEnrollmentData = !is_professor();
?>
<article class="course-card overflow-hidden rounded-2xl">
    <div class="course-card-cover">
        <?php if ($hasCover): ?>
            <img src="<?= e($coverImage); ?>" alt="Capa do curso <?= e((string) ($row['name'] ?? '')); ?>">
        <?php else: ?>
            <div class="course-card-cover-placeholder"></div>
        <?php endif; ?>
        <span class="absolute left-3 top-3 rounded-full border border-slate-700 bg-slate-950/85 px-2.5 py-1 text-[11px] font-semibold text-slate-100">#<?= $courseId; ?></span>
        <span class="absolute right-3 top-3 rounded-full px-2.5 py-1 text-[11px] font-semibold <?= $statusBadgeClass($courseStatus); ?>"><?= e($statusLabel); ?></span>
    </div>

    <div class="space-y-4 p-4">
        <div class="space-y-2">
            <p class="text-xs uppercase tracking-[0.18em] text-cyan-300"><?= e((string) ($row['category_name'] ?: 'Sem categoria')); ?></p>
            <h3 class="course-card-title text-lg font-semibold text-white"><?= e((string) ($row['name'] ?? '')); ?></h3>
        </div>

        <div class="grid <?= $showEnrollmentData ? 'grid-cols-3' : 'grid-cols-2'; ?> gap-2 text-xs">
            <div class="rounded-xl border border-slate-700 bg-slate-900/60 px-3 py-2">
                <p class="text-slate-400">Módulos</p>
                <p class="mt-1 text-sm font-semibold text-white"><?= (int) ($row['modules_total'] ?? 0); ?></p>
            </div>
            <?php if ($showEnrollmentData): ?>
                <div class="rounded-xl border border-slate-700 bg-slate-900/60 px-3 py-2">
                    <p class="text-slate-400">Matrículas</p>
                    <p class="mt-1 text-sm font-semibold text-white"><?= (int) ($row['enrollments_total'] ?? 0); ?></p>
                </div>
            <?php endif; ?>
            <div class="rounded-xl border border-slate-700 bg-slate-900/60 px-3 py-2">
                <p class="text-slate-400">Avaliacoes</p>
                <p class="mt-1 text-sm font-semibold text-white"><?= (int) ($row['exams_total'] ?? 0); ?></p>
            </div>
        </div>

        <p class="text-xs text-slate-400">Atualizado há <?= e($updatedAgo); ?></p>

        <div class="space-y-2 border-t border-slate-800 pt-3">
            <div class="flex items-center gap-2">
                <a href="<?= route('courses/edit&id=' . $courseId); ?>" class="course-card-primary inline-flex min-w-0 flex-1 items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold">Editar</a>

                <div class="relative" data-course-menu-wrap>
                    <button
                        type="button"
                        class="course-card-menu-btn course-card-action rounded-xl px-3 py-2 text-sm"
                        data-course-menu-trigger="<?= e($menuId); ?>"
                        aria-haspopup="menu"
                        aria-expanded="false"
                        aria-label="Mais acoes do curso">
                        ⋮
                    </button>
                    <div class="course-card-menu rounded-xl py-1" data-course-menu="<?= e($menuId); ?>" hidden>
                        <a href="<?= route('courses/preview&id=' . $courseId); ?>" role="menuitem">Visualizar como aluno</a>

                        <?php if ($canCreate): ?>
                            <form method="post" action="<?= route('courses/duplicate'); ?>">
                                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                <input type="hidden" name="id" value="<?= $courseId; ?>">
                                <button type="submit" role="menuitem">Duplicar curso</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($canEdit && $courseStatus !== 'draft'): ?>
                            <form method="post" action="<?= route('courses/status'); ?>">
                                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                <input type="hidden" name="id" value="<?= $courseId; ?>">
                                <input type="hidden" name="status" value="draft">
                                <button type="submit" role="menuitem">Despublicar</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($canEdit && $courseStatus !== 'archived'): ?>
                            <form method="post" action="<?= route('courses/status'); ?>">
                                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                <input type="hidden" name="id" value="<?= $courseId; ?>">
                                <input type="hidden" name="status" value="archived">
                                <button type="submit" role="menuitem">Arquivar</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($canDelete): ?>
                            <button type="button" class="danger" data-course-delete-trigger="<?= $courseId; ?>" role="menuitem">Excluir</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="course-card-actions-grid">
                <a href="<?= route('courses/edit&id=' . $courseId) . '#lms-builder'; ?>" class="course-card-action rounded-xl px-2.5 py-2 text-center text-sm" title="Conteudo / Módulos" aria-label="Conteudo e módulos do curso">
                    Conteudo
                </a>
                <?php if ($showEnrollmentData): ?>
                    <a href="<?= route('courses/enrollments&course_id=' . $courseId); ?>" class="course-card-action rounded-xl px-2.5 py-2 text-center text-sm" title="Matrículas" aria-label="Abrir matrículas do curso">
                        Matrículas
                    </a>
                <?php endif; ?>
                <a href="<?= route('courses/exams&course_id=' . $courseId); ?>" class="course-card-action rounded-xl px-2.5 py-2 text-center text-sm" title="Exames" aria-label="Abrir exames do curso">
                    Exames
                </a>
                <a href="<?= route('courses/comments&course_id=' . $courseId); ?>" class="course-card-action relative rounded-xl px-2.5 py-2 text-center text-sm" title="Comentarios" aria-label="Abrir comentarios do curso">
                    Coment.
                    <?php if ($commentsNew > 0): ?>
                        <span class="absolute -right-1 -top-1 rounded-full bg-violet-500 px-1.5 py-0.5 text-[10px] font-semibold text-white"><?= $commentsNew; ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </div>
</article>
