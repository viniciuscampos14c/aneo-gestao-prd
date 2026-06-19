<?php
$canCreate = has_permission('leads.create');
$canSettings = has_permission('leads.settings');
$canExport = has_permission('leads.export');
$canBulk = has_permission('leads.bulk');
$canStatus = has_permission('leads.status');
$canEdit = has_permission('leads.edit');
$canConvert = has_permission('leads.convert');
$canDelete = has_permission('leads.delete');
?>
<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold">Leads</h2>
            <p class="text-sm text-slate-500">Funil comercial com status customizavel e conversao para aluno.</p>
        </div>
        <div class="flex gap-2">
            <?php if ($canSettings): ?>
                <a href="<?= route('leads/settings'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Configurar Pipeline</a>
            <?php endif; ?>
            <?php if ($canCreate): ?>
                <a href="<?= route('leads/create'); ?>" class="rounded-lg bg-cyan-600 px-3 py-2 text-sm font-semibold text-white hover:bg-cyan-700">+ Novo Lead</a>
            <?php endif; ?>
            <?php if ($canExport): ?>
                <a href="<?= route('leads/export&q=' . urlencode($filters['q'])); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Exportar CSV</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-5">
        <?php foreach ($statuses as $status): ?>
            <div class="rounded-lg border border-slate-100 p-3">
                <p class="text-xs uppercase tracking-wide" style="color: <?= e($status['color']); ?>"><?= e($status['name']); ?></p>
                <p class="mt-1 text-xl font-semibold"><?= (int) $status['total_leads']; ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <form method="get" action="index.php" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-4">
        <input type="hidden" name="route" value="leads">
        <input type="text" name="q" value="<?= e($filters['q']); ?>" placeholder="Buscar lead..." class="rounded-lg border border-slate-200 px-3 py-2 text-sm">

        <select name="status_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option value="">Todos os status</option>
            <?php foreach ($statuses as $status): ?>
                <option value="<?= (int) $status['id']; ?>" <?= (string) $filters['status_id'] === (string) $status['id'] ? 'selected' : ''; ?>><?= e($status['name']); ?></option>
            <?php endforeach; ?>
        </select>

        <select name="per_page" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <?php foreach ($paginationOptions as $opt): ?>
                <option value="<?= (int) $opt; ?>" <?= (int) $meta['per_page'] === (int) $opt ? 'selected' : ''; ?>><?= (int) $opt; ?>/página</option>
            <?php endforeach; ?>
        </select>

        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrar</button>
    </form>

    <?php if ($canBulk): ?>
        <form id="leads-bulk-form" method="post" action="<?= route('leads/bulk'); ?>" class="flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white p-4">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
            <select name="bulk_action" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Acoes em massa</option>
                <option value="status">Alterar status</option>
                <option value="delete">Excluir</option>
            </select>
            <select name="bulk_status_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Status alvo</option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= (int) $status['id']; ?>"><?= e($status['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">Aplicar nos selecionados</button>
        </form>
    <?php endif; ?>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full border-collapse text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-2 py-3"><?= $canBulk ? '<input type="checkbox" onclick="document.querySelectorAll(\'.lead-check\').forEach(el => el.checked = this.checked)">' : ''; ?></th>
                    <th class="px-2 py-3">ID</th>
                    <th class="px-2 py-3">Nome</th>
                    <th class="px-2 py-3">Email</th>
                    <th class="px-2 py-3">Telefone</th>
                    <th class="px-2 py-3">Valor</th>
                    <th class="px-2 py-3">Atribuido</th>
                    <th class="px-2 py-3">Fonte</th>
                    <th class="px-2 py-3">Status</th>
                    <th class="px-2 py-3">Unidade</th>
                    <th class="px-2 py-3">Tags</th>
                    <th class="px-2 py-3">Ultimo contato</th>
                    <th class="px-2 py-3">Acoes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leads as $lead): ?>
                    <tr class="border-b border-slate-100 hover:bg-slate-50">
                        <td class="px-2 py-3"><?= $canBulk ? '<input class="lead-check" type="checkbox" value="' . (int) $lead['id'] . '">' : ''; ?></td>
                        <td class="px-2 py-3"><?= (int) $lead['id']; ?></td>
                        <td class="px-2 py-3 font-medium"><?= e($lead['full_name']); ?></td>
                        <td class="px-2 py-3"><?= e($lead['email']); ?></td>
                        <td class="px-2 py-3"><?= e($lead['phone']); ?></td>
                        <td class="px-2 py-3"><?= e(format_currency($lead['lead_value'])); ?></td>
                        <td class="px-2 py-3"><?= e($lead['assigned_name']); ?></td>
                        <td class="px-2 py-3"><?= e($lead['source']); ?></td>
                        <td class="px-2 py-3">
                            <?php if ($canStatus): ?>
                                <form method="post" action="<?= route('leads/set-status'); ?>" class="flex items-center gap-2">
                                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                    <input type="hidden" name="id" value="<?= (int) $lead['id']; ?>">
                                    <select name="lead_status_id" onchange="this.form.submit()" class="rounded-lg border px-2 py-1 text-xs" style="border-color: <?= e($lead['status_color'] ?: '#64748b'); ?>; color: <?= e($lead['status_color'] ?: '#64748b'); ?>;">
                                        <?php foreach ($statuses as $status): ?>
                                            <option value="<?= (int) $status['id']; ?>" <?= (int) $lead['lead_status_id'] === (int) $status['id'] ? 'selected' : ''; ?>><?= e($status['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            <?php else: ?>
                                <span class="rounded px-2 py-1 text-xs" style="background: rgba(100,116,139,.12); color: <?= e($lead['status_color'] ?: '#64748b'); ?>">
                                    <?= e($lead['status_name'] ?? 'Sem status'); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-2 py-3"><?= e($lead['unit_name']); ?></td>
                        <td class="px-2 py-3"><?= e($lead['tags']); ?></td>
                        <td class="px-2 py-3"><?= e($lead['last_contact_at']); ?></td>
                        <td class="px-2 py-3">
                            <div class="flex gap-2">
                                <?php if ($canEdit): ?>
                                    <a href="<?= route('leads/edit&id=' . (int) $lead['id']); ?>" class="rounded border border-slate-200 px-2 py-1 text-xs hover:bg-slate-100">Editar</a>
                                <?php endif; ?>
                                <?php if ($canConvert): ?>
                                    <form method="post" action="<?= route('leads/convert'); ?>" onsubmit="return confirm('Converter lead em aluno?');">
                                        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                        <input type="hidden" name="id" value="<?= (int) $lead['id']; ?>">
                                        <button class="rounded border border-emerald-200 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50">Converter</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($canDelete): ?>
                                    <form method="post" action="<?= route('leads/delete'); ?>" onsubmit="return confirm('Excluir lead?');">
                                        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                        <input type="hidden" name="id" value="<?= (int) $lead['id']; ?>">
                                        <button class="rounded border border-rose-200 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50">Excluir</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($leads === []): ?>
                    <tr>
                        <td colspan="13" class="px-2 py-6 text-center text-slate-500">Nenhum lead encontrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
        <p>Total: <?= (int) $meta['total']; ?> registros | Página <?= (int) $meta['page']; ?>/<?= (int) $meta['pages']; ?></p>
        <div class="flex gap-2">
            <?php for ($p = 1; $p <= (int) $meta['pages']; $p++): ?>
                <a href="index.php?<?= build_query(['route' => 'leads', 'page' => $p]); ?>" class="rounded px-3 py-1 <?= $p === (int) $meta['page'] ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white hover:bg-slate-50'; ?>"><?= $p; ?></a>
            <?php endfor; ?>
        </div>
    </div>
</section>

<script>
document.getElementById('leads-bulk-form')?.addEventListener('submit', function (event) {
    this.querySelectorAll('input[name="ids[]"]').forEach((node) => node.remove());
    const selected = Array.from(document.querySelectorAll('.lead-check:checked')).map((el) => el.value);
    if (selected.length === 0) {
        event.preventDefault();
        alert('Selecione ao menos um lead.');
        return;
    }
    selected.forEach((id) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = id;
        this.appendChild(input);
    });
});
</script>
