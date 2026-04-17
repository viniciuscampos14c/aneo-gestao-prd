<?php
/** @var array $request dados completos da solicitação */

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

$st     = (string) ($request['status'] ?? 'pending');
$badge  = $statusBadge[$st]  ?? 'bg-slate-100 text-slate-600';
$slabel = $statusLabels[$st] ?? $st;

$dm    = (string) ($request['desired_month'] ?? '');
$dmFmt = '';
if ($dm !== '' && preg_match('/^(\d{4})-(\d{2})$/', $dm, $m)) {
    $months = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
               'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    $dmFmt = ($months[(int) $m[2]] ?? $m[2]) . ' de ' . $m[1];
}

$createdAt = (string) ($request['created_at'] ?? '');
$dateFmt   = $createdAt !== '' ? date('d/m/Y H:i', strtotime($createdAt)) : '—';
?>
<div class="space-y-6 max-w-2xl">

    <!-- Cabeçalho -->
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="<?= route('exchange'); ?>" class="text-sm text-sky-600 hover:underline">← Voltar às solicitações</a>
            <h2 class="mt-1 text-2xl font-semibold text-slate-800">
                Solicitação #<?= (int) ($request['id'] ?? 0); ?>
            </h2>
        </div>
        <span class="inline-block rounded-full px-4 py-1 text-sm font-semibold <?= $badge; ?>">
            <?= e($slabel); ?>
        </span>
    </div>

    <!-- Dados do aluno -->
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm divide-y divide-slate-100">
        <div class="px-6 py-4">
            <h3 class="font-semibold text-slate-800">Dados do Aluno</h3>
        </div>
        <dl class="divide-y divide-slate-100">
            <div class="grid grid-cols-3 px-6 py-3 text-sm">
                <dt class="font-medium text-slate-500">Nome completo</dt>
                <dd class="col-span-2 text-slate-800"><?= e($request['student_name'] ?? '—'); ?></dd>
            </div>
            <div class="grid grid-cols-3 px-6 py-3 text-sm">
                <dt class="font-medium text-slate-500">Login</dt>
                <dd class="col-span-2 text-slate-600"><?= e($request['student_login'] ?? '—'); ?></dd>
            </div>
            <div class="grid grid-cols-3 px-6 py-3 text-sm">
                <dt class="font-medium text-slate-500">E-mail</dt>
                <dd class="col-span-2 text-slate-600"><?= e($request['student_email'] ?? '—'); ?></dd>
            </div>
            <div class="grid grid-cols-3 px-6 py-3 text-sm">
                <dt class="font-medium text-slate-500">Meses cursando</dt>
                <dd class="col-span-2 text-slate-800 font-semibold">
                    <?= (int) ($request['months_enrolled'] ?? 0); ?> mese(s)
                </dd>
            </div>
        </dl>
    </div>

    <!-- Dados do intercâmbio -->
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm divide-y divide-slate-100">
        <div class="px-6 py-4">
            <h3 class="font-semibold text-slate-800">Intercâmbio Solicitado</h3>
        </div>
        <dl class="divide-y divide-slate-100">
            <div class="grid grid-cols-3 px-6 py-3 text-sm">
                <dt class="font-medium text-slate-500">Unidade atual</dt>
                <dd class="col-span-2 text-slate-800"><?= e($request['current_unit'] ?? '—'); ?></dd>
            </div>
            <div class="grid grid-cols-3 px-6 py-3 text-sm">
                <dt class="font-medium text-slate-500">Unidade destino</dt>
                <dd class="col-span-2 font-semibold text-sky-700"><?= e($request['target_unit'] ?? '—'); ?></dd>
            </div>
            <div class="grid grid-cols-3 px-6 py-3 text-sm">
                <dt class="font-medium text-slate-500">Mês desejado</dt>
                <dd class="col-span-2 text-slate-800"><?= e($dmFmt ?: $dm ?: '—'); ?></dd>
            </div>
            <div class="grid grid-cols-3 px-6 py-3 text-sm">
                <dt class="font-medium text-slate-500">Enviado em</dt>
                <dd class="col-span-2 text-slate-500"><?= e($dateFmt); ?></dd>
            </div>
        </dl>
    </div>

    <!-- Painel de ação admin -->
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-6 py-4">
            <h3 class="font-semibold text-slate-800">Atualizar Status</h3>
        </div>
        <form method="POST" action="<?= route('exchange/update-status'); ?>" class="px-6 py-6 space-y-4">
            <?= csrf_field(); ?>
            <input type="hidden" name="id" value="<?= (int) ($request['id'] ?? 0); ?>">

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1" for="status">Status</label>
                <select id="status" name="status"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-100">
                    <?php foreach ($statusLabels as $val => $lbl): ?>
                        <option value="<?= e($val); ?>" <?= $st === $val ? 'selected' : ''; ?>><?= e($lbl); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1" for="admin_notes">
                    Observação para o aluno <span class="text-slate-400 font-normal">(opcional)</span>
                </label>
                <textarea id="admin_notes" name="admin_notes" rows="3"
                          placeholder="Ex.: Aprovado! Entraremos em contato para agendar..."
                          class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-100"><?= e((string) ($request['admin_notes'] ?? '')); ?></textarea>
            </div>

            <button type="submit"
                    class="rounded-lg bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-sky-700 transition">
                Salvar
            </button>
        </form>
    </div>

</div>
