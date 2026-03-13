<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold">Administracao de Usuarios</h2>
            <p class="text-sm text-slate-500">Gerencie acessos do sistema com perfis Administrador e Suporte.</p>
        </div>
        <a href="<?= route('users/create'); ?>" class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700">+ Novo Usuario</a>
    </div>

    <form method="get" action="index.php" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-5">
        <input type="hidden" name="route" value="users">
        <input type="text" name="q" value="<?= e($filters['q']); ?>" placeholder="Buscar por nome/email/usuario..." class="rounded-lg border border-slate-200 px-3 py-2 text-sm">

        <select name="role" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option value="">Todos os perfis</option>
            <?php foreach ($roles as $roleKey => $roleLabel): ?>
                <option value="<?= e($roleKey); ?>" <?= $filters['role'] === $roleKey ? 'selected' : ''; ?>><?= e($roleLabel); ?></option>
            <?php endforeach; ?>
        </select>

        <select name="is_active" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option value="">Todos os status</option>
            <option value="1" <?= (string) $filters['is_active'] === '1' ? 'selected' : ''; ?>>Ativo</option>
            <option value="0" <?= (string) $filters['is_active'] === '0' ? 'selected' : ''; ?>>Inativo</option>
        </select>

        <select name="per_page" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <?php foreach ($paginationOptions as $opt): ?>
                <option value="<?= (int) $opt; ?>" <?= (int) $meta['per_page'] === (int) $opt ? 'selected' : ''; ?>><?= (int) $opt; ?>/pagina</option>
            <?php endforeach; ?>
        </select>

        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrar</button>
    </form>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-3">ID</th>
                    <th class="px-3 py-3">Nome</th>
                    <th class="px-3 py-3">Usuario</th>
                    <th class="px-3 py-3">Email</th>
                    <th class="px-3 py-3">Perfil</th>
                    <th class="px-3 py-3">Status</th>
                    <th class="px-3 py-3">Ultimo login</th>
                    <th class="px-3 py-3">Criado em</th>
                    <th class="px-3 py-3">Acoes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr class="border-b border-slate-100 hover:bg-slate-50">
                        <td class="px-3 py-3"><?= (int) $row['id']; ?></td>
                        <td class="px-3 py-3 font-medium"><?= e($row['name']); ?></td>
                        <td class="px-3 py-3"><?= e($row['username']); ?></td>
                        <td class="px-3 py-3"><?= e($row['email']); ?></td>
                        <td class="px-3 py-3"><?= e(role_label($row['role'])); ?></td>
                        <td class="px-3 py-3">
                            <span class="rounded-full px-2 py-1 text-xs font-semibold <?= (int) $row['is_active'] === 1 ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'; ?>">
                                <?= (int) $row['is_active'] === 1 ? 'Ativo' : 'Inativo'; ?>
                            </span>
                        </td>
                        <td class="px-3 py-3"><?= e($row['last_login_at'] ?: '-'); ?></td>
                        <td class="px-3 py-3"><?= e($row['created_at']); ?></td>
                        <td class="px-3 py-3">
                            <div class="flex flex-wrap gap-2">
                                <a href="<?= route('users/edit&id=' . (int) $row['id']); ?>" class="rounded border border-slate-200 px-2 py-1 text-xs hover:bg-slate-100">Editar</a>

                                <form method="post" action="<?= route('users/toggle'); ?>">
                                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                    <input type="hidden" name="id" value="<?= (int) $row['id']; ?>">
                                    <input type="hidden" name="active" value="<?= (int) $row['is_active'] === 1 ? 0 : 1; ?>">
                                    <button class="rounded border border-amber-200 px-2 py-1 text-xs text-amber-700 hover:bg-amber-50">
                                        <?= (int) $row['is_active'] === 1 ? 'Inativar' : 'Ativar'; ?>
                                    </button>
                                </form>

                                <?php if ((int) current_user()['id'] !== (int) $row['id']): ?>
                                    <form method="post" action="<?= route('users/delete'); ?>" onsubmit="return confirm('Excluir usuario?');">
                                        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                        <input type="hidden" name="id" value="<?= (int) $row['id']; ?>">
                                        <button class="rounded border border-rose-200 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50">Excluir</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?>
                    <tr>
                        <td colspan="9" class="px-3 py-6 text-center text-slate-500">Nenhum usuario encontrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
        <p>Total: <?= (int) $meta['total']; ?> registros | Pagina <?= (int) $meta['page']; ?>/<?= (int) $meta['pages']; ?></p>
        <div class="flex gap-2">
            <?php for ($p = 1; $p <= (int) $meta['pages']; $p++): ?>
                <a href="index.php?<?= build_query(['route' => 'users', 'page' => $p]); ?>" class="rounded px-3 py-1 <?= $p === (int) $meta['page'] ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white hover:bg-slate-50'; ?>">
                    <?= $p; ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>
</section>
