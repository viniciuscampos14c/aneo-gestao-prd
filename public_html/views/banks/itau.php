<?php
$settings   = is_array($settings ?? null) ? $settings : [];
$webhookUrl = (string) ($webhookUrl ?? '');
$companyId  = (int) ($companyId ?? 0);

$isEnabled   = !empty($settings['enabled']);
$environment = (string) ($settings['environment'] ?? 'sandbox');
?>
<section class="space-y-6">
    <div>
        <h2 class="text-2xl font-semibold">Banco Itaú — Configuração</h2>
        <p class="text-sm text-slate-500">Configure a integração com a API do Itaú para emissão de boletos via mTLS.</p>
    </div>

    <form method="post" action="<?= route('banks/itau/save'); ?>" class="space-y-5">
        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">

        <!-- Status e Ambiente -->
        <div class="rounded-xl border border-slate-200 bg-white p-5 space-y-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Status</h3>

            <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                <input type="checkbox" name="itau_enabled" value="1" <?= $isEnabled ? 'checked' : ''; ?> class="h-4 w-4 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500">
                Ativar integração Itaú
            </label>

            <label class="block">
                <span class="mb-1 block text-sm">Ambiente</span>
                <select name="itau_environment" class="w-full max-w-xs rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <option value="sandbox" <?= $environment === 'sandbox' ? 'selected' : ''; ?>>Sandbox (testes)</option>
                    <option value="production" <?= $environment === 'production' ? 'selected' : ''; ?>>Produção</option>
                </select>
            </label>
        </div>

        <!-- Credenciais API -->
        <div class="rounded-xl border border-slate-200 bg-white p-5 space-y-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Credenciais API (OAuth2)</h3>

            <div class="grid gap-3 md:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block text-sm">Client ID</span>
                    <input type="text" name="itau_client_id"
                           value="<?= e($settings['client_id'] ?? ''); ?>"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono">
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm">Client Secret</span>
                    <input type="password" name="itau_client_secret" value=""
                           placeholder="<?= !empty($settings['client_secret']) ? 'Deixe vazio para manter o atual' : 'Informe o client secret'; ?>"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>
            </div>
        </div>

        <!-- Dados Bancários -->
        <div class="rounded-xl border border-slate-200 bg-white p-5 space-y-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Dados Bancários</h3>

            <div class="grid gap-3 md:grid-cols-3">
                <label class="block">
                    <span class="mb-1 block text-sm">ID Beneficiário</span>
                    <input type="text" name="itau_id_beneficiario"
                           value="<?= e($settings['id_beneficiario'] ?? ''); ?>"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono">
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm">Agência</span>
                    <input type="text" name="itau_agencia"
                           value="<?= e($settings['agencia'] ?? ''); ?>"
                           placeholder="0001"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono">
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm">Carteira</span>
                    <input type="text" name="itau_carteira"
                           value="<?= e($settings['carteira'] ?? ''); ?>"
                           placeholder="109"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono">
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm">Conta</span>
                    <input type="text" name="itau_conta"
                           value="<?= e($settings['conta'] ?? ''); ?>"
                           placeholder="12345"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono">
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm">Dígito da Conta</span>
                    <input type="text" name="itau_conta_dv"
                           value="<?= e($settings['conta_dv'] ?? ''); ?>"
                           placeholder="6"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono">
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm">Chave PIX (opcional)</span>
                    <input type="text" name="itau_chave_pix"
                           value="<?= e($settings['chave_pix'] ?? ''); ?>"
                           placeholder="CNPJ, e-mail, telefone ou chave aleatória"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>
            </div>
        </div>

        <!-- Identificação do Beneficiário -->
        <div class="rounded-xl border border-slate-200 bg-white p-5 space-y-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Identificação do Beneficiário</h3>

            <div class="grid gap-3 md:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block text-sm">Nome do Beneficiário</span>
                    <input type="text" name="itau_beneficiary_name"
                           value="<?= e($settings['beneficiary_name'] ?? ''); ?>"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm">CNPJ do Beneficiário</span>
                    <input type="text" name="itau_beneficiary_cnpj"
                           value="<?= e($settings['beneficiary_cnpj'] ?? ''); ?>"
                           placeholder="00000000000000"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono">
                </label>
            </div>
        </div>

        <!-- Certificados SSL (mTLS) -->
        <div class="rounded-xl border border-slate-200 bg-white p-5 space-y-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Certificados SSL (mTLS)</h3>

            <div class="rounded-lg border border-amber-100 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                Os arquivos de certificado devem estar armazenados <strong>fora do diretório public_html</strong>, em um diretório protegido no servidor. Informe o caminho absoluto no servidor.
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block text-sm">Caminho do Certificado (.crt)</span>
                    <input type="text" name="itau_cert_path"
                           value="<?= e($settings['cert_path'] ?? ''); ?>"
                           placeholder="/home/usuário/certs/itau.crt"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono">
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm">Caminho da Chave Privada (.key)</span>
                    <input type="text" name="itau_key_path"
                           value="<?= e($settings['key_path'] ?? ''); ?>"
                           placeholder="/home/usuário/certs/itau.key"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono">
                </label>
            </div>
        </div>

        <!-- Webhook -->
        <div class="rounded-xl border border-slate-200 bg-white p-5 space-y-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Webhook de Pagamento</h3>

            <div class="grid gap-3 md:grid-cols-2">
                <label class="block md:col-span-2">
                    <span class="mb-1 block text-sm">URL do Webhook (somente leitura)</span>
                    <input type="text" value="<?= e($webhookUrl); ?>" readonly
                           class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-mono text-slate-600 cursor-default">
                    <p class="mt-1 text-xs text-slate-500">Informe esta URL ao registrar o webhook no portal do Itaú ou use o botão abaixo para registrar via API.</p>
                </label>
                <label class="block md:col-span-2">
                    <span class="mb-1 block text-sm">Token de Validação do Webhook</span>
                    <input type="text" name="itau_webhook_token"
                           value="<?= e($settings['webhook_token'] ?? ''); ?>"
                           placeholder="Gerado automaticamente ao salvar"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono">
                </label>
            </div>

            <button type="button" id="btn-register-webhook"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 disabled:opacity-50">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                </svg>
                Registrar Webhook no Itaú
            </button>
            <p id="webhook-result" class="hidden text-xs font-medium"></p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 space-y-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Parametros de Emissao</h3>

            <div class="grid gap-3 md:grid-cols-3">
                <label class="block">
                    <span class="mb-1 block text-sm">Etapa do processo</span>
                    <select name="itau_process_stage" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option value="efetivacao" <?= ($settings['process_stage'] ?? 'efetivacao') === 'efetivacao' ? 'selected' : ''; ?>>Efetivacao</option>
                        <option value="validacao" <?= ($settings['process_stage'] ?? '') === 'validacao' ? 'selected' : ''; ?>>Validação</option>
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm">Instrumento</span>
                    <select name="itau_instrument" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option value="boleto_pix" <?= ($settings['instrument'] ?? 'boleto_pix') === 'boleto_pix' ? 'selected' : ''; ?>>Boleto PIX / BoleCode</option>
                        <option value="boleto" <?= ($settings['instrument'] ?? '') === 'boleto' ? 'selected' : ''; ?>>Boleto</option>
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm">Codigo especie</span>
                    <input type="text" name="itau_codigo_especie"
                           value="<?= e($settings['codigo_especie'] ?? '01'); ?>"
                           placeholder="01"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono">
                </label>
            </div>

            <p class="text-xs text-slate-500">
                Para producao, o padrao esperado e efetivacao + Boleto PIX, igual ao fluxo atual do Perfex.
            </p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 space-y-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Instrucoes, Juros e Multa</h3>

            <div class="grid gap-3 md:grid-cols-2">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <label class="block <?= $i === 5 ? 'md:col-span-2' : ''; ?>">
                        <span class="mb-1 block text-sm">Instrucao <?= $i; ?></span>
                        <input type="text" name="itau_instrucao_<?= $i; ?>"
                               value="<?= e($settings['instrucao_' . $i] ?? ''); ?>"
                               class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    </label>
                <?php endfor; ?>
            </div>

            <div class="grid gap-3 md:grid-cols-4">
                <label class="block">
                    <span class="mb-1 block text-sm">Tipo de juros</span>
                    <input type="text" name="itau_codigo_tipo_juros"
                           value="<?= e($settings['codigo_tipo_juros'] ?? '05'); ?>"
                           placeholder="05"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono">
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm">Percentual juros</span>
                    <input type="text" name="itau_percentual_juros"
                           value="<?= e($settings['percentual_juros'] ?? ''); ?>"
                           placeholder="000000100000"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono">
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm">Valor juros</span>
                    <input type="text" name="itau_valor_juros"
                           value="<?= e($settings['valor_juros'] ?? ''); ?>"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono">
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm">Dias juros</span>
                    <input type="number" min="0" name="itau_dias_juros"
                           value="<?= e($settings['dias_juros'] ?? ''); ?>"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>
            </div>

            <div class="grid gap-3 md:grid-cols-4">
                <label class="block">
                    <span class="mb-1 block text-sm">Tipo de multa</span>
                    <input type="text" name="itau_codigo_tipo_multa"
                           value="<?= e($settings['codigo_tipo_multa'] ?? '03'); ?>"
                           placeholder="03"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono">
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm">Percentual multa</span>
                    <input type="text" name="itau_percentual_multa"
                           value="<?= e($settings['percentual_multa'] ?? ''); ?>"
                           placeholder="000000200000"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono">
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm">Valor multa</span>
                    <input type="text" name="itau_valor_multa"
                           value="<?= e($settings['valor_multa'] ?? ''); ?>"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono">
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm">Dias multa</span>
                    <input type="number" min="0" name="itau_dias_multa"
                           value="<?= e($settings['dias_multa'] ?? ''); ?>"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>
            </div>
        </div>

        <!-- Botões -->
        <div class="flex flex-wrap gap-2">
            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                Salvar Configurações
            </button>
            <a href="<?= route('banks'); ?>" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm hover:bg-slate-50">
                Voltar
            </a>
        </div>
    </form>
</section>

<script>
(function () {
    var btn = document.getElementById('btn-register-webhook');
    var result = document.getElementById('webhook-result');

    if (!btn || !result) { return; }

    btn.addEventListener('click', function () {
        btn.disabled = true;
        btn.textContent = 'Registrando...';
        result.className = 'hidden text-xs font-medium';

        fetch('<?= route('banks/itau/register-webhook'); ?>', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            result.classList.remove('hidden');
            if (data.ok) {
                result.classList.add('text-emerald-700');
                result.textContent = data.message || 'Webhook registrado com sucesso.';
            } else {
                result.classList.add('text-red-600');
                result.textContent = data.message || 'Falha ao registrar webhook.';
            }
        })
        .catch(function () {
            result.classList.remove('hidden');
            result.classList.add('text-red-600');
            result.textContent = 'Erro de comunicação com o servidor.';
        })
        .finally(function () {
            btn.disabled = false;
            btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" /><\/svg> Registrar Webhook no Itaú';
        });
    });
}());
</script>
