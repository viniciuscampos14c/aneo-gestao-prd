<?php
$featureAvailable = $calendarFeatureAvailable ?? false;
$automation = $automationSummary ?? ['available' => false, 'queued' => 0, 'sent' => 0];
?>
<section class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-semibold">Agenda Academica</h2>
            <p class="text-sm text-slate-500">Calendario unificado com provas, aulas ao vivo e prazos de atividades.</p>
        </div>
    </div>

    <?php if (!$featureAvailable): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Agenda academica ainda nao habilitada nesta base. Avise o administrativo para executar a migracao mais recente.
        </div>
    <?php endif; ?>

    <div class="grid gap-4 sm:grid-cols-3">
        <article class="rounded-xl border border-sky-200 bg-sky-50 p-4">
            <p class="text-xs uppercase text-sky-700">Eventos no periodo</p>
            <p class="mt-2 text-2xl font-semibold text-sky-700"><?= count($events); ?></p>
            <p class="text-xs text-sky-700">De <?= e($fromDate); ?> ate <?= e($toDate); ?></p>
        </article>
        <article class="rounded-xl border border-cyan-200 bg-cyan-50 p-4">
            <p class="text-xs uppercase text-cyan-700">Lembretes enfileirados</p>
            <p class="mt-2 text-2xl font-semibold text-cyan-700"><?= (int) ($automation['queued'] ?? 0); ?></p>
            <p class="text-xs text-cyan-700">Processados automaticamente</p>
        </article>
        <article class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs uppercase text-emerald-700">Lembretes enviados</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-700"><?= (int) ($automation['sent'] ?? 0); ?></p>
            <p class="text-xs text-emerald-700">Disparados automaticamente</p>
        </article>
    </div>

    <form method="get" action="index.php" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-4">
        <input type="hidden" name="route" value="student/calendar">
        <label class="text-sm">
            <span class="mb-1 block text-slate-600">De</span>
            <input type="date" name="from" value="<?= e($fromDate); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>
        <label class="text-sm">
            <span class="mb-1 block text-slate-600">Ate</span>
            <input type="date" name="to" value="<?= e($toDate); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>
        <div class="md:col-span-2 flex items-end gap-2">
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Aplicar periodo</button>
            <a href="<?= route('student/calendar'); ?>" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm hover:bg-slate-50">Limpar</a>
        </div>
    </form>

    <section class="rounded-xl border border-slate-200 bg-white p-4">
        <h3 class="mb-3 text-lg font-semibold">Meu calendario</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-3 py-2">Data/Hora</th>
                        <th class="px-3 py-2">Tipo</th>
                        <th class="px-3 py-2">Curso</th>
                        <th class="px-3 py-2">Evento</th>
                        <th class="px-3 py-2">Lembrete automatico</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $event): ?>
                        <?php
                        $type = (string) ($event['event_type'] ?? '');
                        $typeLabel = match ($type) {
                            'exam' => 'Prova',
                            'live_class' => 'Aula ao vivo',
                            'activity' => 'Atividade',
                            default => 'Evento',
                        };
                        $typeBadge = match ($type) {
                            'exam' => 'border border-amber-300/70 bg-amber-200 text-amber-950 shadow-sm',
                            'live_class' => 'border border-cyan-300/70 bg-cyan-200 text-cyan-950 shadow-sm',
                            'activity' => 'border border-indigo-300/70 bg-indigo-100 text-indigo-900 shadow-sm',
                            default => 'border border-slate-300 bg-slate-100 text-slate-800 shadow-sm',
                        };
                        ?>
                        <tr class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="px-3 py-2"><?= e(date('d/m/Y H:i', strtotime((string) $event['event_datetime']))); ?></td>
                            <td class="px-3 py-2"><span class="rounded-full px-2 py-1 text-xs font-semibold <?= $typeBadge; ?>"><?= e($typeLabel); ?></span></td>
                            <td class="px-3 py-2"><?= e($event['course_name']); ?></td>
                            <td class="px-3 py-2">
                                <p class="font-medium"><?= e($event['event_title']); ?></p>
                                <?php if (!empty($event['event_description'])): ?>
                                    <p class="text-xs text-slate-500"><?= e($event['event_description']); ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2"><?= e(date('d/m/Y H:i', strtotime((string) $event['reminder_datetime']))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($events === []): ?>
                        <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">Nenhum evento academico no periodo.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-4">
        <h3 class="mb-3 text-lg font-semibold">Lembretes automaticos recentes</h3>
        <div class="space-y-2 text-sm">
            <?php foreach ($reminders as $reminder): ?>
                <div class="rounded-lg border border-slate-100 px-3 py-2">
                    <p class="font-medium"><?= e($reminder['message']); ?></p>
                    <p class="text-xs text-slate-500">
                        Curso: <?= e($reminder['course_name'] ?? '-'); ?> |
                        Enviado em: <?= !empty($reminder['sent_at']) ? e(date('d/m/Y H:i', strtotime((string) $reminder['sent_at']))) : '-'; ?>
                    </p>
                </div>
            <?php endforeach; ?>
            <?php if ($reminders === []): ?>
                <p class="text-slate-500">Nenhum lembrete automatico enviado para voce ainda.</p>
            <?php endif; ?>
        </div>
    </section>
</section>
