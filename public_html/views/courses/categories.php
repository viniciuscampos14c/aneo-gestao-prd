<section class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-semibold">Categorias de Cursos</h2>
            <p class="text-sm text-slate-500">Organize cursos por categorias.</p>
        </div>
        <a href="<?= route('courses'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Voltar</a>
    </div>

    <form method="post" action="<?= route('courses/categories/store'); ?>" class="flex gap-2 rounded-xl border border-slate-200 bg-white p-4">
        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
        <input type="text" name="name" required placeholder="Nome da categoria" class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Adicionar</button>
    </form>

    <div class="space-y-2 rounded-xl border border-slate-200 bg-white p-4">
        <?php foreach ($categories as $category): ?>
            <div class="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2">
                <p class="text-sm font-medium"><?= e($category['name']); ?></p>
                <form method="post" action="<?= route('courses/categories/delete'); ?>" onsubmit="return confirm('Excluir categoria?');">
                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                    <input type="hidden" name="id" value="<?= (int) $category['id']; ?>">
                    <button class="rounded border border-rose-200 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50">Excluir</button>
                </form>
            </div>
        <?php endforeach; ?>
        <?php if ($categories === []): ?>
            <p class="text-sm text-slate-500">Nenhuma categoria cadastrada.</p>
        <?php endif; ?>
    </div>
</section>
