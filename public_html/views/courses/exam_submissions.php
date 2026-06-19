<?php
$statusLabels = [
    'pending_review' => 'Aguardando correcao',
    'submitted' => 'Corrigida',
    'auto_graded' => 'Corrigida automaticamente',
];
$currentStatus = (string) ($filters['status'] ?? 'pending_review');
?>
<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Exames</p>
            <h2 class="text-2xl font-semibold text-white">Correção de provas dissertativas</h2>
            <p class="text-sm text-slate-300">Receba as respostas enviadas pelo Portal do Aluno e publique a nota sem poluir a tela principal de exames.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= route('courses/exams'); ?>" class="rounded-lg border border-sky-700 bg-sky-950/60 px-3 py-2 text-sm font-semibold text-sky-100 hover:bg-sky-900">Voltar para exames</a>
        </div>
    </div>

    <?php if (!$featureAvailable): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            A fila de correção depende das tabelas <code>exam_submissions</code> e <code>exam_submission_answers</code>. Execute a migration de submissões de prova antes de usar este painel.
        </div>
    <?php endif; ?>

    <form method="get" action="index.php" class="grid gap-3 rounded-2xl border border-sky-800 bg-slate-950/50 p-4 shadow-sm lg:grid-cols-6">
        <input type="hidden" name="route" value="courses/exams/submissions">

        <div class="lg:col-span-2">
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-cyan-100/85">Busca</label>
            <input type="text" name="q" value="<?= e((string) ($filters['q'] ?? '')); ?>" placeholder="Aluno, curso ou prova" class="w-full rounded-lg border border-slate-600 bg-slate-950/80 px-3 py-2 text-sm text-white placeholder:text-slate-500 focus:border-cyan-300 focus:outline-none">
        </div>

        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-cyan-100/85">Status</label>
            <select name="status" class="w-full rounded-lg border border-slate-600 bg-slate-950/80 px-3 py-2 text-sm text-white focus:border-cyan-300 focus:outline-none">
                <option value="pending_review" <?= $currentStatus === 'pending_review' ? 'selected' : ''; ?>>Aguardando</option>
                <option value="submitted" <?= $currentStatus === 'submitted' ? 'selected' : ''; ?>>Corrigidas</option>
                <option value="auto_graded" <?= $currentStatus === 'auto_graded' ? 'selected' : ''; ?>>Auto</option>
                <option value="all" <?= $currentStatus === 'all' ? 'selected' : ''; ?>>Todas</option>
            </select>
        </div>

        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-cyan-100/85">Curso</label>
            <select name="course_id" class="w-full rounded-lg border border-slate-600 bg-slate-950/80 px-3 py-2 text-sm text-white focus:border-cyan-300 focus:outline-none">
                <option value="0">Todos</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?= (int) $course['id']; ?>" <?= (int) ($filters['course_id'] ?? 0) === (int) $course['id'] ? 'selected' : ''; ?>><?= e((string) $course['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-cyan-100/85">Prova</label>
            <select name="exam_id" class="w-full rounded-lg border border-slate-600 bg-slate-950/80 px-3 py-2 text-sm text-white focus:border-cyan-300 focus:outline-none">
                <option value="0">Todas</option>
                <?php foreach ($exams as $exam): ?>
                    <option value="<?= (int) $exam['id']; ?>" <?= (int) ($filters['exam_id'] ?? 0) === (int) $exam['id'] ? 'selected' : ''; ?>><?= e((string) $exam['title']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-cyan-100/85">Página</label>
            <select name="per_page" class="w-full rounded-lg border border-slate-600 bg-slate-950/80 px-3 py-2 text-sm text-white focus:border-cyan-300 focus:outline-none">
                <?php foreach ($paginationOptions as $option): ?>
                    <option value="<?= (int) $option; ?>" <?= (int) ($meta['per_page'] ?? 50) === (int) $option ? 'selected' : ''; ?>><?= (int) $option; ?>/página</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex items-end gap-2 lg:col-span-6">
            <button class="rounded-lg bg-cyan-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-300">Filtrar</button>
            <a href="<?= route('courses/exams/submissions'); ?>" class="rounded-lg border border-slate-600 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-800">Limpar</a>
        </div>
    </form>

    <div class="rounded-2xl border border-sky-800 bg-slate-950/45 p-4 shadow-sm">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold text-white">Entregas recebidas</h3>
                <p class="text-sm text-slate-400"><?= (int) ($meta['total'] ?? 0); ?> entrega(s) encontradas.</p>
            </div>
            <span class="rounded-full border border-cyan-400/30 bg-cyan-400/10 px-3 py-1 text-xs font-semibold text-cyan-100"><?= e($statusLabels[$currentStatus] ?? 'Todas'); ?></span>
        </div>

        <div class="overflow-hidden rounded-xl border border-sky-900">
            <table class="min-w-full divide-y divide-sky-900 text-sm">
                <thead class="bg-slate-900/80 text-left text-xs uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Aluno</th>
                        <th class="px-4 py-3">Prova</th>
                        <th class="px-4 py-3">Envio</th>
                        <th class="px-4 py-3">Respostas</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Acao</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sky-950 bg-slate-950/40">
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $status = (string) ($row['status'] ?? '');
                        $isPending = $status === 'pending_review';
                        ?>
                        <tr class="text-slate-200">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-white"><?= e((string) ($row['student_name'] ?? '-')); ?></p>
                                <p class="text-xs text-slate-400"><?= e((string) ($row['student_email'] ?? '')); ?></p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-semibold"><?= e((string) ($row['exam_title'] ?? '-')); ?></p>
                                <p class="text-xs text-slate-400"><?= e((string) ($row['course_name'] ?? '-')); ?></p>
                            </td>
                            <td class="px-4 py-3 text-slate-300"><?= !empty($row['submitted_at']) ? e(date('d/m/Y H:i', strtotime((string) $row['submitted_at']))) : '-'; ?></td>
                            <td class="px-4 py-3 text-slate-300">
                                <?= (int) ($row['essay_answers_total'] ?? 0); ?> dissertativa(s)
                                <p class="text-xs text-slate-500"><?= (int) ($row['answers_total'] ?? 0); ?> resposta(s) no total</p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-semibold <?= $isPending ? 'bg-amber-200 text-amber-950' : 'bg-emerald-200 text-emerald-950'; ?>">
                                    <?= e($statusLabels[$status] ?? $status); ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="<?= route('courses/exams/submission&id=' . (int) $row['id']); ?>" class="rounded-lg border border-cyan-400/50 px-3 py-1.5 text-xs font-semibold text-cyan-100 hover:bg-cyan-400/10">
                                    <?= $isPending ? 'Corrigir' : 'Visualizar'; ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if ($rows === []): ?>
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-400">Nenhuma entrega encontrada para os filtros selecionados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (($meta['pages'] ?? 1) > 1): ?>
            <div class="mt-4 flex flex-wrap gap-2">
                <?php for ($p = 1; $p <= (int) $meta['pages']; $p++): ?>
                    <a href="index.php?<?= build_query(['route' => 'courses/exams/submissions', 'page' => $p]); ?>" class="rounded px-3 py-1 text-sm <?= $p === (int) $meta['page'] ? 'bg-cyan-400 text-slate-950' : 'border border-sky-800 text-slate-200 hover:bg-slate-900'; ?>"><?= $p; ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
