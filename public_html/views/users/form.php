<?php
$isEdit = !empty($userData);
$selectedMap = array_fill_keys($selectedPermissions ?? [], true);
$roleValue = (string) ($userData['role'] ?? 'suporte');
$selectedCompanyMap = array_fill_keys(array_map('intval', $selectedCompanyIds ?? []), true);
$availableCompanies = $availableCompanies ?? [];
?>
<section class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-semibold"><?= e($title); ?></h2>
            <p class="text-sm text-slate-500">Configure credenciais e permissoes por tela/funcao do sistema.</p>
        </div>
        <a href="<?= route('users'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Voltar</a>
    </div>

    <form method="post" action="<?= e($action); ?>" class="space-y-4 rounded-xl border border-slate-200 bg-white p-5">
        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">

        <div class="grid gap-3 md:grid-cols-2">
            <label class="block">
                <span class="mb-1 block text-sm">Nome *</span>
                <input type="text" name="name" required value="<?= e($userData['name'] ?? ''); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </label>
            <label class="block">
                <span class="mb-1 block text-sm">Usuário *</span>
                <input type="text" name="username" required value="<?= e($userData['username'] ?? ''); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </label>
            <label class="block">
                <span class="mb-1 block text-sm">Email *</span>
                <input type="email" name="email" required value="<?= e($userData['email'] ?? ''); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </label>
            <label class="block">
                <span class="mb-1 block text-sm"><?= $isEdit ? 'Nova senha (opcional)' : 'Senha *'; ?></span>
                <input type="password" name="password" <?= $isEdit ? '' : 'required'; ?> class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="<?= $isEdit ? 'Deixe em branco para manter' : 'Minimo 6 caracteres'; ?>">
            </label>
            <label class="block">
                <span class="mb-1 block text-sm">Perfil *</span>
                <select name="role" id="role-select" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <?php foreach ($roles as $k => $label): ?>
                        <option value="<?= e($k); ?>" <?= $roleValue === $k ? 'selected' : ''; ?>><?= e($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="block">
                <span class="mb-1 block text-sm">Status</span>
                <select name="is_active" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <option value="1" <?= (int) ($userData['is_active'] ?? 1) === 1 ? 'selected' : ''; ?>>Ativo</option>
                    <option value="0" <?= (int) ($userData['is_active'] ?? 1) === 0 ? 'selected' : ''; ?>>Inativo</option>
                </select>
            </label>
        </div>

        <?php if ($availableCompanies !== []): ?>
            <div class="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-lg font-semibold">Empresas com acesso</h3>
                    <p class="text-xs text-slate-500">Selecione os CNPJs que este usuário podera acessar.</p>
                </div>
                <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                    <?php foreach ($availableCompanies as $company): ?>
                        <?php
                        $companyId = (int) ($company['id'] ?? 0);
                        $companyName = trim((string) ($company['trade_name'] ?? '')) !== '' ? (string) $company['trade_name'] : (string) ($company['legal_name'] ?? '');
                        ?>
                        <label class="flex items-start gap-2 rounded border border-slate-200 bg-white px-3 py-2 text-sm">
                            <input type="checkbox" name="company_ids[]" value="<?= $companyId; ?>" <?= isset($selectedCompanyMap[$companyId]) ? 'checked' : ''; ?> class="mt-1">
                            <span>
                                <span class="block font-medium"><?= e($companyName); ?></span>
                                <span class="block text-xs text-slate-500"><?= e((string) ($company['cnpj'] ?? '')); ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div id="support-permissions" class="<?= $roleValue === 'suporte' ? '' : 'hidden'; ?> space-y-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-lg font-semibold">Permissoes do suporte</h3>
                <div class="flex gap-2 text-xs">
                    <button type="button" id="perm-check-all" class="rounded border border-slate-300 bg-white px-2 py-1 hover:bg-slate-100">Marcar tudo</button>
                    <button type="button" id="perm-clear-all" class="rounded border border-slate-300 bg-white px-2 py-1 hover:bg-slate-100">Limpar tudo</button>
                </div>
            </div>

            <div>
                <p class="mb-2 text-sm font-medium">Acesso a telas</p>
                <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                    <?php foreach ($catalog['modules'] as $key => $label): ?>
                        <label class="flex items-center gap-2 rounded border border-slate-200 bg-white px-3 py-2 text-sm">
                            <input class="support-perm" type="checkbox" name="permissions[]" value="<?= e($key); ?>" <?= isset($selectedMap[$key]) ? 'checked' : ''; ?>>
                            <span><?= e($label); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div>
                <p class="mb-2 text-sm font-medium">Acesso a funcoes</p>
                <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                    <?php foreach ($catalog['functions'] as $key => $label): ?>
                        <label class="flex items-center gap-2 rounded border border-slate-200 bg-white px-3 py-2 text-sm">
                            <input class="support-perm" type="checkbox" name="permissions[]" value="<?= e($key); ?>" <?= isset($selectedMap[$key]) ? 'checked' : ''; ?>>
                            <span><?= e($label); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="flex gap-2">
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                <?= $isEdit ? 'Salvar alteracoes' : 'Criar usuário'; ?>
            </button>
            <a href="<?= route('users'); ?>" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm hover:bg-slate-50">Cancelar</a>
        </div>
    </form>
</section>

<script>
(() => {
    const roleSelect = document.getElementById('role-select');
    const supportBox = document.getElementById('support-permissions');
    const checkAll = document.getElementById('perm-check-all');
    const clearAll = document.getElementById('perm-clear-all');
    const perms = () => Array.from(document.querySelectorAll('.support-perm'));

    function toggleSupportSection() {
        if (!roleSelect || !supportBox) return;
        const isSupport = roleSelect.value === 'suporte';
        supportBox.classList.toggle('hidden', !isSupport);
        perms().forEach((el) => { el.disabled = !isSupport; });
    }

    roleSelect?.addEventListener('change', toggleSupportSection);
    checkAll?.addEventListener('click', () => perms().forEach((el) => { el.checked = true; }));
    clearAll?.addEventListener('click', () => perms().forEach((el) => { el.checked = false; }));

    toggleSupportSection();
})();
</script>
