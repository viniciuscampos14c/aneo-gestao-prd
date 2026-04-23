<?php
$canCategory = has_permission('courses.category');
$canEnrollment = has_permission('courses.enrollment');
$canExam = has_permission('courses.exam');
$canComment = has_permission('courses.comment');
$canCreate = has_permission('courses.create');
$canEdit = has_permission('courses.edit');
$canDelete = has_permission('courses.delete');
$canTrial = has_permission('courses.enrollment');
?>
<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold">Cursos EAD</h2>
            <p class="text-sm text-slate-500">Catalogo de cursos, publicacao e acompanhamento.</p>
        </div>
        <div class="flex gap-2">
            <?php if ($canCategory): ?>
                <a href="<?= route('courses/categories'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Categorias</a>
            <?php endif; ?>
            <?php if ($canEnrollment): ?>
                <a href="<?= route('courses/enrollments'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Matriculas</a>
            <?php endif; ?>
            <?php if ($canTrial): ?>
                <a href="<?= route('courses/trial-access'); ?>" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-100">Degustacao</a>
            <?php endif; ?>
            <a href="<?= route('courses/calendar'); ?>" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-700 hover:bg-amber-100">Agenda Academica</a>
            <?php if ($canExam): ?>
                <a href="<?= route('courses/exams'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Exames</a>
            <?php endif; ?>
            <?php if ($canComment): ?>
                <a href="<?= route('courses/comments'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Comentarios</a>
            <?php endif; ?>
            <?php if ($canCreate): ?>
                <a href="<?= route('courses/create'); ?>" class="rounded-lg bg-cyan-600 px-3 py-2 text-sm font-semibold text-white hover:bg-cyan-700">+ Criar novo curso</a>
            <?php endif; ?>
        </div>
    </div>

    <form method="get" action="index.php" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-4">
        <input type="hidden" name="route" value="courses">
        <input type="text" name="q" value="<?= e($filters['q']); ?>" placeholder="Buscar curso..." class="rounded-lg border border-slate-200 px-3 py-2 text-sm">

        <select name="status" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option value="">Todas situacoes</option>
            <option value="published" <?= $filters['status'] === 'published' ? 'selected' : ''; ?>>Publicado</option>
            <option value="draft" <?= $filters['status'] === 'draft' ? 'selected' : ''; ?>>Rascunho</option>
        </select>

        <select name="per_page" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <?php foreach ($paginationOptions as $opt): ?>
                <option value="<?= (int) $opt; ?>" <?= (int) $meta['per_page'] === (int) $opt ? 'selected' : ''; ?>><?= (int) $opt; ?>/pagina</option>
            <?php endforeach; ?>
        </select>

        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrar</button>
    </form>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-3">ID</th>
                    <th class="px-3 py-3">Capa</th>
                    <th class="px-3 py-3">Nome do curso</th>
                    <th class="px-3 py-3">Categoria</th>
                    <th class="px-3 py-3">Situacao</th>
                    <th class="px-3 py-3">Opcoes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr class="border-b border-slate-100 hover:bg-slate-50">
                        <td class="px-3 py-3"><?= (int) $row['id']; ?></td>
                        <td class="px-3 py-3">
                            <?php $coverImage = trim((string) ($row['cover_image'] ?? '')); ?>
                            <?php if ($coverImage !== '' && media_path_available($coverImage)): ?>
                                <img src="<?= e($coverImage); ?>" alt="Capa" class="h-10 w-16 rounded object-cover">
                            <?php else: ?>
                                <span class="text-xs text-slate-400">Sem capa</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-3 font-medium"><?= e($row['name']); ?></td>
                        <td class="px-3 py-3"><?= e($row['category_name']); ?></td>
                        <td class="px-3 py-3">
                            <span class="rounded-full px-2 py-1 text-xs font-semibold <?= $row['status'] === 'published' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700'; ?>">
                                <?= e($row['status'] === 'published' ? 'Publicado' : 'Rascunho'); ?>
                            </span>
                        </td>
                        <td class="px-3 py-3">
                            <div class="flex gap-2">
                                <?php if ($canEdit): ?>
                                    <a href="<?= route('courses/edit&id=' . (int) $row['id']); ?>" class="rounded border border-slate-200 px-2 py-1 text-xs hover:bg-slate-100">Editar</a>
                                <?php endif; ?>
                                <?php if ($canDelete): ?>
                                    <form method="post" action="<?= route('courses/delete'); ?>" onsubmit="return confirm('Excluir curso?');">
                                        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                        <input type="hidden" name="id" value="<?= (int) $row['id']; ?>">
                                        <button class="rounded border border-rose-200 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50">Excluir</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?>
                    <tr>
                        <td colspan="6" class="px-3 py-6 text-center text-slate-500">Nenhum curso encontrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
        <p>Total: <?= (int) $meta['total']; ?> registros | Pagina <?= (int) $meta['page']; ?>/<?= (int) $meta['pages']; ?></p>
        <div class="flex gap-2">
            <?php for ($p = 1; $p <= (int) $meta['pages']; $p++): ?>
                <a href="index.php?<?= build_query(['route' => 'courses', 'page' => $p]); ?>" class="rounded px-3 py-1 <?= $p === (int) $meta['page'] ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white hover:bg-slate-50'; ?>"><?= $p; ?></a>
            <?php endfor; ?>
        </div>
    </div>
</section>
