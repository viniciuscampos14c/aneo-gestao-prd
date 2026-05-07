<?php
$metrics = $summary['metrics'];
$learningFocus = $summary['learning_focus'] ?? [
    'has_course' => false,
    'all_done' => false,
    'course' => null,
    'module' => null,
    'lesson' => null,
    'completion_percent' => 0,
    'completion_remaining' => 100,
    'cta_url' => route('student/courses'),
];
$upcoming = $summary['upcoming_live'];
$recentResults = $summary['recent_results'];
$upcomingExams = $summary['upcoming_exams'] ?? [];
$scheduleEnabled = $examScheduleEnabled ?? false;
$focusCourse = is_array($learningFocus['course'] ?? null) ? $learningFocus['course'] : null;
$focusModule = is_array($learningFocus['module'] ?? null) ? $learningFocus['module'] : null;
$focusLesson = is_array($learningFocus['lesson'] ?? null) ? $learningFocus['lesson'] : null;
$focusProgress = max(0, min(100, (int) ($learningFocus['completion_percent'] ?? 0)));
$focusRemaining = max(0, min(100, (int) ($learningFocus['completion_remaining'] ?? (100 - $focusProgress))));
$hasFocusCourse = !empty($learningFocus['has_course']) && $focusCourse;
?>
<section class="space-y-6">
    <div class="rounded-2xl border border-sky-100 bg-white/80 p-5">
        <h2 class="text-2xl font-semibold">Ola, <?= e($student['name']); ?></h2>
        <p class="text-sm text-slate-500">Esse e seu painel de estudos.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-xl border border-sky-100 bg-white/90 p-4">
            <p class="text-xs uppercase text-slate-500">Cursos matriculados</p>
            <p class="mt-2 text-2xl font-semibold"><?= (int) $metrics['courses_total']; ?></p>
        </article>
        <article class="rounded-xl border border-sky-100 bg-sky-50/80 p-4">
            <p class="text-xs uppercase text-slate-500">Cursos ativos</p>
            <p class="mt-2 text-2xl font-semibold text-sky-700"><?= (int) $metrics['courses_active']; ?></p>
        </article>
        <article class="rounded-xl border border-emerald-100 bg-emerald-50/70 p-4">
            <p class="text-xs uppercase text-slate-500">Cursos concluidos</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-700"><?= (int) $metrics['courses_completed']; ?></p>
        </article>
        <article class="rounded-xl border border-amber-100 bg-amber-50/70 p-4">
            <p class="text-xs uppercase text-slate-500">Media de progresso</p>
            <p class="mt-2 text-2xl font-semibold"><?= (int) round((float) $metrics['avg_progress']); ?>%</p>
        </article>
    </div>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1.6fr)_minmax(280px,0.9fr)]">
        <article class="student-focus-hero overflow-hidden rounded-2xl border border-cyan-200 bg-gradient-to-br from-cyan-950 via-slate-900 to-sky-800 p-5 text-white shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="student-focus-eyebrow text-xs font-semibold uppercase tracking-[0.28em] text-cyan-200">Continue de onde parou</p>
                    <?php if ($hasFocusCourse): ?>
                        <h3 class="student-focus-title mt-3 text-2xl font-semibold"><?= e((string) ($focusCourse['name'] ?? 'Curso')); ?></h3>
                        <p class="student-focus-copy mt-2 text-sm text-cyan-100">
                            <?= $focusLesson ? 'Proxima aula: ' . e((string) ($focusLesson['title'] ?? 'Aula')) : 'Sua trilha esta pronta para continuar.'; ?>
                        </p>
                    <?php else: ?>
                        <h3 class="student-focus-title mt-3 text-2xl font-semibold">Sua trilha comeca aqui</h3>
                        <p class="student-focus-copy mt-2 text-sm text-cyan-100">Assim que houver curso com modulos, ele aparece aqui como atalho principal.</p>
                    <?php endif; ?>
                </div>
                <div class="student-focus-progress-card rounded-2xl border border-white/15 bg-white/10 px-4 py-3 text-right">
                    <p class="student-focus-progress-label text-xs uppercase tracking-wide text-cyan-100">Progresso</p>
                    <p class="student-focus-progress-value mt-1 text-3xl font-semibold"><?= $focusProgress; ?>%</p>
                </div>
            </div>
            <div class="student-focus-progress-track mt-5 h-2 rounded-full bg-white/15">
                <div class="student-focus-progress-fill h-2 rounded-full bg-cyan-300" style="width: <?= $focusProgress; ?>%"></div>
            </div>
            <div class="mt-5 flex flex-wrap items-center gap-3">
                <a href="<?= e((string) ($learningFocus['cta_url'] ?? route('student/courses'))); ?>" class="student-focus-primary rounded-xl bg-white px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-50">
                    <?= $focusLesson ? 'Continuar aula' : 'Ver meus cursos'; ?>
                </a>
                <?php if (!empty($learningFocus['all_done'])): ?>
                    <span class="student-focus-chip rounded-full border border-emerald-300/50 bg-emerald-300/15 px-3 py-1 text-xs font-semibold text-emerald-100">Curso concluido</span>
                <?php elseif ($focusRemaining > 0): ?>
                    <span class="student-focus-chip rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold text-cyan-100">Faltam <?= $focusRemaining; ?>% para concluir o curso</span>
                <?php endif; ?>
            </div>
        </article>

        <article class="student-focus-panel rounded-2xl border border-sky-100 bg-white/90 p-5">
            <p class="student-focus-panel-eyebrow text-xs font-semibold uppercase tracking-[0.22em] text-sky-600">Modulo atual</p>
            <?php if ($focusModule): ?>
                <h3 class="student-focus-panel-title mt-3 text-lg font-semibold text-slate-900"><?= e((string) ($focusModule['title'] ?? 'Modulo atual')); ?></h3>
                <p class="student-focus-panel-copy mt-2 text-sm text-slate-500">
                    <?= (int) ($focusModule['completed_lessons'] ?? 0); ?>/<?= (int) ($focusModule['total_lessons'] ?? 0); ?> aula(s) concluidas
                </p>
                <p class="student-focus-note mt-4 rounded-xl border border-sky-100 bg-sky-50 px-3 py-2 text-xs text-sky-800">
                    Concluir este modulo libera naturalmente a proxima etapa da trilha.
                </p>
            <?php else: ?>
                <h3 class="student-focus-panel-title mt-3 text-lg font-semibold text-slate-900">Nenhum modulo ativo</h3>
                <p class="student-focus-panel-copy mt-2 text-sm text-slate-500">Quando voce iniciar um curso modular, acompanhamos seu ponto atual aqui.</p>
            <?php endif; ?>
        </article>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <section class="rounded-xl border border-slate-200 bg-white/90 p-4">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-lg font-semibold">Proximas aulas ao vivo</h3>
                <a href="<?= route('student/live'); ?>" class="text-sm text-cyan-700 hover:underline">Ver todas</a>
            </div>
            <div class="space-y-2 text-sm">
                <?php foreach ($upcoming as $item): ?>
                    <div class="rounded-lg border border-slate-100 px-3 py-2">
                        <p class="font-medium"><?= e($item['name']); ?></p>
                        <p class="text-slate-500">Data: <?= e(date('d/m/Y H:i', strtotime((string) $item['live_datetime']))); ?></p>
                    </div>
                <?php endforeach; ?>
                <?php if ($upcoming === []): ?>
                    <p class="text-slate-500">Nenhuma aula ao vivo agendada.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="rounded-xl border border-sky-100 bg-sky-50/70 p-4">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-sky-900">Calendario de provas</h3>
                <a href="<?= route('student/exams'); ?>" class="text-sm text-sky-700 hover:underline">Abrir avaliacoes</a>
            </div>
            <?php if (!$scheduleEnabled): ?>
                <p class="text-sm text-slate-600">Datas de provas ainda nao foram habilitadas pela equipe administrativa.</p>
            <?php else: ?>
                <div class="space-y-2 text-sm">
                    <?php foreach ($upcomingExams as $exam): ?>
                        <div class="rounded-lg border border-sky-100 bg-white px-3 py-2">
                            <p class="font-medium"><?= e($exam['title']); ?> <span class="text-slate-500">(<?= e($exam['course_name']); ?>)</span></p>
                            <p class="text-sky-700">Data: <?= e(date('d/m/Y H:i', strtotime((string) $exam['scheduled_at']))); ?></p>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($upcomingExams === []): ?>
                        <p class="text-slate-600">Nenhuma prova com data marcada no momento.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white/90 p-4">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-lg font-semibold">Ultimas avaliacoes</h3>
                <a href="<?= route('student/exams'); ?>" class="text-sm text-cyan-700 hover:underline">Ver historico</a>
            </div>
            <div class="space-y-2 text-sm">
                <?php foreach ($recentResults as $result): ?>
                    <div class="rounded-lg border border-slate-100 px-3 py-2">
                        <p class="font-medium"><?= e($result['exam_title']); ?> <span class="text-slate-500">(<?= e($result['course_name']); ?>)</span></p>
                        <p class="text-slate-500">Nota <?= e(number_format((float) $result['score'], 2, ',', '.')); ?> | <?= e($result['status']); ?></p>
                    </div>
                <?php endforeach; ?>
                <?php if ($recentResults === []): ?>
                    <p class="text-slate-500">Sem avaliacoes registradas.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>
</section>
