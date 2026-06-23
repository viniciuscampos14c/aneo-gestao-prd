<?php
/** @var array $request dados completos da solicitacao */
/** @var bool  $readOnly professor visualiza sem editar */

$readOnly = (bool) ($readOnly ?? false);

$statusLabels = [
    'pending'  => 'Aguardando',
    'viewed'   => 'Visualizado',
    'approved' => 'Aprovado',
    'rejected' => 'Recusado',
];

$statusBadge = [
    'pending'  => 'bg-amber-100 text-amber-700',
    'viewed'   => 'bg-sky-100 text-sky-700',
    'approved' => 'bg-emerald-100 text-emerald-700',
    'rejected' => 'bg-rose-100 text-rose-700',
];

$st = (string) ($request['status'] ?? 'pending');
$badge = $statusBadge[$st] ?? 'bg-slate-100 text-slate-600';
$slabel = $statusLabels[$st] ?? $st;

$dm = (string) ($request['desired_month'] ?? '');
$dmFmt = '';
if ($dm !== '' && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dm, $m)) {
    $dmFmt = $m[3] . '/' . $m[2] . '/' . $m[1];
} elseif ($dm !== '' && preg_match('/^(\d{4})-(\d{2})$/', $dm, $m)) {
    $months = ['', 'Janeiro', 'Fevereiro', 'Marco', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    $dmFmt = ($months[(int) $m[2]] ?? $m[2]) . ' de ' . $m[1];
}

$createdAt = (string) ($request['created_at'] ?? '');
$dateFmt = $createdAt !== '' ? date('d/m/Y H:i', strtotime($createdAt)) : '-';
?>
<div class="exchange-show-shell space-y-6 max-w-2xl">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="<?= route('exchange'); ?>" class="text-sm text-sky-600 hover:underline">&larr; Voltar as solicitacoes</a>
            <h2 class="mt-1 text-2xl font-semibold text-slate-800">
                Solicitacao #<?= (int) ($request['id'] ?? 0); ?>
            </h2>
        </div>
        <span class="exchange-status-pill inline-block rounded-full px-4 py-1 text-sm font-semibold <?= $badge; ?>">
            <?= e($slabel); ?>
        </span>
    </div>

    <div class="exchange-panel rounded-xl border border-slate-200 bg-white shadow-sm divide-y divide-slate-100">
        <div class="px-6 py-4">
            <h3 class="font-semibold text-slate-800">Dados do Aluno</h3>
        </div>
        <dl class="divide-y divide-slate-100">
            <div class="grid grid-cols-3 px-6 py-3 text-sm">
                <dt class="font-medium text-slate-500">Nome completo</dt>
                <dd class="col-span-2 text-slate-800"><?= e((string) ($request['student_name'] ?? '-')); ?></dd>
            </div>
            <div class="grid grid-cols-3 px-6 py-3 text-sm">
                <dt class="font-medium text-slate-500">Login</dt>
                <dd class="col-span-2 text-slate-600"><?= e((string) ($request['student_login'] ?? '-')); ?></dd>
            </div>
            <div class="grid grid-cols-3 px-6 py-3 text-sm">
                <dt class="font-medium text-slate-500">E-mail</dt>
                <dd class="col-span-2 text-slate-600"><?= e((string) ($request['student_email'] ?? '-')); ?></dd>
            </div>
            <div class="grid grid-cols-3 px-6 py-3 text-sm">
                <dt class="font-medium text-slate-500">Meses cursando</dt>
                <dd class="col-span-2 font-semibold text-slate-800">
                    <?= (int) ($request['months_enrolled'] ?? 0); ?> mes(es)
                </dd>
            </div>
        </dl>
    </div>

    <div class="exchange-panel rounded-xl border border-slate-200 bg-white shadow-sm divide-y divide-slate-100">
        <div class="px-6 py-4">
            <h3 class="font-semibold text-slate-800">Interc&acirc;mbio Solicitado</h3>
        </div>
        <dl class="divide-y divide-slate-100">
            <div class="grid grid-cols-3 px-6 py-3 text-sm">
                <dt class="font-medium text-slate-500">Unidade atual</dt>
                <dd class="col-span-2 text-slate-800"><?= e((string) ($request['current_unit'] ?? '-')); ?></dd>
            </div>
            <div class="grid grid-cols-3 px-6 py-3 text-sm">
                <dt class="font-medium text-slate-500">Unidade destino</dt>
                <dd class="col-span-2 font-semibold text-sky-700"><?= e((string) ($request['target_unit'] ?? '-')); ?></dd>
            </div>
            <div class="grid grid-cols-3 px-6 py-3 text-sm">
                <dt class="font-medium text-slate-500">Data pretendida</dt>
                <dd class="col-span-2 text-slate-800"><?= e($dmFmt !== '' ? $dmFmt : ($dm !== '' ? $dm : '-')); ?></dd>
            </div>
            <div class="grid grid-cols-3 px-6 py-3 text-sm">
                <dt class="font-medium text-slate-500">Enviado em</dt>
                <dd class="col-span-2 text-slate-500"><?= e($dateFmt); ?></dd>
            </div>
        </dl>
    </div>

    <div class="exchange-panel rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-6 py-4">
            <h3 class="font-semibold text-slate-800"><?= $readOnly ? 'Status da Solicitação' : 'Atualizar Status'; ?></h3>
        </div>

        <?php if ($readOnly): ?>
            <div class="space-y-4 px-6 py-6 text-sm">
                <div>
                    <p class="mb-1 font-medium text-slate-700">Status</p>
                    <span class="exchange-status-pill inline-block rounded-full px-3 py-1 text-xs font-semibold <?= $badge; ?>">
                        <?= e($slabel); ?>
                    </span>
                </div>
                <div>
                    <p class="mb-1 font-medium text-slate-700">Observação da equipe</p>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 text-slate-700">
                        <?= trim((string) ($request['admin_notes'] ?? '')) !== '' ? nl2br(e((string) $request['admin_notes'])) : 'Nenhuma observação registrada.'; ?>
                    </div>
                </div>
                <p class="rounded-lg border border-cyan-200 bg-cyan-50 px-3 py-2 text-xs text-cyan-800">
                    Perfil professor: acesso somente para visualização.
                </p>
            </div>
        <?php else: ?>
            <form method="POST" action="<?= route('exchange/update-status'); ?>" class="space-y-4 px-6 py-6">
                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                <input type="hidden" name="id" value="<?= (int) ($request['id'] ?? 0); ?>">

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="status">Status</label>
                    <select id="status" name="status"
                            class="exchange-form-select w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-100">
                        <?php foreach ($statusLabels as $val => $lbl): ?>
                            <option value="<?= e($val); ?>" <?= $st === $val ? 'selected' : ''; ?>><?= e($lbl); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="admin_notes">
                        Observacao para o aluno <span class="font-normal text-slate-400">(opcional)</span>
                    </label>
                    <textarea id="admin_notes" name="admin_notes" rows="3"
                              placeholder="Ex.: Aprovado! Entraremos em contato para agendar..."
                              class="exchange-form-textarea w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-100"><?= e((string) ($request['admin_notes'] ?? '')); ?></textarea>
                </div>

                <button type="submit"
                        class="exchange-save-btn rounded-lg bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-sky-700 transition">
                    Salvar
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>
