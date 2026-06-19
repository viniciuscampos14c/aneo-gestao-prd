<?php
$totalUsers = (int) ($meta['total'] ?? 0);
$activeUsers = 0;
$inactiveUsers = 0;
$roleCounts = [];

foreach ($rows as $row) {
    $isActive = (int) ($row['is_active'] ?? 0) === 1;
    if ($isActive) {
        $activeUsers++;
    } else {
        $inactiveUsers++;
    }

    $roleKey = (string) ($row['role'] ?? '');
    if ($roleKey !== '') {
        $roleCounts[$roleKey] = (int) ($roleCounts[$roleKey] ?? 0) + 1;
    }
}

$preferredRoleCards = ['admin', 'support', 'professor'];
$roleCards = [];
foreach ($preferredRoleCards as $roleKey) {
    if (isset($roles[$roleKey])) {
        $roleCards[] = [
            'label' => (string) $roles[$roleKey],
            'count' => (int) ($roleCounts[$roleKey] ?? 0),
        ];
    }
}

if ($roleCards === []) {
    foreach ($roleCounts as $roleKey => $count) {
        $roleCards[] = [
            'label' => role_label((string) $roleKey),
            'count' => (int) $count,
        ];
        if (count($roleCards) >= 2) {
            break;
        }
    }
}

$currentStatus = (string) ($filters['is_active'] ?? '');
$currentRole = (string) ($filters['role'] ?? '');
$currentQuery = (string) ($filters['q'] ?? '');
$currentPerPage = (int) ($meta['per_page'] ?? config('app.default_pagination', 50));
$initialsFromName = static function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $initials = '';

    foreach ($parts as $part) {
        $part = trim((string) $part);
        if ($part === '') {
            continue;
        }
        $initials .= strtoupper(substr($part, 0, 1));
        if (strlen($initials) >= 2) {
            break;
        }
    }

    return $initials !== '' ? $initials : 'US';
};
?>

<section class="users-preview-shell">
    <div class="users-preview-content space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-[0.24em] text-cyan-300">Area administrativa</p>
                <h2 class="users-preview-title mt-2 text-4xl font-semibold">Gerenciamento de Usuários</h2>
                <p class="users-preview-subtitle mt-2 text-sm">Fase inicial da nova interface visual no módulo de usuários.</p>
            </div>
            <a href="<?= route('users/create'); ?>" class="users-preview-btn-primary rounded-xl px-4 py-2 text-sm font-semibold">
                + Novo Usuário
            </a>
        </div>

        <div class="grid gap-3 lg:grid-cols-5">
            <article class="users-preview-kpi rounded-2xl p-4">
                <div class="mb-3 flex items-center justify-between">
                    <span class="users-preview-kpi-label text-xs uppercase tracking-[0.16em]">Usuários</span>
                    <span class="users-preview-kpi-pill rounded-full px-2 py-0.5 text-[11px] font-semibold">Total</span>
                </div>
                <p class="users-preview-kpi-value text-3xl font-semibold"><?= (int) $totalUsers; ?></p>
                <p class="users-preview-subtitle text-sm">No filtro atual</p>
            </article>

            <article class="users-preview-kpi rounded-2xl p-4">
                <div class="mb-3 flex items-center justify-between">
                    <span class="users-preview-kpi-label text-xs uppercase tracking-[0.16em]">Status</span>
                    <span class="users-preview-kpi-pill rounded-full px-2 py-0.5 text-[11px] font-semibold">Ativos</span>
                </div>
                <p class="users-preview-kpi-value text-3xl font-semibold"><?= (int) $activeUsers; ?></p>
                <p class="users-preview-subtitle text-sm">Nesta página</p>
            </article>

            <article class="users-preview-kpi rounded-2xl p-4">
                <div class="mb-3 flex items-center justify-between">
                    <span class="users-preview-kpi-label text-xs uppercase tracking-[0.16em]">Status</span>
                    <span class="users-preview-kpi-pill rounded-full px-2 py-0.5 text-[11px] font-semibold">Inativos</span>
                </div>
                <p class="users-preview-kpi-value text-3xl font-semibold"><?= (int) $inactiveUsers; ?></p>
                <p class="users-preview-subtitle text-sm">Nesta página</p>
            </article>

            <?php foreach ($roleCards as $card): ?>
                <article class="users-preview-kpi rounded-2xl p-4">
                    <div class="mb-3 flex items-center justify-between">
                        <span class="users-preview-kpi-label text-xs uppercase tracking-[0.16em]">Perfil</span>
                        <span class="users-preview-kpi-pill rounded-full px-2 py-0.5 text-[11px] font-semibold"><?= e($card['label']); ?></span>
                    </div>
                    <p class="users-preview-kpi-value text-3xl font-semibold"><?= (int) $card['count']; ?></p>
                    <p class="users-preview-subtitle text-sm">Nesta página</p>
                </article>
            <?php endforeach; ?>
        </div>

        <form id="users-preview-filter-form" method="get" action="index.php" class="users-preview-panel grid gap-3 p-4 md:grid-cols-12">
            <input type="hidden" name="route" value="users">
            <input type="hidden" name="is_active" id="users-preview-is-active" value="<?= e($currentStatus); ?>">

            <label class="md:col-span-4">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-cyan-300">Busca</span>
                <input
                    type="text"
                    name="q"
                    value="<?= e($currentQuery); ?>"
                    placeholder="Buscar por nome, email ou usuário..."
                    class="users-preview-filter-input w-full rounded-xl px-3 py-2 text-sm outline-none focus:border-cyan-400"
                >
            </label>

            <label class="md:col-span-3">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-cyan-300">Perfil</span>
                <select name="role" class="users-preview-filter-select w-full rounded-xl px-3 py-2 text-sm outline-none focus:border-cyan-400">
                    <option value="">Todos os perfis</option>
                    <?php foreach ($roles as $roleKey => $roleLabel): ?>
                        <option value="<?= e($roleKey); ?>" <?= $currentRole === (string) $roleKey ? 'selected' : ''; ?>><?= e($roleLabel); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="md:col-span-2">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-cyan-300">Página</span>
                <select name="per_page" class="users-preview-filter-select w-full rounded-xl px-3 py-2 text-sm outline-none focus:border-cyan-400">
                    <?php foreach ($paginationOptions as $opt): ?>
                        <option value="<?= (int) $opt; ?>" <?= $currentPerPage === (int) $opt ? 'selected' : ''; ?>><?= (int) $opt; ?>/página</option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div class="md:col-span-3">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-cyan-300">Status</span>
                <div class="grid grid-cols-3 gap-2">
                    <button type="button" data-status-filter="" class="users-preview-seg-btn <?= $currentStatus === '' ? 'active' : ''; ?> rounded-xl px-2 py-2 text-xs font-semibold">Todos</button>
                    <button type="button" data-status-filter="1" class="users-preview-seg-btn <?= $currentStatus === '1' ? 'active' : ''; ?> rounded-xl px-2 py-2 text-xs font-semibold">Ativos</button>
                    <button type="button" data-status-filter="0" class="users-preview-seg-btn <?= $currentStatus === '0' ? 'active' : ''; ?> rounded-xl px-2 py-2 text-xs font-semibold">Inativos</button>
                </div>
            </div>

            <div class="md:col-span-12 flex flex-wrap items-center justify-between gap-2 pt-1">
                <p class="users-preview-subtitle text-xs">Use os filtros para acompanhar permissão, atividade e volume de contas.</p>
                <button class="users-preview-btn-primary rounded-xl px-4 py-2 text-sm font-semibold">Aplicar Filtros</button>
            </div>
        </form>

        <div class="users-preview-table-wrap overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="users-preview-table-head">
                    <tr class="text-left">
                        <th class="px-4 py-3">Colaborador</th>
                        <th class="px-3 py-3">Usuário</th>
                        <th class="px-3 py-3">Perfil</th>
                        <th class="px-3 py-3">Status</th>
                        <th class="px-3 py-3">Ultimo login</th>
                        <th class="px-3 py-3">Criado em</th>
                        <th class="px-4 py-3 text-right">Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $initials = $initialsFromName((string) ($row['name'] ?? ''));
                        $isActive = (int) ($row['is_active'] ?? 0) === 1;
                        ?>
                        <tr class="users-preview-row">
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="users-preview-avatar"><?= e($initials); ?></span>
                                    <div>
                                        <p class="text-sm font-semibold text-slate-100"><?= e((string) ($row['name'] ?? '')); ?></p>
                                        <p class="text-xs text-slate-400"><?= e((string) ($row['email'] ?? '')); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-4 text-slate-200"><?= e((string) ($row['username'] ?? '')); ?></td>
                            <td class="px-3 py-4">
                                <span class="users-preview-role-badge"><?= e(role_label((string) ($row['role'] ?? ''))); ?></span>
                            </td>
                            <td class="px-3 py-4">
                                <span class="rounded-full px-2 py-1 text-xs font-semibold <?= $isActive ? 'users-preview-status-active' : 'users-preview-status-inactive'; ?>">
                                    <?= $isActive ? 'Ativo' : 'Inativo'; ?>
                                </span>
                            </td>
                            <td class="px-3 py-4 text-slate-300"><?= e((string) (($row['last_login_at'] ?? '') ?: '-')); ?></td>
                            <td class="px-3 py-4 text-slate-300"><?= e((string) ($row['created_at'] ?? '')); ?></td>
                            <td class="px-4 py-4">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a href="<?= route('users/edit&id=' . (int) $row['id']); ?>" class="users-preview-action rounded-lg px-2.5 py-1 text-xs font-semibold">Editar</a>

                                    <form method="post" action="<?= route('users/toggle'); ?>">
                                        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                        <input type="hidden" name="id" value="<?= (int) $row['id']; ?>">
                                        <input type="hidden" name="active" value="<?= $isActive ? 0 : 1; ?>">
                                        <button class="users-preview-action rounded-lg px-2.5 py-1 text-xs font-semibold">
                                            <?= $isActive ? 'Inativar' : 'Ativar'; ?>
                                        </button>
                                    </form>

                                    <?php if ((int) current_user()['id'] !== (int) $row['id']): ?>
                                        <form method="post" action="<?= route('users/delete'); ?>" onsubmit="return confirm('Excluir usuário?');">
                                            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                            <input type="hidden" name="id" value="<?= (int) $row['id']; ?>">
                                            <button class="users-preview-action users-preview-action-danger rounded-lg px-2.5 py-1 text-xs font-semibold">Excluir</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($rows === []): ?>
                        <tr class="users-preview-row">
                            <td colspan="7" class="px-4 py-8 text-center text-slate-400">Nenhum usuário encontrado para os filtros selecionados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
            <p class="text-slate-300">Total: <?= (int) ($meta['total'] ?? 0); ?> | Página <?= (int) ($meta['page'] ?? 1); ?>/<?= (int) ($meta['pages'] ?? 1); ?></p>
            <div class="flex flex-wrap gap-2">
                <?php for ($p = 1; $p <= (int) ($meta['pages'] ?? 1); $p++): ?>
                    <?php
                    $query = [
                        'route' => 'users',
                        'q' => $currentQuery,
                        'role' => $currentRole,
                        'is_active' => $currentStatus,
                        'per_page' => $currentPerPage,
                        'page' => $p,
                    ];
                    $isCurrentPage = $p === (int) ($meta['page'] ?? 1);
                    ?>
                    <a href="index.php?<?= build_query($query); ?>" class="users-preview-pagination-link <?= $isCurrentPage ? 'active' : ''; ?> rounded-lg px-3 py-1.5 text-xs font-semibold">
                        <?= (int) $p; ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</section>

<script>
    (function () {
        const form = document.getElementById('users-preview-filter-form');
        const statusInput = document.getElementById('users-preview-is-active');
        const statusButtons = document.querySelectorAll('[data-status-filter]');
        if (!form || !statusInput || statusButtons.length === 0) return;

        statusButtons.forEach((button) => {
            button.addEventListener('click', () => {
                statusButtons.forEach((item) => item.classList.remove('active'));
                button.classList.add('active');
                statusInput.value = button.dataset.statusFilter || '';
                form.submit();
            });
        });
    })();
</script>
