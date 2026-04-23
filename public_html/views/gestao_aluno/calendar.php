<?php
$monthNames = ['Janeiro','Fevereiro','MarÃ§o','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
$monthLabel = $monthNames[$month - 1] . ' ' . $year;

$prevMonth = $month - 1; $prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1; $nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

$firstDay   = mktime(0, 0, 0, $month, 1, $year);
$weekStart  = (int) date('w', $firstDay); // 0=dom
$daysInMonth = (int) date('t', $firstDay);

// indexar cards por dia
$byDay = [];
foreach ($cards as $c) {
    if ($c['gda_due_date']) {
        $d = (int) date('j', strtotime($c['gda_due_date']));
        $byDay[$d][] = $c;
    }
}
?>
<link rel="stylesheet" href="assets/css/gestao_aluno.css">

<div class="p-4">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-bold text-slate-800">CalendÃ¡rio de Prazos</h2>
            <p class="text-xs text-slate-500"><?= e($monthLabel) ?></p>
        </div>
        <div class="flex items-center gap-3">
            <a href="<?= route('gestao-aluno/calendar&year=' . $prevYear . '&month=' . $prevMonth) ?>" class="gda-btn gda-btn-default text-sm">â† Anterior</a>
            <a href="<?= route('gestao-aluno/calendar&year=' . date('Y') . '&month=' . date('n')) ?>" class="gda-btn gda-btn-default text-sm">Hoje</a>
            <a href="<?= route('gestao-aluno/calendar&year=' . $nextYear . '&month=' . $nextMonth) ?>" class="gda-btn gda-btn-default text-sm">PrÃ³ximo â†’</a>
            <a href="<?= route('gestao-aluno') ?>" class="gda-btn gda-btn-default text-sm">â† Board</a>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <!-- CabeÃ§alho da semana -->
        <div class="grid grid-cols-7 border-b border-slate-200">
            <?php foreach (['Dom','Seg','Ter','Qua','Qui','Sex','SÃ¡b'] as $wd) { ?>
                <div class="text-center text-xs font-semibold text-slate-500 py-2"><?= $wd ?></div>
            <?php } ?>
        </div>

        <!-- CÃ©lulas -->
        <div class="grid grid-cols-7">
            <?php
            $today = date('Y-m-d');
            $cell  = 0;

            // cÃ©lulas vazias antes do dia 1
            for ($i = 0; $i < $weekStart; $i++) {
                echo '<div class="min-h-24 border-r border-b border-slate-100 bg-slate-50"></div>';
                $cell++;
            }

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $isToday = $dateStr === $today;
                $dayCards = $byDay[$day] ?? [];
                $weekCol  = ($cell % 7);
                $isLastCol = $weekCol === 6;
            ?>
            <div class="min-h-24 border-b border-slate-100 p-1 <?= $isLastCol ? '' : 'border-r' ?> <?= $isToday ? 'bg-blue-50' : '' ?>">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-semibold <?= $isToday ? 'bg-blue-600 text-white w-5 h-5 flex items-center justify-center rounded-full' : 'text-slate-500' ?>">
                        <?= $day ?>
                    </span>
                    <?php if (count($dayCards) > 3) { ?>
                        <span class="text-xs text-slate-400">+<?= count($dayCards) - 3 ?></span>
                    <?php } ?>
                </div>
                <?php foreach (array_slice($dayCards, 0, 3) as $c) {
                    $priority = $c['gda_priority'] ?? 'none';
                    $dueStr   = $c['gda_due_date'];
                    $overdue  = $dueStr < $today;
                ?>
                    <div class="text-xs rounded px-1 py-0.5 mb-0.5 truncate cursor-pointer hover:opacity-80
                        <?= $overdue ? 'bg-red-100 text-red-700' : ($dueStr === $today ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700') ?>"
                        onclick="openGdaCard(<?= (int)$c['id'] ?>)"
                        title="<?= e($c['full_name']) ?>">
                        <?= e($c['full_name']) ?>
                    </div>
                <?php } ?>
            </div>
            <?php
                $cell++;
            } // end for day

            // cÃ©lulas vazias no final
            $remaining = (7 - ($cell % 7)) % 7;
            for ($i = 0; $i < $remaining; $i++) {
                echo '<div class="min-h-24 border-r border-b border-slate-100 bg-slate-50"></div>';
            }
            ?>
        </div>
    </div>

    <!-- Legenda -->
    <div class="flex gap-4 mt-4 text-xs text-slate-500">
        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-red-200 inline-block"></span> Vencido</span>
        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-amber-200 inline-block"></span> Vence hoje</span>
        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-blue-200 inline-block"></span> Futuro</span>
    </div>
</div>

<script>
window.gdaConfig = window.gdaConfig || {};
window.gdaConfig.getCardUrl = <?= json_encode(route('gestao-aluno/card')) ?>;
window.gdaConfig.csrf = <?= json_encode(csrf_token()) ?>;

function openGdaCard(id) {
    // Reutiliza o modal se jÃ¡ estiver carregado, senÃ£o redireciona
    if (typeof openModal === 'function') {
        openModal(id);
    } else {
        window.location.href = <?= json_encode(route('gestao-aluno') . '&open=') ?> + id;
    }
}
</script>

