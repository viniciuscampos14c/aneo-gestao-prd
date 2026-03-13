<section class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-semibold">Responder Prova</h2>
            <p class="text-sm text-slate-500"><?= e($exam['course_name']); ?> | <?= e($exam['title']); ?></p>
        </div>
        <a href="<?= route('student/exams'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Voltar</a>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">
        <p><strong>Descricao:</strong> <?= e($exam['description'] ?: 'Sem descricao.'); ?></p>
        <p class="mt-1"><strong>Nota minima:</strong> <?= e(number_format((float) $exam['passing_score'], 2, ',', '.')); ?></p>
        <?php if (!empty($exam['scheduled_at'])): ?>
            <p class="mt-1"><strong>Data da prova:</strong> <?= e(date('d/m/Y H:i', strtotime((string) $exam['scheduled_at']))); ?></p>
        <?php endif; ?>
        <p class="mt-1 text-xs text-slate-500">As questoes objetivas com gabarito sao corrigidas automaticamente.</p>
    </div>

    <form method="post" action="<?= route('student/exams/submit'); ?>" class="space-y-4">
        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
        <input type="hidden" name="exam_id" value="<?= (int) $exam['id']; ?>">

        <?php foreach ($questions as $index => $question): ?>
            <?php
            $options = [];
            $decoded = json_decode((string) ($question['options_json'] ?? ''), true);
            if (is_array($decoded)) {
                $options = array_values(array_filter(array_map('strval', $decoded), fn ($item) => trim($item) !== ''));
            } elseif (!empty($question['options_json'])) {
                $lines = preg_split('/\r\n|\r|\n/', (string) $question['options_json']) ?: [];
                $options = array_values(array_filter(array_map('trim', $lines), fn ($item) => $item !== ''));
            }
            ?>
            <article class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-sm font-semibold text-slate-900">Questao <?= (int) ($index + 1); ?> (<?= e($question['question_type']); ?>)</p>
                <p class="mt-1 text-sm text-slate-700"><?= e($question['question_text']); ?></p>

                <?php if ((string) $question['question_type'] === 'objective' && $options !== []): ?>
                    <div class="mt-3 space-y-2 text-sm">
                        <?php foreach ($options as $option): ?>
                            <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 hover:bg-slate-50">
                                <input type="radio" name="answers[<?= (int) $question['id']; ?>]" value="<?= e($option); ?>">
                                <span><?= e($option); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ((string) $question['question_type'] === 'objective'): ?>
                    <input type="text" name="answers[<?= (int) $question['id']; ?>]" class="mt-3 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Digite sua resposta objetiva">
                <?php else: ?>
                    <textarea name="answers[<?= (int) $question['id']; ?>]" rows="4" class="mt-3 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Digite sua resposta dissertativa"></textarea>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>

        <div class="flex justify-end">
            <button class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700">Enviar prova</button>
        </div>
    </form>
</section>
