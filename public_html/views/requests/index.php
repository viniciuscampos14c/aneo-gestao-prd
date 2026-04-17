<?php
$canManage = has_permission('requests.manage');
$statusLabels = [
    'open' => 'Aberto',
    'in_progress' => 'Em andamento',
    'resolved' => 'Resolvido',
    'closed' => 'Fechado',
];
$priorityLabels = [
    'low' => 'Baixa',
    'medium' => 'Media',
    'high' => 'Alta',
    'urgent' => 'Urgente',
];
$sourceLabels = [
    'internal' => 'Interno',
    'webhook' => 'Webhook',
    'api' => 'API',
    'student_portal' => 'Portal Aluno',
];
$mobileQueue = $mobileQueue ?? [
    'pending_total' => 0,
    'pending_aditivos' => 0,
    'pending_negociacoes' => 0,
];

$queueLinks = [
    'all' => 'index.php?' . http_build_query([
        'route' => 'requests',
        'source' => 'api',
        'mobile_flow' => 1,
        'status' => 'pending',
    ]),
    'aditivos' => 'index.php?' . http_build_query([
        'route' => 'requests',
        'source' => 'api',
        'mobile_flow' => 1,
        'status' => 'pending',
        'q' => 'Aditivo financeiro - ',
    ]),
    'negociacoes' => 'index.php?' . http_build_query([
        'route' => 'requests',
        'source' => 'api',
        'mobile_flow' => 1,
        'status' => 'pending',
        'q' => 'Negociacao financeira - ',
    ]),
];
?>
<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold">Solicitações (Chamados)</h2>
            <p class="text-sm text-slate-500">Registre incidentes, anexe prints, acompanhe comentarios e status.</p>
        </div>
        <?php if ($canManage): ?>
            <button id="toggle-ticket-form" type="button" class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700">
                + Adicionar chamado
            </button>
        <?php endif; ?>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <article class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase text-slate-500">Total</p>
            <p class="mt-2 text-2xl font-semibold"><?= (int) ($stats['total'] ?? 0); ?></p>
        </article>
        <article class="rounded-xl border border-cyan-200 bg-cyan-50 p-4">
            <p class="text-xs uppercase text-cyan-700">Abertos</p>
            <p class="mt-2 text-2xl font-semibold text-cyan-700"><?= (int) ($stats['open'] ?? 0); ?></p>
        </article>
        <article class="rounded-xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs uppercase text-amber-700">Em andamento</p>
            <p class="mt-2 text-2xl font-semibold text-amber-700"><?= (int) ($stats['in_progress'] ?? 0); ?></p>
        </article>
        <article class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs uppercase text-emerald-700">Resolvidos</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-700"><?= (int) ($stats['resolved'] ?? 0); ?></p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase text-slate-500">Email destino</p>
            <p class="mt-2 text-sm font-semibold"><?= e((string) ($integration['notification_email'] ?? 'vinicius14c@hotmail.com')); ?></p>
            <p class="mt-1 text-xs text-slate-500">
                Webhook externo: <?= !empty($integration['webhook_enabled']) ? 'ativo' : 'desativado'; ?>
            </p>
        </article>
    </div>

    <div class="grid gap-3 md:grid-cols-3">
        <a href="<?= e($queueLinks['all']); ?>" class="rounded-xl border border-indigo-200 bg-indigo-50 p-4 transition hover:border-indigo-300 hover:bg-indigo-100/50">
            <p class="text-xs uppercase tracking-wide text-indigo-700">Fila Mobile</p>
            <p class="mt-1 text-2xl font-semibold text-indigo-900"><?= (int) ($mobileQueue['pending_total'] ?? 0); ?></p>
            <p class="mt-1 text-xs text-indigo-700">Negociacoes pendentes vindas do app.</p>
        </a>
        <a href="<?= e($queueLinks['aditivos']); ?>" class="rounded-xl border border-cyan-200 bg-cyan-50 p-4 transition hover:border-cyan-300 hover:bg-cyan-100/50">
            <p class="text-xs uppercase tracking-wide text-cyan-700">Aditivos Pendentes</p>
            <p class="mt-1 text-2xl font-semibold text-cyan-900"><?= (int) ($mobileQueue['pending_aditivos'] ?? 0); ?></p>
            <p class="mt-1 text-xs text-cyan-700">Fluxo rapido para aprovacao de aditivos.</p>
        </a>
        <a href="<?= e($queueLinks['negociacoes']); ?>" class="rounded-xl border border-amber-200 bg-amber-50 p-4 transition hover:border-amber-300 hover:bg-amber-100/50">
            <p class="text-xs uppercase tracking-wide text-amber-700">Negociacoes Pendentes</p>
            <p class="mt-1 text-2xl font-semibold text-amber-900"><?= (int) ($mobileQueue['pending_negociacoes'] ?? 0); ?></p>
            <p class="mt-1 text-xs text-amber-700">Negociacoes financeiras aguardando decisao.</p>
        </a>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-4">
        <h3 class="text-base font-semibold">Integracao entre sites (Chamados)</h3>
        <p class="mt-1 text-xs text-slate-500">Use a URL abaixo no site receptor (mesmo sistema/estilo) para receber chamados automaticamente.</p>
        <div class="mt-3 grid gap-2 md:grid-cols-[1fr_auto]">
            <input type="text" readonly value="<?= e((string) ($integration['local_webhook_url'] ?? '')); ?>" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs">
            <button type="button" onclick="navigator.clipboard.writeText('<?= e((string) ($integration['local_webhook_url'] ?? '')); ?>')" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs hover:bg-slate-50">Copiar URL</button>
        </div>
        <p class="mt-2 text-xs text-slate-500">URL configurada para envio externo: <?= e((string) ($integration['webhook_url'] ?? '')); ?><?= empty($integration['webhook_url']) ? ' (nao definida)' : ''; ?></p>
    </div>

    <?php if (!$featureAvailable): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            Estrutura de chamados indisponivel no banco. Execute as migracoes `migrations/20260309_support_tickets.sql` e `migrations/20260317_support_ticket_codes_aneo.sql`.
        </div>
    <?php endif; ?>

    <?php if ($canManage): ?>
        <form id="ticket-create-form" method="post" action="<?= route('requests/store'); ?>" enctype="multipart/form-data" class="hidden grid gap-3 rounded-xl border border-slate-200 bg-white p-4 lg:grid-cols-5">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
            <input type="hidden" name="return_to" value="requests">
            <label class="block lg:col-span-3">
                <span class="mb-1 block text-sm font-medium">Assunto *</span>
                <input type="text" name="subject" required placeholder="Ex: Erro ao emitir boleto" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </label>
            <label class="block lg:col-span-2">
                <span class="mb-1 block text-sm font-medium">Prioridade</span>
                <select name="priority" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <?php foreach ($priorityLabels as $key => $label): ?>
                        <option value="<?= e($key); ?>"><?= e($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="block lg:col-span-5">
                <span class="mb-1 block text-sm font-medium">Descricao do chamado *</span>
                <textarea name="description" rows="4" required placeholder="Descreva o que esta acontecendo em detalhes." class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></textarea>
            </label>
            <label class="block lg:col-span-4">
                <span class="mb-1 block text-sm font-medium">Prints (imagens)</span>
                <input type="file" name="prints[]" multiple accept=".png,.jpg,.jpeg,.webp,.gif,.bmp,image/png,image/jpeg,image/webp,image/gif,image/bmp" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-slate-500">Ate 8MB por arquivo.</p>
            </label>
            <div class="flex items-end">
                <button class="w-full rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Enviar chamado</button>
            </div>
        </form>
    <?php endif; ?>

    <form method="get" action="index.php" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-6">
        <input type="hidden" name="route" value="requests">
        <input type="text" name="q" value="<?= e($filters['q'] ?? ''); ?>" placeholder="Buscar por codigo, assunto ou descricao..." class="rounded-lg border border-slate-200 px-3 py-2 text-sm md:col-span-2">
        <select name="status" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option value="">Todos os status</option>
            <option value="pending" <?= (string) ($filters['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pendentes (aberto + em andamento)</option>
            <?php foreach ($statusLabels as $key => $label): ?>
                <option value="<?= e($key); ?>" <?= (string) ($filters['status'] ?? '') === (string) $key ? 'selected' : ''; ?>><?= e($label); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="priority" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option value="">Todas as prioridades</option>
            <?php foreach ($priorityLabels as $key => $label): ?>
                <option value="<?= e($key); ?>" <?= (string) ($filters['priority'] ?? '') === (string) $key ? 'selected' : ''; ?>><?= e($label); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="source" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option value="">Todas as origens</option>
            <?php foreach ($sourceLabels as $key => $label): ?>
                <option value="<?= e($key); ?>" <?= (string) ($filters['source'] ?? '') === (string) $key ? 'selected' : ''; ?>><?= e($label); ?></option>
            <?php endforeach; ?>
        </select>
        <div class="flex flex-wrap items-center justify-end gap-2 md:col-span-6">
            <label class="inline-flex items-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs text-indigo-700">
                <input type="checkbox" name="mobile_flow" value="1" <?= !empty($filters['mobile_flow']) ? 'checked' : ''; ?>>
                Somente negociacoes do app
            </label>
            <select name="per_page" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <?php foreach ($paginationOptions as $opt): ?>
                    <option value="<?= (int) $opt; ?>" <?= (int) ($meta['per_page'] ?? 50) === (int) $opt ? 'selected' : ''; ?>><?= (int) $opt; ?>/pagina</option>
                <?php endforeach; ?>
            </select>
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrar</button>
        </div>
    </form>

    <div class="space-y-3">
        <?php foreach ($rows as $row): ?>
            <?php
            $ticketId = (int) $row['id'];
            $ticketCode = trim((string) ($row['ticket_code'] ?? ''));
            if (!preg_match('/^ANEO\d+$/', $ticketCode)) {
                $ticketCode = 'ANEO' . str_pad((string) $ticketId, 3, '0', STR_PAD_LEFT);
            }
            $attachments = $attachmentsByTicket[$ticketId] ?? [];
            $comments = $commentsByTicket[$ticketId] ?? [];
            $status = (string) ($row['status'] ?? 'open');
            $priority = (string) ($row['priority'] ?? 'medium');
            $source = (string) ($row['source'] ?? 'internal');
            $statusBadge = match ($status) {
                'resolved' => 'bg-emerald-100 text-emerald-700',
                'in_progress' => 'bg-amber-100 text-amber-700',
                'closed' => 'bg-slate-200 text-slate-700',
                default => 'bg-cyan-100 text-cyan-700',
            };
            $priorityBadge = match ($priority) {
                'urgent' => 'bg-rose-100 text-rose-700',
                'high' => 'bg-orange-100 text-orange-700',
                'low' => 'bg-slate-100 text-slate-700',
                default => 'bg-sky-100 text-sky-700',
            };
            $sourceBadge = match ($source) {
                'student_portal' => 'bg-violet-100 text-violet-700',
                'api' => 'bg-indigo-100 text-indigo-700',
                default => 'bg-slate-100 text-slate-700',
            };
            $subject = strtolower(trim((string) ($row['subject'] ?? '')));
            $description = strtolower((string) ($row['description'] ?? ''));
            $isMobileFlow = $source === 'api' && (
                str_starts_with($subject, 'aditivo financeiro -')
                || str_starts_with($subject, 'negociacao financeira -')
                || str_contains($description, 'origem: app mobile diretoria')
            );
            ?>
            <article class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900"><?= e((string) ($row['subject'] ?? 'Chamado')); ?></h3>
                        <p class="text-xs text-slate-500">
                            Codigo: <span class="font-semibold text-slate-700"><?= e($ticketCode); ?></span>
                            | <?= e((string) ($row['created_by_name'] ?? 'Sistema')); ?>
                            | <?= e((string) ($row['created_at'] ?? '')); ?>
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full px-2 py-1 text-xs font-semibold <?= $statusBadge; ?>"><?= e($statusLabels[$status] ?? $status); ?></span>
                        <span class="rounded-full px-2 py-1 text-xs font-semibold <?= $priorityBadge; ?>"><?= e($priorityLabels[$priority] ?? $priority); ?></span>
                        <span class="rounded-full px-2 py-1 text-xs font-semibold <?= $sourceBadge; ?>"><?= e($sourceLabels[$source] ?? $source); ?></span>
                        <?php if ($isMobileFlow): ?>
                            <span class="rounded-full bg-indigo-600 px-2 py-1 text-xs font-semibold text-white">Diretoria Mobile</span>
                        <?php endif; ?>
                    </div>
                </div>

                <p class="mt-3 text-sm text-slate-700"><?= nl2br(e((string) ($row['description'] ?? ''))); ?></p>

                <div class="mt-3 grid gap-3 lg:grid-cols-2">
                    <div>
                        <p class="mb-1 text-xs uppercase tracking-wide text-slate-500">Prints anexados</p>
                        <?php if ($attachments === []): ?>
                            <p class="text-sm text-slate-500">Nenhum print anexado.</p>
                        <?php else: ?>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($attachments as $attachment): ?>
                                    <a href="<?= e((string) ($attachment['file_path'] ?? '')); ?>" target="_blank" rel="noopener" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs hover:bg-slate-100">
                                        <?= e((string) ($attachment['file_name'] ?? 'print')); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <p class="mb-1 text-xs uppercase tracking-wide text-slate-500">Comentarios</p>
                        <div class="max-h-36 space-y-1 overflow-y-auto rounded-lg border border-slate-100 bg-slate-50 p-2">
                            <?php foreach ($comments as $comment): ?>
                                <div class="rounded-md bg-white px-2 py-1.5 text-xs">
                                    <p class="text-slate-700"><?= nl2br(e((string) ($comment['comment'] ?? ''))); ?></p>
                                    <p class="mt-1 text-[11px] text-slate-500">
                                        <?= e((string) (($comment['author_name'] ?? '') !== '' ? $comment['author_name'] : 'Equipe')); ?>
                                        | <?= e((string) ($comment['created_at'] ?? '')); ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($comments === []): ?>
                                <p class="text-xs text-slate-500">Sem comentarios.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($canManage): ?>
                    <?php if ($isMobileFlow): ?>
                        <form method="post" action="<?= route('requests/mobile-decision'); ?>" class="mt-3 grid gap-2 rounded-lg border border-indigo-200 bg-indigo-50 p-3 lg:grid-cols-[1fr_auto_auto_auto]">
                            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                            <input type="hidden" name="ticket_id" value="<?= $ticketId; ?>">
                            <input type="hidden" name="return_to" value="requests">
                            <input type="text" name="decision_note" placeholder="Observacao opcional da decisao" class="rounded-lg border border-indigo-200 bg-white px-3 py-2 text-sm">
                            <button name="decision" value="approve" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Aprovar</button>
                            <button name="decision" value="adjust" class="rounded-lg bg-amber-500 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-600">Solicitar ajuste</button>
                            <button name="decision" value="reject" class="rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white hover:bg-rose-700">Reprovar</button>
                        </form>
                    <?php endif; ?>

                    <div class="mt-3 grid gap-3 lg:grid-cols-2">
                        <form method="post" action="<?= route('requests/comment'); ?>" class="flex gap-2">
                            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                            <input type="hidden" name="ticket_id" value="<?= $ticketId; ?>">
                            <input type="hidden" name="return_to" value="requests">
                            <input type="text" name="comment" required placeholder="Adicionar comentario..." class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <button class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Comentar</button>
                        </form>

                        <form method="post" action="<?= route('requests/status'); ?>" class="grid gap-2 md:grid-cols-[180px_1fr_auto]">
                            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                            <input type="hidden" name="ticket_id" value="<?= $ticketId; ?>">
                            <input type="hidden" name="return_to" value="requests">
                            <select name="status" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                <?php foreach ($statusLabels as $key => $label): ?>
                                    <option value="<?= e($key); ?>" <?= $status === $key ? 'selected' : ''; ?>><?= e($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="status_note" placeholder="Observacao (opcional)" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">Atualizar</button>
                        </form>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>

        <?php if ($rows === []): ?>
            <div class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
                Nenhum chamado encontrado.
            </div>
        <?php endif; ?>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
        <p>Total: <?= (int) ($meta['total'] ?? 0); ?> chamados | Pagina <?= (int) ($meta['page'] ?? 1); ?>/<?= (int) ($meta['pages'] ?? 1); ?></p>
        <div class="flex gap-2">
            <?php for ($p = 1; $p <= (int) ($meta['pages'] ?? 1); $p++): ?>
                <a href="index.php?<?= build_query(['route' => 'requests', 'page' => $p]); ?>" class="rounded px-3 py-1 <?= $p === (int) ($meta['page'] ?? 1) ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white hover:bg-slate-50'; ?>"><?= $p; ?></a>
            <?php endfor; ?>
        </div>
    </div>
</section>

<script>
document.getElementById('toggle-ticket-form')?.addEventListener('click', function () {
    const form = document.getElementById('ticket-create-form');
    if (!form) return;
    form.classList.toggle('hidden');
});
</script>
