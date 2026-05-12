<?php
$isEdit = is_array($editing ?? null);
$formatCnpj = static function (?string $value): string {
    $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
    if (strlen($digits) !== 14) {
        return (string) $value;
    }

    return substr($digits, 0, 2) . '.' .
        substr($digits, 2, 3) . '.' .
        substr($digits, 5, 3) . '/' .
        substr($digits, 8, 4) . '-' .
        substr($digits, 12, 2);
};
$integrationAvailable = !empty($integrationAvailable);
$integrationCompanyId = (int) ($integrationCompanyId ?? 0);
$integrationCompany = is_array($integrationCompany ?? null) ? $integrationCompany : null;
$integrationSettings = is_array($integrationSettings ?? null) ? $integrationSettings : [];
$companyOptions = is_array($companyOptions ?? null) ? $companyOptions : [];
$d4sign = is_array($integrationSettings['d4sign'] ?? null) ? $integrationSettings['d4sign'] : [];
$publicBaseUrl = rtrim((string) (config('app.public_url', '') ?: config('app.base_url', '')), '/');
$d4signWebhookUrl = '';
if ($publicBaseUrl !== '') {
    $d4signWebhookUrl = $publicBaseUrl . '/index.php?route=signatures/webhook';
    if (trim((string) ($d4sign['webhook_token'] ?? '')) !== '') {
        $d4signWebhookUrl .= '&token=' . urlencode((string) $d4sign['webhook_token']);
    }
}
?>
<section class="companies-shell space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold">Empresas</h2>
            <p class="text-sm text-slate-500">Cadastre os CNPJs e controle quais usuarios podem acessar cada empresa.</p>
        </div>
    </div>

    <form method="post" action="<?= route($isEdit ? 'companies/update' : 'companies/store'); ?>" class="companies-form space-y-4 rounded-xl border border-slate-200 bg-white p-5">
        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0); ?>">
        <?php endif; ?>

        <div class="grid gap-3 md:grid-cols-2">
            <label class="block">
                <span class="mb-1 block text-sm">Razao social *</span>
                <input type="text" name="legal_name" required value="<?= e((string) ($editing['legal_name'] ?? '')); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </label>
            <label class="block">
                <span class="mb-1 block text-sm">Nome fantasia</span>
                <input type="text" name="trade_name" value="<?= e((string) ($editing['trade_name'] ?? '')); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </label>
            <label class="block">
                <span class="mb-1 block text-sm">CNPJ *</span>
                <input type="text" name="cnpj" required value="<?= e((string) ($editing['cnpj'] ?? '')); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Somente numeros ou formatado">
            </label>
            <label class="block">
                <span class="mb-1 block text-sm">Status</span>
                <select name="is_active" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <option value="1" <?= (int) ($editing['is_active'] ?? 1) === 1 ? 'selected' : ''; ?>>Ativa</option>
                    <option value="0" <?= (int) ($editing['is_active'] ?? 1) === 0 ? 'selected' : ''; ?>>Inativa</option>
                </select>
            </label>
        </div>

        <div class="flex gap-2">
            <button class="companies-save-btn rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                <?= $isEdit ? 'Salvar empresa' : 'Cadastrar empresa'; ?>
            </button>
            <?php if ($isEdit): ?>
                <a href="<?= route('companies'); ?>" class="companies-cancel-btn rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm hover:bg-slate-50">Cancelar edicao</a>
            <?php endif; ?>
        </div>
    </form>

    <form method="get" action="index.php" class="companies-filter grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-4">
        <input type="hidden" name="route" value="companies">
        <input type="text" name="q" value="<?= e((string) $filters['q']); ?>" placeholder="Buscar por nome ou CNPJ..." class="rounded-lg border border-slate-200 px-3 py-2 text-sm">

        <select name="is_active" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option value="">Todos os status</option>
            <option value="1" <?= (string) $filters['is_active'] === '1' ? 'selected' : ''; ?>>Ativa</option>
            <option value="0" <?= (string) $filters['is_active'] === '0' ? 'selected' : ''; ?>>Inativa</option>
        </select>

        <select name="per_page" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <?php foreach ($paginationOptions as $opt): ?>
                <option value="<?= (int) $opt; ?>" <?= (int) $meta['per_page'] === (int) $opt ? 'selected' : ''; ?>><?= (int) $opt; ?>/pagina</option>
            <?php endforeach; ?>
        </select>

        <button class="companies-filter-btn rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrar</button>
    </form>

    <div class="companies-table-wrap overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="companies-table min-w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-3">ID</th>
                    <th class="px-3 py-3">Empresa</th>
                    <th class="px-3 py-3">CNPJ</th>
                    <th class="px-3 py-3">Usuarios</th>
                    <th class="px-3 py-3">Status</th>
                    <th class="px-3 py-3">Acoes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php $name = trim((string) ($row['trade_name'] ?? '')) !== '' ? (string) $row['trade_name'] : (string) $row['legal_name']; ?>
                    <?php $statusToneClass = (int) $row['is_active'] === 1 ? 'companies-status-active' : 'companies-status-inactive'; ?>
                    <tr class="companies-row border-b border-slate-100 hover:bg-slate-50">
                        <td class="px-3 py-3"><?= (int) $row['id']; ?></td>
                        <td class="px-3 py-3">
                            <p class="font-medium"><?= e($name); ?></p>
                            <p class="text-xs text-slate-500"><?= e((string) $row['legal_name']); ?></p>
                        </td>
                        <td class="px-3 py-3"><?= e($formatCnpj((string) $row['cnpj'])); ?></td>
                        <td class="px-3 py-3"><?= (int) ($row['users_count'] ?? 0); ?></td>
                        <td class="px-3 py-3">
                            <span class="companies-status-pill rounded-full px-2 py-1 text-xs font-semibold <?= (int) $row['is_active'] === 1 ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'; ?> <?= $statusToneClass; ?>">
                                <?= (int) $row['is_active'] === 1 ? 'Ativa' : 'Inativa'; ?>
                            </span>
                        </td>
                        <td class="px-3 py-3">
                            <div class="flex flex-wrap gap-2">
                                <a href="<?= route('companies&edit=' . (int) $row['id']); ?>" class="companies-btn companies-btn-edit rounded border border-slate-200 px-2 py-1 text-xs hover:bg-slate-100">Editar</a>

                                <form method="post" action="<?= route('companies/toggle'); ?>">
                                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                    <input type="hidden" name="id" value="<?= (int) $row['id']; ?>">
                                    <input type="hidden" name="active" value="<?= (int) $row['is_active'] === 1 ? 0 : 1; ?>">
                                    <button class="companies-btn companies-btn-toggle rounded border border-amber-200 px-2 py-1 text-xs text-amber-700 hover:bg-amber-50">
                                        <?= (int) $row['is_active'] === 1 ? 'Inativar' : 'Ativar'; ?>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?>
                    <tr>
                        <td colspan="6" class="px-3 py-6 text-center text-slate-500">Nenhuma empresa encontrada.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="companies-pagination flex flex-wrap items-center justify-between gap-3 text-sm">
        <p>Total: <?= (int) $meta['total']; ?> registros | Pagina <?= (int) $meta['page']; ?>/<?= (int) $meta['pages']; ?></p>
        <div class="flex gap-2">
            <?php for ($p = 1; $p <= (int) $meta['pages']; $p++): ?>
                <a href="index.php?<?= build_query(['route' => 'companies', 'page' => $p]); ?>" class="companies-page-link rounded px-3 py-1 <?= $p === (int) $meta['page'] ? 'companies-page-link-active bg-slate-900 text-white' : 'border border-slate-200 bg-white hover:bg-slate-50'; ?>">
                    <?= $p; ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold">Configuracao D4Sign</h3>
                <p class="text-sm text-slate-500">Mantenha aqui as credenciais de assinatura digital da empresa. Essa configuracao saiu do modulo Assinaturas para ficar concentrada em Cadastro.</p>
            </div>
        </div>

        <?php if (!$integrationAvailable): ?>
            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                A tabela <code>company_integrations</code> nao foi encontrada. Execute a migracao <code>migrations/20260306_phase2_company_isolation_integrations.sql</code>.
            </div>
        <?php endif; ?>

        <form method="get" action="index.php" class="mt-4 grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 md:grid-cols-[1fr_auto] md:items-end">
            <input type="hidden" name="route" value="companies">
            <label class="block">
                <span class="mb-1 block text-sm font-medium">Empresa da configuracao</span>
                <select name="integration_company_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <option value="">Selecione...</option>
                    <?php foreach ($companyOptions as $companyOption): ?>
                        <?php $companyName = trim((string) ($companyOption['trade_name'] ?? '')) !== '' ? (string) $companyOption['trade_name'] : (string) ($companyOption['legal_name'] ?? ''); ?>
                        <option value="<?= (int) ($companyOption['id'] ?? 0); ?>" <?= $integrationCompanyId === (int) ($companyOption['id'] ?? 0) ? 'selected' : ''; ?>>
                            <?= e($companyName); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Carregar configuracao</button>
        </form>

        <?php if ($integrationCompany): ?>
            <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                Empresa selecionada: <strong><?= e((string) ($integrationCompany['trade_name'] ?: $integrationCompany['legal_name'])); ?></strong>
            </div>

            <?php if ($d4signWebhookUrl !== ''): ?>
                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <h4 class="text-sm font-semibold text-slate-800">Webhook D4Sign</h4>
                    <p class="mt-1 text-xs text-slate-500">Cadastre esta URL no painel D4Sign para atualizar status e baixar o contrato assinado automaticamente.</p>
                    <div class="mt-3 grid gap-2 md:grid-cols-[1fr_auto]">
                        <input type="text" readonly value="<?= e($d4signWebhookUrl); ?>" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs">
                        <button type="button" onclick="navigator.clipboard.writeText('<?= e($d4signWebhookUrl); ?>')" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs hover:bg-slate-50">Copiar URL</button>
                    </div>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= route('companies/d4sign/save'); ?>" class="mt-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 lg:grid-cols-6">
                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                <input type="hidden" name="company_id" value="<?= $integrationCompanyId; ?>">

                <label class="flex items-center gap-2 text-sm lg:col-span-2">
                    <input type="checkbox" name="d4sign_enabled" value="1" <?= !empty($d4sign['enabled']) ? 'checked' : ''; ?> class="h-4 w-4 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500">
                    Integracao ativa
                </label>

                <label class="block lg:col-span-4">
                    <span class="mb-1 block text-sm font-medium">Base URL</span>
                    <input type="text" name="d4sign_base_url" value="<?= e((string) ($d4sign['base_url'] ?? '')); ?>" placeholder="https://sandbox.d4sign.com.br" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>

                <label class="block lg:col-span-2">
                    <span class="mb-1 block text-sm font-medium">Token API</span>
                    <input type="password" name="d4sign_token_api" value="<?= e((string) ($d4sign['token_api'] ?? '')); ?>" autocomplete="off" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>

                <label class="block lg:col-span-2">
                    <span class="mb-1 block text-sm font-medium">Crypt Key</span>
                    <input type="password" name="d4sign_crypt_key" value="<?= e((string) ($d4sign['crypt_key'] ?? '')); ?>" autocomplete="off" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>

                <label class="block lg:col-span-2">
                    <span class="mb-1 block text-sm font-medium">Safe UUID</span>
                    <input type="text" name="d4sign_safe_uuid" value="<?= e((string) ($d4sign['safe_uuid'] ?? '')); ?>" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>

                <label class="block lg:col-span-3">
                    <span class="mb-1 block text-sm font-medium">Webhook Token</span>
                    <input type="text" name="d4sign_webhook_token" value="<?= e((string) ($d4sign['webhook_token'] ?? '')); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>

                <label class="block lg:col-span-3">
                    <span class="mb-1 block text-sm font-medium">Webhook HMAC Secret</span>
                    <input type="password" name="d4sign_webhook_hmac_secret" value="<?= e((string) ($d4sign['webhook_hmac_secret'] ?? '')); ?>" autocomplete="off" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>

                <div class="lg:col-span-6 flex justify-end">
                    <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800" <?= $integrationAvailable ? '' : 'disabled'; ?>>
                        Salvar configuracao D4Sign
                    </button>
                </div>
            </form>
        <?php else: ?>
            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-600">
                Selecione uma empresa para visualizar e editar as credenciais do D4Sign.
            </div>
        <?php endif; ?>
    </div>
</section>
