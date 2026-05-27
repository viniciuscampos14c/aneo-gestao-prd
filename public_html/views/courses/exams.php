<?php
$currentCourseFilter = (int) (($filters['course_id'] ?? 0));
$scheduleEnabled = $examScheduleEnabled ?? false;
$externalFeatureAvailable = $externalExamFeatureAvailable ?? false;
$internalAudienceFeatureAvailable = $internalExamAudienceFeatureAvailable ?? false;
$externalLinksRows = $externalLinks ?? [];
$recentResults = $recentExamResults ?? [];
$isAdminResultEditor = is_admin();

$approvedResults = 0;
$failedResults = 0;
$resultsByExam = [];
foreach ($recentResults as $result) {
    if ((string) ($result['status'] ?? '') === 'approved') {
        $approvedResults++;
    } else {
        $failedResults++;
    }

    $examId = (int) ($result['exam_id'] ?? 0);
    if (!isset($resultsByExam[$examId])) {
        $resultsByExam[$examId] = [
            'exam_title' => (string) ($result['exam_title'] ?? ''),
            'course_name' => (string) ($result['course_name'] ?? ''),
            'total' => 0,
            'approved' => 0,
            'failed' => 0,
            'last_submitted_at' => (string) ($result['submitted_at'] ?? ''),
        ];
    }

    $resultsByExam[$examId]['total']++;
    if ((string) ($result['status'] ?? '') === 'approved') {
        $resultsByExam[$examId]['approved']++;
    } else {
        $resultsByExam[$examId]['failed']++;
    }

    $submittedAt = trim((string) ($result['submitted_at'] ?? ''));
    if ($submittedAt !== '' && $submittedAt > (string) $resultsByExam[$examId]['last_submitted_at']) {
        $resultsByExam[$examId]['last_submitted_at'] = $submittedAt;
    }
}

$latestResultByExam = [];
foreach ($recentResults as $result) {
    $examId = (int) ($result['exam_id'] ?? 0);
    if ($examId <= 0 || isset($latestResultByExam[$examId])) {
        continue;
    }

    $latestResultByExam[$examId] = [
        'student_name' => (string) ($result['student_name'] ?? ''),
        'score' => (float) ($result['score'] ?? 0),
        'passing_score' => (float) ($result['passing_score'] ?? 0),
        'status' => (string) ($result['status'] ?? ''),
        'submitted_at' => (string) ($result['submitted_at'] ?? ''),
    ];
}

usort($recentResults, static function (array $a, array $b): int {
    return strcmp((string) ($b['submitted_at'] ?? ''), (string) ($a['submitted_at'] ?? ''));
});

$resultsByExamRows = array_values($resultsByExam);
usort($resultsByExamRows, static function (array $a, array $b): int {
    return strcmp((string) ($b['last_submitted_at'] ?? ''), (string) ($a['last_submitted_at'] ?? ''));
});
?>
<section class="courses-exams-shell space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold">Exames / Avaliacoes</h2>
            <p class="text-sm text-slate-500">Crie provas internas e externas, publique links e acompanhe notas dos alunos em um unico lugar.</p>
        </div>
        <a href="<?= route('courses'); ?>" class="rounded-lg border border-sky-700 bg-sky-950/60 px-3 py-2 text-sm font-semibold text-sky-100 hover:bg-sky-900">Voltar</a>
    </div>

    <?php if (!$scheduleEnabled): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Calendario de provas desativado nesta base. Execute a migracao
            <code>migrations/20260305_exam_schedule_calendar.sql</code> para habilitar o campo de data.
        </div>
    <?php endif; ?>

    <?php if (!$externalFeatureAvailable): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Vinculo de prova externa por aluno indisponivel nesta base. Execute a migracao
            <code>migrations/20260317_professor_external_exam_links.sql</code>.
        </div>
    <?php endif; ?>

    <?php if (!$internalAudienceFeatureAvailable): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Direcionamento de prova interna (aluno especifico/turma) indisponivel nesta base. Execute a migracao
            <code>migrations/20260427_exam_internal_audience.sql</code>.
        </div>
    <?php endif; ?>

    <?php if ($currentCourseFilter > 0): ?>
        <div class="courses-exams-filter-alert rounded-xl border border-cyan-400/30 bg-cyan-400/10 px-4 py-3 text-sm text-cyan-100">
            Painel filtrado pelo curso selecionado. <a href="<?= route('courses/exams'); ?>" class="font-semibold underline">Limpar filtro</a>
        </div>
    <?php endif; ?>

    <section class="courses-exams-kpis grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-xl border border-sky-800 bg-slate-950/50 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-sky-300">Exames cadastrados</p>
            <p class="mt-2 text-3xl font-semibold text-white"><?= (int) ($meta['total'] ?? 0); ?></p>
            <p class="mt-1 text-xs text-slate-300">Internos e externos publicados nesta empresa.</p>
        </article>
        <article class="rounded-xl border border-emerald-700/70 bg-emerald-950/35 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-300">Resultados aprovados</p>
            <p class="mt-2 text-3xl font-semibold text-emerald-100"><?= $approvedResults; ?></p>
            <p class="mt-1 text-xs text-emerald-200/90">Notas que atingiram o criterio minimo.</p>
        </article>
        <article class="rounded-xl border border-rose-700/70 bg-rose-950/30 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-rose-300">Resultados reprovados</p>
            <p class="mt-2 text-3xl font-semibold text-rose-100"><?= $failedResults; ?></p>
            <p class="mt-1 text-xs text-rose-200/90">Notas que ainda precisam de reforco/reteste.</p>
        </article>
        <article class="rounded-xl border border-cyan-700/70 bg-cyan-950/30 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-cyan-300">Links externos ativos</p>
            <p class="mt-2 text-3xl font-semibold text-cyan-100"><?= count(array_filter($externalLinksRows, static fn (array $row): bool => !empty($row['is_active']))); ?></p>
            <p class="mt-1 text-xs text-cyan-200/90">Convites atualmente liberados para provas externas.</p>
        </article>
    </section>

    <section class="courses-exams-calendar rounded-xl border border-sky-800 bg-slate-900/85 p-4 shadow-sm">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-sky-100">Calendario de Provas (proximos 90 dias)</h3>
            <span class="rounded-full border border-sky-700 bg-sky-950/80 px-3 py-1 text-xs font-semibold text-sky-200"><?= count($upcomingExams); ?> agendada(s)</span>
        </div>
        <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($upcomingExams as $exam): ?>
                <article class="rounded-lg border border-sky-800 bg-slate-950/45 px-3 py-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-sky-300"><?= e(date('d/m/Y H:i', strtotime((string) $exam['scheduled_at']))); ?></p>
                    <p class="mt-1 text-sm font-semibold text-white"><?= e($exam['title']); ?></p>
                    <p class="text-xs text-slate-300"><?= e($exam['course_name']); ?></p>
                </article>
            <?php endforeach; ?>
            <?php if ($upcomingExams === []): ?>
                <p class="md:col-span-2 xl:col-span-3 text-sm text-slate-300">Nenhuma prova agendada para os proximos dias.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-4">
        <div class="mb-4">
            <h3 class="text-lg font-semibold text-slate-900">Criar prova interna</h3>
            <p class="text-sm text-slate-500">Fluxo para montar a avaliacao no proprio sistema e liberar para o curso ou para um aluno especifico.</p>
        </div>

        <form method="post" action="<?= route('courses/exams/store'); ?>" class="space-y-4" id="internal-exam-form">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
            <input type="hidden" name="exam_kind" value="internal">

            <div class="grid gap-3 lg:grid-cols-6">
                <div class="lg:col-span-2">
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Curso *</label>
                    <select name="course_id" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option value="">Selecione o curso...</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= (int) $course['id']; ?>"><?= e($course['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="lg:col-span-2">
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Titulo da prova *</label>
                    <input type="text" name="title" required placeholder="Ex.: Avaliacao Modulo 1" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Nota minima *</label>
                    <input type="text" name="passing_score" value="7,0" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Data/Hora</label>
                    <input type="datetime-local" name="scheduled_at" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" <?= $scheduleEnabled ? '' : 'disabled'; ?>>
                </div>

                <div class="lg:col-span-3">
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Descricao</label>
                    <input type="text" name="description" placeholder="Contexto da avaliacao (opcional)" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </div>

                <div class="lg:col-span-3">
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Publico da prova *</label>
                    <div class="grid gap-2 md:grid-cols-2">
                        <select name="delivery_scope_internal" id="internal-delivery-scope" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <option value="course">Todos os alunos matriculados no curso</option>
                            <option value="student">Apenas um aluno especifico</option>
                        </select>
                        <div id="internal-target-student-list" class="max-h-52 overflow-y-auto rounded-lg border border-slate-200 bg-white p-2">
                            <div class="mb-2 text-xs font-medium text-slate-500">Selecione um ou mais alunos</div>
                            <div class="space-y-2">
                                <?php foreach ($students as $student): ?>
                                    <label class="flex items-center gap-3 rounded-lg border border-slate-100 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                        <input type="checkbox" name="target_student_ids[]" value="<?= (int) $student['id']; ?>" class="internal-target-student-checkbox h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-400">
                                        <span><?= e($student['full_name']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <p id="internal-audience-hint" class="mt-1 text-xs text-slate-500">A prova sera liberada para todos os alunos ativos/concluidos matriculados no curso.</p>
                </div>
            </div>

            <section class="rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h4 class="text-sm font-semibold text-slate-900">Questoes da prova *</h4>
                        <p class="text-xs text-slate-500">Adicione perguntas objetivas ou dissertativas.</p>
                    </div>
                    <button type="button" id="add-exam-question" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">+ Adicionar pergunta</button>
                </div>

                <div id="exam-questions-wrap" class="space-y-3"></div>
            </section>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-slate-500">Dica: questoes objetivas exigem pelo menos 2 opcoes e resposta correta.</p>
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Criar prova interna</button>
            </div>
        </form>
    </section>

    <?php if ($externalFeatureAvailable): ?>
        <section class="courses-exams-external-panel rounded-xl border border-slate-700 bg-slate-900/90 p-4 shadow-sm ring-1 ring-slate-800/80">
            <div class="mb-4">
                <div class="mb-3 inline-flex rounded-full border border-cyan-400/35 bg-cyan-400/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-cyan-200">
                    Fluxo externo
                </div>
                <h3 class="text-lg font-semibold text-white">Criar prova externa</h3>
                <p class="text-sm text-slate-300">Cadastre a prova normalmente, informe o link do Forms/Quiz e envie para o curso inteiro ou para um aluno especifico.</p>
            </div>

            <form method="post" action="<?= route('courses/exams/store'); ?>" class="space-y-4 rounded-2xl border border-slate-700/90 bg-slate-950/45 p-4 shadow-inner shadow-slate-950/30">
                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                <input type="hidden" name="exam_kind" value="external">

                <div class="grid gap-3 border-l-4 border-cyan-400/65 pl-4 lg:grid-cols-6">
                    <div class="lg:col-span-2">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-cyan-100/85">Curso *</label>
                        <select name="course_id" required class="w-full rounded-lg border border-slate-600 bg-slate-950/80 px-3 py-2 text-sm text-white focus:border-cyan-300 focus:outline-none focus:ring-2 focus:ring-cyan-400/20">
                            <option value="">Selecione o curso...</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= (int) $course['id']; ?>"><?= e($course['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="lg:col-span-2">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-cyan-100/85">Titulo da prova *</label>
                        <input type="text" name="title" required placeholder="Ex.: Prova Externa Modulo 2" class="w-full rounded-lg border border-slate-600 bg-slate-950/80 px-3 py-2 text-sm text-white placeholder:text-slate-500 focus:border-cyan-300 focus:outline-none focus:ring-2 focus:ring-cyan-400/20">
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-cyan-100/85">Nota minima *</label>
                        <input type="text" name="passing_score" value="7,0" class="w-full rounded-lg border border-slate-600 bg-slate-950/80 px-3 py-2 text-sm text-white focus:border-cyan-300 focus:outline-none focus:ring-2 focus:ring-cyan-400/20">
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-cyan-100/85">Data/Hora</label>
                        <input type="datetime-local" name="scheduled_at" class="w-full rounded-lg border border-slate-600 bg-slate-950/80 px-3 py-2 text-sm text-white focus:border-cyan-300 focus:outline-none focus:ring-2 focus:ring-cyan-400/20" <?= $scheduleEnabled ? '' : 'disabled'; ?>>
                    </div>

                    <div class="lg:col-span-3">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-cyan-100/85">Descricao</label>
                        <input type="text" name="description" placeholder="Resumo da prova externa (opcional)" class="w-full rounded-lg border border-slate-600 bg-slate-950/80 px-3 py-2 text-sm text-white placeholder:text-slate-500 focus:border-cyan-300 focus:outline-none focus:ring-2 focus:ring-cyan-400/20">
                    </div>

                    <div class="lg:col-span-3">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-cyan-100/85">URL externa *</label>
                        <input type="url" name="external_url" required placeholder="https://forms..." class="w-full rounded-lg border border-cyan-400/45 bg-slate-950/85 px-3 py-2 text-sm text-white placeholder:text-slate-500 focus:border-cyan-300 focus:outline-none focus:ring-2 focus:ring-cyan-400/25">
                    </div>

                    <div class="lg:col-span-2">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-cyan-100/85">Publico da prova *</label>
                        <select name="delivery_scope_external" id="external-delivery-scope" class="w-full rounded-lg border border-slate-600 bg-slate-950/80 px-3 py-2 text-sm text-white focus:border-cyan-300 focus:outline-none focus:ring-2 focus:ring-cyan-400/20">
                            <option value="course">Todos os alunos matriculados no curso</option>
                            <option value="student">Apenas um aluno especifico</option>
                        </select>
                    </div>

                    <div class="lg:col-span-2">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-cyan-100/85">Aluno especifico</label>
                        <div id="external-target-student-list" class="max-h-52 overflow-y-auto rounded-lg border border-slate-600 bg-slate-950/80 p-2">
                            <div class="mb-2 text-xs font-medium text-slate-400">Selecione um ou mais alunos</div>
                            <div class="space-y-2">
                                <?php foreach ($students as $student): ?>
                                    <label class="flex items-center gap-3 rounded-lg border border-slate-700 bg-slate-950/35 px-3 py-2 text-sm text-slate-100 hover:bg-slate-900/70">
                                        <input type="checkbox" name="target_student_ids_external[]" value="<?= (int) $student['id']; ?>" class="external-target-student-checkbox h-4 w-4 rounded border-slate-500 bg-slate-900 text-cyan-300 focus:ring-cyan-400">
                                        <span><?= e($student['full_name']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-2">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-cyan-100/85">Prazo do link</label>
                        <input type="datetime-local" name="external_due_at" class="w-full rounded-lg border border-slate-600 bg-slate-950/80 px-3 py-2 text-sm text-white focus:border-cyan-300 focus:outline-none focus:ring-2 focus:ring-cyan-400/20">
                    </div>

                    <div class="lg:col-span-6">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-cyan-100/85">Instrucoes para o aluno</label>
                        <textarea name="external_instructions" rows="2" placeholder="Orientacoes para realizacao da prova externa (opcional)" class="w-full rounded-lg border border-slate-600 bg-slate-950/80 px-3 py-2 text-sm text-white placeholder:text-slate-500 focus:border-cyan-300 focus:outline-none focus:ring-2 focus:ring-cyan-400/20"></textarea>
                        <p id="external-audience-hint" class="mt-1 text-xs text-cyan-200/80">A prova externa sera vinculada automaticamente para todos os alunos ativos/concluidos do curso.</p>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3 rounded-xl border border-cyan-400/20 bg-cyan-400/5 px-4 py-3">
                    <p class="text-xs font-medium text-slate-200">Esse fluxo e usado para formularios externos e links de prova fora do sistema.</p>
                    <button class="rounded-lg border border-cyan-300/60 bg-cyan-300 px-4 py-2 text-sm font-semibold text-slate-950 shadow-sm shadow-cyan-950/25 transition hover:bg-cyan-200">Criar prova externa</button>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <section class="rounded-xl border border-slate-200 bg-white p-4">
        <div class="mb-4">
            <h3 class="text-lg font-semibold text-slate-900">Lancamento de notas</h3>
            <p class="text-sm text-slate-500">Use este formulario para registrar ou atualizar a nota final de um aluno em uma avaliacao. Se a nota ja existir, o sistema atualiza o resultado mais recente.</p>
        </div>

        <form method="post" action="<?= route('courses/exams/result'); ?>" class="grid gap-3 lg:grid-cols-6">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
            <input type="hidden" name="result_id" id="exam-result-id" value="">

            <div class="lg:col-span-2">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Avaliacao *</label>
                <select name="exam_id" id="exam-result-exam" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <option value="">Selecione a avaliacao...</option>
                    <?php foreach ($rows as $exam): ?>
                        <?php $dateLabel = !empty($exam['scheduled_at']) ? date('d/m/Y H:i', strtotime((string) $exam['scheduled_at'])) : 'sem data'; ?>
                        <option value="<?= (int) $exam['id']; ?>"><?= e($exam['title']); ?> (<?= e($exam['course_name']); ?> | <?= e($dateLabel); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="lg:col-span-2">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Aluno *</label>
                <select name="student_id" id="exam-result-student" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <option value="">Selecione o aluno...</option>
                    <?php foreach ($students as $student): ?>
                        <option value="<?= (int) $student['id']; ?>"><?= e($student['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Nota do aluno *</label>
                <input type="text" name="score" id="exam-result-score" required placeholder="Ex.: 8,5" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Nota minima</label>
                <input type="text" name="passing_score" id="exam-result-passing-score" value="7,0" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Data da nota</label>
                <input type="datetime-local" name="submitted_at" id="exam-result-submitted-at" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </div>

            <div class="flex items-end gap-2">
                <button id="exam-result-submit" class="w-full rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold hover:bg-slate-50">Salvar nota</button>
                <button type="button" id="exam-result-cancel" class="hidden rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancelar</button>
            </div>
        </form>
    </section>

    <section class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="mb-3">
                <h3 class="text-lg font-semibold text-slate-900">Resultados recentes dos alunos</h3>
                <p class="text-sm text-slate-500">Visao para o professor acompanhar o desempenho mais recente nas provas e atividades corrigidas.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                            <th class="px-3 py-2">Data</th>
                            <th class="px-3 py-2">Aluno</th>
                            <th class="px-3 py-2">Curso</th>
                            <th class="px-3 py-2">Avaliacao</th>
                            <th class="px-3 py-2">Nota</th>
                            <th class="px-3 py-2">Status</th>
                            <?php if ($isAdminResultEditor): ?>
                                <th class="px-3 py-2 text-right">Acao</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentResults as $result): ?>
                            <?php $approved = (string) ($result['status'] ?? '') === 'approved'; ?>
                            <tr class="border-b border-slate-100 hover:bg-slate-50">
                                <td class="px-3 py-2"><?= !empty($result['submitted_at']) ? e(date('d/m/Y H:i', strtotime((string) $result['submitted_at']))) : '-'; ?></td>
                                <td class="px-3 py-2 font-medium"><?= e((string) ($result['student_name'] ?? '-')); ?></td>
                                <td class="px-3 py-2"><?= e((string) ($result['course_name'] ?? '-')); ?></td>
                                <td class="px-3 py-2"><?= e((string) ($result['exam_title'] ?? '-')); ?></td>
                                <td class="px-3 py-2"><?= e(number_format((float) ($result['score'] ?? 0), 2, ',', '.')); ?> / <?= e(number_format((float) ($result['passing_score'] ?? 0), 2, ',', '.')); ?></td>
                                <td class="px-3 py-2">
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold <?= $approved ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'; ?>">
                                        <?= $approved ? 'Aprovado' : 'Reprovado'; ?>
                                    </span>
                                </td>
                                <?php if ($isAdminResultEditor): ?>
                                    <td class="px-3 py-2 text-right">
                                        <button
                                            type="button"
                                            class="exam-result-edit rounded border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                            data-result-id="<?= (int) ($result['id'] ?? 0); ?>"
                                            data-exam-id="<?= (int) ($result['exam_id'] ?? 0); ?>"
                                            data-student-id="<?= (int) ($result['student_id'] ?? 0); ?>"
                                            data-score="<?= e(number_format((float) ($result['score'] ?? 0), 2, '.', '')); ?>"
                                            data-passing-score="<?= e(number_format((float) ($result['passing_score'] ?? 0), 2, '.', '')); ?>"
                                            data-submitted-at="<?= !empty($result['submitted_at']) ? e(date('Y-m-d\TH:i', strtotime((string) $result['submitted_at']))) : ''; ?>"
                                        >
                                            Editar
                                        </button>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($recentResults === []): ?>
                            <tr>
                                <td colspan="<?= $isAdminResultEditor ? '7' : '6'; ?>" class="px-3 py-6 text-center text-slate-500">Nenhum resultado de avaliacao registrado ate o momento.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="mb-3">
                <h3 class="text-lg font-semibold text-slate-900">Resumo por avaliacao</h3>
                <p class="text-sm text-slate-500">Ajuda o professor a enxergar rapidamente quantos alunos foram aprovados ou reprovados em cada prova.</p>
            </div>
            <div class="space-y-3">
                <?php foreach ($resultsByExamRows as $examSummary): ?>
                    <article class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-900"><?= e($examSummary['exam_title']); ?></p>
                                <p class="text-xs text-slate-500"><?= e($examSummary['course_name']); ?></p>
                            </div>
                            <span class="rounded-full bg-white px-2 py-1 text-xs font-semibold text-slate-700"><?= (int) $examSummary['total']; ?> nota(s)</span>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-emerald-800">Aprovados: <strong><?= (int) $examSummary['approved']; ?></strong></div>
                            <div class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-rose-800">Reprovados: <strong><?= (int) $examSummary['failed']; ?></strong></div>
                        </div>
                        <?php if (!empty($examSummary['last_submitted_at'])): ?>
                            <p class="mt-2 text-xs text-slate-500">Ultimo resultado em <?= e(date('d/m/Y H:i', strtotime((string) $examSummary['last_submitted_at']))); ?></p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
                <?php if ($resultsByExamRows === []): ?>
                    <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-sm text-slate-500">
                        Os resultados por avaliacao aparecerao aqui assim que as primeiras notas forem registradas.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php if ($externalFeatureAvailable): ?>
        <section class="rounded-xl border border-slate-200 bg-white p-4">
            <h3 class="mb-3 text-lg font-semibold">Vinculos de Provas Externas</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                            <th class="px-3 py-2">ID</th>
                            <th class="px-3 py-2">Exame</th>
                            <th class="px-3 py-2">Aluno</th>
                            <th class="px-3 py-2">Link externo</th>
                            <th class="px-3 py-2">Prazo</th>
                            <th class="px-3 py-2">Acessos</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Acao</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($externalLinksRows as $link): ?>
                            <tr class="border-b border-slate-100 hover:bg-slate-50">
                                <td class="px-3 py-2"><?= (int) $link['id']; ?></td>
                                <td class="px-3 py-2">
                                    <p class="font-medium"><?= e($link['exam_title']); ?></p>
                                    <p class="text-xs text-slate-500"><?= e($link['course_name']); ?></p>
                                </td>
                                <td class="px-3 py-2"><?= e($link['student_name']); ?></td>
                                <td class="px-3 py-2">
                                    <a href="<?= e($link['external_url']); ?>" target="_blank" rel="noopener noreferrer" class="text-cyan-700 hover:underline">Abrir link</a>
                                    <?php if (!empty($link['instructions'])): ?>
                                        <p class="mt-1 text-xs text-slate-500"><?= e($link['instructions']); ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2">
                                    <?php if (!empty($link['due_at'])): ?>
                                        <?= e(date('d/m/Y H:i', strtotime((string) $link['due_at']))); ?>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-500">Nao definido</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2">
                                    <span class="font-medium"><?= (int) ($link['open_count'] ?? 0); ?></span>
                                    <?php if (!empty($link['last_opened_at'])): ?>
                                        <p class="text-xs text-slate-500">Ultimo: <?= e(date('d/m H:i', strtotime((string) $link['last_opened_at']))); ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2">
                                    <?php if (!empty($link['is_active'])): ?>
                                        <span class="inline-flex rounded-full bg-cyan-100 px-2 py-1 text-xs font-semibold text-cyan-700">Ativo</span>
                                    <?php else: ?>
                                        <span class="inline-flex rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2">
                                    <?php if (!empty($link['is_active'])): ?>
                                        <form method="post" action="<?= route('courses/exams/external-link/deactivate'); ?>" onsubmit="return confirm('Desativar este vinculo externo?');">
                                            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                            <input type="hidden" name="id" value="<?= (int) $link['id']; ?>">
                                            <button class="rounded border border-rose-200 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50">Desativar</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-400">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($externalLinksRows === []): ?>
                            <tr>
                                <td colspan="8" class="px-3 py-5 text-center text-slate-500">Nenhum vinculo de prova externa cadastrado.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <section class="rounded-xl border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-4 py-4">
            <h3 class="text-lg font-semibold text-slate-900">Catalogo de avaliacoes</h3>
            <p class="text-sm text-slate-500">Lista completa das provas cadastradas com seu tipo, publico e quantidade de links externos.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-3 py-3">ID</th>
                        <th class="px-3 py-3">Data da prova</th>
                        <th class="px-3 py-3">Curso</th>
                        <th class="px-3 py-3">Titulo</th>
                        <th class="px-3 py-3">Tipo</th>
                        <th class="px-3 py-3">Descricao</th>
                        <th class="px-3 py-3">Nota minima</th>
                        <th class="px-3 py-3">Publico</th>
                        <th class="px-3 py-3">Links externos</th>
                        <th class="px-3 py-3">Resultados</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $examId = (int) ($row['id'] ?? 0);
                        $questionsTotal = (int) ($row['questions_total'] ?? 0);
                        $externalLinksTotal = (int) ($row['external_links_total'] ?? 0);
                        $internalLinksTotal = (int) ($row['internal_links_total'] ?? 0);
                        $isExternalExam = $questionsTotal <= 0 && $externalLinksTotal > 0;
                        $examResultSummary = $resultsByExam[$examId] ?? null;
                        $latestExamResult = $latestResultByExam[$examId] ?? null;
                        ?>
                        <tr class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="px-3 py-3"><?= (int) $row['id']; ?></td>
                            <td class="px-3 py-3">
                                <?php if (!empty($row['scheduled_at'])): ?>
                                    <span class="inline-flex rounded-full bg-sky-100 px-2 py-1 text-xs font-semibold text-sky-700"><?= e(date('d/m/Y H:i', strtotime((string) $row['scheduled_at']))); ?></span>
                                <?php else: ?>
                                    <span class="text-xs text-slate-500">Nao definida</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-3"><?= e($row['course_name']); ?></td>
                            <td class="px-3 py-3 font-medium"><?= e($row['title']); ?></td>
                            <td class="px-3 py-3">
                                <?php if ($isExternalExam): ?>
                                    <span class="inline-flex rounded-full bg-cyan-100 px-2 py-1 text-xs font-semibold text-cyan-700">Externa</span>
                                <?php else: ?>
                                    <span class="inline-flex rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">Interna</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-3"><?= e($row['description']); ?></td>
                            <td class="px-3 py-3"><?= e(number_format((float) $row['passing_score'], 2, ',', '.')); ?></td>
                            <td class="px-3 py-3">
                                <?php if ($internalLinksTotal > 0): ?>
                                    <span class="inline-flex rounded-full bg-indigo-100 px-2 py-1 text-xs font-semibold text-indigo-700">Direcionada: <?= $internalLinksTotal; ?> aluno(s)</span>
                                <?php else: ?>
                                    <span class="inline-flex rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700">Todos do curso</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-3">
                                <span class="inline-flex rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700"><?= $externalLinksTotal; ?></span>
                            </td>
                            <td class="px-3 py-3">
                                <?php if ($examResultSummary !== null): ?>
                                    <div class="space-y-1">
                                        <p class="text-xs font-semibold text-slate-800">
                                            <?= (int) ($examResultSummary['total'] ?? 0); ?> nota(s) registrada(s)
                                        </p>
                                        <p class="text-xs text-slate-500">
                                            Aprovadas: <?= (int) ($examResultSummary['approved'] ?? 0); ?> | Reprovadas: <?= (int) ($examResultSummary['failed'] ?? 0); ?>
                                        </p>
                                        <?php if ($latestExamResult !== null): ?>
                                            <?php $latestApproved = (string) ($latestExamResult['status'] ?? '') === 'approved'; ?>
                                            <p class="text-xs <?= $latestApproved ? 'text-emerald-700' : 'text-rose-700'; ?>">
                                                Ultima: <?= e((string) ($latestExamResult['student_name'] ?? 'Aluno')); ?> -
                                                <?= e(number_format((float) ($latestExamResult['score'] ?? 0), 2, ',', '.')); ?>
                                                / <?= e(number_format((float) ($latestExamResult['passing_score'] ?? 0), 2, ',', '.')); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs text-slate-500">Sem nota registrada</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="10" class="px-3 py-6 text-center text-slate-500">Nenhum exame cadastrado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
        <p>Total: <?= (int) $meta['total']; ?> registros | Pagina <?= (int) $meta['page']; ?>/<?= (int) $meta['pages']; ?></p>
        <div class="flex gap-2">
            <?php for ($p = 1; $p <= (int) $meta['pages']; $p++): ?>
                <a href="index.php?<?= build_query(['route' => 'courses/exams', 'page' => $p, 'course_id' => $currentCourseFilter]); ?>" class="rounded px-3 py-1 <?= $p === (int) $meta['page'] ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white hover:bg-slate-50'; ?>"><?= $p; ?></a>
            <?php endfor; ?>
        </div>
    </div>

    <script>
        (() => {
            const questionsWrap = document.getElementById('exam-questions-wrap');
            const addQuestionButton = document.getElementById('add-exam-question');
            const audienceScopeField = document.getElementById('internal-delivery-scope');
            const targetStudentField = document.getElementById('internal-target-student-list');
            const targetStudentCheckboxes = Array.from(document.querySelectorAll('.internal-target-student-checkbox'));
            const audienceHintField = document.getElementById('internal-audience-hint');
            const externalScopeField = document.getElementById('external-delivery-scope');
            const externalStudentField = document.getElementById('external-target-student-list');
            const externalStudentCheckboxes = Array.from(document.querySelectorAll('.external-target-student-checkbox'));
            const externalHintField = document.getElementById('external-audience-hint');
            const resultIdField = document.getElementById('exam-result-id');
            const resultExamField = document.getElementById('exam-result-exam');
            const resultStudentField = document.getElementById('exam-result-student');
            const resultScoreField = document.getElementById('exam-result-score');
            const resultPassingScoreField = document.getElementById('exam-result-passing-score');
            const resultSubmittedAtField = document.getElementById('exam-result-submitted-at');
            const resultSubmitButton = document.getElementById('exam-result-submit');
            const resultCancelButton = document.getElementById('exam-result-cancel');
            const resultEditButtons = Array.from(document.querySelectorAll('.exam-result-edit'));

            if (questionsWrap && addQuestionButton && audienceScopeField && targetStudentField) {
                let questionIndex = 0;

                const buildQuestionCard = () => {
                    questionIndex += 1;
                    const card = document.createElement('article');
                    card.className = 'rounded-lg border border-slate-200 bg-white p-3';
                    card.innerHTML = `
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <h5 class="text-sm font-semibold text-slate-800">Pergunta ${questionIndex}</h5>
                            <button type="button" class="internal-question-remove rounded border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50">Remover</button>
                        </div>
                        <div class="grid gap-2 lg:grid-cols-4">
                            <div class="lg:col-span-1">
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Tipo</label>
                                <select name="question_type[]" class="internal-question-type w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    <option value="objective">Objetiva</option>
                                    <option value="essay">Dissertativa</option>
                                </select>
                            </div>
                            <div class="lg:col-span-3">
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Enunciado *</label>
                                <input type="text" name="question_text[]" required placeholder="Digite a pergunta" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            </div>
                            <div class="lg:col-span-2">
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Opcoes (uma por linha)</label>
                                <textarea name="options_text[]" rows="3" placeholder="Opcao A&#10;Opcao B&#10;Opcao C" class="internal-options-field w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></textarea>
                            </div>
                            <div class="lg:col-span-2">
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Resposta correta</label>
                                <input type="text" name="correct_answer[]" placeholder="Texto exato da resposta correta" class="internal-answer-field w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                <p class="mt-1 text-xs text-slate-400">Para dissertativa, a resposta correta pode ficar em branco.</p>
                            </div>
                        </div>
                    `;

                    const typeField = card.querySelector('.internal-question-type');
                    const optionsField = card.querySelector('.internal-options-field');
                    const answerField = card.querySelector('.internal-answer-field');
                    const removeButton = card.querySelector('.internal-question-remove');

                    const syncTypeFields = () => {
                        const isObjective = typeField.value === 'objective';
                        optionsField.disabled = !isObjective;
                        answerField.disabled = !isObjective;

                        if (!isObjective) {
                            optionsField.value = '';
                            answerField.value = '';
                        }
                    };

                    removeButton.addEventListener('click', () => {
                        if (questionsWrap.children.length <= 1) {
                            return;
                        }
                        card.remove();
                    });

                    typeField.addEventListener('change', syncTypeFields);
                    syncTypeFields();
                    return card;
                };

                const syncAudienceScope = () => {
                    const byStudent = audienceScopeField.value === 'student';
                    if (targetStudentField) {
                        targetStudentField.classList.toggle('opacity-50', !byStudent);
                    }
                    targetStudentCheckboxes.forEach((checkbox) => {
                        checkbox.disabled = !byStudent;
                        checkbox.required = false;
                        if (!byStudent) {
                            checkbox.checked = false;
                        }
                    });

                    if (audienceHintField) {
                        audienceHintField.textContent = byStudent
                            ? 'A prova sera enviada somente para os alunos selecionados.'
                            : 'A prova sera liberada para todos os alunos ativos/concluidos matriculados no curso.';
                    }
                };

                addQuestionButton.addEventListener('click', () => {
                    questionsWrap.appendChild(buildQuestionCard());
                });

                audienceScopeField.addEventListener('change', syncAudienceScope);
                targetStudentCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', syncAudienceScope));
                syncAudienceScope();
                questionsWrap.appendChild(buildQuestionCard());
            }

            if (externalScopeField && externalStudentField) {
                const syncExternalScope = () => {
                    const byStudent = externalScopeField.value === 'student';
                    externalStudentField.classList.toggle('opacity-50', !byStudent);
                    externalStudentCheckboxes.forEach((checkbox) => {
                        checkbox.disabled = !byStudent;
                        checkbox.required = false;
                        if (!byStudent) {
                            checkbox.checked = false;
                        }
                    });

                    if (externalHintField) {
                        externalHintField.textContent = byStudent
                            ? 'A prova externa sera vinculada somente para os alunos selecionados.'
                            : 'A prova externa sera vinculada automaticamente para todos os alunos ativos/concluidos do curso.';
                    }
                };

                externalScopeField.addEventListener('change', syncExternalScope);
                externalStudentCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', syncExternalScope));
                syncExternalScope();
            }

            if (resultIdField && resultExamField && resultStudentField && resultScoreField && resultPassingScoreField && resultSubmittedAtField && resultSubmitButton && resultCancelButton) {
                const resetResultForm = () => {
                    resultIdField.value = '';
                    resultExamField.value = '';
                    resultStudentField.value = '';
                    resultScoreField.value = '';
                    resultPassingScoreField.value = '7,0';
                    resultSubmittedAtField.value = '';
                    resultSubmitButton.textContent = 'Salvar nota';
                    resultCancelButton.classList.add('hidden');
                };

                resultEditButtons.forEach((button) => {
                    button.addEventListener('click', () => {
                        resultIdField.value = button.dataset.resultId || '';
                        resultExamField.value = button.dataset.examId || '';
                        resultStudentField.value = button.dataset.studentId || '';
                        resultScoreField.value = button.dataset.score || '';
                        resultPassingScoreField.value = button.dataset.passingScore || '7.0';
                        resultSubmittedAtField.value = button.dataset.submittedAt || '';
                        resultSubmitButton.textContent = 'Atualizar nota';
                        resultCancelButton.classList.remove('hidden');
                        resultScoreField.focus();
                        resultScoreField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    });
                });

                resultCancelButton.addEventListener('click', resetResultForm);
            }
        })();
    </script>
</section>
