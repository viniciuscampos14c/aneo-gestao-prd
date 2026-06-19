<?php
$available = !empty($available);
$rows = is_array($rows ?? null) ? $rows : [];
$filters = is_array($filters ?? null) ? $filters : [];
$meta = is_array($meta ?? null) ? $meta : pagination_meta(0, 50, 1);
$paginationOptions = is_array($paginationOptions ?? null) ? $paginationOptions : [50, 100, 200];
$paginationBase = [
    'route' => 'finance/suppliers',
    'q' => $filters['q'] ?? '',
    'status' => $filters['status'] ?? '',
    'per_page' => $meta['per_page'] ?? 50,
];
?>
<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold">Fornecedores</h2>
            <p class="text-sm text-slate-500">Cadastre os parceiros financeiros e mantenha os dados de contato e pagamento centralizados.</p>
        </div>
        <div class="flex gap-2">
            <a href="<?= route('finance/payables'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Contas a pagar</a>
        </div>
    </div>

    <?php if (!$available): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
            Estrutura indisponivel no banco. Execute a migration <code>migrations/20260523_finance_payables.sql</code>.
        </div>
    <?php else: ?>
        <form method="get" action="index.php" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-4 xl:grid-cols-6">
            <input type="hidden" name="route" value="finance/suppliers">
            <input type="text" name="q" value="<?= e((string) ($filters['q'] ?? '')); ?>" placeholder="Buscar fornecedor, documento ou contato..." class="rounded-lg border border-slate-200 px-3 py-2 text-sm md:col-span-2">
            <select name="status" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Todos os status</option>
                <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Ativos</option>
                <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inativos</option>
            </select>
            <select name="per_page" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <?php foreach ($paginationOptions as $opt): ?>
                    <option value="<?= (int) $opt; ?>" <?= (int) ($meta['per_page'] ?? 50) === (int) $opt ? 'selected' : ''; ?>><?= (int) $opt; ?>/página</option>
                <?php endforeach; ?>
            </select>
            <div class="flex gap-2 md:col-span-4 xl:col-span-6">
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Aplicar</button>
                <a href="index.php?<?= http_build_query(['route' => 'finance/suppliers']); ?>" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm hover:bg-slate-50">Limpar</a>
            </div>
        </form>

        <form method="post" action="<?= route('finance/suppliers/store'); ?>" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-2 xl:grid-cols-4">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">

            <label class="block xl:col-span-2">
                <span class="mb-1 block text-sm font-medium">Nome do fornecedor *</span>
                <input type="text" name="name" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Ex.: Hostinger, Zoom, Escritório Contábil...">
            </label>
            <label class="block">
                <span class="mb-1 block text-sm font-medium">Documento</span>
                <input type="text" name="document" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="CNPJ/CPF">
            </label>
            <label class="block">
                <span class="mb-1 block text-sm font-medium">Contato</span>
                <input type="text" name="contact_name" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Nome do responsável">
            </label>
            <label class="block">
                <span class="mb-1 block text-sm font-medium">Email</span>
                <input type="email" name="email" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="financeiro@fornecedor.com">
            </label>
            <label class="block">
                <span class="mb-1 block text-sm font-medium">Telefone</span>
                <input type="text" name="phone" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </label>
            <label class="block">
                <span class="mb-1 block text-sm font-medium">WhatsApp</span>
                <input type="text" name="whatsapp" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </label>
            <label class="block">
                <span class="mb-1 block text-sm font-medium">Chave PIX</span>
                <input type="text" name="pix_key" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </label>
            <label class="block">
                <span class="mb-1 block text-sm font-medium">Banco</span>
                <input type="text" name="bank_name" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </label>
            <label class="block">
                <span class="mb-1 block text-sm font-medium">Agencia</span>
                <input type="text" name="bank_agency" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </label>
            <label class="block">
                <span class="mb-1 block text-sm font-medium">Conta</span>
                <input type="text" name="bank_account" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </label>
            <label class="block xl:col-span-3">
                <span class="mb-1 block text-sm font-medium">Observações</span>
                <input type="text" name="notes" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Categoria, contrato, vencimento padrao, observações internas...">
            </label>
            <label class="flex items-center gap-2 pt-7 text-sm">
                <input type="checkbox" name="is_active" value="1" checked>
                Ativo
            </label>
            <div class="xl:col-span-4">
                <button class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700">Salvar fornecedor</button>
            </div>
        </form>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-3 py-3">Fornecedor</th>
                        <th class="px-3 py-3">Contato</th>
                        <th class="px-3 py-3">Documento</th>
                        <th class="px-3 py-3">PIX / Banco</th>
                        <th class="px-3 py-3">Status</th>
                        <th class="px-3 py-3">Acao</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php $isActive = (int) ($row['is_active'] ?? 0) === 1; ?>
                        <tr class="border-b border-slate-100">
                            <td class="px-3 py-3">
                                <p class="font-medium"><?= e((string) ($row['name'] ?? '')); ?></p>
                                <p class="text-xs text-slate-500"><?= e((string) ($row['notes'] ?? '')); ?></p>
                            </td>
                            <td class="px-3 py-3">
                                <p><?= e((string) ($row['contact_name'] ?? '-')); ?></p>
                                <p class="text-xs text-slate-500"><?= e((string) ($row['email'] ?? '-')); ?><?= !empty($row['phone']) ? ' | ' . e((string) $row['phone']) : ''; ?></p>
                            </td>
                            <td class="px-3 py-3"><?= e((string) ($row['document'] ?? '-')); ?></td>
                            <td class="px-3 py-3">
                                <p><?= e((string) ($row['pix_key'] ?? '-')); ?></p>
                                <p class="text-xs text-slate-500"><?= e((string) ($row['bank_name'] ?? '-')); ?><?= !empty($row['bank_agency']) ? ' ag. ' . e((string) $row['bank_agency']) : ''; ?><?= !empty($row['bank_account']) ? ' conta ' . e((string) $row['bank_account']) : ''; ?></p>
                            </td>
                            <td class="px-3 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-semibold <?= $isActive ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'; ?>">
                                    <?= $isActive ? 'Ativo' : 'Inativo'; ?>
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <form method="post" action="<?= route('finance/suppliers/toggle'); ?>">
                                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                    <input type="hidden" name="id" value="<?= (int) $row['id']; ?>">
                                    <input type="hidden" name="set_active" value="<?= $isActive ? 0 : 1; ?>">
                                    <button class="rounded border border-slate-200 bg-white px-2 py-1 text-xs hover:bg-slate-50"><?= $isActive ? 'Inativar' : 'Ativar'; ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="6" class="px-3 py-6 text-center text-slate-500">Nenhum fornecedor encontrado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
            <p>Total: <?= (int) ($meta['total'] ?? 0); ?> registros | Página <?= (int) ($meta['page'] ?? 1); ?>/<?= (int) ($meta['pages'] ?? 1); ?></p>
            <div class="flex gap-2">
                <?php for ($p = 1; $p <= (int) ($meta['pages'] ?? 1); $p++): ?>
                    <a href="index.php?<?= http_build_query($paginationBase + ['page' => $p]); ?>" class="rounded px-3 py-1 <?= $p === (int) ($meta['page'] ?? 1) ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white hover:bg-slate-50'; ?>"><?= $p; ?></a>
                <?php endfor; ?>
            </div>
        </div>
    <?php endif; ?>
</section>
