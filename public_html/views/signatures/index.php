<?php
$canCreate = has_permission('signatures.create');
$canSend = has_permission('signatures.send');
$canSync = has_permission('signatures.sync');
$canDelete = has_permission('signatures.delete');
$statusOptions = [
    '' => 'Todos os status',
    'draft' => 'Rascunho',
    'sent' => 'Enviado',
    'signed' => 'Assinado',
    'cancelled' => 'Cancelado',
    'error' => 'Com erro',
];
?>
<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold">Assinaturas Eletronicas</h2>
            <p class="text-sm text-slate-500">Envio de contratos para assinatura no D4Sign e recepcao automatica do documento assinado.</p>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <article class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase text-slate-500">Integracao D4Sign</p>
            <?php if (!$integration['enabled']): ?>
                <p class="mt-2 text-base font-semibold text-rose-700">Desativada</p>
                <p class="mt-1 text-xs text-slate-500">Defina `d4sign.enabled = true`.</p>
            <?php elseif (!$integration['configured']): ?>
                <p class="mt-2 text-base font-semibold text-amber-700">Incompleta</p>
                <p class="mt-1 text-xs text-slate-500">Preencha token, crypt key e safe UUID.</p>
            <?php else: ?>
                <p class="mt-2 text-base font-semibold text-emerald-700">Ativa</p>
                <p class="mt-1 text-xs text-slate-500"><?= e($integration['base_url']); ?></p>
            <?php endif; ?>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase text-slate-500">Total</p>
            <p class="mt-2 text-2xl font-semibold"><?= (int) ($stats['total'] ?? 0); ?></p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase text-slate-500">Rascunho</p>
            <p class="mt-2 text-2xl font-semibold"><?= (int) ($stats['draft'] ?? 0); ?></p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase text-slate-500">Enviado</p>
            <p class="mt-2 text-2xl font-semibold"><?= (int) ($stats['sent'] ?? 0); ?></p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase text-slate-500">Assinado</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-700"><?= (int) ($stats['signed'] ?? 0); ?></p>
        </article>
    </div>

    <?php if (!$featureAvailable): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            Estrutura de assinatura indisponivel no banco. Execute a migracao `migrations/20260306_d4sign_signatures.sql`.
        </div>
    <?php endif; ?>

    <div class="rounded-xl border border-slate-200 bg-white p-4">
        <h3 class="text-base font-semibold">Webhook D4Sign</h3>
        <p class="mt-1 text-xs text-slate-500">Cadastre esta URL no painel D4Sign para atualizacao automatica do status e download do contrato assinado.</p>
        <div class="mt-3 grid gap-2 md:grid-cols-[1fr_auto]">
            <input type="text" readonly value="<?= e($integration['webhook_url']); ?>" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs">
            <button type="button" onclick="navigator.clipboard.writeText('<?= e($integration['webhook_url']); ?>')" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs hover:bg-slate-50">Copiar URL</button>
        </div>
        <?php if (trim((string) $integration['webhook_token']) === ''): ?>
            <p class="mt-2 text-xs text-amber-700">Recomendado: configure `d4sign.webhook_token` para proteger o endpoint.</p>
        <?php endif; ?>
    </div>

    <?php if ($canCreate): ?>
        <form method="post" action="<?= route('signatures/store'); ?>" enctype="multipart/form-data" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 lg:grid-cols-5">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
            <label class="block lg:col-span-2">
                <span class="mb-1 block text-sm font-medium">Aluno *</span>
                <select name="student_id" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <option value="">Selecione</option>
                    <?php foreach ($students as $student): ?>
                        <option value="<?= (int) $student['id']; ?>"><?= e($student['full_name']); ?><?= !empty($student['email_primary']) ? ' - ' . e($student['email_primary']) : ''; ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="block">
                <span class="mb-1 block text-sm font-medium">Titulo *</span>
                <input type="text" name="title" required placeholder="Contrato de Matricula" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </label>
            <label class="block">
                <span class="mb-1 block text-sm font-medium">Arquivo *</span>
                <input type="file" name="contract_file" required accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </label>
            <label class="block lg:col-span-4">
                <span class="mb-1 block text-sm font-medium">Descricao</span>
                <input type="text" name="description" placeholder="Observacoes internas do contrato" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </label>
            <div class="flex items-end">
                <button class="w-full rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700">Criar solicitacao</button>
            </div>
        </form>
    <?php endif; ?>

    <form method="get" action="index.php" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-5">
        <input type="hidden" name="route" value="signatures">
        <input type="text" name="q" value="<?= e($filters['q']); ?>" placeholder="Buscar contrato, aluno, email ou UUID..." class="rounded-lg border border-slate-200 px-3 py-2 text-sm md:col-span-2">
        <select name="status" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <?php foreach ($statusOptions as $key => $label): ?>
                <option value="<?= e($key); ?>" <?= (string) $filters['status'] === (string) $key ? 'selected' : ''; ?>><?= e($label); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="student_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option value="">Todos os alunos</option>
            <?php foreach ($students as $student): ?>
                <option value="<?= (int) $student['id']; ?>" <?= (string) $filters['student_id'] === (string) $student['id'] ? 'selected' : ''; ?>><?= e($student['full_name']); ?></option>
            <?php endforeach; ?>
        </select>
        <div class="flex gap-2">
            <select name="per_page" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <?php foreach ($paginationOptions as $opt): ?>
                    <option value="<?= (int) $opt; ?>" <?= (int) $meta['per_page'] === (int) $opt ? 'selected' : ''; ?>><?= (int) $opt; ?>/pagina</option>
                <?php endforeach; ?>
            </select>
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrar</button>
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-3">Contrato</th>
                    <th class="px-3 py-3">Aluno</th>
                    <th class="px-3 py-3">Status</th>
                    <th class="px-3 py-3">D4Sign</th>
                    <th class="px-3 py-3">Arquivos</th>
                    <th class="px-3 py-3">Atualizacao</th>
                    <th class="px-3 py-3">Acoes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $status = (string) ($row['status'] ?? 'draft');
                    $badgeClass = match ($status) {
                        'signed' => 'bg-emerald-100 text-emerald-700',
                        'sent' => 'bg-cyan-100 text-cyan-700',
                        'cancelled' => 'bg-amber-100 text-amber-700',
                        'error' => 'bg-rose-100 text-rose-700',
                        default => 'bg-slate-100 text-slate-700',
                    };
                    ?>
                    <tr class="border-b border-slate-100 hover:bg-slate-50">
                        <td class="px-3 py-3">
                            <p class="font-medium"><?= e($row['title']); ?></p>
                            <p class="text-xs text-slate-500">#<?= (int) $row['id']; ?><?= !empty($row['description']) ? ' | ' . e($row['description']) : ''; ?></p>
                            <?php if (!empty($row['last_error'])): ?>
                                <p class="mt-1 text-xs text-rose-600"><?= e($row['last_error']); ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-3">
                            <p class="font-medium"><?= e($row['student_name']); ?></p>
                            <p class="text-xs text-slate-500"><?= e($row['signer_email']); ?></p>
                        </td>
                        <td class="px-3 py-3">
                            <span class="rounded-full px-2 py-1 text-xs font-semibold <?= $badgeClass; ?>"><?= e($status); ?></span>
                        </td>
                        <td class="px-3 py-3">
                            <p class="text-xs text-slate-600"><?= e($row['d4sign_status'] ?: '-'); ?></p>
                            <p class="text-xs text-slate-500"><?= e($row['d4sign_document_uuid'] ?: '-'); ?></p>
                        </td>
                        <td class="px-3 py-3">
                            <?php if (!empty($row['file_original_path'])): ?>
                                <a href="<?= e($row['file_original_path']); ?>" target="_blank" rel="noopener" class="block text-xs text-cyan-700 hover:underline">Contrato original</a>
                            <?php endif; ?>
                            <?php if (!empty($row['file_signed_path'])): ?>
                                <a href="<?= e($row['file_signed_path']); ?>" target="_blank" rel="noopener" class="block text-xs text-emerald-700 hover:underline">Contrato assinado</a>
                            <?php else: ?>
                                <span class="text-xs text-slate-500">Sem arquivo assinado</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-3 text-xs text-slate-600">
                            <p>Criado: <?= e($row['created_at']); ?></p>
                            <p>Sync: <?= e($row['last_synced_at'] ?: '-'); ?></p>
                        </td>
                        <td class="px-3 py-3">
                            <div class="flex flex-wrap gap-2">
                                <?php if ($canSend): ?>
                                    <form method="post" action="<?= route('signatures/send'); ?>">
                                        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                        <input type="hidden" name="id" value="<?= (int) $row['id']; ?>">
                                        <button class="rounded border border-cyan-200 bg-cyan-50 px-2 py-1 text-xs text-cyan-700 hover:bg-cyan-100">Enviar D4Sign</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($canSync && !empty($row['d4sign_document_uuid'])): ?>
                                    <form method="post" action="<?= route('signatures/sync'); ?>">
                                        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                        <input type="hidden" name="id" value="<?= (int) $row['id']; ?>">
                                        <button class="rounded border border-slate-200 bg-white px-2 py-1 text-xs hover:bg-slate-100">Sincronizar</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($canDelete): ?>
                                    <form method="post" action="<?= route('signatures/delete'); ?>" onsubmit="return confirm('Excluir solicitacao de assinatura?');">
                                        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                        <input type="hidden" name="id" value="<?= (int) $row['id']; ?>">
                                        <button class="rounded border border-rose-200 bg-rose-50 px-2 py-1 text-xs text-rose-700 hover:bg-rose-100">Excluir</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?>
                    <tr>
                        <td colspan="7" class="px-3 py-6 text-center text-slate-500">Nenhuma solicitacao de assinatura encontrada.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
        <p>Total: <?= (int) $meta['total']; ?> registros | Pagina <?= (int) $meta['page']; ?>/<?= (int) $meta['pages']; ?></p>
        <div class="flex gap-2">
            <?php for ($p = 1; $p <= (int) $meta['pages']; $p++): ?>
                <a href="index.php?<?= build_query(['route' => 'signatures', 'page' => $p]); ?>" class="rounded px-3 py-1 <?= $p === (int) $meta['page'] ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white hover:bg-slate-50'; ?>"><?= $p; ?></a>
            <?php endfor; ?>
        </div>
    </div>

    <?php if ($recentEvents !== []): ?>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <h3 class="text-base font-semibold">Ultimos eventos de webhook</h3>
            <div class="mt-3 space-y-2 text-sm">
                <?php foreach ($recentEvents as $event): ?>
                    <div class="rounded-lg border border-slate-100 px-3 py-2">
                        <p class="font-medium"><?= e($event['event_type'] ?: 'webhook'); ?><?= !empty($event['event_status']) ? ' - ' . e($event['event_status']) : ''; ?></p>
                        <p class="text-xs text-slate-600">Contrato: <?= e($event['request_title'] ?: '-'); ?> | UUID: <?= e($event['d4sign_document_uuid'] ?: '-'); ?></p>
                        <p class="text-xs text-slate-500"><?= e($event['received_at']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</section>
