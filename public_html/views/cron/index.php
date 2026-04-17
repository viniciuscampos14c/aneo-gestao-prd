<?php
/** @var array  $jobs    lista de jobs do banco */
/** @var string $token   token configurado */
/** @var string $baseUrl base URL do sistema */

$cronUrl = rtrim($baseUrl, '/') . '/cron.php';
$hasToken = $token !== '';
?>
<div class="space-y-6">

    <!-- Cabeçalho -->
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold text-slate-800">Cron Jobs</h2>
            <p class="text-sm text-slate-500">Tarefas agendadas executadas automaticamente pelo sistema.</p>
        </div>
    </div>

    <!-- Instrução de configuração na Hostinger -->
    <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-800">
        <p class="font-semibold mb-1">Como configurar na Hostinger:</p>
        <p class="mb-2">No hPanel → Avancado → Cron Jobs, adicione o comando abaixo para executar todos os jobs a cada hora:</p>
        <div class="flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2 font-mono text-xs text-emerald-300 overflow-x-auto">
            <span id="cron-cmd">0 * * * *&nbsp;&nbsp;curl -s "<?= e($cronUrl); ?>?token=<?= e($token); ?>&amp;job=all" &gt; /dev/null 2&gt;&amp;1</span>
            <button type="button" onclick="copyCronCmd()" class="ml-auto flex-shrink-0 rounded bg-slate-700 px-2 py-1 text-xs text-slate-200 hover:bg-slate-600">Copiar</button>
        </div>
        <?php if (!$hasToken): ?>
            <p class="mt-2 font-semibold text-amber-700">⚠ Token nao configurado. Defina <code>cron.secret_token</code> em <code>config.local.php</code>.</p>
        <?php endif; ?>
    </div>

    <!-- Tabela de jobs -->
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Job</th>
                    <th class="px-4 py-3 text-left">Descricao</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-left">Ultima execucao</th>
                    <th class="px-4 py-3 text-left">Duracao</th>
                    <th class="px-4 py-3 text-left">Ultima mensagem</th>
                    <th class="px-4 py-3 text-center">Ativo</th>
                    <th class="px-4 py-3 text-center">Acoes</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100" id="jobs-tbody">
            <?php foreach ($jobs as $job): ?>
                <?php
                $statusColor = match ($job['last_status'] ?? '') {
                    'ok'      => 'bg-emerald-100 text-emerald-700',
                    'error'   => 'bg-rose-100 text-rose-700',
                    'running' => 'bg-amber-100 text-amber-700',
                    default   => 'bg-slate-100 text-slate-500',
                };
                $statusLabel = match ($job['last_status'] ?? '') {
                    'ok'      => 'OK',
                    'error'   => 'Erro',
                    'running' => 'Executando',
                    default   => 'Nunca executado',
                };
                $enabled = (bool) $job['enabled'];
                $durMs   = $job['last_duration_ms'] !== null ? (int) $job['last_duration_ms'] : null;
                $durLabel = $durMs !== null ? ($durMs >= 1000 ? number_format($durMs / 1000, 1) . 's' : $durMs . 'ms') : '—';
                $lastRun  = $job['last_run_at'] ? date('d/m/Y H:i:s', strtotime($job['last_run_at'])) : '—';
                $jobKey   = e($job['job_key']);
                ?>
                <tr class="hover:bg-slate-50" id="row-<?= $jobKey; ?>">
                    <td class="px-4 py-3 font-mono text-xs text-slate-700"><?= $jobKey; ?></td>
                    <td class="px-4 py-3 text-slate-600"><?= e($job['label']); ?><br><span class="text-xs text-slate-400"><?= e($job['description']); ?></span></td>
                    <td class="px-4 py-3 text-center">
                        <span id="status-<?= $jobKey; ?>" class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold <?= $statusColor; ?>">
                            <?= $statusLabel; ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-slate-500 text-xs" id="lastrun-<?= $jobKey; ?>"><?= $lastRun; ?></td>
                    <td class="px-4 py-3 text-slate-500 text-xs" id="dur-<?= $jobKey; ?>"><?= $durLabel; ?></td>
                    <td class="px-4 py-3 text-slate-500 text-xs max-w-xs truncate" id="msg-<?= $jobKey; ?>" title="<?= e((string)($job['last_message'] ?? '')); ?>"><?= e(mb_strimwidth((string)($job['last_message'] ?? '—'), 0, 80, '...')); ?></td>
                    <td class="px-4 py-3 text-center">
                        <button type="button"
                            onclick="toggleJob('<?= $jobKey; ?>', <?= $enabled ? 0 : 1; ?>)"
                            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold <?= $enabled ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'; ?>"
                            id="toggle-<?= $jobKey; ?>">
                            <?= $enabled ? 'Sim' : 'Nao'; ?>
                        </button>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <button type="button"
                                onclick="runJob('<?= $jobKey; ?>')"
                                class="rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-700 disabled:opacity-50"
                                id="run-<?= $jobKey; ?>">
                                Executar
                            </button>
                            <button type="button"
                                onclick="showLogs('<?= $jobKey; ?>')"
                                class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">
                                Logs
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<!-- Modal de logs -->
<div id="logs-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <div class="w-full max-w-3xl rounded-2xl border border-slate-200 bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h3 class="font-semibold text-slate-800" id="logs-modal-title">Logs do Job</h3>
            <button type="button" onclick="closeLogs()" class="text-slate-400 hover:text-slate-600">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="max-h-[60vh] overflow-y-auto p-4" id="logs-modal-body">
            <p class="text-center text-sm text-slate-400">Carregando...</p>
        </div>
    </div>
</div>

<script>
function runJob(jobKey) {
    var btn = document.getElementById('run-' + jobKey);
    var statusEl = document.getElementById('status-' + jobKey);
    btn.disabled = true;
    btn.textContent = 'Executando...';
    statusEl.className = 'inline-block rounded-full px-2 py-0.5 text-xs font-semibold bg-amber-100 text-amber-700';
    statusEl.textContent = 'Executando';

    var form = new FormData();
    form.append('job', jobKey);

    fetch('<?= route('cron/run'); ?>', { method: 'POST', body: form })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.textContent = 'Executar';

            if (data.ok) {
                statusEl.className = 'inline-block rounded-full px-2 py-0.5 text-xs font-semibold bg-emerald-100 text-emerald-700';
                statusEl.textContent = 'OK';
            } else {
                statusEl.className = 'inline-block rounded-full px-2 py-0.5 text-xs font-semibold bg-rose-100 text-rose-700';
                statusEl.textContent = 'Erro';
            }

            // Atualiza mensagem e duração
            var msgEl = document.getElementById('msg-' + jobKey);
            var durEl = document.getElementById('dur-' + jobKey);
            var runEl = document.getElementById('lastrun-' + jobKey);
            if (msgEl) msgEl.textContent = data.message || '';
            if (durEl && data.duration_ms != null) {
                durEl.textContent = data.duration_ms >= 1000
                    ? (data.duration_ms / 1000).toFixed(1) + 's'
                    : data.duration_ms + 'ms';
            }
            if (runEl) {
                var now = new Date();
                var pad = function(n) { return String(n).padStart(2, '0'); };
                runEl.textContent = pad(now.getDate()) + '/' + pad(now.getMonth()+1) + '/' + now.getFullYear()
                    + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = 'Executar';
            statusEl.className = 'inline-block rounded-full px-2 py-0.5 text-xs font-semibold bg-rose-100 text-rose-700';
            statusEl.textContent = 'Erro';
        });
}

function toggleJob(jobKey, enable) {
    var form = new FormData();
    form.append('job', jobKey);
    form.append('enabled', enable);

    fetch('<?= route('cron/toggle'); ?>', { method: 'POST', body: form })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.ok) return;
            var btn = document.getElementById('toggle-' + jobKey);
            if (enable) {
                btn.className = 'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold bg-emerald-100 text-emerald-700 hover:bg-emerald-200';
                btn.textContent = 'Sim';
                btn.setAttribute('onclick', "toggleJob('" + jobKey + "', 0)");
            } else {
                btn.className = 'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold bg-slate-100 text-slate-500 hover:bg-slate-200';
                btn.textContent = 'Nao';
                btn.setAttribute('onclick', "toggleJob('" + jobKey + "', 1)");
            }
        });
}

function showLogs(jobKey) {
    var modal = document.getElementById('logs-modal');
    var body  = document.getElementById('logs-modal-body');
    var title = document.getElementById('logs-modal-title');
    title.textContent = 'Logs: ' + jobKey;
    body.innerHTML = '<p class="text-center text-sm text-slate-400">Carregando...</p>';
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    fetch('<?= route('cron/logs'); ?>&job=' + encodeURIComponent(jobKey) + '&limit=30')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.ok || !data.data || data.data.length === 0) {
                body.innerHTML = '<p class="text-center text-sm text-slate-400 py-6">Nenhum log registrado para este job.</p>';
                return;
            }
            var html = '<table class="w-full text-xs">';
            html += '<thead class="bg-slate-50 text-slate-500"><tr>'
                 + '<th class="px-3 py-2 text-left">Inicio</th>'
                 + '<th class="px-3 py-2 text-left">Fim</th>'
                 + '<th class="px-3 py-2 text-center">Status</th>'
                 + '<th class="px-3 py-2 text-right">Duracao</th>'
                 + '<th class="px-3 py-2 text-left">Mensagem</th>'
                 + '</tr></thead><tbody class="divide-y divide-slate-100">';
            data.data.forEach(function(row) {
                var sc = row.status === 'ok'
                    ? 'bg-emerald-100 text-emerald-700'
                    : row.status === 'error'
                        ? 'bg-rose-100 text-rose-700'
                        : 'bg-amber-100 text-amber-700';
                var dur = row.duration_ms != null
                    ? (row.duration_ms >= 1000 ? (row.duration_ms/1000).toFixed(1)+'s' : row.duration_ms+'ms')
                    : '—';
                html += '<tr class="hover:bg-slate-50">'
                    + '<td class="px-3 py-2 text-slate-600">' + (row.started_at || '—') + '</td>'
                    + '<td class="px-3 py-2 text-slate-500">' + (row.finished_at || '—') + '</td>'
                    + '<td class="px-3 py-2 text-center"><span class="rounded-full px-2 py-0.5 text-xs font-semibold ' + sc + '">' + row.status + '</span></td>'
                    + '<td class="px-3 py-2 text-right text-slate-500">' + dur + '</td>'
                    + '<td class="px-3 py-2 text-slate-500 max-w-xs">' + (row.message || '—') + '</td>'
                    + '</tr>';
            });
            html += '</tbody></table>';
            body.innerHTML = html;
        })
        .catch(function() {
            body.innerHTML = '<p class="text-center text-sm text-rose-500 py-6">Erro ao carregar logs.</p>';
        });
}

function closeLogs() {
    var modal = document.getElementById('logs-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function copyCronCmd() {
    var cmd = document.getElementById('cron-cmd').textContent;
    navigator.clipboard.writeText(cmd).catch(function() {});
}
</script>
