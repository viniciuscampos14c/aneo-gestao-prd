<section class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-semibold">Configurar Status do Kanban</h2>
            <p class="text-sm text-slate-500">Crie, edite, ordene e defina o status padrao.</p>
        </div>
        <a href="<?= route('kanban'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Voltar ao Kanban</a>
    </div>

    <form method="post" action="<?= route('kanban/status/store'); ?>" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-5">
        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
        <input type="text" name="name" placeholder="Nome do status" required class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <input type="color" name="color" value="#0ea5e9" class="h-10 rounded-lg border border-slate-200 px-1 py-1">
        <input type="number" name="display_order" value="99" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_default" value="1"> Padrao</label>
        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Adicionar</button>
    </form>

    <div class="space-y-3">
        <?php foreach ($statuses as $st): ?>
            <form method="post" action="<?= route('kanban/status/update'); ?>" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-[auto_1fr_auto_auto_auto_auto]">
                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                <input type="hidden" name="id" value="<?= (int) $st['id']; ?>">

                <span class="my-auto h-4 w-4 rounded-full" style="background-color: <?= e($st['color']); ?>"></span>
                <input type="text" name="name" value="<?= e($st['name']); ?>" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <input type="color" name="color" value="<?= e($st['color']); ?>" class="h-10 rounded-lg border border-slate-200 px-1 py-1">
                <input type="number" name="display_order" value="<?= (int) $st['display_order']; ?>" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_default" value="1" <?= (int) $st['is_default'] === 1 ? 'checked' : ''; ?>> Padrao</label>

                <div class="flex gap-2">
                    <button class="rounded-lg border border-slate-200 px-3 py-2 text-sm hover:bg-slate-50">Salvar</button>
                </div>
            </form>

            <form method="post" action="<?= route('kanban/status/delete'); ?>" onsubmit="return confirm('Excluir status?');" class="-mt-2 flex justify-end">
                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                <input type="hidden" name="id" value="<?= (int) $st['id']; ?>">
                <button class="rounded-lg border border-rose-200 px-3 py-1 text-xs text-rose-700 hover:bg-rose-50">Excluir</button>
            </form>
        <?php endforeach; ?>
    </div>
</section>
