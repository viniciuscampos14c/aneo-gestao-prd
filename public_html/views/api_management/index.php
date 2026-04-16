<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h2 class="text-xl font-bold text-slate-800">Gerenciamento de API</h2>
        <p class="mt-0.5 text-sm text-slate-500">Tokens de acesso para integração com sistemas externos.</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= route('api-management/manual'); ?>" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Manual da API
        </a>
        <a href="<?= route('api-management/create'); ?>" class="inline-flex items-center gap-2 rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700">
            + Novo Token
        </a>
    </div>
</div>

<?php if (empty($rows)): ?>
    <div class="rounded-xl border border-slate-200 bg-white p-10 text-center">
        <p class="text-slate-500">Nenhum token cadastrado. Crie o primeiro para integrar sistemas externos.</p>
        <a href="<?= route('api-management/create'); ?>" class="mt-4 inline-block rounded-lg bg-cyan-600 px-5 py-2 text-sm font-semibold text-white hover:bg-cyan-700">Criar Token</a>
    </div>
<?php else: ?>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">ID</th>
                    <th class="px-4 py-3 text-left">Usuário</th>
                    <th class="px-4 py-3 text-left">Nome</th>
                    <th class="px-4 py-3 text-left">Token</th>
                    <th class="px-4 py-3 text-left">Validade</th>
                    <th class="px-4 py-3 text-left">Último Uso</th>
                    <th class="px-4 py-3 text-right">Opções</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($rows as $row): ?>
                    <?php
                        $expired = $row['expires_at'] !== null && $row['expires_at'] < date('Y-m-d');
                    ?>
                    <tr class="hover:bg-slate-50 <?= $expired ? 'opacity-60' : ''; ?>">
                        <td class="px-4 py-3 font-mono text-slate-400"><?= (int) $row['id']; ?></td>
                        <td class="px-4 py-3 text-slate-600"><?= e($row['user_name']); ?></td>
                        <td class="px-4 py-3 font-medium text-slate-800">
                            <?= e($row['name']); ?>
                            <?php if ($expired): ?>
                                <span class="ml-1 rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-semibold text-rose-600">Expirado</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-400">
                            <?php
                                $hash = (string) $row['token_hash'];
                                echo e(substr($hash, 0, 8) . '...' . substr($hash, -8));
                            ?>
                        </td>
                        <td class="px-4 py-3 text-slate-500">
                            <?= $row['expires_at'] ? e((string) $row['expires_at']) : '<span class="text-slate-400">Sem expiração</span>'; ?>
                        </td>
                        <td class="px-4 py-3 text-slate-400 text-xs">
                            <?= $row['last_used_at'] ? e((string) $row['last_used_at']) : '—'; ?>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="<?= route('api-management/edit?id=' . (int) $row['id']); ?>"
                                   class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">
                                    Editar
                                </a>
                                <form method="post" action="<?= route('api-management/destroy'); ?>"
                                      onsubmit="return confirm('Remover este token? Integrações que o utilizam irão parar de funcionar.')">
                                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                    <input type="hidden" name="id" value="<?= (int) $row['id']; ?>">
                                    <button type="submit" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-100">
                                        Remover
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
