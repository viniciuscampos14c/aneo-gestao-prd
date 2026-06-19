<?php
$featureAvailable = $calendarFeatureAvailable ?? false;
$automation = $automationSummary ?? ['available' => false, 'queued' => 0, 'sent' => 0];
?>
<section class="courses-calendar-shell space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold">Agenda Acadêmica</h2>
            <p class="text-sm text-slate-500">Calendário unificado com provas, aulas ao vivo, atividades e lembretes automaticos.</p>
        </div>
        <a href="<?= route('courses'); ?>" class="courses-calendar-back-btn rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Voltar para Cursos</a>
    </div>

    <?php if (!$featureAvailable): ?>
        <div class="courses-calendar-alert rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Agenda acadêmica indisponivel nesta base. Execute a migração
            <code>migrations/20260305_academic_calendar_full.sql</code> para habilitar atividades e lembretes automaticos.
        </div>
    <?php endif; ?>

    <div class="grid gap-4 sm:grid-cols-3">
        <article class="courses-calendar-kpi courses-calendar-kpi-events rounded-xl border border-indigo-200 bg-indigo-50 p-4">
            <p class="text-xs uppercase text-indigo-700">Eventos no periodo</p>
            <p class="mt-2 text-2xl font-semibold text-indigo-700"><?= count($events); ?></p>
            <p class="text-xs text-indigo-700">De <?= e($fromDate); ?> ate <?= e($toDate); ?></p>
        </article>
        <article class="courses-calendar-kpi courses-calendar-kpi-queued rounded-xl border border-cyan-200 bg-cyan-50 p-4">
            <p class="text-xs uppercase text-cyan-700">Lembretes enfileirados</p>
            <p class="mt-2 text-2xl font-semibold text-cyan-700"><?= (int) ($automation['queued'] ?? 0); ?></p>
            <p class="text-xs text-cyan-700">Gerados automaticamente neste carregamento</p>
        </article>
        <article class="courses-calendar-kpi courses-calendar-kpi-sent rounded-xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs uppercase text-emerald-700">Lembretes enviados</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-700"><?= (int) ($automation['sent'] ?? 0); ?></p>
            <p class="text-xs text-emerald-700">Disparados automaticamente neste carregamento</p>
        </article>
    </div>

    <form method="get" action="index.php" class="courses-calendar-filter grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-4">
        <input type="hidden" name="route" value="courses/calendar">
        <label class="text-sm">
            <span class="mb-1 block text-slate-600">De</span>
            <input type="date" name="from" value="<?= e($fromDate); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>
        <label class="text-sm">
            <span class="mb-1 block text-slate-600">Ate</span>
            <input type="date" name="to" value="<?= e($toDate); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>
        <div class="md:col-span-2 flex items-end gap-2">
            <button class="courses-calendar-apply-btn rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Aplicar periodo</button>
            <a href="<?= route('courses/calendar'); ?>" class="courses-calendar-clear-btn rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm hover:bg-slate-50">Limpar</a>
        </div>
    </form>

    <section class="courses-calendar-create rounded-xl border border-slate-200 bg-white p-4">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-lg font-semibold">Cadastrar prazo de atividade</h3>
            <p class="text-xs text-slate-500">Lembrete automatico para aluno e professor.</p>
        </div>
        <form method="post" action="<?= route('courses/activities/store'); ?>" class="grid gap-3 lg:grid-cols-5">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
            <select name="course_id" required class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Curso...</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?= (int) $course['id']; ?>"><?= e($course['name']); ?> (<?= e($course['status']); ?>)</option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="title" required placeholder="Título da atividade" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <input type="datetime-local" name="due_datetime" required class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <select name="reminder_hours_before" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <?php foreach ([2, 6, 12, 24, 48, 72] as $hours): ?>
                    <option value="<?= $hours; ?>" <?= $hours === 24 ? 'selected' : ''; ?>>Lembrar <?= $hours; ?>h antes</option>
                <?php endforeach; ?>
            </select>
            <button class="courses-calendar-save-btn rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Salvar atividade</button>
            <textarea name="description" rows="2" placeholder="Descrição (opcional)" class="rounded-lg border border-slate-200 px-3 py-2 text-sm lg:col-span-5"></textarea>
        </form>
    </section>

    <section class="courses-calendar-table-panel rounded-xl border border-slate-200 bg-white p-4">
        <h3 class="mb-3 text-lg font-semibold">Calendário Unificado</h3>
        <div class="courses-calendar-table-wrap overflow-x-auto">
            <table class="courses-calendar-table min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-3 py-2">Data/Hora</th>
                        <th class="px-3 py-2">Tipo</th>
                        <th class="px-3 py-2">Curso</th>
                        <th class="px-3 py-2">Evento</th>
                        <th class="px-3 py-2">Publico</th>
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
                            'exam' => 'courses-calendar-type-pill courses-calendar-type-exam bg-amber-100 text-amber-700',
                            'live_class' => 'courses-calendar-type-pill courses-calendar-type-live bg-cyan-100 text-cyan-700',
                            'activity' => 'courses-calendar-type-pill courses-calendar-type-activity bg-indigo-100 text-indigo-700',
                            default => 'courses-calendar-type-pill courses-calendar-type-default bg-slate-100 text-slate-700',
                        };
                        ?>
                        <tr class="courses-calendar-row border-b border-slate-100 hover:bg-slate-50">
                            <td class="px-3 py-2"><?= e(date('d/m/Y H:i', strtotime((string) $event['event_datetime']))); ?></td>
                            <td class="px-3 py-2"><span class="rounded-full px-2 py-1 text-xs font-semibold <?= $typeBadge; ?>"><?= e($typeLabel); ?></span></td>
                            <td class="px-3 py-2"><?= e($event['course_name']); ?></td>
                            <td class="px-3 py-2">
                                <p class="font-medium"><?= e($event['event_title']); ?></p>
                                <?php if (!empty($event['event_description'])): ?>
                                    <p class="text-xs text-slate-500"><?= e($event['event_description']); ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2"><?= (int) ($event['audience_students'] ?? 0); ?> aluno(s)</td>
                            <td class="px-3 py-2"><?= e(date('d/m/Y H:i', strtotime((string) $event['reminder_datetime']))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($events === []): ?>
                        <tr><td colspan="6" class="px-3 py-6 text-center text-slate-500">Sem eventos no periodo selecionado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="courses-calendar-activities rounded-xl border border-slate-200 bg-white p-4">
            <h3 class="mb-3 text-lg font-semibold">Atividades cadastradas</h3>
            <div class="space-y-2 text-sm">
                <?php foreach ($activities as $activity): ?>
                    <div class="courses-calendar-activity-card rounded-lg border border-slate-100 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold"><?= e($activity['title']); ?> <span class="text-slate-500">(<?= e($activity['course_name']); ?>)</span></p>
                                <p class="text-slate-500">Prazo: <?= e(date('d/m/Y H:i', strtotime((string) $activity['due_datetime']))); ?> | Lembrete: <?= (int) $activity['reminder_hours_before']; ?>h antes</p>
                                <?php if (!empty($activity['description'])): ?>
                                    <p class="text-slate-600"><?= e($activity['description']); ?></p>
                                <?php endif; ?>
                            </div>
                            <form method="post" action="<?= route('courses/activities/delete'); ?>" onsubmit="return confirm('Remover atividade?');">
                                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                <input type="hidden" name="activity_id" value="<?= (int) $activity['id']; ?>">
                                <button class="courses-calendar-delete-btn rounded border border-rose-200 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50">Excluir</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if ($activities === []): ?>
                    <p class="text-slate-500">Nenhuma atividade cadastrada no periodo.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="courses-calendar-reminders rounded-xl border border-slate-200 bg-white p-4">
            <h3 class="mb-3 text-lg font-semibold">Lembretes automaticos recentes</h3>
            <div class="space-y-2 text-sm">
                <?php foreach ($recentReminders as $reminder): ?>
                    <?php $recipient = (string) ($reminder['recipient_name'] ?? '#'); ?>
                    <div class="courses-calendar-reminder-card rounded-lg border border-slate-100 px-3 py-2">
                        <p class="font-medium"><?= e($reminder['message']); ?></p>
                        <p class="text-xs text-slate-500">
                            Curso: <?= e($reminder['course_name'] ?? '-'); ?> |
                            Destinatario: <?= e($recipient); ?> (<?= e($reminder['recipient_type']); ?>) |
                            Enviado em: <?= !empty($reminder['sent_at']) ? e(date('d/m/Y H:i', strtotime((string) $reminder['sent_at']))) : '-'; ?>
                        </p>
                    </div>
                <?php endforeach; ?>
                <?php if ($recentReminders === []): ?>
                    <p class="text-slate-500">Nenhum lembrete automatico enviado ainda.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>
</section>
