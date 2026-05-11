<?php
$monthNames = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
$monthLabel = $monthNames[$month - 1] . ' ' . $year;

$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

$firstDay = mktime(0, 0, 0, $month, 1, $year);
$weekStart = (int) date('w', $firstDay);
$daysInMonth = (int) date('t', $firstDay);
$weekdayLabels = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
$today = date('Y-m-d');
$gdaCssPath = __DIR__ . '/../../assets/css/gestao_aluno.css';
$gdaCssVersion = is_file($gdaCssPath) ? (string) filemtime($gdaCssPath) : date('YmdHis');

$byDay = [];
foreach ($cards as $card) {
    if (!empty($card['gda_due_date'])) {
        $day = (int) date('j', strtotime((string) $card['gda_due_date']));
        $byDay[$day][] = $card;
    }
}
?>
<link rel="stylesheet" href="assets/css/gestao_aluno.css?v=<?= e($gdaCssVersion); ?>">

<div class="gda-calendar-shell">
    <div class="gda-calendar-topbar">
        <div>
            <span class="gda-calendar-eyebrow">Gestão do Aluno</span>
            <h2 class="gda-calendar-title">Calendário de Prazos</h2>
            <p class="gda-calendar-subtitle"><?= e($monthLabel); ?></p>
        </div>
        <div class="gda-calendar-actions">
            <a href="<?= route('gestao-aluno/calendar&year=' . $prevYear . '&month=' . $prevMonth); ?>" class="gda-btn gda-btn-default text-sm">&larr; Mês anterior</a>
            <a href="<?= route('gestao-aluno/calendar&year=' . date('Y') . '&month=' . date('n')); ?>" class="gda-btn gda-btn-default text-sm">Hoje</a>
            <a href="<?= route('gestao-aluno/calendar&year=' . $nextYear . '&month=' . $nextMonth); ?>" class="gda-btn gda-btn-default text-sm">Próximo mês &rarr;</a>
            <a href="<?= route('gestao-aluno'); ?>" class="gda-btn gda-btn-primary text-sm">Voltar ao board</a>
        </div>
    </div>

    <div class="gda-calendar-panel">
        <div class="gda-calendar-weekdays">
            <?php foreach ($weekdayLabels as $weekday) { ?>
                <div class="gda-calendar-weekday"><?= e($weekday); ?></div>
            <?php } ?>
        </div>

        <div class="gda-calendar-grid">
            <?php
            $cell = 0;

            for ($i = 0; $i < $weekStart; $i++) {
                echo '<div class="gda-calendar-cell gda-calendar-cell-muted"></div>';
                $cell++;
            }

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $isToday = $dateStr === $today;
                $dayCards = $byDay[$day] ?? [];
                $cellClasses = 'gda-calendar-cell' . ($isToday ? ' is-today' : '') . ($dayCards ? ' has-events' : '');
            ?>
                <div class="<?= e($cellClasses); ?>">
                    <div class="gda-calendar-day-head">
                        <span class="gda-calendar-day-number"><?= (int) $day; ?></span>
                        <?php if (count($dayCards) > 3) { ?>
                            <span class="gda-calendar-more">+<?= count($dayCards) - 3; ?></span>
                        <?php } ?>
                    </div>

                    <div class="gda-calendar-events">
                        <?php foreach (array_slice($dayCards, 0, 3) as $card) {
                            $dueStr = (string) ($card['gda_due_date'] ?? '');
                            $overdue = $dueStr !== '' && $dueStr < $today;
                            $eventClass = $overdue ? 'is-overdue' : ($dueStr === $today ? 'is-due-today' : 'is-future');
                            $columnColor = trim((string) ($card['column_color'] ?? '')) ?: '#38bdf8';
                            $columnName = trim((string) ($card['column_name'] ?? ''));
                        ?>
                            <button type="button"
                                class="gda-calendar-event <?= e($eventClass); ?>"
                                onclick="openGdaCard(<?= (int) $card['id']; ?>)"
                                title="<?= e($card['full_name'] . ($columnName !== '' ? ' - ' . $columnName : '')); ?>">
                                <span class="gda-calendar-event-dot" style="background: <?= e($columnColor); ?>"></span>
                                <span class="gda-calendar-event-name"><?= e($card['full_name']); ?></span>
                            </button>
                        <?php } ?>
                    </div>
                </div>
            <?php
                $cell++;
            }

            $remaining = (7 - ($cell % 7)) % 7;
            for ($i = 0; $i < $remaining; $i++) {
                echo '<div class="gda-calendar-cell gda-calendar-cell-muted"></div>';
            }
            ?>
        </div>
    </div>

    <div class="gda-calendar-legend">
        <span><i class="is-overdue"></i> Vencido</span>
        <span><i class="is-due-today"></i> Vence hoje</span>
        <span><i class="is-future"></i> Futuro</span>
    </div>
</div>

<script>
window.gdaConfig = window.gdaConfig || {};
window.gdaConfig.getCardUrl = <?= json_encode(route('gestao-aluno/card')) ?>;
window.gdaConfig.csrf = <?= json_encode(csrf_token()) ?>;

function openGdaCard(id) {
    if (typeof openModal === 'function') {
        openModal(id);
        return;
    }

    window.location.href = <?= json_encode(route('gestao-aluno') . '&open=') ?> + id;
}
</script>