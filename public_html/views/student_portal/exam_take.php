<?php
$questionTypes = array_values(array_unique(array_map(
    static fn ($question): string => (string) ($question['question_type'] ?? ''),
    $questions
)));
$isEssayExam = in_array('essay', $questionTypes, true);
$questionCount = count($questions);
$scheduledAt = trim((string) ($exam['scheduled_at'] ?? ''));
$scheduledLabel = $scheduledAt !== '' ? date('d/m/Y H:i', strtotime($scheduledAt)) : 'Liberada';
?>

<section class="student-exam-take-shell space-y-6">
    <div class="rounded-2xl border border-sky-800 bg-slate-900/90 p-5 shadow-sm shadow-slate-950/40">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-cyan-300">Portal do Aluno</p>
                <h2 class="mt-2 text-2xl font-semibold text-white"><?= e((string) ($exam['title'] ?? 'Responder Prova')); ?></h2>
                <p class="mt-1 text-sm text-slate-300"><?= e((string) ($exam['course_name'] ?? 'Curso')); ?></p>
            </div>
            <a href="<?= route('student/exams'); ?>" class="rounded-xl border border-sky-700 bg-sky-950/60 px-4 py-2 text-sm font-semibold text-sky-100 hover:bg-sky-900">Voltar</a>
        </div>

        <div class="mt-5 grid gap-3 md:grid-cols-3">
            <article class="rounded-xl border border-sky-800 bg-slate-950/55 p-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Questões</p>
                <p class="mt-1 text-2xl font-bold text-white"><?= $questionCount; ?></p>
            </article>
            <article class="rounded-xl border border-sky-800 bg-slate-950/55 p-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Nota mínima</p>
                <p class="mt-1 text-2xl font-bold text-white"><?= e(number_format((float) ($exam['passing_score'] ?? 7), 2, ',', '.')); ?></p>
            </article>
            <article class="rounded-xl border border-sky-800 bg-slate-950/55 p-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Data da prova</p>
                <p class="mt-1 text-base font-bold text-white"><?= e($scheduledLabel); ?></p>
            </article>
        </div>

        <?php if (trim((string) ($exam['description'] ?? '')) !== ''): ?>
            <p class="mt-4 rounded-xl border border-sky-800 bg-slate-950/45 px-4 py-3 text-sm leading-6 text-slate-200"><?= e((string) $exam['description']); ?></p>
        <?php endif; ?>

        <div class="mt-4 rounded-xl border <?= $isEssayExam ? 'border-amber-500/40 bg-amber-500/10 text-amber-100' : 'border-emerald-500/40 bg-emerald-500/10 text-emerald-100'; ?> px-4 py-3 text-sm">
            <?= $isEssayExam
                ? 'Esta avaliação possui resposta dissertativa. Após o envio, ela ficará aguardando correção.'
                : 'As questões objetivas com gabarito serão corrigidas automaticamente ao finalizar.'; ?>
        </div>
    </div>

    <form method="post" action="<?= route('student/exams/submit'); ?>" class="space-y-5" id="student-exam-form">
        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
        <input type="hidden" name="exam_id" value="<?= (int) $exam['id']; ?>">

        <div class="sticky top-3 z-10 rounded-xl border border-sky-800 bg-slate-900/95 p-3 shadow-sm shadow-slate-950/40 backdrop-blur">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm font-semibold text-slate-100">Progresso da prova: <span id="exam-answer-progress">0/<?= $questionCount; ?></span></p>
                <p class="text-xs text-slate-400">Revise suas respostas antes de enviar. Após o envio, a prova não poderá ser alterada.</p>
            </div>
            <div class="mt-2 h-2 rounded-full bg-slate-800">
                <div id="exam-answer-progress-bar" class="h-2 rounded-full bg-cyan-400 transition-all" style="width:0%"></div>
            </div>
        </div>

        <?php foreach ($questions as $index => $question): ?>
            <?php
            $questionId = (int) ($question['id'] ?? 0);
            $questionType = (string) ($question['question_type'] ?? 'objective');
            $options = [];
            $decoded = json_decode((string) ($question['options_json'] ?? ''), true);
            if (is_array($decoded)) {
                $options = array_values(array_filter(array_map('strval', $decoded), fn ($item) => trim($item) !== ''));
            } elseif (!empty($question['options_json'])) {
                $lines = preg_split('/\r\n|\r|\n/', (string) $question['options_json']) ?: [];
                $options = array_values(array_filter(array_map('trim', $lines), fn ($item) => $item !== ''));
            }
            ?>
            <article class="student-exam-question rounded-2xl border border-sky-800 bg-slate-900/90 p-5 shadow-sm shadow-slate-950/30" data-question-id="<?= $questionId; ?>">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-cyan-300">Questão <?= (int) ($index + 1); ?> de <?= $questionCount; ?></p>
                        <h3 class="mt-2 text-base font-semibold leading-7 text-white"><?= e((string) ($question['question_text'] ?? '')); ?></h3>
                    </div>
                    <span class="student-exam-question-status rounded-full bg-slate-800 px-3 py-1 text-xs font-semibold text-slate-300">Pendente</span>
                </div>

                <?php if ($questionType === 'objective' && $options !== []): ?>
                    <div class="mt-4 grid gap-2">
                        <?php foreach ($options as $optionIndex => $option): ?>
                            <label class="student-exam-option flex cursor-pointer items-start gap-3 rounded-xl border border-sky-800 bg-slate-950/40 px-4 py-3 text-sm text-slate-100 hover:border-cyan-400/60 hover:bg-cyan-400/10">
                                <input type="radio" name="answers[<?= $questionId; ?>]" value="<?= e($option); ?>" class="mt-1 h-4 w-4 border-slate-500 bg-slate-900 text-cyan-400 focus:ring-cyan-400">
                                <span>
                                    <strong class="mr-2 text-cyan-200"><?= chr(65 + $optionIndex); ?>.</strong>
                                    <?= e($option); ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($questionType === 'objective'): ?>
                    <input type="text" name="answers[<?= $questionId; ?>]" class="student-exam-answer mt-4 w-full rounded-xl border border-sky-800 bg-slate-950/60 px-4 py-3 text-sm text-white outline-none placeholder:text-slate-500 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/20" placeholder="Digite sua resposta objetiva">
                <?php else: ?>
                    <textarea name="answers[<?= $questionId; ?>]" rows="7" maxlength="4000" class="student-exam-answer mt-4 w-full rounded-xl border border-sky-800 bg-slate-950/60 px-4 py-3 text-sm leading-6 text-white outline-none placeholder:text-slate-500 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/20" placeholder="Digite sua resposta dissertativa com clareza."></textarea>
                    <p class="mt-2 text-xs text-slate-400">Resposta dissertativa: organize seu raciocínio antes de enviar.</p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>

        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-sky-800 bg-slate-900/90 p-4 shadow-sm shadow-slate-950/30">
            <p class="text-sm text-slate-300">Ao enviar, suas respostas serão registradas e encaminhadas para correção quando necessário.</p>
            <button class="rounded-xl bg-cyan-600 px-5 py-3 text-sm font-semibold text-white hover:bg-cyan-500">Enviar prova</button>
        </div>
    </form>
</section>

<script>
(() => {
    const form = document.getElementById('student-exam-form');
    if (!form) { return; }

    const questionCards = Array.from(document.querySelectorAll('.student-exam-question'));
    const progressLabel = document.getElementById('exam-answer-progress');
    const progressBar = document.getElementById('exam-answer-progress-bar');

    const questionAnswered = (card) => {
        const checked = card.querySelector('input[type="radio"]:checked');
        if (checked) { return true; }
        const field = card.querySelector('input[type="text"], textarea');
        return field ? field.value.trim() !== '' : false;
    };

    const refreshProgress = () => {
        let answered = 0;
        questionCards.forEach((card) => {
            const done = questionAnswered(card);
            if (done) { answered += 1; }
            const status = card.querySelector('.student-exam-question-status');
            if (status) {
                status.textContent = done ? 'Respondida' : 'Pendente';
                status.className = done
                    ? 'student-exam-question-status rounded-full border border-emerald-400/50 bg-emerald-400/15 px-3 py-1 text-xs font-semibold text-emerald-100'
                    : 'student-exam-question-status rounded-full bg-slate-800 px-3 py-1 text-xs font-semibold text-slate-300';
            }
        });

        if (progressLabel) {
            progressLabel.textContent = answered + '/' + questionCards.length;
        }
        if (progressBar) {
            const pct = questionCards.length > 0 ? Math.round((answered / questionCards.length) * 100) : 0;
            progressBar.style.width = pct + '%';
        }
    };

    form.addEventListener('change', refreshProgress);
    form.addEventListener('input', refreshProgress);
    form.addEventListener('submit', (event) => {
        const unanswered = questionCards.filter((card) => !questionAnswered(card)).length;
        const message = unanswered > 0
            ? 'Ainda há ' + unanswered + ' questão(ões) sem resposta. Deseja enviar mesmo assim?'
            : 'Confirmar envio da prova?';
        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });

    refreshProgress();
})();
</script>
