<div class="max-w-2xl">
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-6 shadow-sm">
        <div class="mb-4 flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-600 text-white">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </div>
            <div>
                <p class="font-semibold text-emerald-800">Token criado com sucesso!</p>
                <p class="text-sm text-emerald-700">Token: <strong><?= e($tokenName); ?></strong> (ID <?= (int) $tokenId; ?>)</p>
            </div>
        </div>

        <div class="rounded-lg border border-amber-300 bg-amber-50 p-4">
            <p class="mb-2 text-xs font-bold uppercase tracking-wider text-amber-700">Atenção — copie agora, este valor não será exibido novamente</p>
            <div class="flex items-center gap-2">
                <code id="raw-token" class="flex-1 break-all rounded bg-white px-3 py-2 font-mono text-sm text-slate-800 border border-amber-200 select-all">
                    <?= e($rawToken); ?>
                </code>
                <button onclick="copyToken()" class="shrink-0 rounded-lg border border-amber-300 bg-amber-100 px-4 py-2 text-sm font-medium text-amber-800 hover:bg-amber-200" title="Copiar token">
                    Copiar
                </button>
            </div>
        </div>
    </div>

    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="mb-3 text-sm font-semibold text-slate-700">Como usar</h3>
        <p class="mb-2 text-sm text-slate-600">Informe o token no header <code class="rounded bg-slate-100 px-1 font-mono text-xs">Authorization</code> de cada requisição:</p>
        <pre class="overflow-x-auto rounded-lg bg-slate-900 p-4 text-sm text-emerald-300"><code>curl -H "Authorization: Bearer <?= e($rawToken); ?>" \
     "https://erp-hml.aneobrasil.com.br/api.php?r=students"</code></pre>
        <p class="mt-3 text-xs text-slate-400">Documentação completa em <a href="<?= route('api-management/manual'); ?>" class="text-cyan-600 hover:underline">Manual da API</a>.</p>
    </div>

    <div class="mt-4 flex gap-3">
        <a href="<?= route('api-management'); ?>" class="rounded-lg bg-cyan-600 px-5 py-2 text-sm font-semibold text-white hover:bg-cyan-700">
            Voltar à lista
        </a>
        <a href="<?= route('api-management/create'); ?>" class="rounded-lg border border-slate-300 px-5 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
            Criar outro token
        </a>
    </div>
</div>

<script>
function copyToken() {
    const el = document.getElementById('raw-token');
    navigator.clipboard.writeText(el.textContent.trim()).then(function() {
        alert('Token copiado para a área de transferência!');
    });
}
</script>
