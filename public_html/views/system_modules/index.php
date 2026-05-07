<?php
$modules = is_array($modules ?? null) ? $modules : [];
$stats = is_array($stats ?? null) ? $stats : [];
$logs = is_array($logs ?? null) ? $logs : [];
$migrations = is_array($migrations ?? null) ? $migrations : [];
$selectedModule = $selectedModule ?? null;
$zipAvailable = (bool) ($zipAvailable ?? false);
$modulesPath = (string) ($modulesPath ?? 'public_html/modules');
$packagesPath = (string) ($packagesPath ?? 'public_html/uploads/module_packages');
$coreVersion = (string) ($coreVersion ?? '1.0.0');

$decodeJson = function ($json): array {
    $decoded = json_decode((string) $json, true);
    return is_array($decoded) ? $decoded : [];
};

$statusLabel = function (string $status): string {
    return match ($status) {
        'active' => 'Ativo',
        'inactive' => 'Inativo',
        'error' => 'Com erro',
        default => ucfirst($status),
    };
};

$statusClass = function (string $status): string {
    return match ($status) {
        'active' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'inactive' => 'border-amber-200 bg-amber-50 text-amber-700',
        'error' => 'border-rose-200 bg-rose-50 text-rose-700',
        default => 'border-slate-200 bg-slate-50 text-slate-600',
    };
};

$logClass = function (string $level): string {
    return match ($level) {
        'error' => 'border-rose-200 bg-rose-50 text-rose-700',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-700',
        default => 'border-cyan-200 bg-cyan-50 text-cyan-700',
    };
};

$manifestExample = <<<'JSON'
{
  "key": "relatorio_avancado",
  "title": "Relatorio Avancado",
  "version": "1.0.0",
  "min_core_version": "1.0.0",
  "author": "ANEO",
  "description": "Modulo oficial instalado pelo painel administrativo.",
  "permissions": [
    {"key": "relatorio_avancado.view", "label": "Relatorio Avancado: acessar"}
  ],
  "menu": [
    {"label": "Relatorio Avancado", "route": "modules/relatorio_avancado", "icon": "chart-bar", "area": "main"}
  ],
  "migrations": [
    "migrations/001_create_tables.sql"
  ]
}
JSON;
?>
<section class="system-modules-page space-y-6">
    <div class="system-modules-hero-shell overflow-hidden rounded-[1.75rem] border border-cyan-200/50 bg-slate-950 shadow-xl">
        <div class="system-modules-hero grid gap-6 bg-[radial-gradient(circle_at_top_left,rgba(34,211,238,.28),transparent_36%),linear-gradient(135deg,#081629,#0e4f63)] p-6 text-white lg:grid-cols-[1.4fr_.9fr]">
            <div>
                <p class="system-modules-hero-eyebrow text-xs font-semibold uppercase tracking-[0.28em] text-cyan-200">Cadastro</p>
                <h2 class="system-modules-hero-title mt-2 text-3xl font-semibold">Modulos do Sistema</h2>
                <p class="system-modules-hero-copy mt-2 max-w-3xl text-sm leading-relaxed text-cyan-50/80">
                    Instale pacotes oficiais sem sobrescrever o core. Cada modulo fica isolado, com manifesto, logs,
                    migrations controladas e ativacao manual pelo administrador.
                </p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="system-modules-kpi rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur">
                    <p class="system-modules-kpi-label text-xs uppercase tracking-[0.18em] text-cyan-100">Core</p>
                    <p class="system-modules-kpi-value mt-2 text-2xl font-semibold"><?= e($coreVersion); ?></p>
                </div>
                <div class="system-modules-kpi rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur">
                    <p class="system-modules-kpi-label text-xs uppercase tracking-[0.18em] text-cyan-100">Instalados</p>
                    <p class="system-modules-kpi-value mt-2 text-2xl font-semibold"><?= (int) ($stats['total'] ?? 0); ?></p>
                </div>
                <div class="system-modules-kpi rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur">
                    <p class="system-modules-kpi-label text-xs uppercase tracking-[0.18em] text-cyan-100">Ativos</p>
                    <p class="system-modules-kpi-value mt-2 text-2xl font-semibold"><?= (int) ($stats['active'] ?? 0); ?></p>
                </div>
                <div class="system-modules-kpi rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur">
                    <p class="system-modules-kpi-label text-xs uppercase tracking-[0.18em] text-cyan-100">Com erro</p>
                    <p class="system-modules-kpi-value mt-2 text-2xl font-semibold"><?= (int) ($stats['error'] ?? 0); ?></p>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$zipAvailable): ?>
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            A extensao ZIP do PHP nao esta habilitada. O upload de instaladores depende dela.
        </div>
    <?php endif; ?>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_420px]">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-cyan-500">Instalador</p>
                    <h3 class="mt-1 text-lg font-semibold text-slate-800">Enviar novo pacote oficial</h3>
                    <p class="text-sm text-slate-500">O pacote fica inativo depois da instalacao. Voce ativa somente apos revisar.</p>
                </div>
                <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">ZIP + module.json</span>
            </div>

            <form method="post" action="<?= route('system-modules/upload'); ?>" enctype="multipart/form-data" class="grid gap-3 md:grid-cols-[1fr_auto]">
                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-slate-700">Pacote do modulo</span>
                    <input type="file" name="module_zip" accept=".zip,application/zip" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" <?= $zipAvailable ? '' : 'disabled'; ?>>
                </label>
                <div class="flex items-end">
                    <button class="w-full rounded-xl bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50" <?= $zipAvailable ? '' : 'disabled'; ?>>
                        Instalar pacote
                    </button>
                </div>
            </form>

            <div class="mt-4 grid gap-3 md:grid-cols-3">
                <div class="rounded-xl border border-cyan-100 bg-cyan-50/70 p-3 text-xs text-cyan-800">
                    <p class="font-semibold">1. Validacao</p>
                    <p class="mt-1">Confere ZIP, manifesto, chave unica e caminhos seguros.</p>
                </div>
                <div class="rounded-xl border border-amber-100 bg-amber-50/70 p-3 text-xs text-amber-800">
                    <p class="font-semibold">2. Isolamento</p>
                    <p class="mt-1">Extrai em <code><?= e($modulesPath); ?></code>, sem tocar nos arquivos do core.</p>
                </div>
                <div class="rounded-xl border border-emerald-100 bg-emerald-50/70 p-3 text-xs text-emerald-800">
                    <p class="font-semibold">3. Ativacao</p>
                    <p class="mt-1">Registra logs, migrations e deixa o modulo inativo por seguranca.</p>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-cyan-500">Estrutura esperada</p>
            <h3 class="mt-1 text-lg font-semibold text-slate-800">Contrato do pacote</h3>
            <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs leading-relaxed text-slate-600">
                <p class="font-semibold text-slate-700">Raiz do ZIP:</p>
                <p class="mt-1"><code>module.json</code>, <code>routes.php</code>, <code>controllers/</code>, <code>models/</code>, <code>views/</code>, <code>assets/</code>, <code>migrations/</code>.</p>
                <p class="mt-3">Modulos ativos podem declarar rotas em <code>routes.php</code>. As rotas precisam iniciar com <code>modules/chave_do_modulo</code>.</p>
            </div>
            <details class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
                <summary class="cursor-pointer font-semibold text-slate-700">Exemplo de module.json</summary>
                <pre class="mt-3 max-h-80 overflow-auto whitespace-pre-wrap rounded-lg bg-slate-950 p-3 text-[11px] leading-relaxed text-cyan-50"><?= e($manifestExample); ?></pre>
            </details>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-cyan-500">Inventario</p>
                    <h3 class="mt-1 text-lg font-semibold text-slate-800">Modulos instalados</h3>
                    <p class="text-sm text-slate-500">Use esta lista para revisar versao, estado, pacote, migrations e logs.</p>
                </div>
                <?php if ($selectedModule): ?>
                    <a href="<?= route('system-modules'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Limpar selecao</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Modulo</th>
                        <th class="px-4 py-3 text-left">Versao</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Instalado por</th>
                        <th class="px-4 py-3 text-left">Pacote</th>
                        <th class="px-4 py-3 text-right">Acoes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($modules as $module): ?>
                        <?php
                        $status = (string) ($module['status'] ?? 'inactive');
                        $moduleId = (int) ($module['id'] ?? 0);
                        ?>
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-4 py-3">
                                <a href="<?= route('system-modules&id=' . $moduleId); ?>" class="font-semibold text-slate-800 hover:text-cyan-600"><?= e((string) ($module['title'] ?? 'Modulo')); ?></a>
                                <p class="mt-0.5 text-xs text-slate-500"><?= e((string) ($module['module_key'] ?? '')); ?></p>
                            </td>
                            <td class="px-4 py-3 text-slate-600"><?= e((string) ($module['version'] ?? '-')); ?></td>
                            <td class="px-4 py-3">
                                <span class="rounded-full border px-2.5 py-1 text-xs font-semibold <?= e($statusClass($status)); ?>"><?= e($statusLabel($status)); ?></span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                <p><?= e((string) ($module['installed_by_name'] ?? 'Sistema')); ?></p>
                                <p class="text-xs text-slate-400"><?= e((string) ($module['installed_at'] ?? '')); ?></p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="max-w-[220px] truncate text-slate-600"><?= e((string) ($module['package_filename'] ?? '-')); ?></p>
                                <p class="font-mono text-[11px] text-slate-400"><?= e(substr((string) ($module['package_hash'] ?? ''), 0, 16)); ?>...</p>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a href="<?= route('system-modules&id=' . $moduleId); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Detalhes</a>
                                    <?php if ($status === 'active'): ?>
                                        <form method="post" action="<?= route('system-modules/deactivate'); ?>" onsubmit="return confirm('Desativar este modulo?');">
                                            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                            <input type="hidden" name="module_id" value="<?= $moduleId; ?>">
                                            <button class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 hover:bg-amber-100">Desativar</button>
                                        </form>
                                    <?php elseif ($status !== 'error'): ?>
                                        <form method="post" action="<?= route('system-modules/activate'); ?>" onsubmit="return confirm('Ativar este modulo?');">
                                            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                            <input type="hidden" name="module_id" value="<?= $moduleId; ?>">
                                            <button class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">Ativar</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($modules === []): ?>
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">
                                Nenhum modulo instalado ainda. Envie o primeiro pacote oficial para iniciar o inventario.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($selectedModule): ?>
        <?php
        $manifest = $decodeJson($selectedModule['manifest_json'] ?? '');
        $permissions = $decodeJson($selectedModule['permissions_json'] ?? '');
        $menu = $decodeJson($selectedModule['menu_json'] ?? '');
        ?>
        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_420px]">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-cyan-500">Detalhes</p>
                        <h3 class="mt-1 text-lg font-semibold text-slate-800"><?= e((string) $selectedModule['title']); ?></h3>
                        <p class="text-sm text-slate-500"><?= e((string) ($selectedModule['description'] ?? 'Sem descricao.')); ?></p>
                    </div>
                    <span class="rounded-full border px-3 py-1 text-xs font-semibold <?= e($statusClass((string) $selectedModule['status'])); ?>">
                        <?= e($statusLabel((string) $selectedModule['status'])); ?>
                    </span>
                </div>

                <?php if (!empty($selectedModule['last_error'])): ?>
                    <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        <?= e((string) $selectedModule['last_error']); ?>
                    </div>
                <?php endif; ?>

                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Chave</p>
                        <p class="mt-1 font-mono text-sm text-slate-700"><?= e((string) $selectedModule['module_key']); ?></p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Autor</p>
                        <p class="mt-1 text-sm font-semibold text-slate-700"><?= e((string) ($selectedModule['author'] ?? '-')); ?></p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Pasta</p>
                        <p class="mt-1 font-mono text-sm text-slate-700"><?= e((string) ($selectedModule['install_path'] ?? '-')); ?></p>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 lg:grid-cols-2">
                    <div>
                        <h4 class="text-sm font-semibold text-slate-800">Permissoes declaradas</h4>
                        <div class="mt-2 space-y-2">
                            <?php foreach ($permissions as $permission): ?>
                                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                                    <p class="font-semibold text-slate-700"><?= e((string) ($permission['label'] ?? $permission['key'] ?? '')); ?></p>
                                    <p class="font-mono text-xs text-slate-500"><?= e((string) ($permission['key'] ?? '')); ?></p>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($permissions === []): ?>
                                <p class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">Nenhuma permissao declarada.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <h4 class="text-sm font-semibold text-slate-800">Menu declarado</h4>
                        <div class="mt-2 space-y-2">
                            <?php foreach ($menu as $item): ?>
                                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                                    <p class="font-semibold text-slate-700"><?= e((string) ($item['label'] ?? '')); ?></p>
                                    <p class="font-mono text-xs text-slate-500"><?= e((string) ($item['route'] ?? '')); ?></p>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($menu === []): ?>
                                <p class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">Nenhum item de menu declarado.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-800">Manifesto completo</h3>
                <pre class="mt-3 max-h-[520px] overflow-auto whitespace-pre-wrap rounded-xl bg-slate-950 p-4 text-xs leading-relaxed text-cyan-50"><?= e(json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}'); ?></pre>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid gap-5 xl:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 p-5">
                <h3 class="text-lg font-semibold text-slate-800">Migrations</h3>
                <p class="text-sm text-slate-500">Historico de SQL executado pelos pacotes instalados.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Arquivo</th>
                            <th class="px-4 py-3 text-left">Modulo</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">Data</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($migrations as $migration): ?>
                            <?php $migrationStatus = (string) ($migration['status'] ?? 'executed'); ?>
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-4 py-3">
                                    <p class="font-mono text-xs text-slate-700"><?= e((string) ($migration['migration_file'] ?? '')); ?></p>
                                    <?php if (!empty($migration['error_message'])): ?>
                                        <p class="mt-1 text-xs text-rose-600"><?= e((string) $migration['error_message']); ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-slate-600"><?= e((string) ($migration['module_key'] ?? '')); ?></td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full border px-2.5 py-1 text-xs font-semibold <?= e($migrationStatus === 'executed' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700'); ?>">
                                        <?= e($migrationStatus === 'executed' ? 'Executada' : 'Falhou'); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-500"><?= e((string) ($migration['executed_at'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($migrations === []): ?>
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">Nenhuma migration registrada.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 p-5">
                <h3 class="text-lg font-semibold text-slate-800">Logs de instalacao</h3>
                <p class="text-sm text-slate-500">Auditoria simples das acoes realizadas no gerenciador.</p>
            </div>
            <div class="divide-y divide-slate-100">
                <?php foreach ($logs as $log): ?>
                    <div class="p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full border px-2.5 py-1 text-xs font-semibold <?= e($logClass((string) ($log['level'] ?? 'info'))); ?>">
                                    <?= e(strtoupper((string) ($log['level'] ?? 'info'))); ?>
                                </span>
                                <p class="text-sm font-semibold text-slate-800"><?= e((string) ($log['action'] ?? '')); ?></p>
                            </div>
                            <p class="text-xs text-slate-400"><?= e((string) ($log['created_at'] ?? '')); ?></p>
                        </div>
                        <p class="mt-2 text-sm text-slate-600"><?= e((string) ($log['message'] ?? '')); ?></p>
                        <p class="mt-1 text-xs text-slate-400">
                            <?= e((string) ($log['module_key'] ?? 'geral')); ?> - <?= e((string) ($log['user_name'] ?? 'Sistema')); ?>
                        </p>
                    </div>
                <?php endforeach; ?>
                <?php if ($logs === []): ?>
                    <div class="p-8 text-center text-sm text-slate-500">Nenhum log registrado.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
