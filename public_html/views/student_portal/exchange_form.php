<?php
/** @var array  $student     dados do aluno logado */
/** @var array  $companies   lista de empresas/unidades disponíveis */
/** @var array  $myRequests  solicitações anteriores do aluno */
/** @var bool   $hasPending  aluno já tem solicitação em andamento */

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

// Mês mínimo = próximo mês
$minMonth = date('Y-m', strtotime('+1 month'));
?>
<section class="space-y-8 max-w-2xl mx-auto">

    <!-- Título -->
    <div>
        <h2 class="text-2xl font-semibold text-slate-800">Intercâmbio Aneo</h2>
        <p class="text-sm text-slate-500 mt-1">
            Solicite seu intercâmbio entre unidades da ANEO. Nossa equipe analisará sua solicitação e entrará em contato.
        </p>
    </div>

    <!-- Aviso se já tem solicitação pendente -->
    <?php if ($hasPending): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-800">
            <p class="font-semibold">Você já possui uma solicitação em andamento.</p>
            <p class="mt-1 text-amber-700">Nossa equipe está analisando seu pedido. Você será notificado em breve.</p>
        </div>
    <?php endif; ?>

    <!-- Formulário -->
    <?php if (!$hasPending): ?>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-6 py-4">
            <h3 class="font-semibold text-slate-800">Nova Solicitação</h3>
        </div>
        <form method="POST" action="<?= route('student/exchange/store'); ?>" class="px-6 py-6 space-y-5">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">

            <!-- Nome completo -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1" for="student_name">
                    Nome completo do aluno <span class="text-rose-500">*</span>
                </label>
                <input type="text" id="student_name" name="student_name"
                       value="<?= e($student['name'] ?? ''); ?>"
                       required
                       class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-100">
            </div>

            <!-- Unidade atual (auto-preenchida, read-only) -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1" for="current_unit">
                    Unidade onde estuda atualmente <span class="text-rose-500">*</span>
                </label>
                <?php
                // Busca nome da empresa do aluno
                $currentUnitName = '';
                foreach ($companies as $co) {
                    if ((int) ($co['id'] ?? 0) === (int) ($student['company_id'] ?? 0)) {
                        $currentUnitName = trim((string) ($co['trade_name'] ?? $co['legal_name'] ?? ''));
                        break;
                    }
                }
                if ($currentUnitName === '') {
                    $currentUnitName = (string) ($student['company_name'] ?? '');
                }
                ?>
                <input type="text" id="current_unit" name="current_unit"
                       value="<?= e($currentUnitName); ?>"
                       required
                       class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 shadow-sm">
                <p class="mt-1 text-xs text-slate-400">Preenchido automaticamente com sua unidade atual.</p>
            </div>

            <!-- Unidade de destino -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1" for="target_unit">
                    Unidade onde deseja fazer o intercâmbio <span class="text-rose-500">*</span>
                </label>
                <?php if ($companies !== []): ?>
                <select id="target_unit" name="target_unit" required
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-100">
                    <option value="">Selecione a unidade de destino...</option>
                    <?php foreach ($companies as $co): ?>
                        <?php
                        $coId   = (int) ($co['id'] ?? 0);
                        $coName = trim((string) ($co['trade_name'] ?? $co['legal_name'] ?? ''));
                        // Não exibir a própria unidade do aluno
                        if ($coId === (int) ($student['company_id'] ?? 0)) { continue; }
                        ?>
                        <option value="<?= e($coName); ?>"><?= e($coName); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php else: ?>
                <input type="text" id="target_unit" name="target_unit"
                       placeholder="Ex.: ANEO Rio de Janeiro"
                       required
                       class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-100">
                <?php endif; ?>
            </div>

            <!-- Mês desejado -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1" for="desired_month">
                    Mês que deseja realizar o intercâmbio <span class="text-rose-500">*</span>
                </label>
                <input type="month" id="desired_month" name="desired_month"
                       min="<?= e($minMonth); ?>"
                       required
                       class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-100">
            </div>

            <!-- Meses cursando -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1" for="months_enrolled">
                    Quantos meses você já está cursando? <span class="text-rose-500">*</span>
                </label>
                <input type="number" id="months_enrolled" name="months_enrolled"
                       min="1" max="120" placeholder="Ex.: 6"
                       required
                       class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-100">
            </div>

            <!-- Botão submit -->
            <div class="pt-2">
                <button type="submit"
                        class="w-full rounded-xl bg-sky-600 px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-400 transition">
                    Enviar Solicitação de Intercâmbio
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Histórico de solicitações -->
    <?php if ($myRequests !== []): ?>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-100 px-6 py-4">
            <h3 class="font-semibold text-slate-800">Minhas Solicitações</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3 text-left">Data</th>
                        <th class="px-4 py-3 text-left">Destino</th>
                        <th class="px-4 py-3 text-left">Mês desejado</th>
                        <th class="px-4 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($myRequests as $req): ?>
                        <?php
                        $st     = (string) ($req['status'] ?? 'pending');
                        $badge  = $statusBadge[$st]  ?? 'bg-slate-100 text-slate-600';
                        $slabel = $statusLabels[$st] ?? $st;
                        $dm     = (string) ($req['desired_month'] ?? '');
                        $dmFmt  = '';
                        if ($dm !== '' && preg_match('/^(\d{4})-(\d{2})$/', $dm, $m)) {
                            $months = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
                                       'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
                            $dmFmt = ($months[(int) $m[2]] ?? $m[2]) . '/' . $m[1];
                        }
                        $createdAt = (string) ($req['created_at'] ?? '');
                        $dateFmt   = $createdAt !== '' ? date('d/m/Y', strtotime($createdAt)) : '—';
                        ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-4 py-3 text-slate-500 text-xs"><?= e($dateFmt); ?></td>
                            <td class="px-4 py-3 font-medium text-slate-800"><?= e($req['target_unit'] ?? '—'); ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= e($dmFmt ?: $dm); ?></td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-block rounded-full px-3 py-0.5 text-xs font-semibold <?= $badge; ?>">
                                    <?= e($slabel); ?>
                                </span>
                            </td>
                        </tr>
                        <?php if (!empty($req['admin_notes'])): ?>
                        <tr>
                            <td colspan="4" class="px-4 pb-3 text-xs text-slate-500 italic">
                                Observação da equipe: <?= e($req['admin_notes']); ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</section>
