<?php $canChatOpen = has_permission('chat.open'); ?>
<section class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-semibold"><?= e($lead ? 'Editar Lead' : 'Novo Lead'); ?></h2>
            <p class="text-sm text-slate-500">Cadastro completo do funil comercial.</p>
        </div>
        <div class="flex items-center gap-2">
            <?php if (!empty($lead['id']) && $canChatOpen): ?>
                <form method="post" action="<?= route('chatwoot/open-lead'); ?>">
                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                    <input type="hidden" name="lead_id" value="<?= (int) $lead['id']; ?>">
                    <input type="hidden" name="return_route" value="<?= e('leads/edit&id=' . (int) $lead['id']); ?>">
                    <button class="rounded-lg border border-cyan-200 bg-cyan-50 px-3 py-2 text-sm font-medium text-cyan-700 hover:bg-cyan-100">Atender no Chatwoot</button>
                </form>
            <?php endif; ?>
            <a href="<?= route('leads'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Voltar</a>
        </div>
    </div>

    <form method="post" action="<?= e($action); ?>" class="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 lg:grid-cols-2">
        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Nome completo *</span>
            <input type="text" name="full_name" required value="<?= e($lead['full_name'] ?? ''); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Email</span>
            <input type="email" name="email" value="<?= e($lead['email'] ?? ''); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Telefone</span>
            <input type="text" name="phone" value="<?= e($lead['phone'] ?? ''); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Valor do lead</span>
            <input type="text" name="lead_value" value="<?= e((string) ($lead['lead_value'] ?? '')); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Atribuido</span>
            <select name="assigned_to" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Não atribuido</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= (int) $user['id']; ?>" <?= (string) ($lead['assigned_to'] ?? '') === (string) $user['id'] ? 'selected' : ''; ?>><?= e($user['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Fonte</span>
            <input type="text" name="source" value="<?= e($lead['source'] ?? 'Google'); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Status</span>
            <select name="lead_status_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Padrao</option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= (int) $status['id']; ?>" <?= (string) ($lead['lead_status_id'] ?? '') === (string) $status['id'] ? 'selected' : ''; ?>><?= e($status['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Unidade</span>
            <input type="text" name="unit_name" value="<?= e($lead['unit_name'] ?? ''); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Tags</span>
            <input type="text" name="tags" value="<?= e($lead['tags'] ?? ''); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Ultimo contato</span>
            <input type="datetime-local" name="last_contact_at" value="<?= e(isset($lead['last_contact_at']) ? str_replace(' ', 'T', substr((string) $lead['last_contact_at'], 0, 16)) : ''); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>

        <div class="lg:col-span-2 flex justify-end gap-2">
            <a href="<?= route('leads'); ?>" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm hover:bg-slate-50">Cancelar</a>
            <button class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700">Salvar</button>
        </div>
    </form>

    <?php if (!empty($lead['id'])): ?>
        <section class="rounded-xl border border-slate-200 bg-white p-4">
            <h3 class="mb-3 text-lg font-semibold">Histórico de Interacoes</h3>

            <form method="post" action="<?= route('leads/history/store'); ?>" class="mb-4 flex gap-2">
                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                <input type="hidden" name="lead_id" value="<?= (int) ($lead['id'] ?? 0); ?>">
                <input type="text" name="interaction" required placeholder="Registrar contato..." class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Adicionar</button>
            </form>

            <div class="space-y-2 text-sm">
                <?php foreach (($history ?? []) as $item): ?>
                    <div class="rounded-lg border border-slate-100 px-3 py-2">
                        <p class="font-medium"><?= e($item['interaction']); ?></p>
                        <p class="text-slate-500"><?= e($item['created_at']); ?> | <?= e($item['created_by_name']); ?> <?= $item['status_name'] ? '| ' . e($item['status_name']) : ''; ?></p>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($history)): ?>
                    <p class="text-slate-500">Sem interacoes registradas.</p>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</section>
