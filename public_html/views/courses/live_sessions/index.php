<?php
/** @var array  $sessions        resultado paginado (rows + meta) */
/** @var array  $courses         opções de curso para o filtro */
/** @var array  $filters         filtros ativos */
/** @var bool   $zoomConfigured  credenciais Zoom cadastradas */

$newId = (int) request('new_id');

function ls_url(array $filters, int $page = 1): string {
    $params = ['route' => 'courses/live-sessions'];
    if (!empty($filters['course_id'])) $params['course_id'] = $filters['course_id'];
    if (!empty($filters['status']))    $params['status']    = $filters['status'];
    if ($page > 1)                     $params['page']      = $page;
    return 'index.php?' . http_build_query($params);
}

$statusBadge = [
    'scheduled' => 'bg-emerald-100 text-emerald-700',
    'cancelled' => 'bg-rose-100 text-rose-700',
];
$statusLabel = [
    'scheduled' => 'Agendada',
    'cancelled' => 'Cancelada',
];

$rows = $sessions['rows'] ?? [];
$meta = $sessions['meta'] ?? ['page' => 1, 'last_page' => 1, 'total' => 0];
?>
<div class="space-y-6">

    <!-- Cabeçalho -->
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold text-slate-800">Aulas Online (Zoom)</h2>
            <p class="text-sm text-slate-500">Gerencie as aulas ao vivo dos cursos. As reuniões são criadas automaticamente no Zoom.</p>
        </div>
        <div class="flex gap-2">
            <a href="<?= route('courses/live-sessions/zoom-settings'); ?>"
               class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Configurar Zoom
            </a>
            <?php if ($zoomConfigured): ?>
                <a href="<?= route('courses/live-sessions/create'); ?>"
                   class="inline-flex items-center gap-1.5 rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Nova Aula
                </a>
            <?php else: ?>
                <span class="inline-flex items-center gap-1.5 rounded-lg bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-400 cursor-not-allowed"
                      title="Configure as credenciais Zoom primeiro">
                    Nova Aula
                </span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$zoomConfigured): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            <strong>Credenciais Zoom não configuradas.</strong>
            <a href="<?= route('courses/live-sessions/zoom-settings'); ?>" class="underline ml-1">Clique aqui para configurar</a>
            antes de criar aulas online.
        </div>
    <?php endif; ?>

    <?php if ($newId > 0): ?>
        <?php
        // Busca a sessão recém-criada para exibir o painel de credenciais
        foreach ($rows as $s) {
            if ((int) $s['id'] === $newId) {
                $newSession = $s;
                break;
            }
        }
        if (isset($newSession)):
        ?>
        <div class="rounded-xl border border-emerald-300 bg-emerald-50 p-5 space-y-3">
            <div class="flex items-center gap-2 text-emerald-800 font-semibold text-base">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Reunião criada com sucesso!
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-lg border border-emerald-200 bg-white p-3">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Link do Aluno (join_url)</p>
                    <div class="flex items-center gap-2">
                        <input id="join_url_copy" type="text" readonly value="<?= e($newSession['join_url'] ?? ''); ?>"
                               class="flex-1 rounded border border-slate-200 bg-slate-50 px-2 py-1 text-xs text-slate-700 select-all">
                        <button onclick="copyField('join_url_copy')"
                                class="rounded bg-sky-600 px-2 py-1 text-xs font-semibold text-white hover:bg-sky-700">Copiar</button>
                    </div>
                </div>
                <div class="rounded-lg border border-emerald-200 bg-white p-3">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Link do Professor (start_url)</p>
                    <div class="flex items-center gap-2">
                        <input id="start_url_copy" type="text" readonly value="<?= e($newSession['start_url'] ?? ''); ?>"
                               class="flex-1 rounded border border-slate-200 bg-slate-50 px-2 py-1 text-xs text-slate-700 select-all">
                        <button onclick="copyField('start_url_copy')"
                                class="rounded bg-sky-600 px-2 py-1 text-xs font-semibold text-white hover:bg-sky-700">Copiar</button>
                    </div>
                </div>
                <div class="rounded-lg border border-emerald-200 bg-white p-3">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Meeting ID</p>
                    <p class="text-sm font-mono font-bold text-slate-800"><?= e($newSession['zoom_meeting_id'] ?? '—'); ?></p>
                </div>
                <div class="rounded-lg border border-emerald-200 bg-white p-3">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Senha</p>
                    <p class="text-sm font-mono font-bold text-slate-800"><?= e($newSession['zoom_password'] ?? '—'); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Filtros -->
    <form method="GET" action="index.php" class="flex flex-wrap items-center gap-2">
        <input type="hidden" name="route" value="courses/live-sessions">
        <select name="course_id"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-100">
            <option value="">Todos os cursos</option>
            <?php foreach ($courses as $c): ?>
                <option value="<?= (int) $c['id']; ?>" <?= (int) ($filters['course_id'] ?? 0) === (int) $c['id'] ? 'selected' : ''; ?>>
                    <?= e($c['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="status"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-100">
            <option value="">Todos os status</option>
            <option value="scheduled" <?= ($filters['status'] ?? '') === 'scheduled' ? 'selected' : ''; ?>>Agendadas</option>
            <option value="cancelled" <?= ($filters['status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>Canceladas</option>
        </select>
        <button type="submit"
                class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700 transition">
            Filtrar
        </button>
        <?php if (!empty($filters['course_id']) || !empty($filters['status'])): ?>
            <a href="<?= route('courses/live-sessions'); ?>" class="text-sm text-slate-500 hover:text-slate-700">Limpar</a>
        <?php endif; ?>
    </form>

    <!-- Tabela -->
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <?php if ($rows === []): ?>
            <div class="px-6 py-12 text-center text-sm text-slate-400">
                Nenhuma aula encontrada. <?= $zoomConfigured ? '<a href="' . route('courses/live-sessions/create') . '" class="text-sky-600 underline">Criar primeira aula</a>' : ''; ?>
            </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3 text-left">Curso</th>
                        <th class="px-4 py-3 text-left">Título</th>
                        <th class="px-4 py-3 text-left">Meeting ID</th>
                        <th class="px-4 py-3 text-left">Data/Hora</th>
                        <th class="px-4 py-3 text-center">Duração</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($rows as $s): ?>
                        <?php
                        $st     = (string) ($s['status'] ?? 'scheduled');
                        $badge  = $statusBadge[$st] ?? 'bg-slate-100 text-slate-600';
                        $slabel = $statusLabel[$st] ?? $st;
                        $sched  = (string) ($s['scheduled_at'] ?? '');
                        $schedFmt = $sched !== '' ? date('d/m/Y H:i', strtotime($sched)) : '—';
                        $isNew = (int) ($s['id'] ?? 0) === $newId;
                        ?>
                        <tr class="hover:bg-slate-50 transition <?= $isNew ? 'ring-1 ring-emerald-300 bg-emerald-50/30' : ''; ?>">
                            <td class="px-4 py-3 text-slate-600"><?= e($s['course_name'] ?? '—'); ?></td>
                            <td class="px-4 py-3 font-medium text-slate-800"><?= e($s['title'] ?? '—'); ?></td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-500"><?= e($s['zoom_meeting_id'] ?? '—'); ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= e($schedFmt); ?></td>
                            <td class="px-4 py-3 text-center text-slate-500"><?= (int) ($s['duration_minutes'] ?? 60); ?>min</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-block rounded-full px-3 py-0.5 text-xs font-semibold <?= $badge; ?>">
                                    <?= e($slabel); ?>
                                </span>
                                <?php if (!empty($s['is_global'])): ?>
                                    <span class="mt-1 inline-block rounded-full bg-cyan-100 px-2.5 py-0.5 text-xs font-semibold text-cyan-700">
                                        Global
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <?php if (!empty($s['join_url'])): ?>
                                        <a href="<?= e($s['join_url']); ?>" target="_blank" rel="noopener"
                                           class="inline-block rounded-lg border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-100 transition">
                                            Entrar
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($st === 'scheduled'): ?>
                                        <a href="<?= route('courses/live-sessions/edit') . '&id=' . (int) $s['id']; ?>"
                                           class="inline-block rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition">
                                            Editar
                                        </a>
                                        <form method="POST" action="index.php?route=courses/live-sessions/cancel"
                                              onsubmit="return confirm('Cancelar esta aula? A reunião também será removida do Zoom.')">
                                            <input type="hidden" name="route" value="courses/live-sessions/cancel">
                                            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                            <input type="hidden" name="id" value="<?= (int) $s['id']; ?>">
                                            <button type="submit"
                                                    class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100 transition">
                                                Cancelar
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Paginação -->
    <?php if ($meta['last_page'] > 1): ?>
        <div class="flex items-center justify-between">
            <?php if ($meta['page'] > 1): ?>
                <a href="<?= ls_url($filters, $meta['page'] - 1); ?>"
                   class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                    ← Anterior
                </a>
            <?php else: ?><span></span><?php endif; ?>
            <span class="text-xs text-slate-400">Página <?= $meta['page']; ?> de <?= $meta['last_page']; ?> — <?= $meta['total']; ?> registro(s)</span>
            <?php if ($meta['page'] < $meta['last_page']): ?>
                <a href="<?= ls_url($filters, $meta['page'] + 1); ?>"
                   class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                    Próxima →
                </a>
            <?php else: ?><span></span><?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<script>
function copyField(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.select();
    document.execCommand('copy');
    const btn = el.nextElementSibling;
    if (btn) { btn.textContent = 'Copiado!'; setTimeout(() => btn.textContent = 'Copiar', 2000); }
}
</script>
