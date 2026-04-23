<?php
$banks = is_array($banks ?? null) ? $banks : [];
?>
<section class="banks-shell space-y-6">
    <div>
        <h2 class="text-2xl font-semibold">Bancos</h2>
        <p class="text-sm text-slate-500">Gerencie as integracoes bancarias para emissao de boletos.</p>
    </div>

    <?php if ($banks === []): ?>
        <div class="banks-empty rounded-xl border border-slate-200 bg-white px-4 py-6 text-sm text-slate-600">
            Nenhum banco disponivel para configurar.
        </div>
    <?php else: ?>
        <div class="banks-grid grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($banks as $bank): ?>
                <?php
                $isEnabled = !empty($bank['enabled']);
                $environment = (string) ($bank['environment'] ?? 'sandbox');
                $envLabel = $environment === 'production' ? 'Producao' : 'Sandbox';
                $envColor = $environment === 'production'
                    ? 'text-emerald-700 bg-emerald-50 border-emerald-200'
                    : 'text-amber-700 bg-amber-50 border-amber-200';
                $envToneClass = $environment === 'production' ? 'banks-env-production' : 'banks-env-sandbox';
                $statusToneClass = $isEnabled ? 'banks-status-active' : 'banks-status-inactive';
                ?>
                <div class="banks-card flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="banks-icon flex h-10 w-10 items-center justify-center rounded-lg bg-orange-100 text-orange-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z" />
                                </svg>
                            </div>
                            <div>
                                <p class="font-semibold text-slate-800"><?= e((string) ($bank['label'] ?? '')); ?></p>
                                <span class="banks-env-badge inline-flex items-center rounded border px-1.5 py-0.5 text-xs font-medium <?= $envColor; ?> <?= $envToneClass; ?>">
                                    <?= e($envLabel); ?>
                                </span>
                            </div>
                        </div>
                        <span class="banks-status-pill inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium <?= $isEnabled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'; ?> <?= $statusToneClass; ?>">
                            <span class="h-1.5 w-1.5 rounded-full <?= $isEnabled ? 'bg-emerald-500' : 'bg-slate-400'; ?>"></span>
                            <?= $isEnabled ? 'Ativo' : 'Inativo'; ?>
                        </span>
                    </div>

                    <a
                        href="<?= route((string) ($bank['route'] ?? 'banks')); ?>"
                        class="banks-config-btn inline-flex items-center justify-center rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
                    >
                        Configurar
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
