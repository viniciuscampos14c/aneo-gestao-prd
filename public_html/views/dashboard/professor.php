<?php
$overview = is_array($overview ?? null) ? $overview : [];
$coursePerformance = is_array($coursePerformance ?? null) ? $coursePerformance : [];
$studentsAtRisk = is_array($studentsAtRisk ?? null) ? $studentsAtRisk : [];
$upcomingEvents = is_array($upcomingEvents ?? null) ? $upcomingEvents : [];
$recentComments = is_array($recentComments ?? null) ? $recentComments : [];
$professorName = trim((string) (current_user()['name'] ?? 'Professor'));
$firstName = trim((string) preg_replace('/\s+.*/', '', $professorName));
$firstName = $firstName !== '' ? $firstName : 'Professor';

$publishedCourses = (int) ($overview['published_courses'] ?? 0);
$activeEnrollments = (int) ($overview['active_enrollments'] ?? 0);
$averageProgress = max(0, min(100, (float) ($overview['average_progress'] ?? 0)));
$pendingReviews = (int) ($overview['pending_reviews'] ?? 0);
$pendingQuestions = (int) ($overview['pending_questions'] ?? 0);
$questionsAvailable = !empty($overview['questions_available']);
$approvalRate = max(0, min(100, (float) ($overview['exam_approval_rate'] ?? 0)));
?>

<section class="dashboard-preview-shell">
    <div class="dashboard-preview-content space-y-6">
        <div class="flex flex-col gap-5 border-b border-slate-700/40 pb-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-cyan-300">Portal do professor</p>
                <h2 class="dashboard-preview-title mt-3 text-3xl font-semibold sm:text-4xl">Olá, Prof. <?= e($firstName); ?></h2>
                <p class="dashboard-preview-subtitle mt-2 max-w-2xl text-sm leading-6">
                    Visão pedagógica da sua unidade com cursos, progresso, avaliações e próximos compromissos EAD.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="<?= route('courses'); ?>" class="dashboard-preview-btn-link rounded-xl px-4 py-2.5 text-sm font-semibold transition">
                    Abrir Cursos EAD
                </a>
                <a href="<?= route('courses/calendar'); ?>" class="dashboard-preview-btn-link rounded-xl px-4 py-2.5 text-sm font-semibold transition">
                    Ver agenda
                </a>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <a href="<?= route('courses'); ?>" class="dashboard-preview-kpi group p-5">
                <div class="flex items-start justify-between gap-3">
                    <p class="dashboard-preview-kpi-title text-xs font-semibold uppercase tracking-[0.16em]">Cursos publicados</p>
                    <span class="rounded-full border border-cyan-400/20 bg-cyan-400/10 px-2 py-1 text-[10px] font-semibold text-cyan-200">EAD</span>
                </div>
                <p class="dashboard-preview-kpi-value mt-4 text-4xl font-bold"><?= $publishedCourses; ?></p>
                <p class="dashboard-preview-subtitle mt-2 text-xs">Conteúdos disponíveis no portal</p>
            </a>

            <a href="<?= route('courses/enrollments'); ?>" class="dashboard-preview-kpi group p-5">
                <div class="flex items-start justify-between gap-3">
                    <p class="dashboard-preview-kpi-title text-xs font-semibold uppercase tracking-[0.16em]">Matrículas ativas</p>
                    <span class="rounded-full border border-sky-400/20 bg-sky-400/10 px-2 py-1 text-[10px] font-semibold text-sky-200">Cursos</span>
                </div>
                <p class="dashboard-preview-kpi-value mt-4 text-4xl font-bold"><?= $activeEnrollments; ?></p>
                <p class="dashboard-preview-subtitle mt-2 text-xs">Matrículas ativas ou concluídas</p>
            </a>

            <div class="dashboard-preview-kpi p-5">
                <div class="flex items-start justify-between gap-3">
                    <p class="dashboard-preview-kpi-title text-xs font-semibold uppercase tracking-[0.16em]">Progresso médio</p>
                    <span class="rounded-full border border-cyan-400/20 bg-cyan-400/10 px-2 py-1 text-[10px] font-semibold text-cyan-200">Real</span>
                </div>
                <p class="dashboard-preview-kpi-value mt-4 text-4xl font-bold"><?= e(number_format($averageProgress, 1, ',', '.')); ?>%</p>
                <div class="dashboard-preview-track mt-3 h-1.5 overflow-hidden rounded-full">
                    <div class="h-full rounded-full bg-gradient-to-r from-blue-500 to-cyan-300" style="width: <?= e((string) $averageProgress); ?>%"></div>
                </div>
            </div>

            <a href="<?= route('courses/exams/submissions&status=pending_review'); ?>" class="dashboard-preview-kpi group p-5">
                <div class="flex items-start justify-between gap-3">
                    <p class="dashboard-preview-kpi-title text-xs font-semibold uppercase tracking-[0.16em]">Correções pendentes</p>
                    <span class="rounded-full border border-amber-400/20 bg-amber-400/10 px-2 py-1 text-[10px] font-semibold text-amber-200">Exames</span>
                </div>
                <p class="dashboard-preview-kpi-value mt-4 text-4xl font-bold"><?= $pendingReviews; ?></p>
                <p class="dashboard-preview-subtitle mt-2 text-xs">Respostas aguardando revisão</p>
            </a>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.45fr_0.9fr]">
            <article class="dashboard-preview-section p-5 sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Acompanhamento</p>
                        <h3 class="mt-1 text-lg font-semibold text-white">Progresso por curso</h3>
                    </div>
                    <a href="<?= route('courses'); ?>" class="text-xs font-semibold text-cyan-300 hover:text-cyan-200">Ver todos</a>
                </div>

                <div class="mt-5 space-y-5">
                    <?php foreach ($coursePerformance as $course): ?>
                        <?php
                        $progress = max(0, min(100, (float) ($course['average_progress'] ?? 0)));
                        $risk = (int) ($course['students_at_risk'] ?? 0);
                        ?>
                        <div>
                            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <p class="text-sm font-semibold text-slate-100"><?= e((string) ($course['name'] ?? 'Curso')); ?></p>
                                    <p class="mt-0.5 text-xs text-slate-500">
                                        <?= (int) ($course['enrollments_total'] ?? 0); ?> matrícula(s)
                                        <?php if ($risk > 0): ?>
                                            <span class="text-rose-300"> | <?= $risk; ?> abaixo de 40%</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <span class="text-sm font-bold text-cyan-200"><?= e(number_format($progress, 1, ',', '.')); ?>%</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-800">
                                <div class="h-full rounded-full bg-gradient-to-r from-blue-600 via-sky-400 to-cyan-300" style="width: <?= e((string) $progress); ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($coursePerformance === []): ?>
                        <div class="rounded-xl border border-dashed border-slate-700 px-4 py-8 text-center text-sm text-slate-500">
                            Nenhum curso encontrado para esta unidade.
                        </div>
                    <?php endif; ?>
                </div>
            </article>

            <article class="dashboard-preview-section p-5 sm:p-6">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Atenção pedagógica</p>
                        <h3 class="mt-1 text-lg font-semibold text-white">Baixo progresso</h3>
                    </div>
                    <span class="rounded-full bg-rose-500/10 px-2.5 py-1 text-xs font-semibold text-rose-300">
                        <?= (int) ($overview['students_at_risk'] ?? 0); ?>
                    </span>
                </div>

                <div class="mt-4 divide-y divide-slate-800">
                    <?php foreach ($studentsAtRisk as $student): ?>
                        <div class="flex items-center gap-3 py-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-800 text-sm font-bold text-cyan-200">
                                <?= e(mb_strtoupper(mb_substr((string) ($student['full_name'] ?? 'A'), 0, 1))); ?>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-slate-100"><?= e((string) ($student['full_name'] ?? 'Aluno')); ?></p>
                                <p class="truncate text-xs text-slate-500"><?= e((string) ($student['course_name'] ?? 'Curso')); ?></p>
                            </div>
                            <span class="text-sm font-bold text-rose-300"><?= (int) ($student['progress_percent'] ?? 0); ?>%</span>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($studentsAtRisk === []): ?>
                        <div class="py-8 text-center text-sm text-slate-500">Nenhuma matrícula abaixo de 40%.</div>
                    <?php endif; ?>
                </div>
            </article>
        </div>

        <div class="grid gap-6 lg:grid-cols-2 xl:grid-cols-[1fr_1fr_0.8fr]">
            <article class="dashboard-preview-section p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Próximos 45 dias</p>
                        <h3 class="mt-1 text-lg font-semibold text-white">Agenda EAD</h3>
                    </div>
                    <a href="<?= route('courses/calendar'); ?>" class="text-xs font-semibold text-cyan-300 hover:text-cyan-200">Agenda completa</a>
                </div>

                <div class="mt-4 space-y-3">
                    <?php foreach ($upcomingEvents as $event): ?>
                        <?php $isLive = (string) ($event['type'] ?? '') === 'live'; ?>
                        <div class="dashboard-preview-bi-card flex gap-3 p-3">
                            <div class="min-w-[3.25rem] rounded-lg <?= $isLive ? 'bg-cyan-500/10 text-cyan-200' : 'bg-amber-500/10 text-amber-200'; ?> px-2 py-2 text-center">
                                <p class="text-lg font-bold leading-none"><?= e(date('d', strtotime((string) $event['event_at']))); ?></p>
                                <p class="mt-1 text-[10px] font-semibold uppercase"><?= e(date('m', strtotime((string) $event['event_at']))); ?></p>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-100"><?= e((string) ($event['title'] ?? 'Evento')); ?></p>
                                <p class="mt-1 truncate text-xs text-slate-500"><?= e((string) ($event['course_name'] ?? 'Curso')); ?></p>
                                <p class="mt-1 text-xs <?= $isLive ? 'text-cyan-300' : 'text-amber-300'; ?>">
                                    <?= $isLive ? 'Aula Zoom' : 'Exame'; ?> | <?= e(date('d/m/Y H:i', strtotime((string) $event['event_at']))); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($upcomingEvents === []): ?>
                        <div class="rounded-xl border border-dashed border-slate-700 px-4 py-7 text-center text-sm text-slate-500">
                            Nenhuma prova ou aula Zoom agendada.
                        </div>
                    <?php endif; ?>
                </div>
            </article>

            <article class="dashboard-preview-section p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Comunicação atual</p>
                        <h3 class="mt-1 text-lg font-semibold text-white">Comentários recentes</h3>
                    </div>
                    <a href="<?= route('courses/comments'); ?>" class="text-xs font-semibold text-cyan-300 hover:text-cyan-200">Abrir comentários</a>
                </div>

                <div class="mt-4 divide-y divide-slate-800">
                    <?php foreach ($recentComments as $comment): ?>
                        <div class="py-3">
                            <div class="flex items-center justify-between gap-3">
                                <p class="truncate text-xs font-semibold text-cyan-200"><?= e((string) ($comment['course_name'] ?? 'Curso')); ?></p>
                                <span class="shrink-0 text-[11px] text-slate-600"><?= e(date('d/m H:i', strtotime((string) $comment['created_at']))); ?></span>
                            </div>
                            <p class="mt-2 text-sm leading-5 text-slate-300"><?= e(mb_strimwidth((string) ($comment['comment'] ?? ''), 0, 150, '...')); ?></p>
                            <p class="mt-1 text-[11px] text-slate-600"><?= e((string) (($comment['author_name'] ?? '') ?: 'Equipe ANEO')); ?></p>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($recentComments === []): ?>
                        <div class="py-8 text-center text-sm text-slate-500">Nenhum comentário registrado.</div>
                    <?php endif; ?>
                </div>
            </article>

            <article class="dashboard-preview-section p-5">
                <div>
                    <span class="inline-flex rounded-full border border-cyan-400/20 bg-cyan-400/10 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-cyan-200">
                        Comunicação direta
                    </span>
                    <h3 class="mt-5 text-xl font-semibold text-white">Dúvidas dos alunos</h3>
                    <p class="mt-3 text-sm leading-6 text-slate-400">
                        Perguntas enviadas pelo portal com histórico por curso e aula. Ao responder, o aluno recebe um alerta no portal.
                    </p>
                    <div class="dashboard-preview-bi-card mt-5 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Aguardando resposta</p>
                        <p class="mt-2 text-3xl font-bold text-cyan-200"><?= $questionsAvailable ? $pendingQuestions : 0; ?></p>
                        <p class="mt-1 text-xs leading-5 text-slate-500">
                            <?= $questionsAvailable ? 'Dúvida(s) aberta(s) nesta unidade.' : 'Módulo aguardando ativação no ambiente.'; ?>
                        </p>
                    </div>
                    <?php if ($questionsAvailable): ?>
                        <a href="<?= route('courses/questions'); ?>" class="dashboard-preview-btn-link mt-4 inline-flex rounded-xl px-4 py-2.5 text-sm font-semibold transition">
                            Abrir dúvidas
                        </a>
                    <?php endif; ?>
                </div>
            </article>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <a href="<?= route('courses/exams'); ?>" class="dashboard-preview-btn-link rounded-xl px-4 py-3 text-sm font-semibold transition">
                Exames e notas
            </a>
            <a href="<?= route('courses/live-sessions'); ?>" class="dashboard-preview-btn-link rounded-xl px-4 py-3 text-sm font-semibold transition">
                Aulas Zoom
            </a>
            <a href="<?= route('courses/enrollments'); ?>" class="dashboard-preview-btn-link rounded-xl px-4 py-3 text-sm font-semibold transition">
                Matrículas
            </a>
            <a href="<?= route('escala-aluno'); ?>" class="dashboard-preview-btn-link rounded-xl px-4 py-3 text-sm font-semibold transition">
                Escala aluno
            </a>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-800 pt-5 text-xs text-slate-500">
            <span>Taxa de aprovação registrada: <?= e(number_format($approvalRate, 1, ',', '.')); ?>%</span>
            <span><?= (int) ($overview['exam_results'] ?? 0); ?> resultado(s) de exame | <?= (int) ($overview['recent_comments'] ?? 0); ?> comentário(s) nos últimos 7 dias</span>
        </div>
    </div>
</section>
