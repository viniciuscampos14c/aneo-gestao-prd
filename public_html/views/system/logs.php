<?php
$filters = is_array($filters ?? null) ? $filters : [];
$rows = is_array($rows ?? null) ? $rows : [];
$meta = is_array($meta ?? null) ? $meta : ['total' => 0, 'page' => 1, 'pages' => 1, 'per_page' => 50];
$modules = is_array($modules ?? null) ? $modules : [];
$roles = is_array($roles ?? null) ? $roles : [];
$companyOptions = is_array($companyOptions ?? null) ? $companyOptions : [];
$logsAvailable = !empty($logsAvailable);
$rowsOnPage = count($rows);

$pageActionCounters = [
    'create' => 0,
    'update' => 0,
    'delete' => 0,
    'settle' => 0,
];
foreach ($rows as $auditRow) {
    $actionKey = strtolower(trim((string) ($auditRow['action'] ?? '')));
    if (array_key_exists($actionKey, $pageActionCounters)) {
        $pageActionCounters[$actionKey]++;
        continue;
    }

    if (in_array($actionKey, ['paid', 'payment', 'close', 'closed'], true)) {
        $pageActionCounters['settle']++;
    }
}

$prettyJson = static function (?string $raw): string {
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '{}';
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return '{}';
    }

    $json = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    return $json !== false ? $json : '{}';
};

$companyName = static function (array $row): string {
    $trade = trim((string) ($row['company_trade_name'] ?? ''));
    if ($trade !== '') {
        return $trade;
    }

    $legal = trim((string) ($row['company_legal_name'] ?? ''));
    return $legal !== '' ? $legal : '-';
};

$actionBadgeClass = static function (?string $action): string {
    $key = strtolower(trim((string) $action));

    return match ($key) {
        'create' => 'logs-action-create',
        'update' => 'logs-action-update',
        'delete' => 'logs-action-delete',
        'settle', 'paid', 'payment', 'close', 'closed' => 'logs-action-settle',
        default => 'logs-action-default',
    };
};

$roleBadgeClass = static function (?string $role): string {
    $key = strtolower(trim((string) $role));

    return match ($key) {
        'admin' => 'logs-role-admin',
        'teacher' => 'logs-role-teacher',
        'support' => 'logs-role-support',
        default => 'logs-role-default',
    };
};
?>
<section class="space-y-6">
    <div>
        <h2 class="text-2xl font-semibold">Logs de Sistema</h2>
        <p class="text-sm text-slate-500">Auditoria das alteracoes realizadas por perfis administrador, professor e suporte.</p>
    </div>

    <?php if (!$logsAvailable): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Tabela de auditoria nao encontrada. Execute a migration <code>migrations/20260315_system_audit_logs.sql</code>.
        </div>
    <?php endif; ?>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <article class="system-logs-kpi system-logs-kpi-total rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">Registros (pagina)</p>
            <p class="mt-2 text-2xl font-semibold"><?= (int) $rowsOnPage; ?></p>
        </article>
        <article class="system-logs-kpi system-logs-kpi-create rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">Create</p>
            <p class="mt-2 text-2xl font-semibold"><?= (int) $pageActionCounters['create']; ?></p>
        </article>
        <article class="system-logs-kpi system-logs-kpi-update rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">Update</p>
            <p class="mt-2 text-2xl font-semibold"><?= (int) $pageActionCounters['update']; ?></p>
        </article>
        <article class="system-logs-kpi system-logs-kpi-delete rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">Delete</p>
            <p class="mt-2 text-2xl font-semibold"><?= (int) $pageActionCounters['delete']; ?></p>
        </article>
        <article class="system-logs-kpi system-logs-kpi-settle rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">Settles/Pagamentos</p>
            <p class="mt-2 text-2xl font-semibold"><?= (int) $pageActionCounters['settle']; ?></p>
        </article>
    </div>

    <form method="get" action="index.php" class="system-logs-filters grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-4">
        <input type="hidden" name="route" value="system/logs">

        <input type="text" name="q" value="<?= e((string) ($filters['q'] ?? '')); ?>" placeholder="Buscar por usuario, modulo, acao..." class="rounded-lg border border-slate-200 px-3 py-2 text-sm">

        <select name="module" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option value="">Todos os modulos</option>
            <?php foreach ($modules as $module): ?>
                <option value="<?= e((string) $module); ?>" <?= (string) ($filters['module'] ?? '') === (string) $module ? 'selected' : ''; ?>><?= e((string) $module); ?></option>
            <?php endforeach; ?>
        </select>

        <select name="user_role" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option value="">Todos os perfis</option>
            <?php foreach ($roles as $roleKey => $roleLabel): ?>
                <option value="<?= e((string) $roleKey); ?>" <?= (string) ($filters['user_role'] ?? '') === (string) $roleKey ? 'selected' : ''; ?>><?= e((string) $roleLabel); ?></option>
            <?php endforeach; ?>
        </select>

        <select name="company_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option value="0">Todas as empresas</option>
            <?php foreach ($companyOptions as $company): ?>
                <?php
                $cId = (int) ($company['id'] ?? 0);
                $cName = trim((string) ($company['trade_name'] ?? '')) !== ''
                    ? (string) ($company['trade_name'] ?? '')
                    : (string) ($company['legal_name'] ?? '');
                ?>
                <option value="<?= $cId; ?>" <?= (int) ($filters['company_id'] ?? 0) === $cId ? 'selected' : ''; ?>><?= e($cName); ?></option>
            <?php endforeach; ?>
        </select>

        <input type="date" name="start_date" value="<?= e((string) ($filters['start_date'] ?? '')); ?>" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <input type="date" name="end_date" value="<?= e((string) ($filters['end_date'] ?? '')); ?>" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">

        <select name="per_page" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <?php foreach ($paginationOptions as $opt): ?>
                <option value="<?= (int) $opt; ?>" <?= (int) ($meta['per_page'] ?? 50) === (int) $opt ? 'selected' : ''; ?>><?= (int) $opt; ?>/pagina</option>
            <?php endforeach; ?>
        </select>

        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrar</button>
    </form>

    <div class="system-logs-table-wrap overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-3">Data/Hora</th>
                    <th class="px-3 py-3">Usuario</th>
                    <th class="px-3 py-3">Perfil</th>
                    <th class="px-3 py-3">Empresa</th>
                    <th class="px-3 py-3">Modulo</th>
                    <th class="px-3 py-3">Acao</th>
                    <th class="px-3 py-3">Registro</th>
                    <th class="px-3 py-3">Alteracoes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr class="system-logs-row border-b border-slate-100 align-top hover:bg-slate-50">
                        <td class="px-3 py-3 whitespace-nowrap"><?= e((string) ($row['created_at'] ?? '')); ?></td>
                        <td class="px-3 py-3">
                            <p class="font-medium"><?= e((string) ($row['user_name'] ?? '-')); ?></p>
                            <p class="text-xs text-slate-500"><?= e((string) ($row['user_email'] ?? '')); ?></p>
                        </td>
                        <td class="px-3 py-3">
                            <span class="logs-role-badge <?= $roleBadgeClass((string) ($row['user_role'] ?? '')); ?>">
                                <?= e((string) ($roles[(string) ($row['user_role'] ?? '')] ?? ucfirst((string) ($row['user_role'] ?? '-')))); ?>
                            </span>
                        </td>
                        <td class="px-3 py-3"><?= e($companyName($row)); ?></td>
                        <td class="px-3 py-3">
                            <span class="logs-module-tag"><?= e((string) ($row['module'] ?? '-')); ?></span>
                        </td>
                        <td class="px-3 py-3">
                            <span class="logs-action-badge <?= $actionBadgeClass((string) ($row['action'] ?? '')); ?>"><?= e((string) ($row['action'] ?? '-')); ?></span>
                        </td>
                        <td class="px-3 py-3">
                            <p class="font-medium"><?= e((string) ($row['entity_type'] ?? '-')); ?> #<?= (int) ($row['entity_id'] ?? 0); ?></p>
                            <p class="text-xs text-slate-500"><?= e((string) ($row['entity_label'] ?? '')); ?></p>
                            <?php if (trim((string) ($row['description'] ?? '')) !== ''): ?>
                                <p class="mt-1 text-xs text-slate-500"><?= e((string) ($row['description'] ?? '')); ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-3">
                            <details>
                                <summary class="logs-details-trigger cursor-pointer text-xs font-semibold text-cyan-700">Ver detalhes</summary>
                                <pre class="logs-json mt-2 max-w-[520px] whitespace-pre-wrap rounded border border-slate-200 bg-slate-50 p-2 text-[11px] leading-relaxed text-slate-700"><?= e($prettyJson((string) ($row['changes_json'] ?? '{}'))); ?></pre>
                            </details>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?>
                    <tr>
                        <td colspan="8" class="px-3 py-6 text-center text-slate-500">Nenhum log encontrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
        <p>Total: <?= (int) ($meta['total'] ?? 0); ?> registros | Pagina <?= (int) ($meta['page'] ?? 1); ?>/<?= (int) ($meta['pages'] ?? 1); ?></p>
        <div class="flex gap-2">
            <?php for ($p = 1; $p <= (int) ($meta['pages'] ?? 1); $p++): ?>
                <a href="index.php?<?= build_query(['route' => 'system/logs', 'page' => $p]); ?>" class="rounded px-3 py-1 <?= $p === (int) ($meta['page'] ?? 1) ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white hover:bg-slate-50'; ?>">
                    <?= $p; ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>
</section>
