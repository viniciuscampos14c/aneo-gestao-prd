<?php
$rows = is_array($rows ?? null) ? $rows : [];
$filters = is_array($filters ?? null) ? $filters : [];
$meta = is_array($meta ?? null) ? $meta : pagination_meta(0, 50, 1);
$paginationOptions = is_array($paginationOptions ?? null) ? $paginationOptions : [50, 100, 200];
$adminControlAvailable = !empty($adminControlAvailable);

$formatDateTime = static function (?string $value): string {
    $value = trim((string) $value);
    if ($value === '' || strtotime($value) === false) {
        return '-';
    }

    return date('d/m/Y H:i', strtotime($value));
};

$formatDate = static function (?string $value): string {
    $value = trim((string) $value);
    if ($value === '' || strtotime($value) === false) {
        return '-';
    }

    return date('d/m/Y', strtotime($value));
};
?>
<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold">Controle de Rematriculas</h2>
            <p class="text-sm text-slate-500">Acompanhe as confirmacoes automaticas feitas pelo portal do aluno, com data, e-mail enviado e visualizacao administrativa.</p>
        </div>
        <a href="<?= route('students'); ?>" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm hover:bg-slate-50">Voltar para alunos</a>
    </div>

    <?php if (!$adminControlAvailable): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Controle administrativo parcialmente indisponivel. Execute a migração de rematriculas administrativas para habilitar alertas, visualizacao e status do e-mail.
        </div>
    <?php endif; ?>

    <div class="rounded-xl border border-slate-200 bg-white p-4">
        <form method="get" action="index.php" class="grid gap-3 md:grid-cols-6">
            <input type="hidden" name="route" value="students/reenrollments">
            <input type="text" name="q" value="<?= e((string) ($filters['q'] ?? '')); ?>" placeholder="Buscar aluno ou e-mail..." class="rounded-lg border border-slate-200 px-3 py-2 text-sm md:col-span-2">
            <input type="date" name="start_date" value="<?= e((string) ($filters['start_date'] ?? '')); ?>" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <input type="date" name="end_date" value="<?= e((string) ($filters['end_date'] ?? '')); ?>" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <select name="per_page" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <?php foreach ($paginationOptions as $opt): ?>
                    <option value="<?= (int) $opt; ?>" <?= (int) $meta['per_page'] === (int) $opt ? 'selected' : ''; ?>><?= (int) $opt; ?>/página</option>
                <?php endforeach; ?>
            </select>
            <div class="flex gap-2">
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrar</button>
                <a href="<?= route('students/reenrollments'); ?>" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm hover:bg-slate-50">Limpar</a>
            </div>
        </form>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <article class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">Confirmacoes no filtro</p>
            <p class="mt-2 text-2xl font-semibold"><?= (int) ($meta['total'] ?? 0); ?></p>
        </article>
        <article class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs uppercase tracking-wide text-emerald-700">Controle operacional</p>
            <p class="mt-2 text-sm font-semibold text-emerald-800">Sininho + lista administrativa</p>
        </article>
        <article class="rounded-xl border border-cyan-200 bg-cyan-50 p-4">
            <p class="text-xs uppercase tracking-wide text-cyan-700">Comunicacao com aluno</p>
            <p class="mt-2 text-sm font-semibold text-cyan-800">E-mail de confirmacao registrado</p>
        </article>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full border-collapse text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-4 py-3">Aluno</th>
                    <th class="px-4 py-3">Periodo</th>
                    <th class="px-4 py-3">Confirmado em</th>
                    <th class="px-4 py-3">IP</th>
                    <th class="px-4 py-3">E-mail aluno</th>
                    <th class="px-4 py-3">Visualizacao admin</th>
                    <th class="px-4 py-3">Acoes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $emailSent = trim((string) ($row['confirmation_email_sent_at'] ?? '')) !== '';
                    $emailError = trim((string) ($row['confirmation_email_error'] ?? ''));
                    $viewed = trim((string) ($row['admin_viewed_at'] ?? '')) !== '';
                    ?>
                    <tr class="border-b border-slate-100 hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <p class="font-semibold text-slate-800"><?= e((string) ($row['student_name'] ?? '-')); ?></p>
                            <p class="text-xs text-slate-500"><?= e((string) ($row['student_email'] ?? '-')); ?></p>
                        </td>
                        <td class="px-4 py-3">
                            <?= e($formatDate((string) ($row['period_start'] ?? ''))); ?>
                            ate
                            <?= e($formatDate((string) ($row['period_end'] ?? ''))); ?>
                        </td>
                        <td class="px-4 py-3"><?= e($formatDateTime((string) ($row['confirmed_at'] ?? ''))); ?></td>
                        <td class="px-4 py-3"><?= e((string) ($row['confirmed_ip'] ?? '-')); ?></td>
                        <td class="px-4 py-3">
                            <?php if ($emailSent): ?>
                                <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700">Enviado</span>
                                <p class="mt-1 text-xs text-slate-500"><?= e($formatDateTime((string) ($row['confirmation_email_sent_at'] ?? ''))); ?></p>
                            <?php elseif ($emailError !== ''): ?>
                                <span class="rounded-full bg-rose-100 px-2 py-1 text-xs font-semibold text-rose-700">Falhou</span>
                                <p class="mt-1 max-w-xs text-xs text-rose-600"><?= e($emailError); ?></p>
                            <?php else: ?>
                                <span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-700">Pendente</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <?php if ($viewed): ?>
                                <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">Visualizado</span>
                                <p class="mt-1 text-xs text-slate-500"><?= e($formatDateTime((string) ($row['admin_viewed_at'] ?? ''))); ?></p>
                            <?php else: ?>
                                <span class="rounded-full bg-cyan-100 px-2 py-1 text-xs font-semibold text-cyan-700">Novo</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <a href="<?= route('students/show&id=' . (int) ($row['student_id'] ?? 0)); ?>" class="rounded border border-slate-200 px-2 py-1 text-xs hover:bg-slate-100">Ver aluno</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?>
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-slate-500">Nenhuma rematricula confirmada encontrada no periodo.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
        <p>Total: <?= (int) $meta['total']; ?> registros | Página <?= (int) $meta['page']; ?>/<?= (int) $meta['pages']; ?></p>
        <div class="flex flex-wrap gap-2">
            <?php for ($p = 1; $p <= (int) $meta['pages']; $p++): ?>
                <a href="index.php?<?= build_query(['route' => 'students/reenrollments', 'page' => $p]); ?>" class="rounded px-3 py-1 <?= $p === (int) $meta['page'] ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white hover:bg-slate-50'; ?>">
                    <?= $p; ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>
</section>
