<?php
$titleText = (string) ($schedule['title'] ?? 'Escala Aluno');
$unitName = (string) ($schedule['unit_name'] ?? '');
$periodText = date('d/m/Y', strtotime((string) $schedule['start_date'])) . ' ate ' . date('d/m/Y', strtotime((string) $schedule['end_date']));
?>
<div class="print-shell">
    <header class="print-hero">
        <div class="print-brand">ANEO</div>
        <div class="print-heading">
            <p class="print-kicker">Escala de Plantoes</p>
            <h1><?= e($titleText); ?></h1>
            <div class="print-meta">
                <span><strong>Unidade:</strong> <?= e($unitName); ?></span>
                <span><strong>Periodo:</strong> <?= e($periodText); ?></span>
                <span><strong>Gerado em:</strong> <?= e(date('d/m/Y H:i')); ?></span>
            </div>
        </div>
    </header>

    <table class="print-table">
        <thead>
            <tr>
                <th>Mes</th>
                <th>Datas</th>
                <th>R3</th>
                <th>R2</th>
                <th>R1</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($weeksByMonth as $monthRef => $monthWeeks): ?>
                <?php foreach ($monthWeeks as $index => $week): ?>
                    <tr>
                        <?php if ($index === 0): ?>
                            <td rowspan="<?= (int) count($monthWeeks); ?>" class="month-cell"><?= e((string) $monthRef); ?></td>
                        <?php endif; ?>
                        <td class="date-cell">
                            <div class="date-range"><?= e(date('d', strtotime((string) $week['start_date'])) . ' a ' . date('d', strtotime((string) $week['end_date']))); ?></div>
                            <div class="date-sub"><?= e(date('d/m', strtotime((string) $week['start_date'])) . ' - ' . date('d/m', strtotime((string) $week['end_date']))); ?></div>
                        </td>
                        <?php foreach (['R3', 'R2', 'R1'] as $group): ?>
                            <td>
                                <?= e(implode(' / ', array_map(static fn ($assignment) => (string) ($assignment['student_name'] ?? ''), $week['assignments'][$group] ?? []))); ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script>window.print();</script>
