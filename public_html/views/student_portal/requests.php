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
    'student_portal' => 'Portal do Aluno',
    'internal' => 'Administrativo',
    'webhook' => 'Webhook',
];
?>
<section class="student-requests-shell space-y-6">
    <div class="rounded-2xl border border-sky-100 bg-white/80 p-5">
        <h2 class="text-2xl font-semibold">Meus Chamados Tecnicos</h2>
        <p class="text-sm text-slate-500">Abra chamados e acompanhe o atendimento pelo codigo (ex.: ANEO001).</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-xl border border-slate-200 bg-white/90 p-4">
            <p class="text-xs uppercase text-slate-500">Total</p>
            <p class="mt-2 text-2xl font-semibold"><?= (int) ($stats['total'] ?? 0); ?></p>
        </article>
        <article class="rounded-xl border border-cyan-200 bg-cyan-50/80 p-4">
            <p class="text-xs uppercase text-cyan-700">Abertos</p>
            <p class="mt-2 text-2xl font-semibold text-cyan-700"><?= (int) ($stats['open'] ?? 0); ?></p>
        </article>
        <article class="rounded-xl border border-amber-200 bg-amber-50/80 p-4">
            <p class="text-xs uppercase text-amber-700">Em andamento</p>
            <p class="mt-2 text-2xl font-semibold text-amber-700"><?= (int) ($stats['in_progress'] ?? 0); ?></p>
        </article>
        <article class="rounded-xl border border-emerald-200 bg-emerald-50/80 p-4">
            <p class="text-xs uppercase text-emerald-700">Resolvidos</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-700"><?= (int) ($stats['resolved'] ?? 0); ?></p>
        </article>
    </div>

    <?php if (!$featureAvailable): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            Estrutura de chamados indisponivel no banco. Execute as migracoes `migrations/20260309_support_tickets.sql` e `migrations/20260317_support_ticket_codes_aneo.sql`.
        </div>
    <?php endif; ?>

    <?php if ($featureAvailable): ?>
    <form method="post" action="<?= route('student/requests/store'); ?>" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 lg:grid-cols-5">
        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
        <label class="block lg:col-span-3">
            <span class="mb-1 block text-sm font-medium">Assunto *</span>
            <input type="text" name="subject" required placeholder="Ex: Nao consigo acessar a aula" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>
        <label class="block lg:col-span-2">
            <span class="mb-1 block text-sm font-medium">Prioridade</span>
            <select name="priority" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <?php foreach ($priorityLabels as $key => $label): ?>
                    <option value="<?= e($key); ?>"><?= e($label); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="block lg:col-span-4">
            <span class="mb-1 block text-sm font-medium">Descricao do problema *</span>
            <textarea name="description" rows="4" required placeholder="Explique o problema com detalhes para o suporte." class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></textarea>
        </label>
        <div class="flex items-end">
            <button class="w-full rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">Abrir chamado</button>
        </div>
    </form>

    <form method="get" action="index.php" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-5">
        <input type="hidden" name="route" value="student/requests">
        <input type="text" name="q" value="<?= e($filters['q'] ?? ''); ?>" placeholder="Buscar por codigo, assunto ou descricao..." class="rounded-lg border border-slate-200 px-3 py-2 text-sm md:col-span-2">
        <select name="status" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option value="">Todos os status</option>
            <?php foreach ($statusLabels as $key => $label): ?>
                <option value="<?= e($key); ?>" <?= (string) ($filters['status'] ?? '') === (string) $key ? 'selected' : ''; ?>><?= e($label); ?></option>
            <?php endforeach; ?>
        </select>
        <div class="flex gap-2 md:col-span-2">
            <select name="per_page" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <?php foreach ($paginationOptions as $opt): ?>
                    <option value="<?= (int) $opt; ?>" <?= (int) ($meta['per_page'] ?? 20) === (int) $opt ? 'selected' : ''; ?>><?= (int) $opt; ?>/pagina</option>
                <?php endforeach; ?>
            </select>
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrar</button>
        </div>
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
            $source = (string) ($row['source'] ?? 'student_portal');
            $statusBadge = match ($status) {
                'resolved' => 'student-request-pill-status-resolved',
                'in_progress' => 'student-request-pill-status-progress',
                'closed' => 'student-request-pill-status-closed',
                default => 'student-request-pill-status-open',
            };
            $priorityBadge = match ($priority) {
                'urgent' => 'student-request-pill-priority-urgent',
                'high' => 'student-request-pill-priority-high',
                'low' => 'student-request-pill-priority-low',
                default => 'student-request-pill-priority-medium',
            };
            $sourceBadge = match ($source) {
                'webhook' => 'student-request-pill-source-webhook',
                'internal' => 'student-request-pill-source-internal',
                'api' => 'student-request-pill-source-api',
                default => 'student-request-pill-source-student',
            };
            ?>
            <article class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900"><?= e((string) ($row['subject'] ?? 'Chamado')); ?></h3>
                        <p class="text-xs text-slate-500">
                            Codigo: <span class="font-semibold text-slate-700"><?= e($ticketCode); ?></span>
                            | Aberto em <?= e((string) ($row['created_at'] ?? '')); ?>
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="student-request-pill rounded-full px-2 py-1 text-xs font-semibold <?= $statusBadge; ?>"><?= e($statusLabels[$status] ?? $status); ?></span>
                        <span class="student-request-pill rounded-full px-2 py-1 text-xs font-semibold <?= $priorityBadge; ?>"><?= e($priorityLabels[$priority] ?? $priority); ?></span>
                        <span class="student-request-pill rounded-full px-2 py-1 text-xs font-semibold <?= $sourceBadge; ?>"><?= e($sourceLabels[$source] ?? $source); ?></span>
                    </div>
                </div>

                <p class="mt-3 text-sm text-slate-700"><?= nl2br(e((string) ($row['description'] ?? ''))); ?></p>

                <div class="mt-3 grid gap-3 lg:grid-cols-2">
                    <div>
                        <p class="mb-1 text-xs uppercase tracking-wide text-slate-500">Anexos</p>
                        <?php if ($attachments === []): ?>
                            <p class="text-sm text-slate-500">Sem anexos.</p>
                        <?php else: ?>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($attachments as $attachment): ?>
                                    <a href="<?= e((string) ($attachment['file_path'] ?? '')); ?>" target="_blank" rel="noopener" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs hover:bg-slate-100">
                                        <?= e((string) ($attachment['file_name'] ?? 'anexo')); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <p class="mb-1 text-xs uppercase tracking-wide text-slate-500">Historico de comentarios</p>
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
                                <p class="text-xs text-slate-500">Sem comentarios ainda.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
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
                <a href="index.php?<?= build_query(['route' => 'student/requests', 'page' => $p]); ?>" class="rounded px-3 py-1 <?= $p === (int) ($meta['page'] ?? 1) ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white hover:bg-slate-50'; ?>"><?= $p; ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</section>
