<?php
$rows = is_array($rows ?? null) ? $rows : [];
$featureAvailable = !empty($featureAvailable);
$statusLabels = [
    'open' => ['Aguardando professor', 'border-amber-200 bg-amber-50 text-amber-700'],
    'answered' => ['Respondida', 'border-emerald-200 bg-emerald-50 text-emerald-700'],
    'resolved' => ['Resolvida', 'border-slate-200 bg-slate-100 text-slate-600'],
];
?>
<section class="space-y-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold text-slate-900">Minhas Dúvidas</h2>
            <p class="mt-1 text-sm text-slate-500">Acompanhe as perguntas enviadas e as respostas dos professores.</p>
        </div>
        <a href="<?= route('student/courses'); ?>" class="rounded-lg border border-sky-200 bg-white px-4 py-2 text-sm font-semibold text-sky-700 hover:bg-sky-50">
            Abrir meus cursos
        </a>
    </div>

    <?php if (!$featureAvailable): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            O módulo de dúvidas ainda não esta ativo neste ambiente.
        </div>
    <?php endif; ?>

    <div class="space-y-4">
        <?php foreach ($rows as $question): ?>
            <?php
            $status = (string) ($question['status'] ?? 'open');
            [$statusLabel, $statusClass] = $statusLabels[$status] ?? $statusLabels['open'];
            ?>
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-sky-600"><?= e((string) ($question['course_name'] ?? 'Curso')); ?></p>
                        <h3 class="mt-1 text-lg font-semibold text-slate-900"><?= e((string) ($question['subject'] ?? 'Dúvida')); ?></h3>
                        <?php if (!empty($question['lesson_title'])): ?>
                            <p class="mt-1 text-xs text-slate-500">Aula: <?= e((string) $question['lesson_title']); ?></p>
                        <?php endif; ?>
                    </div>
                    <span class="rounded-full border px-3 py-1 text-xs font-semibold <?= $statusClass; ?>"><?= e($statusLabel); ?></span>
                </div>

                <div class="mt-4 space-y-3">
                    <?php foreach (($question['messages'] ?? []) as $message): ?>
                        <?php $fromProfessor = (string) ($message['sender_type'] ?? '') === 'professor'; ?>
                        <div class="rounded-xl border p-3 <?= $fromProfessor ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-slate-50'; ?>">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-xs font-semibold <?= $fromProfessor ? 'text-emerald-700' : 'text-slate-700'; ?>">
                                    <?= $fromProfessor ? e((string) (($message['professor_name'] ?? '') ?: 'Professor')) : 'Você'; ?>
                                </p>
                                <p class="text-xs text-slate-500"><?= e(date('d/m/Y H:i', strtotime((string) ($message['created_at'] ?? 'now')))); ?></p>
                            </div>
                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700"><?= e((string) ($message['message'] ?? '')); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>
        <?php endforeach; ?>

        <?php if ($featureAvailable && $rows === []): ?>
            <div class="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-500">
                Você ainda não enviou dúvidas. Abra um curso e use o formulário abaixo da aula.
            </div>
        <?php endif; ?>
    </div>
</section>
