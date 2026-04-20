<?php
/** @var array $creds  zoom_account_id, zoom_client_id, zoom_client_secret */
?>
<div class="space-y-6 max-w-2xl">

    <div>
        <h2 class="text-2xl font-semibold text-slate-800">Integração Zoom</h2>
        <p class="text-sm text-slate-500">Configure as credenciais do app Zoom desta empresa para criar reuniões automaticamente.</p>
    </div>

    <!-- Instruções -->
    <div class="rounded-xl border border-sky-200 bg-sky-50 p-5 text-sm text-sky-800 space-y-2">
        <p class="font-semibold">Como obter as credenciais:</p>
        <ol class="list-decimal list-inside space-y-1 text-sky-700">
            <li>Acesse <strong>marketplace.zoom.us</strong> e faça login com a conta Zoom da empresa.</li>
            <li>Clique em <strong>Build App</strong> → <strong>Server-to-Server OAuth</strong>.</li>
            <li>Dê um nome ao app (ex: "ANEO ERP") e clique em <strong>Create</strong>.</li>
            <li>Em <strong>Scopes</strong>, adicione: <code>meeting:write:admin</code>.</li>
            <li>Anote o <strong>Account ID</strong>, <strong>Client ID</strong> e <strong>Client Secret</strong> e cole abaixo.</li>
            <li>Ative o app (botão "Activate your app").</li>
        </ol>
    </div>

    <form method="POST" action="index.php?route=courses/live-sessions/zoom-credentials" class="space-y-5">
        <input type="hidden" name="route" value="courses/live-sessions/zoom-credentials">
        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">

        <label class="block">
            <span class="mb-1 block text-sm font-medium text-slate-700">Account ID</span>
            <input type="text" name="zoom_account_id"
                   value="<?= e($creds['zoom_account_id'] ?? ''); ?>"
                   placeholder="Ex: AbCdEfGhIjKl1234"
                   autocomplete="off"
                   class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-100">
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium text-slate-700">Client ID</span>
            <input type="text" name="zoom_client_id"
                   value="<?= e($creds['zoom_client_id'] ?? ''); ?>"
                   placeholder="Ex: Ab1Cd2Ef3Gh4Ij5Kl6"
                   autocomplete="off"
                   class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-100">
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium text-slate-700">Client Secret</span>
            <div class="relative">
                <input type="password" name="zoom_client_secret" id="zoom_secret_field"
                       value="<?= e($creds['zoom_client_secret'] ?? ''); ?>"
                       placeholder="••••••••••••••••••••"
                       autocomplete="new-password"
                       class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-100 pr-20">
                <button type="button" onclick="toggleSecret()"
                        class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-slate-400 hover:text-slate-600">
                    Mostrar
                </button>
            </div>
        </label>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit"
                    class="rounded-lg bg-sky-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-sky-700 transition">
                Salvar Credenciais
            </button>
            <a href="<?= route('courses/live-sessions'); ?>"
               class="text-sm text-slate-500 hover:text-slate-700">← Voltar</a>
        </div>
    </form>

</div>

<script>
function toggleSecret() {
    const f   = document.getElementById('zoom_secret_field');
    const btn = f.nextElementSibling;
    if (f.type === 'password') {
        f.type    = 'text';
        btn.textContent = 'Ocultar';
    } else {
        f.type    = 'password';
        btn.textContent = 'Mostrar';
    }
}
</script>
