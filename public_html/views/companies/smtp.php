<?php
$companyOptions = is_array($companyOptions ?? null) ? $companyOptions : [];
$selectedCompanyId = (int) ($selectedCompanyId ?? 0);
$smtpSettings = is_array($smtpSettings ?? null) ? $smtpSettings : [];
$integrationAvailable = !empty($integrationAvailable);
$currentUser = current_user();
$defaultTestEmail = strtolower(trim((string) ($currentUser['email'] ?? config('support.notification_email', ''))));

$selectedCompanyName = '';
foreach ($companyOptions as $option) {
    if ((int) ($option['id'] ?? 0) !== $selectedCompanyId) {
        continue;
    }

    $selectedCompanyName = trim((string) ($option['trade_name'] ?? '')) !== ''
        ? (string) ($option['trade_name'] ?? '')
        : (string) ($option['legal_name'] ?? '');
    break;
}
?>
<section class="space-y-6">
    <div>
        <h2 class="text-2xl font-semibold">Cadastro SMTP</h2>
        <p class="text-sm text-slate-500">Configure o provedor de e-mail por empresa para envio de notificacoes do sistema.</p>
    </div>

    <?php if (!$integrationAvailable): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            A tabela <code>company_integrations</code> nao foi encontrada. Execute a migration <code>migrations/20260306_phase2_company_isolation_integrations.sql</code>.
        </div>
    <?php endif; ?>

    <form method="get" action="index.php" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-[1fr_auto] md:items-end">
        <input type="hidden" name="route" value="companies/smtp">
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
            Nenhuma empresa ativa foi encontrada para configurar SMTP.
        </div>
    <?php else: ?>
        <form method="post" action="<?= route('companies/smtp/save'); ?>" class="space-y-4 rounded-xl border border-slate-200 bg-white p-5">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
            <input type="hidden" name="company_id" value="<?= $selectedCompanyId; ?>">

            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                Empresa selecionada: <span class="font-semibold text-slate-700"><?= e($selectedCompanyName !== '' ? $selectedCompanyName : 'Nao informada'); ?></span>
            </div>

            <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                <input type="checkbox" name="smtp_enabled" value="1" <?= !empty($smtpSettings['enabled']) ? 'checked' : ''; ?> class="h-4 w-4 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500">
                Ativar envio por SMTP
            </label>

            <div class="grid gap-3 md:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block text-sm">Host SMTP *</span>
                    <input type="text" name="smtp_host" value="<?= e((string) ($smtpSettings['host'] ?? '')); ?>" placeholder="smtp.seuprovedor.com" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm">Porta *</span>
                    <input type="number" name="smtp_port" min="1" max="65535" value="<?= e((string) ($smtpSettings['port'] ?? '587')); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm">Seguranca</span>
                    <?php $security = (string) ($smtpSettings['security'] ?? 'tls'); ?>
                    <select name="smtp_security" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option value="tls" <?= $security === 'tls' ? 'selected' : ''; ?>>TLS (STARTTLS)</option>
                        <option value="ssl" <?= $security === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                        <option value="none" <?= $security === 'none' ? 'selected' : ''; ?>>Sem criptografia</option>
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm">Timeout (segundos)</span>
                    <input type="number" name="smtp_timeout" min="5" max="120" value="<?= e((string) ($smtpSettings['timeout'] ?? '20')); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm">Usuario SMTP</span>
                    <input type="text" name="smtp_username" value="<?= e((string) ($smtpSettings['username'] ?? '')); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm">Senha SMTP</span>
                    <input type="password" name="smtp_password" value="" placeholder="Deixe vazio para manter a senha atual" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm">E-mail remetente *</span>
                    <input type="email" name="smtp_from_email" value="<?= e((string) ($smtpSettings['from_email'] ?? '')); ?>" placeholder="nao-responda@empresa.com" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm">Nome remetente</span>
                    <input type="text" name="smtp_from_name" value="<?= e((string) ($smtpSettings['from_name'] ?? '')); ?>" placeholder="ANEO" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>
                <label class="block md:col-span-2">
                    <span class="mb-1 block text-sm">Reply-To (opcional)</span>
                    <input type="email" name="smtp_reply_to" value="<?= e((string) ($smtpSettings['reply_to'] ?? '')); ?>" placeholder="financeiro@empresa.com" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>
                <label class="block md:col-span-2">
                    <span class="mb-1 block text-sm">E-mail para teste SMTP</span>
                    <input type="email" name="smtp_test_email" value="<?= e($defaultTestEmail); ?>" placeholder="seuemail@dominio.com" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>
            </div>

            <div class="rounded-lg border border-cyan-100 bg-cyan-50 px-3 py-2 text-xs text-cyan-800">
                Dica: para Gmail/Outlook corporativo use porta 587 com TLS. Se o provedor exigir autenticacao, preencha usuario e senha.
            </div>

            <div class="flex flex-wrap gap-2">
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800" <?= $integrationAvailable ? '' : 'disabled'; ?>>Salvar configuracao SMTP</button>
                <button type="submit" formaction="<?= route('companies/smtp/test'); ?>" formmethod="post" class="rounded-lg border border-cyan-200 bg-cyan-50 px-4 py-2 text-sm font-semibold text-cyan-700 hover:bg-cyan-100">Enviar e-mail de teste</button>
                <a href="<?= route('companies'); ?>" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm hover:bg-slate-50">Voltar para Empresas</a>
            </div>
        </form>
    <?php endif; ?>
</section>
