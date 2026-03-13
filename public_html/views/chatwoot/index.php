<?php
$canChatOpen = has_permission('chat.open');
$dashboardUrl = $integration['conversations_url'] ?? '#';
$webhookToken = trim((string) ($integration['webhook_token'] ?? ''));
$isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$scheme = $isHttps ? 'https' : 'http';
$host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
$scriptDir = dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
$scriptDir = str_replace('\\', '/', $scriptDir);
$scriptDir = ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '\\') ? '' : rtrim($scriptDir, '/');
$baseUrl = trim((string) config('app.base_url', ''));
if ($baseUrl !== '') {
    $baseUrl = rtrim($baseUrl, '/');
} else {
    $baseUrl = $scheme . '://' . $host . $scriptDir;
}
$webhookUrl = $baseUrl . '/index.php?route=chatwoot/webhook';
if ($webhookToken !== '') {
    $webhookUrl .= '&token=' . rawurlencode($webhookToken);
}
?>
<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold">Atendimento (Chatwoot)</h2>
            <p class="text-sm text-slate-500">Central para abrir conversas de aluno/lead e acompanhar vinculos com o CRM.</p>
        </div>
        <?php if ($dashboardUrl !== '#'): ?>
            <a target="_blank" rel="noopener" href="<?= e($dashboardUrl); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Abrir painel Chatwoot</a>
        <?php endif; ?>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase text-slate-500">Integracao</p>
            <?php if (!$integration['enabled']): ?>
                <p class="mt-2 text-base font-semibold text-rose-700">Desativada</p>
                <p class="mt-1 text-xs text-slate-500">Defina `chatwoot.enabled = true` no `config.php`.</p>
            <?php elseif (!$integration['configured']): ?>
                <p class="mt-2 text-base font-semibold text-amber-700">Incompleta</p>
                <p class="mt-1 text-xs text-slate-500">Preencha base URL, account, inbox e token API.</p>
            <?php else: ?>
                <p class="mt-2 text-base font-semibold text-emerald-700">Ativa</p>
                <p class="mt-1 text-xs text-slate-500">Conta <?= (int) $integration['account_id']; ?> | Inbox <?= (int) $integration['inbox_id']; ?></p>
            <?php endif; ?>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase text-slate-500">Vinculos salvos</p>
            <p class="mt-2 text-2xl font-semibold"><?= (int) ($stats['total'] ?? 0); ?></p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase text-slate-500">Com conversa</p>
            <p class="mt-2 text-2xl font-semibold"><?= (int) ($stats['with_conversation'] ?? 0); ?></p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase text-slate-500">Alunos / Leads</p>
            <p class="mt-2 text-2xl font-semibold"><?= (int) ($stats['students'] ?? 0); ?> / <?= (int) ($stats['leads'] ?? 0); ?></p>
        </article>
    </div>

    <?php if (!$featureAvailable): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            A tabela `chatwoot_links` ainda nao existe neste banco. Execute a migracao `migrations/20260304_chatwoot_links.sql`.
        </div>
    <?php endif; ?>
    <?php if (empty($flowFeatureAvailable)): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            A tabela `chatwoot_flow_sessions` ainda nao existe neste banco. Execute a migracao `migrations/20260304_chatwoot_flow_sessions.sql`.
        </div>
    <?php endif; ?>

    <div class="rounded-xl border border-slate-200 bg-white p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-base font-semibold">Webhook de Automacao (menu 1/2, nome/cidade, encaminhamento)</h3>
            <span class="rounded-full px-2 py-1 text-xs font-semibold <?= !empty($integration['bot_enabled']) ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'; ?>">
                <?= !empty($integration['bot_enabled']) ? 'Bot ativo' : 'Bot desativado'; ?>
            </span>
        </div>
        <p class="mt-2 text-sm text-slate-600">Configure esta URL em <strong>Chatwoot > Inbox > Configuration > Webhook URL</strong>.</p>
        <div class="mt-3 grid gap-2 md:grid-cols-[1fr_auto]">
            <input type="text" readonly value="<?= e($webhookUrl); ?>" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs">
            <button type="button" onclick="navigator.clipboard.writeText('<?= e($webhookUrl); ?>')" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs hover:bg-slate-50">Copiar URL</button>
        </div>
        <p class="mt-2 text-xs text-slate-500">Seguranca: troque `chatwoot.webhook_token` no `config.php` antes de ir para producao.</p>
    </div>

    <div class="grid gap-4 rounded-xl border border-slate-200 bg-white p-4 lg:grid-cols-[1fr_auto]">
        <form method="get" action="index.php" class="grid gap-3 md:grid-cols-4">
            <input type="hidden" name="route" value="chatwoot">
            <input type="text" name="q" value="<?= e($filters['q']); ?>" placeholder="Buscar por contato, entidade ou conversa..." class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <select name="entity_type" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Todos os tipos</option>
                <option value="student" <?= $filters['entity_type'] === 'student' ? 'selected' : ''; ?>>Aluno</option>
                <option value="lead" <?= $filters['entity_type'] === 'lead' ? 'selected' : ''; ?>>Lead</option>
                <option value="other" <?= $filters['entity_type'] === 'other' ? 'selected' : ''; ?>>Outro</option>
            </select>
            <select name="per_page" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <?php foreach ($paginationOptions as $opt): ?>
                    <option value="<?= (int) $opt; ?>" <?= (int) $meta['per_page'] === (int) $opt ? 'selected' : ''; ?>><?= (int) $opt; ?>/pagina</option>
                <?php endforeach; ?>
            </select>
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrar</button>
        </form>

        <?php if ($canChatOpen): ?>
            <form method="post" action="<?= route('chatwoot/open-phone'); ?>" class="grid gap-2 md:grid-cols-4">
                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                <input type="hidden" name="entity_type" value="other">
                <input type="hidden" name="entity_id" value="0">
                <input type="hidden" name="return_route" value="chatwoot">
                <input type="text" name="name" placeholder="Nome" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <input type="text" name="phone" placeholder="Telefone" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <input type="email" name="email" placeholder="Email" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <button class="rounded-lg border border-cyan-200 bg-cyan-50 px-3 py-2 text-sm font-semibold text-cyan-700 hover:bg-cyan-100">Nova conversa</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-3">Entidade</th>
                    <th class="px-3 py-3">Contato</th>
                    <th class="px-3 py-3">Conversa</th>
                    <th class="px-3 py-3">Status</th>
                    <th class="px-3 py-3">Atualizado em</th>
                    <th class="px-3 py-3">Acoes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $entityLabel = $row['entity_type'] === 'student' ? 'Aluno' : ($row['entity_type'] === 'lead' ? 'Lead' : 'Outro');
                    $entityRoute = '';
                    if ($row['entity_type'] === 'student') {
                        $entityRoute = route('students/show&id=' . (int) $row['entity_id']);
                    } elseif ($row['entity_type'] === 'lead') {
                        $entityRoute = route('leads/edit&id=' . (int) $row['entity_id']);
                    }
                    ?>
                    <tr class="border-b border-slate-100 hover:bg-slate-50">
                        <td class="px-3 py-3">
                            <p class="font-medium"><?= e($entityLabel); ?> #<?= (int) $row['entity_id']; ?></p>
                            <p class="text-xs text-slate-500"><?= e($row['entity_name'] ?? 'Sem nome'); ?></p>
                        </td>
                        <td class="px-3 py-3">
                            <p class="font-medium"><?= e($row['contact_name'] ?: '-'); ?></p>
                            <p class="text-xs text-slate-500"><?= e($row['contact_phone'] ?: '-'); ?></p>
                            <p class="text-xs text-slate-500"><?= e($row['contact_email'] ?: '-'); ?></p>
                        </td>
                        <td class="px-3 py-3">
                            <p class="font-medium">#<?= e((string) ($row['conversation_id'] ?? '-')); ?></p>
                            <?php if (!empty($row['conversation_url'])): ?>
                                <a target="_blank" rel="noopener" href="<?= e($row['conversation_url']); ?>" class="text-xs text-cyan-700 underline">Abrir conversa</a>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-3">
                            <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700"><?= e($row['status'] ?: 'open'); ?></span>
                        </td>
                        <td class="px-3 py-3"><?= e($row['updated_at']); ?></td>
                        <td class="px-3 py-3">
                            <div class="flex flex-wrap gap-2">
                                <?php if ($entityRoute !== ''): ?>
                                    <a href="<?= e($entityRoute); ?>" class="rounded border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50">Abrir cadastro</a>
                                <?php endif; ?>
                                <?php if ($canChatOpen && $row['entity_type'] === 'student'): ?>
                                    <form method="post" action="<?= route('chatwoot/open-student'); ?>">
                                        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                        <input type="hidden" name="student_id" value="<?= (int) $row['entity_id']; ?>">
                                        <input type="hidden" name="return_route" value="chatwoot">
                                        <button class="rounded border border-cyan-200 bg-cyan-50 px-2 py-1 text-xs text-cyan-700 hover:bg-cyan-100">Reabrir chat</button>
                                    </form>
                                <?php elseif ($canChatOpen && $row['entity_type'] === 'lead'): ?>
                                    <form method="post" action="<?= route('chatwoot/open-lead'); ?>">
                                        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                        <input type="hidden" name="lead_id" value="<?= (int) $row['entity_id']; ?>">
                                        <input type="hidden" name="return_route" value="chatwoot">
                                        <button class="rounded border border-cyan-200 bg-cyan-50 px-2 py-1 text-xs text-cyan-700 hover:bg-cyan-100">Reabrir chat</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?>
                    <tr>
                        <td colspan="6" class="px-3 py-6 text-center text-slate-500">Nenhum vinculo de atendimento encontrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
        <p>Total: <?= (int) $meta['total']; ?> registros | Pagina <?= (int) $meta['page']; ?>/<?= (int) $meta['pages']; ?></p>
        <div class="flex gap-2">
            <?php for ($p = 1; $p <= (int) $meta['pages']; $p++): ?>
                <a href="index.php?<?= build_query(['route' => 'chatwoot', 'page' => $p]); ?>" class="rounded px-3 py-1 <?= $p === (int) $meta['page'] ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white hover:bg-slate-50'; ?>"><?= $p; ?></a>
            <?php endfor; ?>
        </div>
    </div>
</section>
