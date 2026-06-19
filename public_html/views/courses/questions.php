<?php
$rows = is_array($rows ?? null) ? $rows : [];
$status = (string) ($status ?? '');
$featureAvailable = !empty($featureAvailable);
$statusLabels = [
    'open' => ['Aguardando resposta', 'border-amber-200 bg-amber-50 text-amber-700'],
    'answered' => ['Respondida', 'border-emerald-200 bg-emerald-50 text-emerald-700'],
    'resolved' => ['Resolvida', 'border-slate-300 bg-slate-100 text-slate-600'],
];
?>
<section class="space-y-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-600">Portal do professor</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">Dúvidas dos Alunos</h2>
            <p class="mt-1 text-sm text-slate-500">Responda perguntas vinculadas aos cursos e aulas da unidade atual.</p>
        </div>
        <a href="<?= route('dashboard'); ?>" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            Voltar ao inicio
        </a>
    </div>

    <nav class="flex flex-wrap gap-2">
        <?php foreach (['' => 'Todas', 'open' => 'Aguardando', 'answered' => 'Respondidas', 'resolved' => 'Resolvidas'] as $value => $label): ?>
            <a href="<?= route('courses/questions' . ($value !== '' ? '&status=' . $value : '')); ?>" class="rounded-lg px-3 py-2 text-sm font-semibold <?= $status === $value ? 'bg-sky-600 text-white' : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50'; ?>">
                <?= e($label); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if (!$featureAvailable): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            O módulo de dúvidas ainda não esta ativo neste ambiente.
        </div>
    <?php endif; ?>

    <div class="space-y-4">
        <?php foreach ($rows as $question): ?>
            <?php
            $questionStatus = (string) ($question['status'] ?? 'open');
            [$statusLabel, $statusClass] = $statusLabels[$questionStatus] ?? $statusLabels['open'];
            ?>
            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-sky-600"><?= e((string) ($question['course_name'] ?? 'Curso')); ?></p>
                        <h3 class="mt-1 text-lg font-semibold text-slate-900"><?= e((string) ($question['subject'] ?? 'Dúvida')); ?></h3>
                        <p class="mt-1 text-sm text-slate-600">
                            <?= e((string) ($question['student_name'] ?? 'Aluno')); ?>
                            <?php if (!empty($question['lesson_title'])): ?>
                                | Aula: <?= e((string) $question['lesson_title']); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <span class="rounded-full border px-3 py-1 text-xs font-semibold <?= $statusClass; ?>"><?= e($statusLabel); ?></span>
                </div>

                <div class="mt-4 space-y-3">
                    <?php foreach (($question['messages'] ?? []) as $message): ?>
                        <?php $fromProfessor = (string) ($message['sender_type'] ?? '') === 'professor'; ?>
                        <div class="course-question-message rounded-xl border p-3 <?= $fromProfessor ? 'course-question-message-professor' : 'course-question-message-student'; ?>">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="course-question-message-author text-xs font-semibold">
                                    <?= $fromProfessor ? e((string) (($message['professor_name'] ?? '') ?: 'Professor')) : e((string) ($question['student_name'] ?? 'Aluno')); ?>
                                </p>
                                <p class="course-question-message-date text-xs"><?= e(date('d/m/Y H:i', strtotime((string) ($message['created_at'] ?? 'now')))); ?></p>
                            </div>
                            <p class="course-question-message-body mt-2 whitespace-pre-line text-sm leading-6"><?= e((string) ($message['message'] ?? '')); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($questionStatus !== 'resolved'): ?>
                    <form method="post" action="<?= route('courses/questions/reply'); ?>" class="mt-4 grid gap-3">
                        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                        <input type="hidden" name="question_id" value="<?= (int) ($question['id'] ?? 0); ?>">
                        <textarea name="message" rows="3" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100" placeholder="Digite a resposta para o aluno."></textarea>
                        <div>
                            <button type="submit" class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">
                                Responder e notificar aluno
                            </button>
                        </div>
                    </form>
                    <form method="post" action="<?= route('courses/questions/resolve'); ?>" class="mt-2">
                        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                        <input type="hidden" name="question_id" value="<?= (int) ($question['id'] ?? 0); ?>">
                        <button type="submit" class="text-xs font-semibold text-slate-500 hover:text-slate-800">Marcar como resolvida</button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>

        <?php if ($featureAvailable && $rows === []): ?>
            <div class="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-500">
                Nenhuma dúvida encontrada neste filtro.
            </div>
        <?php endif; ?>
    </div>
</section>
