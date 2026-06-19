<?php
/**
 * @var array  $student      dados do aluno logado
 * @var array  $openInvoices faturas em aberto/vencidas
 * @var array  $period       ['period_start' => ..., 'period_end' => ...]
 * @var bool   $canConfirm   true se não há faturas em aberto
 */

$statusLabels = [
    'open'    => 'Em aberto',
    'overdue' => 'Vencida',
    'partial' => 'Parcial',
];
$statusBadge = [
    'open'    => 'bg-blue-100 text-blue-700',
    'overdue' => 'bg-rose-100 text-rose-700',
    'partial' => 'bg-amber-100 text-amber-700',
];

$fmtDate = fn (string $d): string => $d !== '' ? date('d/m/Y', strtotime($d)) : '—';
$fmtMoney = fn (float $v): string => 'R$ ' . number_format($v, 2, ',', '.');

$periodStartFmt = isset($period['period_start']) ? $fmtDate($period['period_start']) : '—';
$periodEndFmt   = isset($period['period_end'])   ? $fmtDate($period['period_end'])   : '—';

$studentName    = trim((string) ($student['name'] ?? $student['full_name'] ?? ''));
$studentEmail   = trim((string) ($student['email_primary'] ?? $student['email'] ?? ''));
$studentUnit    = trim((string) ($student['company_name'] ?? ''));
$whatsappPhone  = '5561984264485';
$whatsappMessage = trim('Olá, sou ' . ($studentName !== '' ? $studentName : 'aluno ANEO') . ' e preciso de apoio para regularizar minha situação financeira e concluir a rematrícula.');
$whatsappUrl = 'https://wa.me/' . $whatsappPhone . '?text=' . rawurlencode($whatsappMessage);
?>

<section class="student-reenroll-shell max-w-2xl mx-auto space-y-8">

    <!-- Cabeçalho -->
    <div class="text-center">
        <div class="student-reenroll-icon-wrap inline-flex items-center justify-center w-16 h-16 rounded-full bg-sky-100 mb-4">
            <svg class="w-8 h-8 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <h2 class="student-reenroll-title text-2xl font-bold text-slate-800">Chegou a hora da sua rematrícula!</h2>
        <p class="student-reenroll-subtitle mt-2 text-slate-500 text-sm">
            Confirme seus dados abaixo para renovar sua matrícula no período
            <strong><?= e($periodStartFmt); ?></strong> a <strong><?= e($periodEndFmt); ?></strong>.
        </p>
    </div>

    <!-- Dados do aluno -->
    <div class="student-reenroll-card rounded-xl border border-slate-200 bg-white shadow-sm divide-y divide-slate-100">
        <div class="px-6 py-4">
            <h3 class="font-semibold text-slate-800">Seus dados</h3>
        </div>
        <dl class="student-reenroll-dl divide-y divide-slate-100">
            <div class="student-reenroll-row grid grid-cols-3 px-6 py-3 text-sm">
                <dt class="font-medium text-slate-500">Nome completo</dt>
                <dd class="col-span-2 text-slate-800 font-medium"><?= e($studentName ?: '—'); ?></dd>
            </div>
            <div class="student-reenroll-row grid grid-cols-3 px-6 py-3 text-sm">
                <dt class="font-medium text-slate-500">E-mail</dt>
                <dd class="col-span-2 text-slate-700"><?= e($studentEmail ?: '—'); ?></dd>
            </div>
            <?php if ($studentUnit !== ''): ?>
            <div class="student-reenroll-row grid grid-cols-3 px-6 py-3 text-sm">
                <dt class="font-medium text-slate-500">Unidade</dt>
                <dd class="col-span-2 text-slate-700"><?= e($studentUnit); ?></dd>
            </div>
            <?php endif; ?>
            <div class="student-reenroll-row grid grid-cols-3 px-6 py-3 text-sm">
                <dt class="font-medium text-slate-500">Período</dt>
                <dd class="col-span-2 text-slate-700">
                    <?= e($periodStartFmt); ?> até <?= e($periodEndFmt); ?>
                </dd>
            </div>
        </dl>
    </div>

    <!-- Situação financeira -->
    <?php if ($canConfirm): ?>
        <div class="student-reenroll-success rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 flex items-center gap-3">
            <svg class="w-6 h-6 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm font-medium text-emerald-800">
                Você está em dia com suas mensalidades. Pode confirmar sua rematrícula!
            </p>
        </div>
    <?php else: ?>
        <!-- Alerta de inadimplência -->
        <div class="student-reenroll-warning rounded-xl border border-rose-200 bg-rose-50 px-5 py-4 space-y-4">
            <div class="flex items-start gap-3">
                <svg class="w-6 h-6 text-rose-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                <div>
                    <p class="text-sm font-semibold text-rose-800">Você possui faturas em aberto.</p>
                    <p class="text-sm text-rose-700 mt-1">
                        Para confirmar sua rematrícula, é necessário regularizar sua situação financeira.
                    </p>
                </div>
            </div>

            <!-- Tabela de faturas em aberto -->
            <div class="student-reenroll-table-wrap overflow-x-auto rounded-lg border border-rose-200 bg-white">
                <table class="student-reenroll-table min-w-full text-sm">
                    <thead class="student-reenroll-table-head bg-rose-50 text-xs uppercase tracking-wide text-rose-600 border-b border-rose-200">
                        <tr>
                            <th class="px-4 py-2 text-left">Fatura</th>
                            <th class="px-4 py-2 text-left">Vencimento</th>
                            <th class="px-4 py-2 text-right">Valor</th>
                            <th class="px-4 py-2 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="student-reenroll-table-body divide-y divide-slate-100">
                        <?php foreach ($openInvoices as $inv): ?>
                            <?php
                            $st     = (string) ($inv['status'] ?? 'open');
                            $badge  = $statusBadge[$st]  ?? 'bg-slate-100 text-slate-600';
                            $slabel = $statusLabels[$st] ?? $st;
                            $due    = (string) ($inv['due_date'] ?? '');
                            $dueFmt = $due !== '' ? date('d/m/Y', strtotime($due)) : '—';
                            $amount = (float) ($inv['amount'] ?? 0);
                            $paid   = (float) ($inv['paid_amount'] ?? 0);
                            $remaining = isset($inv['outstanding_amount'])
                                ? (float) $inv['outstanding_amount']
                                : max($amount - $paid, 0);
                            $isOverdue = $st === 'overdue';
                            ?>
                            <tr>
                                <td class="px-4 py-2 text-slate-700">
                                    <?= e((string) ($inv['invoice_number'] ?? '#' . $inv['id'])); ?>
                                </td>
                                <td class="px-4 py-2 <?= $isOverdue ? 'text-rose-600 font-semibold' : 'text-slate-600'; ?>">
                                    <?= e($dueFmt); ?>
                                </td>
                                <td class="px-4 py-2 text-right text-slate-800 font-medium">
                                    <?= e($fmtMoney($remaining > 0 ? $remaining : $amount)); ?>
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <span class="student-reenroll-status inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $badge; ?>">
                                        <?= e($slabel); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mensagem para procurar o administrativo -->
            <div class="student-reenroll-help rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <p class="font-semibold">O que fazer?</p>
                <p class="mt-1">
                    Procure o administrativo da sua unidade e regularize sua situação financeira.
                    Assim que as faturas forem quitadas, você poderá confirmar sua rematrícula normalmente.
                </p>
                <a
                    href="<?= e($whatsappUrl); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-3 inline-flex w-full items-center justify-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-400 sm:w-auto">
                    Entrar em contato pelo WhatsApp
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Botão de confirmação -->
    <?php if ($canConfirm): ?>
        <form method="POST" action="<?= route('student/reenrollment/confirm'); ?>">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
            <button type="submit"
                    class="student-reenroll-btn-confirm w-full rounded-xl bg-emerald-600 px-6 py-3.5 text-base font-bold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-400 transition">
                Confirmar Rematrícula
            </button>
            <p class="student-reenroll-note mt-2 text-center text-xs text-slate-400">
                Ao confirmar, você renova sua matrícula pelo próximo período de 6 meses.
            </p>
        </form>
    <?php else: ?>
        <button type="button" disabled
                class="student-reenroll-btn-disabled w-full rounded-xl bg-slate-200 px-6 py-3.5 text-base font-bold text-slate-400 cursor-not-allowed">
            Confirmar Rematrícula
        </button>
        <p class="student-reenroll-note mt-2 text-center text-xs text-slate-400">
            A confirmação ficará disponível após a regularização das faturas em aberto.
        </p>
    <?php endif; ?>

</section>
