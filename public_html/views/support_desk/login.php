<section class="grid w-full gap-8 lg:grid-cols-[1.1fr_0.9fr]">
    <div class="rounded-3xl border border-blue-700/60 bg-gradient-to-br from-blue-900/70 to-cyan-900/30 p-8 shadow-2xl shadow-blue-950/40">
        <img src="assets/brand/aneo-wordmark-transparente-branco.svg?v=20260512-brand-kit-v1" alt="Logo ANEO" class="h-12 w-auto">
        <p class="text-xs uppercase tracking-[0.25em] text-cyan-300">ANEO</p>
        <h1 class="mt-3 text-3xl font-semibold leading-tight">Central Tecnica de Chamados</h1>
        <p class="mt-3 text-sm text-blue-100/90">
            Portal isolado para a equipe tecnica atender, comentar e atualizar os chamados abertos pelo administrativo usando usuarios cadastrados no modulo Usuarios.
        </p>
        <div class="mt-6 rounded-xl border border-blue-700/60 bg-blue-950/70 p-4 text-sm text-blue-100">
            URL de acesso: <span class="font-semibold text-cyan-300">support.php</span>
        </div>
    </div>

    <div class="rounded-3xl border border-blue-700/60 bg-blue-950/70 p-8 shadow-2xl shadow-blue-950/30">
        <img src="assets/brand/aneo-wordmark-transparente-branco.svg?v=20260512-brand-kit-v1" alt="Logo ANEO" class="mb-5 h-8 w-auto">
        <h2 class="text-xl font-semibold">Entrar na central</h2>
        <p class="mt-1 text-sm text-blue-200/80">Acesso exclusivo da equipe tecnica.</p>

        <?php if (!$enabled): ?>
            <div class="mt-4 rounded-lg border border-amber-400/50 bg-amber-500/20 px-4 py-3 text-sm text-amber-100">
                Portal tecnico desativado no `config.php` (`support_desk.enabled = false`).
            </div>
        <?php endif; ?>

        <form action="support.php?route=support/login" method="post" class="mt-6 space-y-4">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
            <label class="block">
                <span class="mb-1 block text-sm text-blue-100">Usuario ou Email</span>
                <input
                    type="text"
                    name="username"
                    required
                    autocomplete="username"
                    class="w-full rounded-xl border border-blue-700 bg-blue-900/50 px-4 py-2 text-sm text-blue-50 outline-none placeholder-blue-300/80 focus:border-cyan-400 focus:bg-blue-900/70"
                >
            </label>
            <label class="block">
                <span class="mb-1 block text-sm text-blue-100">Senha</span>
                <input
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    class="w-full rounded-xl border border-blue-700 bg-blue-900/50 px-4 py-2 text-sm text-blue-50 outline-none placeholder-blue-300/80 focus:border-cyan-400 focus:bg-blue-900/70"
                >
            </label>

            <button class="w-full rounded-xl bg-cyan-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-cyan-950/30 hover:bg-cyan-400">
                Acessar central tecnica
            </button>
        </form>
    </div>
</section>
