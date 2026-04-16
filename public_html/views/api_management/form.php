<div class="mb-6 flex items-center gap-3">
    <a href="<?= route('api-management'); ?>" class="text-slate-400 hover:text-slate-600">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
    </a>
    <h2 class="text-xl font-bold text-slate-800"><?= e($title); ?></h2>
</div>

<form method="post" action="<?= e($action); ?>" class="space-y-6 max-w-5xl">
    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">

    <!-- Dados básicos -->
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wider text-slate-500">Identificação</h3>
        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Usuário Vinculado</label>
                <select name="user_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-cyan-500 focus:outline-none" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int) $u['id']; ?>"
                            <?= ((int) ($tokenData['user_id'] ?? 0)) === (int) $u['id'] ? 'selected' : ''; ?>>
                            <?= e($u['name']); ?> (<?= e($u['username']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Nome do Token</label>
                <input type="text" name="name" maxlength="100" required
                    value="<?= e($tokenData['name'] ?? ''); ?>"
                    placeholder="Ex: n8n producao, CRM integracao"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-cyan-500 focus:outline-none">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Data de Expiração <span class="text-slate-400">(opcional)</span></label>
                <input type="date" name="expires_at"
                    value="<?= e($tokenData['expires_at'] ?? ''); ?>"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-cyan-500 focus:outline-none">
                <p class="mt-1 text-[11px] text-slate-400">Deixe vazio para não expirar.</p>
            </div>
        </div>
    </div>

    <!-- Grid de Permissões -->
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="mb-1 text-sm font-semibold uppercase tracking-wider text-slate-500">Permissões</h3>
        <p class="mb-5 text-xs text-slate-400">Selecione quais recursos e operações este token pode acessar.</p>

        <div class="grid gap-4 md:grid-cols-2">
            <?php foreach ($resources as $resource => $caps): ?>
                <div class="rounded-lg border border-slate-200 p-4">
                    <p class="mb-3 text-sm font-semibold text-slate-700"><?= e($resLabels[$resource] ?? ucfirst($resource)); ?></p>
                    <div class="flex flex-wrap gap-x-6 gap-y-2">
                        <?php foreach ($caps as $cap): ?>
                            <?php
                                $checked = in_array($cap, (array) ($selected[$resource] ?? []), true);
                            ?>
                            <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600">
                                <input type="checkbox"
                                       name="permissions[<?= e($resource); ?>][]"
                                       value="<?= e($cap); ?>"
                                       <?= $checked ? 'checked' : ''; ?>
                                       class="h-4 w-4 rounded border-slate-300 text-cyan-600">
                                <?= e($capLabels[$cap] ?? ucfirst($cap)); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="flex gap-3">
        <button type="submit" class="rounded-lg bg-cyan-600 px-6 py-2 text-sm font-semibold text-white hover:bg-cyan-700">
            Salvar Token
        </button>
        <a href="<?= route('api-management'); ?>" class="rounded-lg border border-slate-300 px-6 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
            Cancelar
        </a>
    </div>
</form>
