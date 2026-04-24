<?php
$available = !empty($available);
$rows = is_array($rows ?? null) ? $rows : [];
?>
<section class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-semibold">Formas de Pagamento</h2>
            <p class="text-sm text-slate-500">Cadastre formas avulsas (manual) e acompanhe as formas integradas por contrato.</p>
        </div>
        <a href="<?= route('finance/invoices'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Voltar para Faturas</a>
    </div>

    <?php if (!$available): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
            Estrutura indisponivel no banco. Execute a migration <code>migrations/20260424_finance_payment_methods.sql</code>.
        </div>
    <?php else: ?>
        <form method="post" action="<?= route('finance/payment-methods/store'); ?>" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-4">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">

            <label class="block md:col-span-2">
                <span class="mb-1 block text-sm font-medium">Nome *</span>
                <input type="text" name="name" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Ex.: PIX, Cartao de credito, Transferencia...">
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-medium">Canal</span>
                <select name="channel" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <option value="pix">PIX</option>
                    <option value="card">Cartao</option>
                    <option value="transfer">Transferencia</option>
                    <option value="cash">Dinheiro</option>
                    <option value="boleto">Boleto</option>
                    <option value="other">Outro</option>
                </select>
            </label>

            <div class="flex items-end">
                <button class="w-full rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700">Adicionar forma manual</button>
            </div>
        </form>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-3 py-3">Nome</th>
                        <th class="px-3 py-3">Tipo</th>
                        <th class="px-3 py-3">Contrato</th>
                        <th class="px-3 py-3">Canal</th>
                        <th class="px-3 py-3">Origem</th>
                        <th class="px-3 py-3">Status</th>
                        <th class="px-3 py-3">Acao</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $isActive = (int) ($row['is_active'] ?? 0) === 1;
                        $mode = (string) ($row['mode'] ?? 'manual');
                        $isIntegrated = $mode === 'integrated';
                        $provider = trim((string) ($row['provider_key'] ?? ''));
                        $origin = (int) ($row['auto_created'] ?? 0) === 1 ? 'Automatica' : 'Manual';
                        ?>
                        <tr class="border-b border-slate-100">
                            <td class="px-3 py-3 font-medium"><?= e((string) ($row['name'] ?? '')); ?></td>
                            <td class="px-3 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-semibold <?= $isIntegrated ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-700'; ?>">
                                    <?= $isIntegrated ? 'Integrada' : 'Manual'; ?>
                                </span>
                            </td>
                            <td class="px-3 py-3"><?= e($provider !== '' ? strtoupper($provider) : '-'); ?></td>
                            <td class="px-3 py-3"><?= e((string) ($row['channel'] ?? '-')); ?></td>
                            <td class="px-3 py-3"><?= e($origin); ?></td>
                            <td class="px-3 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-semibold <?= $isActive ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'; ?>">
                                    <?= $isActive ? 'Ativa' : 'Inativa'; ?>
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <form method="post" action="<?= route('finance/payment-methods/toggle'); ?>">
                                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                    <input type="hidden" name="id" value="<?= (int) $row['id']; ?>">
                                    <input type="hidden" name="set_active" value="<?= $isActive ? 0 : 1; ?>">
                                    <button class="rounded border border-slate-200 bg-white px-2 py-1 text-xs hover:bg-slate-50">
                                        <?= $isActive ? 'Inativar' : 'Ativar'; ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($rows === []): ?>
                        <tr>
                            <td colspan="7" class="px-3 py-6 text-center text-slate-500">Nenhuma forma de pagamento cadastrada.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
