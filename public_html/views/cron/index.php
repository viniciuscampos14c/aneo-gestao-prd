<?php
/** @var array  $jobs    lista de jobs do banco */
/** @var string $token   token configurado */
/** @var string $baseUrl base URL do sistema */

$cronUrl = rtrim($baseUrl, '/') . '/cron.php';
$hasToken = $token !== '';
?>
<section class="cron-admin-shell space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold">Cron Jobs</h2>
            <p class="text-sm text-slate-500">Tarefas agendadas executadas automaticamente pelo sistema.</p>
        </div>
    </div>

    <div class="cron-admin-guide rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-800">
        <p class="mb-1 font-semibold">Como configurar na Hostinger:</p>
        <p class="mb-2">No hPanel -> Avancado -> Cron Jobs, adicione o comando abaixo para executar todos os jobs a cada hora:</p>
        <div class="cron-admin-cmd-box flex items-center gap-2 overflow-x-auto rounded-lg bg-slate-900 px-4 py-2 font-mono text-xs text-emerald-300">
            <span id="cron-cmd">0 * * * *  curl -s "<?= e($cronUrl); ?>?token=<?= e($token); ?>&amp;job=all" &gt; /dev/null 2&gt;&amp;1</span>
            <button type="button" onclick="copyCronCmd()" class="cron-admin-copy-btn ml-auto flex-shrink-0 rounded bg-slate-700 px-2 py-1 text-xs text-slate-200 hover:bg-slate-600">Copiar</button>
        </div>
        <?php if (!$hasToken): ?>
            <p class="cron-admin-guide-warning mt-2 font-semibold text-amber-700">Token nao configurado. Defina <code>cron.secret_token</code> em <code>config.local.php</code>.</p>
        <?php endif; ?>
    </div>

    <div class="cron-admin-table-wrap overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="cron-admin-table w-full text-sm">
            <thead class="cron-admin-thead bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
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
            <tbody class="cron-admin-tbody divide-y divide-slate-100" id="jobs-tbody">
                <?php foreach ($jobs as $job): ?>
                    <?php
                    $statusToneClass = match ($job['last_status'] ?? '') {
                        'ok' => 'cron-status-ok',
                        'error' => 'cron-status-error',
                        'running' => 'cron-status-running',
                        default => 'cron-status-never',
                    };
                    $statusLabel = match ($job['last_status'] ?? '') {
                        'ok' => 'OK',
                        'error' => 'Erro',
                        'running' => 'Executando',
                        default => 'Nunca executado',
                    };
                    $enabled = (bool) $job['enabled'];
                    $durMs = $job['last_duration_ms'] !== null ? (int) $job['last_duration_ms'] : null;
                    $durLabel = $durMs !== null ? ($durMs >= 1000 ? number_format($durMs / 1000, 1) . 's' : $durMs . 'ms') : '-';
                    $lastRun = $job['last_run_at'] ? date('d/m/Y H:i:s', strtotime((string) $job['last_run_at'])) : '-';
                    $jobKey = e((string) $job['job_key']);
                    ?>
                    <tr class="cron-admin-row hover:bg-slate-50" id="row-<?= $jobKey; ?>">
                        <td class="px-4 py-3 font-mono text-xs text-slate-700"><?= $jobKey; ?></td>
                        <td class="px-4 py-3 text-slate-600">
                            <?= e((string) $job['label']); ?><br>
                            <span class="text-xs text-slate-400"><?= e((string) $job['description']); ?></span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span id="status-<?= $jobKey; ?>" class="cron-status-pill <?= $statusToneClass; ?>">
                                <?= $statusLabel; ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500" id="lastrun-<?= $jobKey; ?>"><?= $lastRun; ?></td>
                        <td class="px-4 py-3 text-xs text-slate-500" id="dur-<?= $jobKey; ?>"><?= $durLabel; ?></td>
                        <td class="max-w-xs truncate px-4 py-3 text-xs text-slate-500" id="msg-<?= $jobKey; ?>" title="<?= e((string) ($job['last_message'] ?? '')); ?>"><?= e(mb_strimwidth((string) ($job['last_message'] ?? '-'), 0, 80, '...')); ?></td>
                        <td class="px-4 py-3 text-center">
                            <button
                                type="button"
                                onclick="toggleJob('<?= $jobKey; ?>', <?= $enabled ? 0 : 1; ?>)"
                                class="cron-toggle-btn <?= $enabled ? 'cron-toggle-on' : 'cron-toggle-off'; ?>"
                                id="toggle-<?= $jobKey; ?>"
                            >
                                <?= $enabled ? 'Sim' : 'Nao'; ?>
                            </button>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <button
                                    type="button"
                                    onclick="runJob('<?= $jobKey; ?>')"
                                    class="cron-run-btn"
                                    id="run-<?= $jobKey; ?>"
                                >
                                    Executar
                                </button>
                                <button
                                    type="button"
                                    onclick="showLogs('<?= $jobKey; ?>')"
                                    class="cron-logs-btn"
                                >
                                    Logs
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<div id="logs-modal" class="cron-logs-modal fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <div class="cron-logs-panel w-full max-w-3xl rounded-2xl border border-slate-200 bg-white shadow-2xl">
        <div class="cron-logs-header flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h3 class="font-semibold text-slate-800" id="logs-modal-title">Logs do Job</h3>
            <button type="button" onclick="closeLogs()" class="cron-logs-close text-slate-400 hover:text-slate-600">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="cron-logs-body max-h-[60vh] overflow-y-auto p-4" id="logs-modal-body">
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
    statusEl.className = 'cron-status-pill cron-status-running';
    statusEl.textContent = 'Executando';

    var form = new FormData();
    form.append('job', jobKey);

    fetch('<?= route('cron/run'); ?>', { method: 'POST', body: form })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.textContent = 'Executar';

            if (data.ok) {
                statusEl.className = 'cron-status-pill cron-status-ok';
                statusEl.textContent = 'OK';
            } else {
                statusEl.className = 'cron-status-pill cron-status-error';
                statusEl.textContent = 'Erro';
            }

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
                runEl.textContent = pad(now.getDate()) + '/' + pad(now.getMonth() + 1) + '/' + now.getFullYear()
                    + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = 'Executar';
            statusEl.className = 'cron-status-pill cron-status-error';
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
                btn.className = 'cron-toggle-btn cron-toggle-on';
                btn.textContent = 'Sim';
                btn.setAttribute('onclick', "toggleJob('" + jobKey + "', 0)");
            } else {
                btn.className = 'cron-toggle-btn cron-toggle-off';
                btn.textContent = 'Nao';
                btn.setAttribute('onclick', "toggleJob('" + jobKey + "', 1)");
            }
        });
}

function showLogs(jobKey) {
    var modal = document.getElementById('logs-modal');
    var body = document.getElementById('logs-modal-body');
    var title = document.getElementById('logs-modal-title');
    title.textContent = 'Logs: ' + jobKey;
    body.innerHTML = '<p class="text-center text-sm text-slate-400">Carregando...</p>';
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    fetch('<?= route('cron/logs'); ?>&job=' + encodeURIComponent(jobKey) + '&limit=30')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.ok || !data.data || data.data.length === 0) {
                body.innerHTML = '<p class="py-6 text-center text-sm text-slate-400">Nenhum log registrado para este job.</p>';
                return;
            }
            var html = '<table class="cron-logs-table w-full text-xs">';
            html += '<thead><tr>'
                + '<th class="px-3 py-2 text-left">Inicio</th>'
                + '<th class="px-3 py-2 text-left">Fim</th>'
                + '<th class="px-3 py-2 text-center">Status</th>'
                + '<th class="px-3 py-2 text-right">Duracao</th>'
                + '<th class="px-3 py-2 text-left">Mensagem</th>'
                + '</tr></thead><tbody>';
            data.data.forEach(function(row) {
                var sc = row.status === 'ok'
                    ? 'cron-status-pill cron-status-ok'
                    : row.status === 'error'
                        ? 'cron-status-pill cron-status-error'
                        : 'cron-status-pill cron-status-running';
                var dur = row.duration_ms != null
                    ? (row.duration_ms >= 1000 ? (row.duration_ms / 1000).toFixed(1) + 's' : row.duration_ms + 'ms')
                    : '-';
                html += '<tr class="cron-logs-row">'
                    + '<td class="px-3 py-2">' + (row.started_at || '-') + '</td>'
                    + '<td class="px-3 py-2">' + (row.finished_at || '-') + '</td>'
                    + '<td class="px-3 py-2 text-center"><span class="' + sc + '">' + row.status + '</span></td>'
                    + '<td class="px-3 py-2 text-right">' + dur + '</td>'
                    + '<td class="max-w-xs px-3 py-2">' + (row.message || '-') + '</td>'
                    + '</tr>';
            });
            html += '</tbody></table>';
            body.innerHTML = html;
        })
        .catch(function() {
            body.innerHTML = '<p class="py-6 text-center text-sm text-rose-500">Erro ao carregar logs.</p>';
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
