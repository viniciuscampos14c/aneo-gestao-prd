<?php
$companyOptions = is_array($companyOptions ?? null) ? $companyOptions : [];
$selectedCompanyId = (int) ($selectedCompanyId ?? 0);
$selectedCompany = is_array($selectedCompany ?? null) ? $selectedCompany : null;
$licenseStatus = is_array($licenseStatus ?? null) ? $licenseStatus : [];
$licenseHistory = is_array($licenseHistory ?? null) ? $licenseHistory : [];
$licenseTablesAvailable = !empty($licenseTablesAvailable);
$configuredKeyLabels = is_array($configuredKeyLabels ?? null) ? $configuredKeyLabels : [];

$companyName = '';
if ($selectedCompany) {
    $companyName = trim((string) ($selectedCompany['trade_name'] ?? '')) !== ''
        ? (string) ($selectedCompany['trade_name'] ?? '')
        : (string) ($selectedCompany['legal_name'] ?? '');
}

$statusKey = (string) ($licenseStatus['status'] ?? 'missing');
$statusBadge = 'bg-slate-100 text-slate-700';
$statusLabel = 'Sem licenca';
if ($statusKey === 'active') {
    $statusBadge = 'bg-emerald-100 text-emerald-700';
    $statusLabel = 'Ativa';
} elseif ($statusKey === 'grace') {
    $statusBadge = 'bg-amber-100 text-amber-700';
    $statusLabel = 'Em carencia';
} elseif ($statusKey === 'expired') {
    $statusBadge = 'bg-rose-100 text-rose-700';
    $statusLabel = 'Expirada';
}
?>
<section class="space-y-6">
    <div>
        <h2 class="text-2xl font-semibold">Licenca</h2>
        <p class="text-sm text-slate-500">Ative ou renove a licenca anual por empresa usando chave fixa.</p>
    </div>

    <?php if (!$licenseTablesAvailable): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Estrutura de licenciamento indisponivel. Execute a migration <code>migrations/20260316_company_licenses.sql</code>.
        </div>
    <?php endif; ?>

    <form method="get" action="index.php" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-[1fr_auto] md:items-end">
        <input type="hidden" name="route" value="companies/license">
        <label class="block">
            <span class="mb-1 block text-sm font-medium">Empresa</span>
            <select name="company_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <?php foreach ($companyOptions as $option): ?>
                    <?php
                    $optionId = (int) ($option['id'] ?? 0);
                    $optionName = trim((string) ($option['trade_name'] ?? '')) !== ''
                        ? (string) ($option['trade_name'] ?? '')
                        : (string) ($option['legal_name'] ?? '');
                    ?>
                    <option value="<?= $optionId; ?>" <?= $optionId === $selectedCompanyId ? 'selected' : ''; ?>><?= e($optionName); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Carregar</button>
    </form>

    <?php if ($companyOptions === []): ?>
        <div class="rounded-xl border border-slate-200 bg-white px-4 py-6 text-sm text-slate-600">
            Nenhuma empresa ativa foi encontrada para licenciamento.
        </div>
    <?php else: ?>
        <div class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-5 lg:col-span-2">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Empresa Selecionada</p>
                        <h3 class="text-lg font-semibold text-slate-800"><?= e($companyName !== '' ? $companyName : 'Nao informada'); ?></h3>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold <?= $statusBadge; ?>"><?= e($statusLabel); ?></span>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-2 text-sm">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-xs text-slate-500">Licenca</p>
                        <p class="font-semibold text-slate-700"><?= e((string) ($licenseStatus['license_label'] ?? '-')); ?></p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-xs text-slate-500">Hash da chave</p>
                        <p class="font-mono text-xs text-slate-700"><?= e((string) ($licenseStatus['key_masked'] ?? '-')); ?></p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-xs text-slate-500">Valida de</p>
                        <p class="font-semibold text-slate-700"><?= e((string) ($licenseStatus['valid_from'] ?? '-')); ?></p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-xs text-slate-500">Valida ate</p>
                        <p class="font-semibold text-slate-700"><?= e((string) ($licenseStatus['valid_until'] ?? '-')); ?></p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-xs text-slate-500">Dias restantes</p>
                        <p class="font-semibold text-slate-700"><?= isset($licenseStatus['days_left']) ? (int) $licenseStatus['days_left'] : '-'; ?></p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-xs text-slate-500">Carencia ate</p>
                        <p class="font-semibold text-slate-700"><?= e((string) ($licenseStatus['grace_until'] ?? '-')); ?></p>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Modelo Atual</h4>
                <p class="mt-2 text-sm text-slate-600">Chave fixa anual. Ao trocar a chave no <code>config.php</code>, voce emite um novo ciclo.</p>
                <div class="mt-3 space-y-2 text-xs text-slate-600">
                    <?php foreach ($configuredKeyLabels as $item): ?>
                        <div class="rounded border border-slate-200 bg-slate-50 px-2 py-1">
                            <p class="font-semibold text-slate-700"><?= e((string) ($item['label'] ?? 'Licenca fixa')); ?></p>
                            <p>Duracao: <?= (int) ($item['duration_days'] ?? 365); ?> dias</p>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($configuredKeyLabels === []): ?>
                        <p>Nenhuma chave fixa configurada.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <form method="post" action="<?= route('companies/license/activate'); ?>" class="space-y-4 rounded-xl border border-slate-200 bg-white p-5">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
            <input type="hidden" name="company_id" value="<?= $selectedCompanyId; ?>">

            <div class="grid gap-3 md:grid-cols-2">
                <label class="block md:col-span-2">
                    <span class="mb-1 block text-sm">Chave de licenca *</span>
                    <input type="text" name="license_key" required placeholder="Cole a chave fixa anual" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm tracking-wide">
                </label>
                <label class="block md:col-span-2">
                    <span class="mb-1 block text-sm">Observacao (opcional)</span>
                    <input type="text" name="activation_note" placeholder="Ex.: Renovacao anual 2027" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>
            </div>

            <div class="rounded-lg border border-cyan-100 bg-cyan-50 px-3 py-2 text-xs text-cyan-800">
                A ativacao renova a empresa por 1 ano a partir da data atual.
            </div>

            <div class="flex flex-wrap gap-2">
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800" <?= $licenseTablesAvailable ? '' : 'disabled'; ?>>Ativar / Renovar licenca</button>
                <a href="<?= route('companies'); ?>" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm hover:bg-slate-50">Voltar para Empresas</a>
            </div>
        </form>

        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <h4 class="text-lg font-semibold">Historico de Licencas</h4>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                            <th class="px-3 py-2">Data</th>
                            <th class="px-3 py-2">Acao</th>
                            <th class="px-3 py-2">Usuario</th>
                            <th class="px-3 py-2">Licenca</th>
                            <th class="px-3 py-2">Validade</th>
                            <th class="px-3 py-2">Obs.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($licenseHistory as $row): ?>
                            <tr class="border-b border-slate-100 hover:bg-slate-50">
                                <td class="px-3 py-2 whitespace-nowrap"><?= e((string) ($row['created_at'] ?? '')); ?></td>
                                <td class="px-3 py-2"><?= e((string) ($row['action'] ?? '-')); ?></td>
                                <td class="px-3 py-2">
                                    <p class="font-medium"><?= e((string) ($row['user_name'] ?? '-')); ?></p>
                                    <p class="text-xs text-slate-500"><?= e((string) ($row['user_email'] ?? '')); ?></p>
                                </td>
                                <td class="px-3 py-2">
                                    <p class="font-medium"><?= e((string) ($row['license_label'] ?? '-')); ?></p>
                                    <p class="font-mono text-xs text-slate-500"><?= e(substr((string) ($row['license_key_hash'] ?? ''), 0, 8)); ?>...</p>
                                </td>
                                <td class="px-3 py-2"><?= e((string) ($row['valid_from'] ?? '-')); ?> ate <?= e((string) ($row['valid_until'] ?? '-')); ?></td>
                                <td class="px-3 py-2"><?= e((string) ($row['note'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($licenseHistory === []): ?>
                            <tr>
                                <td colspan="6" class="px-3 py-4 text-center text-slate-500">Sem historico de ativacoes para esta empresa.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</section>
