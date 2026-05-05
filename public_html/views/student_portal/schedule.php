<section class="student-schedule-shell space-y-6">
    <div class="student-schedule-hero rounded-2xl p-5 shadow-sm">
        <h2 class="text-2xl font-semibold">Minha Escala</h2>
        <p class="mt-1 text-sm">Consulte seus plantoes publicados por unidade, periodo e semana.</p>
    </div>

    <?php if (!$featureAvailable): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Sua escala ainda nao foi habilitada pela equipe administrativa.
        </div>
    <?php elseif ($schedules === []): ?>
        <div class="student-schedule-empty rounded-2xl border border-dashed px-6 py-10 text-center">
            <p class="text-base font-medium">Nenhuma escala publicada para voce no momento.</p>
            <p class="mt-2 text-sm">Assim que sua unidade publicar uma escala com seu nome, ela aparecera aqui.</p>
        </div>
    <?php else: ?>
        <div class="space-y-6">
            <?php foreach ($schedules as $schedule): ?>
                <section class="student-schedule-card overflow-hidden rounded-2xl border shadow-sm">
                    <div class="student-schedule-card-head border-b px-5 py-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="student-schedule-unit text-xs font-semibold uppercase tracking-[0.2em]"><?= e((string) ($schedule['unit_name'] ?? '')); ?></p>
                                <h3 class="mt-1 text-xl font-semibold"><?= e((string) ($schedule['schedule_title'] ?? 'Escala')); ?></h3>
                                <p class="student-schedule-period mt-1 text-sm">
                                    Periodo: <?= e(date('d/m/Y', strtotime((string) ($schedule['schedule_start_date'] ?? 'now')))); ?> ate <?= e(date('d/m/Y', strtotime((string) ($schedule['schedule_end_date'] ?? 'now')))); ?>
                                </p>
                            </div>
                            <span class="student-schedule-badge rounded-full px-3 py-1 text-xs font-semibold">Publicada</span>
                        </div>
                    </div>

                    <div class="space-y-5 p-5">
                        <?php foreach (($schedule['months'] ?? []) as $monthRef => $weeks): ?>
                            <div class="student-schedule-month-wrap overflow-hidden rounded-xl border">
                                <div class="student-schedule-month-head px-4 py-3 text-sm font-semibold uppercase tracking-[0.18em]">
                                    <?= e((string) $monthRef); ?>
                                </div>
                                <div class="student-schedule-month-body divide-y">
                                    <?php foreach ($weeks as $week): ?>
                                        <article class="grid gap-3 px-4 py-4 md:grid-cols-[180px_1fr] md:items-center">
                                            <div>
                                                <p class="student-schedule-date text-lg font-semibold"><?= e(date('d', strtotime((string) $week['week_start_date'])) . ' a ' . date('d', strtotime((string) $week['week_end_date']))); ?></p>
                                                <p class="student-schedule-date-sub text-sm"><?= e(date('d/m', strtotime((string) $week['week_start_date'])) . ' - ' . date('d/m', strtotime((string) $week['week_end_date']))); ?></p>
                                            </div>
                                            <div class="student-schedule-week-card rounded-xl border px-4 py-3">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="rounded-full bg-sky-600 px-2.5 py-1 text-xs font-semibold text-white"><?= e((string) ($week['slot_group'] ?? 'R1')); ?></span>
                                                    <span class="student-schedule-week-text text-sm font-medium">Voce esta escalado(a) nesta semana.</span>
                                                </div>
                                                <?php if (!empty($week['week_notes'])): ?>
                                                    <p class="student-schedule-week-note mt-2 text-sm"><?= nl2br(e((string) $week['week_notes'])); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
