<section class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold">Kanban Cliente (Financeiro)</h2>
            <p class="text-sm text-slate-500">Arraste alunos entre colunas para alterar o status financeiro.</p>
        </div>
        <div class="flex gap-2">
            <a href="<?= route('kanban/settings'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Configurar status</a>
            <a href="<?= route('students/create'); ?>" class="rounded-lg bg-cyan-600 px-3 py-2 text-sm font-semibold text-white hover:bg-cyan-700">+ Novo Aluno</a>
        </div>
    </div>

    <form method="get" action="index.php" class="rounded-xl border border-slate-200 bg-white p-4">
        <input type="hidden" name="route" value="kanban">
        <input type="text" name="q" value="<?= e($search); ?>" placeholder="Buscar clientes..." class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
    </form>

    <div class="grid gap-4 xl:grid-cols-5">
        <?php foreach ($columns as $column): ?>
            <article data-status-column class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <header class="border-b border-slate-100 px-4 py-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold" style="color: <?= e($column['color'] ?: '#0ea5e9'); ?>"><?= e($column['name']); ?></h3>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs" data-status-count="<?= (int) $column['id']; ?>"><?= (int) $column['total_students']; ?></span>
                    </div>
                </header>

                <div class="kanban-column space-y-2 p-3" data-dropzone="<?= (int) $column['id']; ?>" data-csrf="<?= csrf_token(); ?>">
                    <?php foreach ($column['students'] as $student): ?>
                        <div data-student-card data-student-id="<?= (int) $student['id']; ?>" draggable="true" class="kanban-card rounded-lg border border-slate-200 bg-slate-50 p-3 shadow-sm">
                            <div class="mb-2 flex items-start justify-between gap-2">
                                <p class="text-sm font-semibold"><?= e($student['full_name']); ?></p>
                                <a href="<?= route('students/show&id=' . (int) $student['id']); ?>" class="text-xs text-slate-500">...</a>
                            </div>
                            <p class="text-xs text-slate-500"><?= e($student['primary_contact']); ?></p>
                            <p class="text-xs text-slate-500"><?= e($student['email_primary']); ?></p>
                            <p class="mt-1 text-xs text-slate-500"><?= e($student['phone']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
