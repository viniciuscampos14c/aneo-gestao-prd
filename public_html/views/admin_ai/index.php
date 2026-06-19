<?php
$sessions = is_array($sessions ?? null) ? $sessions : [];
$messages = is_array($messages ?? null) ? $messages : [];
$sessionId = (int) ($sessionId ?? 0);
$historyAvailable = !empty($historyAvailable);
$aiEnabled = !empty($aiEnabled);
$aiConfigured = !empty($aiConfigured);
$aiProvider = trim((string) ($aiProvider ?? ''));
$aiModel = trim((string) ($aiModel ?? ''));
?>
<section class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold text-slate-900">Chat IA Jully</h2>
            <p class="text-sm text-slate-500">Assistente operacional da ANEO para financeiro, renegociacao, comercial e acompanhamento do negocio.</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600">
            <p><strong>Status IA:</strong> <?= $aiEnabled ? ($aiConfigured ? 'Ativo e configurado' : 'Ativo sem credenciais') : 'Desativado'; ?></p>
            <p><strong>Provider:</strong> <?= e($aiProvider !== '' ? $aiProvider : '-'); ?> | <strong>Modelo:</strong> <?= e($aiModel !== '' ? $aiModel : '-'); ?></p>
        </div>
    </div>

    <?php if (!$historyAvailable): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Histórico no banco indisponivel. Execute a migração <code>migrations/20260310_admin_ai_chat.sql</code>.
            O chat segue funcional, mas os dados ficam apenas na sessao atual do navegador.
        </div>
    <?php endif; ?>

    <?php if (!$aiEnabled || !$aiConfigured): ?>
        <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
            Configure a chave da API no bloco <code>admin_ai</code> do <code>public_html/config.php</code> (ou em <code>company_integrations</code> com a chave <code>admin_ai</code>).
            Sem isso, o sistema responde com resumo tecnico do banco interno (fallback).
        </div>
    <?php endif; ?>

    <div class="grid gap-4 lg:grid-cols-[280px_1fr]">
        <aside class="rounded-2xl border border-slate-200 bg-white p-4">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Conversas</h3>
                <?php if ($historyAvailable): ?>
                    <form method="post" action="<?= route('ai-chat/session'); ?>">
                        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                        <button class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            + Novo
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="mt-3 space-y-2">
                <?php foreach ($sessions as $row): ?>
                    <?php
                    $rowId = (int) ($row['id'] ?? 0);
                    $active = $rowId > 0 && $rowId === $sessionId;
                    $title = trim((string) ($row['title'] ?? 'Novo chat'));
                    $lastMessage = trim((string) ($row['last_message'] ?? ''));
                    if ($lastMessage !== '' && strlen($lastMessage) > 80) {
                        $lastMessage = substr($lastMessage, 0, 80) . '...';
                    }
                    ?>
                    <a href="<?= route('ai-chat&session_id=' . $rowId); ?>" class="block rounded-xl border px-3 py-2 text-sm <?= $active ? 'border-cyan-400 bg-cyan-50 text-cyan-900' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'; ?>">
                        <p class="font-semibold"><?= e($title !== '' ? $title : 'Novo chat'); ?></p>
                        <?php if ($lastMessage !== ''): ?>
                            <p class="mt-1 text-xs text-slate-500"><?= e($lastMessage); ?></p>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>

                <?php if ($sessions === []): ?>
                    <p class="rounded-xl border border-dashed border-slate-200 px-3 py-3 text-xs text-slate-500">
                        Nenhuma conversa salva ainda.
                    </p>
                <?php endif; ?>
            </div>
        </aside>

        <div class="rounded-2xl border border-slate-200 bg-white">
            <div id="ai-chat-messages" class="h-[62vh] space-y-3 overflow-y-auto p-4">
                <?php foreach ($messages as $message): ?>
                    <?php
                    $role = (string) ($message['role'] ?? 'assistant');
                    $isUser = $role === 'user';
                    $meta = is_array($message['metadata'] ?? null) ? $message['metadata'] : [];
                    $warning = trim((string) ($meta['warning'] ?? ''));
                    $sources = is_array($meta['sources'] ?? null) ? $meta['sources'] : [];
                    ?>
                    <article class="flex <?= $isUser ? 'justify-end' : 'justify-start'; ?>">
                        <div class="max-w-[88%] rounded-2xl border px-4 py-3 text-sm shadow-sm <?= $isUser ? 'border-cyan-200 bg-cyan-50 text-cyan-900' : 'border-slate-200 bg-slate-50 text-slate-800'; ?>">
                            <p class="whitespace-pre-wrap"><?= e((string) ($message['content'] ?? '')); ?></p>
                            <?php if (!$isUser && $warning !== ''): ?>
                                <p class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-2 py-1 text-xs text-amber-700"><?= e($warning); ?></p>
                            <?php endif; ?>
                            <?php if (!$isUser && $sources !== []): ?>
                                <p class="mt-2 text-xs text-slate-500">Fontes: <?= e(implode(', ', array_map('strval', $sources))); ?></p>
                            <?php endif; ?>
                            <p class="mt-2 text-[11px] text-slate-400"><?= e((string) ($message['created_at'] ?? '')); ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>

                <?php if ($messages === []): ?>
                    <div id="ai-empty-state" class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                        Pergunte algo como:
                        <br>- Qual e o saldo vencido hoje e quantos alunos estão em atraso?
                        <br>- Temos negociacoes ou aditivos mobile pendentes?
                        <br>- Quais leads o comercial precisa atacar primeiro?
                    </div>
                <?php endif; ?>
            </div>

            <div class="border-t border-slate-200 p-4">
                <form id="ai-chat-form" method="post" action="<?= route('ai-chat/ask'); ?>" class="space-y-3">
                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                    <input type="hidden" name="session_id" id="ai-session-id" value="<?= $sessionId; ?>">
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="ai-suggestion rounded-full border border-slate-200 bg-white px-3 py-1 text-xs text-slate-700 hover:bg-slate-50" data-prompt="Qual e o saldo vencido hoje e quem precisa de acao imediata?">Financeiro critico</button>
                        <button type="button" class="ai-suggestion rounded-full border border-slate-200 bg-white px-3 py-1 text-xs text-slate-700 hover:bg-slate-50" data-prompt="Temos negociacoes ou aditivos mobile pendentes agora?">Renegociacoes pendentes</button>
                        <button type="button" class="ai-suggestion rounded-full border border-slate-200 bg-white px-3 py-1 text-xs text-slate-700 hover:bg-slate-50" data-prompt="Quais leads o comercial precisa priorizar hoje?">Prioridade comercial</button>
                        <button type="button" class="ai-suggestion rounded-full border border-slate-200 bg-white px-3 py-1 text-xs text-slate-700 hover:bg-slate-50" data-prompt="O que eu devo priorizar agora na operacao?">O que priorizar</button>
                    </div>
                    <textarea
                        name="message"
                        id="ai-message-input"
                        rows="3"
                        required
                        maxlength="2000"
                        placeholder="Pergunte sobre financeiro, renegociacao, comercial ou prioridades da operacao..."
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-cyan-500"
                    ></textarea>
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs text-slate-500">Cada pergunta consulta o banco interno da empresa ativa e a Jully responde com foco operacional.</p>
                        <button id="ai-submit-btn" class="rounded-xl bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700">
                            Enviar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<script>
(function () {
    const form = document.getElementById('ai-chat-form');
    const input = document.getElementById('ai-message-input');
    const submitBtn = document.getElementById('ai-submit-btn');
    const messagesEl = document.getElementById('ai-chat-messages');
    const sessionEl = document.getElementById('ai-session-id');
    const suggestionButtons = Array.from(document.querySelectorAll('.ai-suggestion'));
    const historyAvailable = <?= $historyAvailable ? 'true' : 'false'; ?>;

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
            html += '<p class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-2 py-1 text-xs text-amber-700">' + escapeHtml(warning) + '</p>';
        }
        if (Array.isArray(sources) && sources.length > 0) {
            html += '<p class="mt-2 text-xs text-slate-500">Fontes: ' + escapeHtml(sources.join(', ')) + '</p>';
        }
        return html;
    };

    const appendMessage = (role, content, options = {}) => {
        const isUser = role === 'user';
        const emptyState = document.getElementById('ai-empty-state');
        if (emptyState) {
            emptyState.remove();
        }

        const article = document.createElement('article');
        article.className = 'flex ' + (isUser ? 'justify-end' : 'justify-start');

        const bubble = document.createElement('div');
        bubble.className = 'max-w-[88%] rounded-2xl border px-4 py-3 text-sm shadow-sm '
            + (isUser
                ? 'border-cyan-200 bg-cyan-50 text-cyan-900'
                : 'border-slate-200 bg-slate-50 text-slate-800');

        const now = new Date();
        const timestamp = now.toLocaleString('pt-BR');

        bubble.innerHTML = '<p class="whitespace-pre-wrap">' + formatText(content) + '</p>'
            + (!isUser ? renderMeta(options.warning || '', options.sources || []) : '')
            + '<p class="mt-2 text-[11px] text-slate-400">' + escapeHtml(timestamp) + '</p>';

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
                const messageError = payload && payload.message ? payload.message : 'Falha ao consultar assistente.';
                appendMessage('assistant', 'Erro: ' + messageError, { warning: 'Não foi possível concluir a consulta.' });
                return;
            }

            if (historyAvailable && payload.session_id && Number(sessionEl.value || '0') <= 0) {
                sessionEl.value = String(payload.session_id);
                const url = new URL(window.location.href);
                url.searchParams.set('route', 'ai-chat');
                url.searchParams.set('session_id', String(payload.session_id));
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
