<div class="mx-auto w-full max-w-3xl rounded-2xl border border-slate-800 bg-slate-900/80 p-8 shadow-2xl shadow-cyan-900/20">
    <h1 class="text-2xl font-semibold">Selecionar empresa</h1>
    <p class="mt-1 text-sm text-slate-300">Escolha o CNPJ que deseja administrar nesta sessao.</p>

    <?php if ($msg = flash('error')): ?>
        <div class="mt-4 rounded-lg border border-rose-400/40 bg-rose-400/10 px-4 py-3 text-sm text-rose-200"><?= e($msg); ?></div>
    <?php endif; ?>

    <?php if ($msg = flash('success')): ?>
        <div class="mt-4 rounded-lg border border-emerald-400/40 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-200"><?= e($msg); ?></div>
    <?php endif; ?>

    <form method="post" action="<?= route('set-company'); ?>" class="mt-6 space-y-4">
        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">

        <div class="grid gap-3">
            <?php foreach ($companies as $index => $company): ?>
                <?php
                $companyId = (int) ($company['id'] ?? 0);
                $checked = $companyId === (int) $currentCompanyId || ((int) $currentCompanyId <= 0 && (int) $index === 0);
                $name = trim((string) ($company['trade_name'] ?? '')) !== '' ? (string) $company['trade_name'] : (string) ($company['legal_name'] ?? '');
                ?>
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-700 bg-slate-950/70 px-4 py-3 hover:border-cyan-500/60">
                    <input type="radio" name="company_id" value="<?= $companyId; ?>" <?= $checked ? 'checked' : ''; ?> class="mt-1 h-4 w-4 border-slate-600 bg-slate-900 text-cyan-500 focus:ring-cyan-400">
                    <span class="block">
                        <span class="block text-sm font-semibold text-slate-100"><?= e($name); ?></span>
                        <span class="block text-xs text-slate-400"><?= e((string) ($company['legal_name'] ?? '')); ?></span>
                        <span class="block text-xs text-slate-500">CNPJ: <?= e((string) ($company['cnpj'] ?? '-')); ?></span>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>

        <button type="submit" class="w-full rounded-lg bg-cyan-600 px-4 py-2 font-medium text-white hover:bg-cyan-500">Entrar na empresa selecionada</button>
    </form>

    <div class="mt-4 text-center">
        <a href="<?= route('logout'); ?>" class="text-xs text-rose-300 hover:text-rose-200">Sair</a>
    </div>
</div>
