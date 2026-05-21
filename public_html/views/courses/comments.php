<?php $currentCourseFilter = (int) (($filters['course_id'] ?? 0)); ?>
<section class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-semibold">Gerenciar Comentarios</h2>
            <p class="text-sm text-slate-500">Comentarios e feedbacks dos cursos.</p>
        </div>
        <a href="<?= route('courses'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Voltar</a>
    </div>

    <form method="post" action="<?= route('courses/comments/store'); ?>" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-3">
        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">

        <select name="course_id" required class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option value="">Curso...</option>
            <?php foreach ($courses as $course): ?>
                <option value="<?= (int) $course['id']; ?>" <?= $currentCourseFilter === (int) $course['id'] ? 'selected' : ''; ?>><?= e($course['name']); ?></option>
            <?php endforeach; ?>
        </select>

        <input type="text" name="comment" required placeholder="Comentario" class="rounded-lg border border-slate-200 px-3 py-2 text-sm md:col-span-2">

        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Registrar Comentario</button>
    </form>

    <?php if ($currentCourseFilter > 0): ?>
        <div class="rounded-xl border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm text-cyan-900">
            Exibindo comentarios do curso selecionado. <a href="<?= route('courses/comments'); ?>" class="font-semibold underline">Limpar filtro</a>
        </div>
    <?php endif; ?>

    <div class="space-y-2 rounded-xl border border-slate-200 bg-white p-4">
        <?php foreach ($rows as $row): ?>
            <article class="rounded-lg border border-slate-100 px-3 py-2 text-sm">
                <p class="font-semibold"><?= e($row['course_name']); ?></p>
                <p class="mt-1"><?= e($row['comment']); ?></p>
                <p class="mt-1 text-xs text-slate-500"><?= e($row['author_name']); ?> | <?= e($row['created_at']); ?></p>
            </article>
        <?php endforeach; ?>
        <?php if ($rows === []): ?>
            <p class="text-sm text-slate-500">Sem comentarios registrados.</p>
        <?php endif; ?>
    </div>
</section>
