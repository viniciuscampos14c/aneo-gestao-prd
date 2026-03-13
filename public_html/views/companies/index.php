<?php
$isEdit = is_array($editing ?? null);
$formatCnpj = static function (?string $value): string {
    $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
    if (strlen($digits) !== 14) {
        return (string) $value;
    }

    return substr($digits, 0, 2) . '.' .
        substr($digits, 2, 3) . '.' .
        substr($digits, 5, 3) . '/' .
        substr($digits, 8, 4) . '-' .
        substr($digits, 12, 2);
};
?>
<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold">Empresas</h2>
            <p class="text-sm text-slate-500">Cadastre os CNPJs e controle quais usuarios podem acessar cada empresa.</p>
        </div>
    </div>

    <form method="post" action="<?= route($isEdit ? 'companies/update' : 'companies/store'); ?>" class="space-y-4 rounded-xl border border-slate-200 bg-white p-5">
        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0); ?>">
        <?php endif; ?>

        <div class="grid gap-3 md:grid-cols-2">
            <label class="block">
                <span class="mb-1 block text-sm">Razao social *</span>
                <input type="text" name="legal_name" required value="<?= e((string) ($editing['legal_name'] ?? '')); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </label>
            <label class="block">
                <span class="mb-1 block text-sm">Nome fantasia</span>
                <input type="text" name="trade_name" value="<?= e((string) ($editing['trade_name'] ?? '')); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </label>
            <label class="block">
                <span class="mb-1 block text-sm">CNPJ *</span>
                <input type="text" name="cnpj" required value="<?= e((string) ($editing['cnpj'] ?? '')); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Somente numeros ou formatado">
            </label>
            <label class="block">
                <span class="mb-1 block text-sm">Status</span>
                <select name="is_active" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <option value="1" <?= (int) ($editing['is_active'] ?? 1) === 1 ? 'selected' : ''; ?>>Ativa</option>
                    <option value="0" <?= (int) ($editing['is_active'] ?? 1) === 0 ? 'selected' : ''; ?>>Inativa</option>
                </select>
            </label>
        </div>

        <div class="flex gap-2">
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                <?= $isEdit ? 'Salvar empresa' : 'Cadastrar empresa'; ?>
            </button>
            <?php if ($isEdit): ?>
                <a href="<?= route('companies'); ?>" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm hover:bg-slate-50">Cancelar edicao</a>
            <?php endif; ?>
        </div>
    </form>

    <form method="get" action="index.php" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-4">
        <input type="hidden" name="route" value="companies">
        <input type="text" name="q" value="<?= e((string) $filters['q']); ?>" placeholder="Buscar por nome ou CNPJ..." class="rounded-lg border border-slate-200 px-3 py-2 text-sm">

        <select name="is_active" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option value="">Todos os status</option>
            <option value="1" <?= (string) $filters['is_active'] === '1' ? 'selected' : ''; ?>>Ativa</option>
            <option value="0" <?= (string) $filters['is_active'] === '0' ? 'selected' : ''; ?>>Inativa</option>
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
                    <th class="px-3 py-3">Empresa</th>
                    <th class="px-3 py-3">CNPJ</th>
                    <th class="px-3 py-3">Usuarios</th>
                    <th class="px-3 py-3">Status</th>
                    <th class="px-3 py-3">Acoes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php $name = trim((string) ($row['trade_name'] ?? '')) !== '' ? (string) $row['trade_name'] : (string) $row['legal_name']; ?>
                    <tr class="border-b border-slate-100 hover:bg-slate-50">
                        <td class="px-3 py-3"><?= (int) $row['id']; ?></td>
                        <td class="px-3 py-3">
                            <p class="font-medium"><?= e($name); ?></p>
                            <p class="text-xs text-slate-500"><?= e((string) $row['legal_name']); ?></p>
                        </td>
                        <td class="px-3 py-3"><?= e($formatCnpj((string) $row['cnpj'])); ?></td>
                        <td class="px-3 py-3"><?= (int) ($row['users_count'] ?? 0); ?></td>
                        <td class="px-3 py-3">
                            <span class="rounded-full px-2 py-1 text-xs font-semibold <?= (int) $row['is_active'] === 1 ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'; ?>">
                                <?= (int) $row['is_active'] === 1 ? 'Ativa' : 'Inativa'; ?>
                            </span>
                        </td>
                        <td class="px-3 py-3">
                            <div class="flex flex-wrap gap-2">
                                <a href="<?= route('companies&edit=' . (int) $row['id']); ?>" class="rounded border border-slate-200 px-2 py-1 text-xs hover:bg-slate-100">Editar</a>

                                <form method="post" action="<?= route('companies/toggle'); ?>">
                                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                    <input type="hidden" name="id" value="<?= (int) $row['id']; ?>">
                                    <input type="hidden" name="active" value="<?= (int) $row['is_active'] === 1 ? 0 : 1; ?>">
                                    <button class="rounded border border-amber-200 px-2 py-1 text-xs text-amber-700 hover:bg-amber-50">
                                        <?= (int) $row['is_active'] === 1 ? 'Inativar' : 'Ativar'; ?>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?>
                    <tr>
                        <td colspan="6" class="px-3 py-6 text-center text-slate-500">Nenhuma empresa encontrada.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
        <p>Total: <?= (int) $meta['total']; ?> registros | Pagina <?= (int) $meta['page']; ?>/<?= (int) $meta['pages']; ?></p>
        <div class="flex gap-2">
            <?php for ($p = 1; $p <= (int) $meta['pages']; $p++): ?>
                <a href="index.php?<?= build_query(['route' => 'companies', 'page' => $p]); ?>" class="rounded px-3 py-1 <?= $p === (int) $meta['page'] ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white hover:bg-slate-50'; ?>">
                    <?= $p; ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>
</section>
