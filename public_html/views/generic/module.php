<?php
$canManage = has_permission($permission . '.manage');
$isHelpModule = ($module ?? '') === 'help';
$helpAi = is_array($helpAi ?? null) ? $helpAi : [];
$helpAiSessions = is_array($helpAi['sessions'] ?? null) ? $helpAi['sessions'] : [];
$helpAiMessages = is_array($helpAi['messages'] ?? null) ? $helpAi['messages'] : [];
$helpAiSessionId = (int) ($helpAi['session_id'] ?? 0);
$helpAiHistoryAvailable = !empty($helpAi['history_available']);
$helpAiEnabled = !empty($helpAi['ai_enabled']);
$helpAiConfigured = !empty($helpAi['ai_configured']);
$helpAiProvider = trim((string) ($helpAi['ai_provider'] ?? ''));
$helpAiModel = trim((string) ($helpAi['ai_model'] ?? ''));
?>
<?php if ($isHelpModule): ?>
<style>
    .ai-jully-shell {
        --jul-bg-top: #152d68;
        --jul-bg-bottom: #081837;
        --jul-panel: #11275b;
        --jul-panel-soft: #0d214f;
        --jul-border: #1a5adb;
        --jul-accent: #1db8d9;
        --jul-accent-strong: #10a9cc;
        --jul-text: #edf5ff;
        --jul-muted: #9fc4ff;
        color: var(--jul-text);
        border-color: var(--jul-border) !important;
        background:
            radial-gradient(circle at 8% -10%, rgba(55, 123, 255, 0.4), transparent 34%),
            radial-gradient(circle at 88% 0%, rgba(18, 191, 223, 0.25), transparent 28%),
            linear-gradient(145deg, var(--jul-bg-top), var(--jul-bg-bottom));
        box-shadow: 0 20px 48px rgba(3, 10, 30, 0.4);
    }

    .ai-jully-title {
        color: #f5f9ff;
        letter-spacing: 0.01em;
    }

    .ai-jully-subtitle {
        color: var(--jul-muted);
    }

    .ai-jully-status {
        border: 1px solid rgba(67, 130, 255, 0.6);
        background: rgba(8, 21, 54, 0.55);
        color: #dce9ff;
    }

    .ai-jully-warning {
        border: 1px solid rgba(246, 187, 80, 0.6);
        background: rgba(95, 62, 11, 0.38);
        color: #ffe0a6;
    }

    .ai-jully-grid {
        align-items: stretch;
    }

    .ai-jully-sidebar {
        border: 1px solid rgba(66, 130, 255, 0.5);
        background: linear-gradient(180deg, rgba(16, 39, 92, 0.96), rgba(8, 22, 54, 0.95));
    }

    .ai-jully-label {
        color: #94bcff;
    }

    .ai-jully-btn-secondary {
        border: 1px solid rgba(75, 145, 255, 0.65);
        background: rgba(240, 248, 255, 0.08);
        color: #ddedff;
    }

    .ai-jully-btn-secondary:hover {
        background: rgba(240, 248, 255, 0.16);
    }

    .ai-jully-session {
        display: block;
        border: 1px solid rgba(64, 124, 240, 0.55);
        background: rgba(9, 22, 53, 0.6);
        color: #dcecff;
        transition: all 0.2s ease;
    }

    .ai-jully-session:hover {
        border-color: rgba(86, 176, 238, 0.85);
        background: rgba(10, 30, 71, 0.85);
    }

    .ai-jully-session.is-active {
        border-color: var(--jul-accent);
        background: linear-gradient(135deg, rgba(16, 122, 192, 0.34), rgba(20, 169, 204, 0.22));
        box-shadow: 0 0 0 1px rgba(29, 184, 217, 0.35) inset;
    }

    .ai-jully-session-preview {
        color: #8db0e6;
    }

    .ai-jully-empty-side {
        border: 1px dashed rgba(68, 137, 255, 0.5);
        background: rgba(7, 19, 49, 0.55);
        color: #8fb4ee;
    }

    .ai-jully-chat {
        border: 1px solid rgba(67, 131, 255, 0.56);
        background: linear-gradient(180deg, rgba(11, 27, 66, 0.9), rgba(7, 19, 48, 0.95));
    }

    .ai-jully-messages {
        background: linear-gradient(180deg, rgba(6, 16, 40, 0.52), rgba(4, 11, 29, 0.32));
    }

    .ai-jully-bubble {
        border-color: rgba(69, 134, 252, 0.52);
    }

    .ai-jully-bubble-assistant {
        background: rgba(8, 20, 50, 0.78);
        color: #edf5ff;
    }

    .ai-jully-bubble-user {
        border-color: rgba(34, 204, 232, 0.64);
        background: linear-gradient(135deg, rgba(30, 184, 217, 0.29), rgba(20, 128, 199, 0.34));
        color: #dcf8ff;
    }

    .ai-jully-inline-warning {
        border: 1px solid rgba(246, 187, 80, 0.55);
        background: rgba(95, 62, 11, 0.3);
        color: #ffd993;
    }

    .ai-jully-sources {
        color: #8ab5f3;
    }

    .ai-jully-time {
        color: #7aa0dd;
    }

    .ai-jully-empty-state {
        border: 1px dashed rgba(73, 142, 255, 0.52);
        background: rgba(7, 19, 48, 0.62);
        color: #9ec0f8;
    }

    .ai-jully-input-wrap {
        border-top: 1px solid rgba(64, 125, 238, 0.42);
        background: linear-gradient(180deg, rgba(8, 20, 50, 0.75), rgba(6, 15, 39, 0.9));
    }

    .ai-jully-textarea {
        border: 1px solid rgba(70, 138, 255, 0.55);
        background: rgba(5, 15, 38, 0.82);
        color: #f4f8ff;
    }

    .ai-jully-textarea::placeholder {
        color: #81a4dc;
    }

    .ai-jully-textarea:focus {
        border-color: var(--jul-accent);
        box-shadow: 0 0 0 2px rgba(29, 184, 217, 0.24);
    }

    .ai-jully-helper {
        color: #8fb2ea;
    }

    .ai-jully-submit {
        background: linear-gradient(90deg, var(--jul-accent), var(--jul-accent-strong));
        color: #04203a;
    }

    .ai-jully-submit:hover {
        filter: brightness(1.05);
    }

    @media (max-width: 1024px) {
        .ai-jully-status {
            width: 100%;
        }
    }
</style>
<?php endif; ?>
<section class="space-y-6">
    <?php if ($isHelpModule): ?>
        <div class="ai-jully-shell space-y-4 rounded-2xl border p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="ai-jully-title text-lg font-semibold">Chat IA Jully</h3>
                    <p class="ai-jully-subtitle text-sm">Assistente operacional da ANEO para financeiro, renegociacao, comercial e acompanhamento do negocio.</p>
                </div>
                <div class="ai-jully-status rounded-xl px-3 py-2 text-xs">
                    <p><strong>Status IA:</strong> <?= $helpAiEnabled ? ($helpAiConfigured ? 'Ativo e configurado' : 'Ativo sem credenciais') : 'Desativado'; ?></p>
                    <p><strong>Provider:</strong> <?= e($helpAiProvider !== '' ? $helpAiProvider : '-'); ?> | <strong>Modelo:</strong> <?= e($helpAiModel !== '' ? $helpAiModel : '-'); ?></p>
                </div>
            </div>

            <?php if (!$helpAiHistoryAvailable): ?>
                <div class="ai-jully-warning rounded-xl px-3 py-2 text-sm">
                    Histórico de conversas indisponivel. Execute a migração <code>migrations/20260310_admin_ai_chat.sql</code>.
                </div>
            <?php endif; ?>

            <div class="ai-jully-grid grid gap-4 lg:grid-cols-[260px_1fr]">
                <aside class="ai-jully-sidebar rounded-2xl p-3">
                    <div class="flex items-center justify-between">
                        <p class="ai-jully-label text-xs font-semibold uppercase tracking-wide">Conversas</p>
                        <?php if ($helpAiHistoryAvailable): ?>
                            <form method="post" action="<?= route('ai-chat/session'); ?>">
                                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                <input type="hidden" name="return_route" value="help">
                                <button class="ai-jully-btn-secondary rounded-lg px-2 py-1 text-xs">+ Novo</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div class="mt-2 space-y-2">
                        <?php foreach ($helpAiSessions as $chatRow): ?>
                            <?php
                            $chatId = (int) ($chatRow['id'] ?? 0);
                            $chatActive = $chatId > 0 && $chatId === $helpAiSessionId;
                            $chatTitle = trim((string) ($chatRow['title'] ?? 'Novo chat'));
                            $lastMessage = trim((string) ($chatRow['last_message'] ?? ''));
                            if ($lastMessage !== '' && strlen($lastMessage) > 80) {
                                $lastMessage = substr($lastMessage, 0, 80) . '...';
                            }
                            ?>
                            <a href="<?= route('help&chat_session_id=' . $chatId); ?>" class="ai-jully-session rounded-xl px-3 py-2 text-sm <?= $chatActive ? 'is-active' : ''; ?>">
                                <p class="font-semibold"><?= e($chatTitle !== '' ? $chatTitle : 'Novo chat'); ?></p>
                                <?php if ($lastMessage !== ''): ?>
                                    <p class="ai-jully-session-preview mt-1 text-xs"><?= e($lastMessage); ?></p>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>

                        <?php if ($helpAiSessions === []): ?>
                            <p class="ai-jully-empty-side rounded-xl px-3 py-3 text-xs">
                                Nenhuma conversa salva.
                            </p>
                        <?php endif; ?>
                    </div>
                </aside>

                <div class="ai-jully-chat rounded-2xl">
                    <div id="help-ai-messages" class="ai-jully-messages h-[52vh] space-y-3 overflow-y-auto p-4">
                        <?php foreach ($helpAiMessages as $message): ?>
                            <?php
                            $isUser = (string) ($message['role'] ?? '') === 'user';
                            $meta = is_array($message['metadata'] ?? null) ? $message['metadata'] : [];
                            $warning = trim((string) ($meta['warning'] ?? ''));
                            $sources = is_array($meta['sources'] ?? null) ? $meta['sources'] : [];
                            ?>
                            <article class="flex <?= $isUser ? 'justify-end' : 'justify-start'; ?>">
                                <div class="ai-jully-bubble max-w-[88%] rounded-2xl border px-4 py-3 text-sm shadow-sm <?= $isUser ? 'ai-jully-bubble-user' : 'ai-jully-bubble-assistant'; ?>">
                                    <p class="whitespace-pre-wrap"><?= e((string) ($message['content'] ?? '')); ?></p>
                                    <?php if (!$isUser && $warning !== ''): ?>
                                        <p class="ai-jully-inline-warning mt-2 rounded-lg px-2 py-1 text-xs"><?= e($warning); ?></p>
                                    <?php endif; ?>
                                    <?php if (!$isUser && $sources !== []): ?>
                                        <p class="ai-jully-sources mt-2 text-xs">Fontes: <?= e(implode(', ', array_map('strval', $sources))); ?></p>
                                    <?php endif; ?>
                                    <p class="ai-jully-time mt-2 text-[11px]"><?= e((string) ($message['created_at'] ?? '')); ?></p>
                                </div>
                            </article>
                        <?php endforeach; ?>

                        <?php if ($helpAiMessages === []): ?>
                            <div id="help-ai-empty" class="ai-jully-empty-state rounded-xl px-4 py-6 text-sm">
                                Pergunte algo como:
                                <br>- Qual e o saldo vencido hoje e quantos alunos estão em atraso?
                                <br>- Temos negociacoes ou aditivos mobile pendentes?
                                <br>- Quais leads o comercial precisa atacar primeiro?
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="ai-jully-input-wrap p-4">
                        <form id="help-ai-form" method="post" action="<?= route('ai-chat/ask'); ?>" class="space-y-3">
                            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                            <input type="hidden" name="session_id" id="help-ai-session-id" value="<?= $helpAiSessionId; ?>">
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="help-ai-suggestion rounded-full border border-slate-200 bg-white px-3 py-1 text-xs text-slate-700 hover:bg-slate-50" data-prompt="Qual e o saldo vencido hoje e quem precisa de acao imediata?">Financeiro critico</button>
                                <button type="button" class="help-ai-suggestion rounded-full border border-slate-200 bg-white px-3 py-1 text-xs text-slate-700 hover:bg-slate-50" data-prompt="Temos negociacoes ou aditivos mobile pendentes agora?">Renegociacoes pendentes</button>
                                <button type="button" class="help-ai-suggestion rounded-full border border-slate-200 bg-white px-3 py-1 text-xs text-slate-700 hover:bg-slate-50" data-prompt="Quais leads o comercial precisa priorizar hoje?">Prioridade comercial</button>
                                <button type="button" class="help-ai-suggestion rounded-full border border-slate-200 bg-white px-3 py-1 text-xs text-slate-700 hover:bg-slate-50" data-prompt="O que eu devo priorizar agora na operacao?">O que priorizar</button>
                            </div>
                            <textarea name="message" id="help-ai-input" rows="3" maxlength="2000" required placeholder="Pergunte sobre financeiro, renegociacao, comercial ou prioridades da operacao..." class="ai-jully-textarea w-full rounded-xl px-3 py-2 text-sm outline-none"></textarea>
                            <div class="flex items-center justify-between gap-2">
                                <p class="ai-jully-helper text-xs">A resposta usa os dados internos da empresa ativa com foco operacional.</p>
                                <button id="help-ai-submit" class="ai-jully-submit rounded-xl px-4 py-2 text-sm font-semibold">Enviar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!$isHelpModule): ?>
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-semibold"><?= e($title); ?></h2>
                <p class="text-sm text-slate-500">Módulo estrutural (CRUD básico com status, responsável, prioridade e observações).</p>
            </div>
        </div>

        <?php if ($canManage): ?>
            <form method="post" action="<?= route($module . '/store'); ?>" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-5">
                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                <input type="text" name="title" required placeholder="Título" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <input type="text" name="responsible" placeholder="Responsável" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <select name="status" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <option value="aberto">Aberto</option>
                    <option value="em_andamento">Em andamento</option>
                    <option value="concluido">Concluido</option>
                    <option value="cancelado">Cancelado</option>
                </select>
                <select name="priority" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <option value="baixa">Baixa</option>
                    <option value="media">Media</option>
                    <option value="alta">Alta</option>
                    <option value="urgente">Urgente</option>
                </select>
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Adicionar</button>

                <input type="date" name="due_date" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <input type="text" name="notes" placeholder="Observações" class="rounded-lg border border-slate-200 px-3 py-2 text-sm md:col-span-3">
            </form>
        <?php endif; ?>

        <form method="get" action="index.php" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-4">
            <input type="hidden" name="route" value="<?= e($module); ?>">
            <input type="text" name="q" value="<?= e($filters['q']); ?>" placeholder="Buscar..." class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <select name="status" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Todos os status</option>
                <option value="aberto" <?= $filters['status'] === 'aberto' ? 'selected' : ''; ?>>Aberto</option>
                <option value="em_andamento" <?= $filters['status'] === 'em_andamento' ? 'selected' : ''; ?>>Em andamento</option>
                <option value="concluido" <?= $filters['status'] === 'concluido' ? 'selected' : ''; ?>>Concluido</option>
                <option value="cancelado" <?= $filters['status'] === 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
            </select>
            <select name="per_page" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <?php foreach ($paginationOptions as $opt): ?>
                    <option value="<?= (int) $opt; ?>" <?= (int) $meta['per_page'] === (int) $opt ? 'selected' : ''; ?>><?= (int) $opt; ?>/página</option>
                <?php endforeach; ?>
            </select>
            <button class="rounded-lg border border-slate-200 px-4 py-2 text-sm hover:bg-slate-50">Filtrar</button>
        </form>

        <div class="space-y-2 rounded-xl border border-slate-200 bg-white p-4">
            <?php foreach ($rows as $row): ?>
                <article class="rounded-lg border border-slate-100 p-3 text-sm">
                    <?php if ($canManage): ?>
                        <form method="post" action="<?= route($module . '/update'); ?>" class="grid gap-2 md:grid-cols-6">
                            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                            <input type="hidden" name="id" value="<?= (int) $row['id']; ?>">

                            <input type="text" name="title" value="<?= e($row['title']); ?>" class="rounded-lg border border-slate-200 px-2 py-1 text-sm md:col-span-2">
                            <input type="text" name="responsible" value="<?= e($row['responsible']); ?>" class="rounded-lg border border-slate-200 px-2 py-1 text-sm">
                            <select name="status" class="rounded-lg border border-slate-200 px-2 py-1 text-sm">
                                <?php foreach (['aberto', 'em_andamento', 'concluido', 'cancelado'] as $status): ?>
                                    <option value="<?= $status; ?>" <?= $row['status'] === $status ? 'selected' : ''; ?>><?= $status; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="priority" class="rounded-lg border border-slate-200 px-2 py-1 text-sm">
                                <?php foreach (['baixa', 'media', 'alta', 'urgente'] as $priority): ?>
                                    <option value="<?= $priority; ?>" <?= $row['priority'] === $priority ? 'selected' : ''; ?>><?= $priority; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="date" name="due_date" value="<?= e($row['due_date']); ?>" class="rounded-lg border border-slate-200 px-2 py-1 text-sm">
                            <input type="text" name="notes" value="<?= e($row['notes']); ?>" class="rounded-lg border border-slate-200 px-2 py-1 text-sm md:col-span-4">

                            <div class="flex gap-2 md:col-span-6">
                                <button class="rounded-lg border border-slate-200 px-3 py-1 text-xs hover:bg-slate-50">Salvar</button>
                        </form>
                                <form method="post" action="<?= route($module . '/delete'); ?>" onsubmit="return confirm('Excluir registro?');">
                                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                    <input type="hidden" name="id" value="<?= (int) $row['id']; ?>">
                                    <button class="rounded-lg border border-rose-200 px-3 py-1 text-xs text-rose-700 hover:bg-rose-50">Excluir</button>
                                </form>
                            </div>
                    <?php else: ?>
                        <div class="grid gap-2 md:grid-cols-3">
                            <p><strong>Título:</strong> <?= e($row['title']); ?></p>
                            <p><strong>Status:</strong> <?= e($row['status']); ?></p>
                            <p><strong>Prioridade:</strong> <?= e($row['priority']); ?></p>
                            <p><strong>Responsável:</strong> <?= e($row['responsible']); ?></p>
                            <p><strong>Prazo:</strong> <?= e($row['due_date']); ?></p>
                            <p class="md:col-span-3"><strong>Observações:</strong> <?= e($row['notes']); ?></p>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>

            <?php if ($rows === []): ?>
                <p class="text-sm text-slate-500">Nenhum registro encontrado.</p>
            <?php endif; ?>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
            <p>Total: <?= (int) $meta['total']; ?> registros | Página <?= (int) $meta['page']; ?>/<?= (int) $meta['pages']; ?></p>
            <div class="flex gap-2">
                <?php for ($p = 1; $p <= (int) $meta['pages']; $p++): ?>
                    <a href="index.php?<?= build_query(['route' => $module, 'page' => $p]); ?>" class="rounded px-3 py-1 <?= $p === (int) $meta['page'] ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white hover:bg-slate-50'; ?>"><?= $p; ?></a>
                <?php endfor; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php if ($isHelpModule): ?>
<script>
(function () {
    const form = document.getElementById('help-ai-form');
    const input = document.getElementById('help-ai-input');
    const submitBtn = document.getElementById('help-ai-submit');
    const messagesEl = document.getElementById('help-ai-messages');
    const sessionEl = document.getElementById('help-ai-session-id');
    const suggestionButtons = Array.from(document.querySelectorAll('.help-ai-suggestion'));
    const historyAvailable = <?= $helpAiHistoryAvailable ? 'true' : 'false'; ?>;

    if (!form || !input || !submitBtn || !messagesEl || !sessionEl) {
        return;
    }

    const scrollBottom = () => {
        messagesEl.scrollTop = messagesEl.scrollHeight;
    };

    const escapeHtml = (value) => String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const formatText = (value) => escapeHtml(value).replace(/\n/g, '<br>');

    const renderMeta = (warning, sources) => {
        let html = '';
        if (warning) {
            html += '<p class="ai-jully-inline-warning mt-2 rounded-lg px-2 py-1 text-xs">' + escapeHtml(warning) + '</p>';
        }
        if (Array.isArray(sources) && sources.length > 0) {
            html += '<p class="ai-jully-sources mt-2 text-xs">Fontes: ' + escapeHtml(sources.join(', ')) + '</p>';
        }
        return html;
    };

    const appendMessage = (role, content, options = {}) => {
        const emptyState = document.getElementById('help-ai-empty');
        if (emptyState) {
            emptyState.remove();
        }

        const isUser = role === 'user';
        const article = document.createElement('article');
        article.className = 'flex ' + (isUser ? 'justify-end' : 'justify-start');

        const bubble = document.createElement('div');
        bubble.className = 'ai-jully-bubble max-w-[88%] rounded-2xl border px-4 py-3 text-sm shadow-sm '
            + (isUser
                ? 'ai-jully-bubble-user'
                : 'ai-jully-bubble-assistant');

        const now = new Date().toLocaleString('pt-BR');
        bubble.innerHTML = '<p class="whitespace-pre-wrap">' + formatText(content) + '</p>'
            + (!isUser ? renderMeta(options.warning || '', options.sources || []) : '')
            + '<p class="ai-jully-time mt-2 text-[11px]">' + escapeHtml(now) + '</p>';

        article.appendChild(bubble);
        messagesEl.appendChild(article);
        scrollBottom();
    };

    scrollBottom();

    suggestionButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const prompt = button.getAttribute('data-prompt') || '';
            if (!prompt) {
                return;
            }

            input.value = prompt;
            input.focus();
        });
    });

    input.addEventListener('keydown', (event) => {
        if (event.isComposing) {
            return;
        }

        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            if (!submitBtn.disabled) {
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                }
            }
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const message = input.value.trim();
        if (!message) {
            return;
        }

        const formData = new FormData(form);
        formData.set('message', message);

        appendMessage('user', message);
        input.value = '';
        input.focus();
        submitBtn.disabled = true;
        submitBtn.classList.add('opacity-70', 'cursor-not-allowed');

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const payload = await response.json();
            if (!response.ok || !payload || !payload.ok) {
                const err = payload && payload.message ? payload.message : 'Falha ao consultar assistente.';
                appendMessage('assistant', 'Erro: ' + err, { warning: 'Não foi possível concluir a consulta.' });
                return;
            }

            if (historyAvailable && payload.session_id && Number(sessionEl.value || '0') <= 0) {
                sessionEl.value = String(payload.session_id);
                const url = new URL(window.location.href);
                url.searchParams.set('route', 'help');
                url.searchParams.set('chat_session_id', String(payload.session_id));
                window.history.replaceState({}, '', url.toString());
            }

            appendMessage('assistant', payload.answer || '', {
                warning: payload.warning || '',
                sources: Array.isArray(payload.sources) ? payload.sources : []
            });
        } catch (error) {
            appendMessage('assistant', 'Erro de rede ao consultar o assistente.', {
                warning: 'Verifique conexao e configuração da API.'
            });
        } finally {
            submitBtn.disabled = false;
            submitBtn.classList.remove('opacity-70', 'cursor-not-allowed');
        }
    });
})();
</script>
<?php endif; ?>
