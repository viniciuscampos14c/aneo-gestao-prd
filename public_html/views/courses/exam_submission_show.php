<?php
$statusLabels = [
    'pending_review' => 'Aguardando correcao',
    'submitted' => 'Corrigida',
    'auto_graded' => 'Corrigida automaticamente',
];
$status = (string) ($submission['status'] ?? '');
$isPending = $status === 'pending_review';
$scoreValue = $submission['result_score'] !== null
    ? number_format((float) $submission['result_score'], 2, ',', '.')
    : ($submission['score'] !== null ? number_format((float) $submission['score'], 2, ',', '.') : '');
$submittedAtValue = !empty($submission['result_submitted_at'])
    ? date('Y-m-d\TH:i', strtotime((string) $submission['result_submitted_at']))
    : date('Y-m-d\TH:i');
?>
<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Correção de prova</p>
            <h2 class="text-2xl font-semibold text-white"><?= e((string) ($submission['exam_title'] ?? 'Prova')); ?></h2>
            <p class="text-sm text-slate-300"><?= e((string) ($submission['course_name'] ?? '-')); ?> | enviada em <?= !empty($submission['submitted_at']) ? e(date('d/m/Y H:i', strtotime((string) $submission['submitted_at']))) : '-'; ?></p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= route('courses/exams/submissions'); ?>" class="rounded-lg border border-sky-700 bg-sky-950/60 px-3 py-2 text-sm font-semibold text-sky-100 hover:bg-sky-900">Voltar para correções</a>
            <a href="<?= route('courses/exams'); ?>" class="rounded-lg border border-slate-700 px-3 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-900">Exames</a>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-4">
        <article class="rounded-2xl border border-sky-800 bg-slate-950/50 p-4 lg:col-span-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Aluno</p>
            <h3 class="mt-1 text-xl font-semibold text-white"><?= e((string) ($submission['student_name'] ?? '-')); ?></h3>
            <p class="text-sm text-slate-400"><?= e((string) ($submission['student_email'] ?? '')); ?></p>
        </article>
        <article class="rounded-2xl border border-sky-800 bg-slate-950/50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Status</p>
            <span class="mt-2 inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $isPending ? 'bg-amber-200 text-amber-950' : 'bg-emerald-200 text-emerald-950'; ?>">
                <?= e($statusLabels[$status] ?? $status); ?>
            </span>
        </article>
        <article class="rounded-2xl border border-sky-800 bg-slate-950/50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Nota publicada</p>
            <p class="mt-1 text-2xl font-semibold text-white"><?= $submission['result_score'] !== null ? e(number_format((float) $submission['result_score'], 2, ',', '.')) : '-'; ?></p>
        </article>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <section class="rounded-2xl border border-sky-800 bg-slate-950/45 p-5 shadow-sm">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-white">Respostas do aluno</h3>
                <p class="text-sm text-slate-400">Leia cada resposta antes de publicar a nota final.</p>
            </div>

            <div class="space-y-4">
                <?php foreach ($answers as $index => $answer): ?>
                    <?php
                    $type = (string) ($answer['question_type'] ?? '');
                    $answerText = trim((string) ($answer['answer_text'] ?? ''));
                    ?>
                    <article class="rounded-2xl border border-sky-900 bg-slate-900/45 p-4">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-cyan-100">Questão <?= (int) ($index + 1); ?></p>
                            <span class="rounded-full border border-slate-600 px-2 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-300">
                                <?= $type === 'essay' ? 'Dissertativa' : 'Objetiva'; ?>
                            </span>
                        </div>
                        <div class="rounded-xl border border-slate-700 bg-slate-950/60 p-3">
                            <p class="text-sm leading-6 text-slate-100"><?= nl2br(e((string) ($answer['question_text'] ?? '-'))); ?></p>
                        </div>
                        <div class="mt-3 rounded-xl border border-cyan-900/70 bg-cyan-950/20 p-3">
                            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-cyan-300">Resposta enviada</p>
                            <p class="whitespace-pre-wrap text-sm leading-6 text-slate-100"><?= $answerText !== '' ? e($answerText) : 'Sem resposta preenchida.'; ?></p>
                        </div>
                        <?php if ($type === 'objective'): ?>
                            <p class="mt-2 text-xs text-slate-400">Gabarito: <?= e((string) ($answer['correct_answer'] ?? '-')); ?></p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>

                <?php if ($answers === []): ?>
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        Nenhuma resposta foi encontrada para esta entrega.
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <aside class="space-y-4">
            <section class="rounded-2xl border border-cyan-700/70 bg-cyan-950/25 p-5">
                <h3 class="text-lg font-semibold text-white"><?= $isPending ? 'Publicar correção' : 'Atualizar nota' ?></h3>
                <p class="mt-1 text-sm text-cyan-100/80">Ao salvar, o resultado aparece no histórico acadêmico do aluno e a entrega sai da fila de pendências.</p>

                <form method="post" action="<?= route('courses/exams/submission/grade'); ?>" class="mt-4 space-y-3">
                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                    <input type="hidden" name="submission_id" value="<?= (int) ($submission['id'] ?? 0); ?>">

                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-cyan-100/85">Nota do aluno *</label>
                        <input type="text" name="score" required value="<?= e($scoreValue); ?>" placeholder="Ex.: 8,5" class="w-full rounded-lg border border-slate-600 bg-slate-950/80 px-3 py-2 text-sm text-white focus:border-cyan-300 focus:outline-none">
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-cyan-100/85">Nota minima</label>
                        <input type="text" name="passing_score" value="<?= e(number_format((float) ($submission['passing_score'] ?? 7), 2, ',', '.')); ?>" class="w-full rounded-lg border border-slate-600 bg-slate-950/80 px-3 py-2 text-sm text-white focus:border-cyan-300 focus:outline-none">
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-cyan-100/85">Data da publicação</label>
                        <input type="datetime-local" name="submitted_at" value="<?= e($submittedAtValue); ?>" class="w-full rounded-lg border border-slate-600 bg-slate-950/80 px-3 py-2 text-sm text-white focus:border-cyan-300 focus:outline-none">
                    </div>

                    <button class="w-full rounded-lg bg-cyan-400 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-300">
                        <?= $isPending ? 'Publicar nota' : 'Atualizar nota'; ?>
                    </button>
                </form>
            </section>

            <section class="rounded-2xl border border-sky-800 bg-slate-950/50 p-5 text-sm text-slate-300">
                <h3 class="font-semibold text-white">Resumo técnico</h3>
                <p class="mt-2">ID da entrega: #<?= (int) ($submission['id'] ?? 0); ?></p>
                <p>Exame: #<?= (int) ($submission['exam_id'] ?? 0); ?></p>
                <p>Aluno: #<?= (int) ($submission['student_id'] ?? 0); ?></p>
                <?php if (!empty($submission['result_submitted_at'])): ?>
                    <p>Última publicação: <?= e(date('d/m/Y H:i', strtotime((string) $submission['result_submitted_at']))); ?></p>
                <?php endif; ?>
            </section>
        </aside>
    </div>
</section>
