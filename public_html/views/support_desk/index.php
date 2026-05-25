<?php
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
    'api' => 'App Mobile',
    'student_portal' => 'Portal Aluno',
];
?>
<section class="support-desk-shell space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold text-slate-900">Chamados - Atendimento Tecnico</h2>
            <p class="text-sm text-slate-500">Gestao dos chamados recebidos do administrativo e do portal do aluno.</p>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <article class="support-kpi support-kpi-total rounded-2xl border border-white/70 bg-white/65 p-4 shadow-sm backdrop-blur-md">
            <p class="text-xs uppercase text-slate-500">Total</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900"><?= (int) ($stats['total'] ?? 0); ?></p>
        </article>
        <article class="support-kpi support-kpi-open rounded-2xl border border-cyan-200/80 bg-cyan-50/75 p-4 shadow-sm backdrop-blur-md">
            <p class="text-xs uppercase text-cyan-700">Abertos</p>
            <p class="mt-2 text-2xl font-semibold text-cyan-700"><?= (int) ($stats['open'] ?? 0); ?></p>
        </article>
        <article class="support-kpi support-kpi-email rounded-2xl border border-amber-200/80 bg-amber-50/75 p-4 shadow-sm backdrop-blur-md">
            <p class="text-xs uppercase text-amber-700">Email pendente</p>
            <p class="mt-2 text-2xl font-semibold text-amber-700"><?= (int) ($dispatchStats['email_pending'] ?? 0); ?></p>
        </article>
        <article class="support-kpi support-kpi-webhook rounded-2xl border border-rose-200/80 bg-rose-50/75 p-4 shadow-sm backdrop-blur-md">
            <p class="text-xs uppercase text-rose-700">Webhook pendente</p>
            <p class="mt-2 text-2xl font-semibold text-rose-700"><?= (int) ($dispatchStats['webhook_pending'] ?? 0); ?></p>
        </article>
        <article class="support-kpi support-kpi-origin rounded-2xl border border-emerald-200/80 bg-emerald-50/75 p-4 shadow-sm backdrop-blur-md">
            <p class="text-xs uppercase text-emerald-700">Origem webhook</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-700"><?= (int) ($dispatchStats['from_webhook'] ?? 0); ?></p>
        </article>
    </div>

    <?php if (!$featureAvailable): ?>
        <div class="rounded-2xl border border-amber-200/90 bg-amber-50/80 p-4 text-sm text-amber-800 backdrop-blur-md">
            Estrutura de chamados indisponivel no banco. Execute as migracoes `migrations/20260309_support_tickets.sql` e `migrations/20260317_support_ticket_codes_aneo.sql`.
        </div>
    <?php endif; ?>

    <form method="get" action="support.php" class="support-filter-form grid gap-3 rounded-2xl border border-white/70 bg-white/65 p-4 md:grid-cols-7 shadow-sm backdrop-blur-md">
        <input type="hidden" name="route" value="support">
        <input type="text" name="q" value="<?= e($filters['q'] ?? ''); ?>" placeholder="Buscar por codigo, assunto ou descricao..." class="support-field rounded-xl border border-white/80 bg-white/80 px-3 py-2 text-sm text-slate-700 outline-none md:col-span-2 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400">
        <select name="company_id" class="support-select rounded-xl border border-white/80 bg-white/80 px-3 py-2 text-sm text-slate-700 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400">
            <option value="0">Todas as empresas</option>
            <?php foreach ($companies as $company): ?>
                <?php
                $companyId = (int) ($company['id'] ?? 0);
                $companyName = trim((string) ($company['trade_name'] ?? '')) !== '' ? (string) $company['trade_name'] : (string) ($company['legal_name'] ?? 'Empresa');
                ?>
                <option value="<?= $companyId; ?>" <?= (int) ($filters['company_id'] ?? 0) === $companyId ? 'selected' : ''; ?>><?= e($companyName); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status" class="support-select rounded-xl border border-white/80 bg-white/80 px-3 py-2 text-sm text-slate-700 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400">
            <option value="">Todos os status</option>
            <?php foreach ($statusLabels as $key => $label): ?>
                <option value="<?= e($key); ?>" <?= (string) ($filters['status'] ?? '') === (string) $key ? 'selected' : ''; ?>><?= e($label); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="priority" class="support-select rounded-xl border border-white/80 bg-white/80 px-3 py-2 text-sm text-slate-700 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400">
            <option value="">Todas as prioridades</option>
            <?php foreach ($priorityLabels as $key => $label): ?>
                <option value="<?= e($key); ?>" <?= (string) ($filters['priority'] ?? '') === (string) $key ? 'selected' : ''; ?>><?= e($label); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="source" class="support-select rounded-xl border border-white/80 bg-white/80 px-3 py-2 text-sm text-slate-700 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400">
            <option value="">Todas as origens</option>
            <?php foreach ($sourceLabels as $key => $label): ?>
                <option value="<?= e($key); ?>" <?= (string) ($filters['source'] ?? '') === (string) $key ? 'selected' : ''; ?>><?= e($label); ?></option>
            <?php endforeach; ?>
        </select>
        <div class="flex gap-2">
            <select name="per_page" class="support-select w-full rounded-xl border border-white/80 bg-white/80 px-3 py-2 text-sm text-slate-700 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400">
                <?php foreach ($paginationOptions as $opt): ?>
                    <option value="<?= (int) $opt; ?>" <?= (int) ($meta['per_page'] ?? 50) === (int) $opt ? 'selected' : ''; ?>><?= (int) $opt; ?>/pagina</option>
                <?php endforeach; ?>
            </select>
            <button class="support-btn-primary rounded-xl bg-gradient-to-r from-cyan-600 to-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:from-cyan-700 hover:to-blue-700">Filtrar</button>
        </div>
        <select name="email_sent" class="support-select rounded-xl border border-white/80 bg-white/80 px-3 py-2 text-sm text-slate-700 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400">
            <option value="">Email: todos</option>
            <option value="1" <?= (string) ($filters['email_sent'] ?? '') === '1' ? 'selected' : ''; ?>>Email enviado</option>
            <option value="0" <?= (string) ($filters['email_sent'] ?? '') === '0' ? 'selected' : ''; ?>>Email pendente</option>
        </select>
        <select name="webhook_forwarded" class="support-select rounded-xl border border-white/80 bg-white/80 px-3 py-2 text-sm text-slate-700 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400">
            <option value="">Webhook: todos</option>
            <option value="1" <?= (string) ($filters['webhook_forwarded'] ?? '') === '1' ? 'selected' : ''; ?>>Webhook enviado</option>
            <option value="0" <?= (string) ($filters['webhook_forwarded'] ?? '') === '0' ? 'selected' : ''; ?>>Webhook pendente</option>
        </select>
        <div class="md:col-span-5"></div>
    </form>

    <div class="space-y-3">
        <?php foreach ($rows as $row): ?>
            <?php
            $ticketId = (int) ($row['id'] ?? 0);
            $ticketCode = trim((string) ($row['ticket_code'] ?? ''));
            if (!preg_match('/^ANEO\d+$/', $ticketCode)) {
                $ticketCode = 'ANEO' . str_pad((string) $ticketId, 3, '0', STR_PAD_LEFT);
            }
            $attachments = $attachmentsByTicket[$ticketId] ?? [];
            $comments = $commentsByTicket[$ticketId] ?? [];
            $status = (string) ($row['status'] ?? 'open');
            $priority = (string) ($row['priority'] ?? 'medium');
            $source = (string) ($row['source'] ?? 'internal');
            $emailSent = (int) ($row['email_sent'] ?? 0) === 1;
            $webhookForwarded = (int) ($row['webhook_forwarded'] ?? 0) === 1;
            $statusBadge = match ($status) {
                'resolved' => 'support-ticket-pill-status-resolved',
                'in_progress' => 'support-ticket-pill-status-progress',
                'closed' => 'support-ticket-pill-status-closed',
                default => 'support-ticket-pill-status-open',
            };
            $priorityBadge = match ($priority) {
                'urgent' => 'support-ticket-pill-priority-urgent',
                'high' => 'support-ticket-pill-priority-high',
                'low' => 'support-ticket-pill-priority-low',
                default => 'support-ticket-pill-priority-medium',
            };
            $sourceBadge = match ($source) {
                'webhook' => 'support-ticket-pill-source-webhook',
                'api' => 'support-ticket-pill-source-api',
                'student_portal' => 'support-ticket-pill-source-student',
                default => 'support-ticket-pill-source-internal',
            };
            $companyName = trim((string) ($row['company_trade_name'] ?? '')) !== '' ? (string) $row['company_trade_name'] : (string) ($row['company_legal_name'] ?? 'Empresa');
            $studentPhone = trim((string) ($row['student_phone'] ?? ''));
            $requesterName = trim((string) (($row['requester_name'] ?? '') !== '' ? $row['requester_name'] : ($row['student_name'] ?? '')));
            $studentWhatsappLink = $studentPhone !== ''
                ? whatsapp_link($studentPhone, 'Ola ' . ($requesterName !== '' ? $requesterName : 'aluno') . ', aqui e da Central Tecnica da ANEO. Estamos entrando em contato sobre o chamado ' . $ticketCode . '.')
                : null;
            ?>
            <article class="support-ticket-card rounded-2xl border border-white/70 bg-white/65 p-4 shadow-sm backdrop-blur-md">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900"><?= e((string) ($row['subject'] ?? 'Chamado')); ?></h3>
                        <p class="text-xs text-slate-500">
                            <?= e($ticketCode); ?>
                            | Empresa: <?= e($companyName); ?>
                            | <?= e((string) ($row['created_at'] ?? '')); ?>
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="support-ticket-pill rounded-full px-2 py-1 text-xs font-semibold <?= $statusBadge; ?>"><?= e($statusLabels[$status] ?? $status); ?></span>
                        <span class="support-ticket-pill rounded-full px-2 py-1 text-xs font-semibold <?= $priorityBadge; ?>"><?= e($priorityLabels[$priority] ?? $priority); ?></span>
                        <span class="support-ticket-pill rounded-full px-2 py-1 text-xs font-semibold <?= $sourceBadge; ?>"><?= e($sourceLabels[$source] ?? $source); ?></span>
                    </div>
                </div>

                <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                    <div class="support-ticket-meta rounded-xl border border-white/75 bg-white/55 p-3 backdrop-blur-sm">
                        <p class="text-[11px] uppercase tracking-wide text-slate-500">Solicitante</p>
                        <p class="mt-1 text-sm font-semibold text-slate-800"><?= e($requesterName !== '' ? $requesterName : '-'); ?></p>
                        <p class="text-xs text-slate-500"><?= e((string) (($row['requester_email'] ?? '') !== '' ? $row['requester_email'] : '-')); ?></p>
                    </div>
                    <div class="support-ticket-meta rounded-xl border border-white/75 bg-white/55 p-3 backdrop-blur-sm">
                        <p class="text-[11px] uppercase tracking-wide text-slate-500">Telefone do aluno</p>
                        <?php if ($studentPhone !== ''): ?>
                            <p class="mt-1 text-sm font-semibold text-slate-800"><?= e($studentPhone); ?></p>
                            <?php if ($studentWhatsappLink): ?>
                                <a href="<?= e($studentWhatsappLink); ?>" target="_blank" rel="noopener" class="support-whatsapp-link mt-2 inline-flex items-center rounded-full px-3 py-1.5 text-xs font-semibold">
                                    WhatsApp do aluno
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="mt-1 text-sm font-semibold text-slate-800">-</p>
                            <p class="text-xs text-slate-500">Disponivel para chamados do Portal do Aluno com telefone cadastrado.</p>
                        <?php endif; ?>
                    </div>
                    <div class="support-ticket-meta rounded-xl border border-white/75 bg-white/55 p-3 backdrop-blur-sm">
                        <p class="text-[11px] uppercase tracking-wide text-slate-500">Email notificacao</p>
                        <p class="mt-1 text-sm font-semibold <?= $emailSent ? 'text-emerald-700' : 'text-amber-700'; ?>">
                            <?= $emailSent ? 'Enviado' : 'Pendente'; ?>
                        </p>
                    </div>
                    <div class="support-ticket-meta rounded-xl border border-white/75 bg-white/55 p-3 backdrop-blur-sm">
                        <p class="text-[11px] uppercase tracking-wide text-slate-500">Webhook externo</p>
                        <p class="mt-1 text-sm font-semibold <?= $webhookForwarded ? 'text-emerald-700' : 'text-rose-700'; ?>">
                            <?= $webhookForwarded ? 'Enviado' : 'Pendente'; ?>
                        </p>
                    </div>
                    <div class="support-ticket-meta rounded-xl border border-white/75 bg-white/55 p-3 backdrop-blur-sm">
                        <p class="text-[11px] uppercase tracking-wide text-slate-500">Ref. externa</p>
                        <p class="mt-1 text-sm font-semibold text-slate-800"><?= e((string) (($row['external_reference'] ?? '') !== '' ? $row['external_reference'] : '-')); ?></p>
                    </div>
                </div>

                <p class="support-ticket-description mt-3 text-sm text-slate-700"><?= nl2br(e((string) ($row['description'] ?? ''))); ?></p>

                <div class="mt-3 grid gap-3 lg:grid-cols-2">
                    <div>
                        <p class="mb-1 text-xs uppercase tracking-wide text-slate-500">Prints anexados</p>
                        <?php if ($attachments === []): ?>
                            <p class="text-sm text-slate-500">Nenhum print anexado.</p>
                        <?php else: ?>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($attachments as $attachment): ?>
                                    <a href="<?= e((string) ($attachment['file_path'] ?? '')); ?>" target="_blank" rel="noopener" class="support-attachment-link rounded-xl border border-white/75 bg-white/60 px-3 py-1.5 text-xs text-slate-700 backdrop-blur-sm hover:bg-white/80">
                                        <?= e((string) ($attachment['file_name'] ?? 'print')); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <p class="mb-1 text-xs uppercase tracking-wide text-slate-500">Comentarios</p>
                        <div class="support-comments-box max-h-36 space-y-1 overflow-y-auto rounded-xl border border-white/75 bg-white/55 p-2 backdrop-blur-sm">
                            <?php foreach ($comments as $comment): ?>
                                <div class="support-comment-item rounded-lg bg-white/85 px-2 py-1.5 text-xs shadow-sm">
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

                <div class="mt-3 grid gap-3 lg:grid-cols-2">
                    <form method="post" action="support.php?route=support/comment" class="flex gap-2">
                        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                        <input type="hidden" name="ticket_id" value="<?= $ticketId; ?>">
                        <input type="text" name="comment" required placeholder="Adicionar comentario..." class="support-field w-full rounded-xl border border-white/80 bg-white/80 px-3 py-2 text-sm text-slate-700 outline-none focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400">
                        <button class="support-btn-ghost rounded-xl border border-white/80 bg-white/85 px-3 py-2 text-sm text-slate-700 backdrop-blur-sm hover:bg-white">Comentar</button>
                    </form>

                    <form method="post" action="support.php?route=support/status" class="grid gap-2 md:grid-cols-[180px_1fr_auto]">
                        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                        <input type="hidden" name="ticket_id" value="<?= $ticketId; ?>">
                        <select name="status" class="support-select rounded-xl border border-white/80 bg-white/80 px-3 py-2 text-sm text-slate-700 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400">
                            <?php foreach ($statusLabels as $key => $label): ?>
                                <option value="<?= e($key); ?>" <?= $status === $key ? 'selected' : ''; ?>><?= e($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="status_note" placeholder="Observacao (opcional)" class="support-field rounded-xl border border-white/80 bg-white/80 px-3 py-2 text-sm text-slate-700 outline-none focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400">
                        <button class="support-btn-primary rounded-xl bg-gradient-to-r from-cyan-600 to-blue-600 px-3 py-2 text-sm font-semibold text-white shadow hover:from-cyan-700 hover:to-blue-700">Atualizar</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>

        <?php if ($rows === []): ?>
            <div class="support-empty-state rounded-2xl border border-white/70 bg-white/65 p-8 text-center text-sm text-slate-500 shadow-sm backdrop-blur-md">
                Nenhum chamado encontrado para os filtros aplicados.
            </div>
        <?php endif; ?>
    </div>

    <div class="support-footer flex flex-wrap items-center justify-between gap-3 text-sm text-slate-600">
        <p>Total: <?= (int) ($meta['total'] ?? 0); ?> chamados | Pagina <?= (int) ($meta['page'] ?? 1); ?>/<?= (int) ($meta['pages'] ?? 1); ?></p>
        <div class="flex gap-2">
            <?php for ($p = 1; $p <= (int) ($meta['pages'] ?? 1); $p++): ?>
                <a href="support.php?<?= build_query(['route' => 'support', 'page' => $p]); ?>" class="support-page-link rounded-xl px-3 py-1 <?= $p === (int) ($meta['page'] ?? 1) ? 'support-page-link-active bg-gradient-to-r from-cyan-600 to-blue-600 text-white shadow' : 'border border-white/80 bg-white/80 text-slate-700 backdrop-blur-sm hover:bg-white'; ?>"><?= $p; ?></a>
            <?php endfor; ?>
        </div>
    </div>
</section>
