<?php
$metrics = $summary['metrics'];
$upcoming = $summary['upcoming_live'];
$recentResults = $summary['recent_results'];
$upcomingExams = $summary['upcoming_exams'] ?? [];
$scheduleEnabled = $examScheduleEnabled ?? false;
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
