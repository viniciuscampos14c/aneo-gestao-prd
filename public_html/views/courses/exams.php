<?php
$scheduleEnabled = $examScheduleEnabled ?? false;
?>
<section class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-semibold">Exames / Avaliacoes</h2>
            <p class="text-sm text-slate-500">Crie provas, agende datas e registre resultados.</p>
        </div>
        <a href="<?= route('courses'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Voltar</a>
    </div>

    <?php if (!$scheduleEnabled): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Calendario de provas desativado nesta base. Execute a migracao
            <code>migrations/20260305_exam_schedule_calendar.sql</code> para habilitar o campo de data.
        </div>
    <?php endif; ?>

    <section class="rounded-xl border border-sky-200 bg-sky-50/70 p-4">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-sky-900">Calendario de Provas (proximos 90 dias)</h3>
            <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-sky-700"><?= count($upcomingExams); ?> agendada(s)</span>
        </div>
        <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($upcomingExams as $exam): ?>
                <article class="rounded-lg border border-sky-100 bg-white px-3 py-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-sky-700"><?= e(date('d/m/Y H:i', strtotime((string) $exam['scheduled_at']))); ?></p>
                    <p class="mt-1 text-sm font-semibold text-slate-900"><?= e($exam['title']); ?></p>
                    <p class="text-xs text-slate-500"><?= e($exam['course_name']); ?></p>
                </article>
            <?php endforeach; ?>
            <?php if ($upcomingExams === []): ?>
                <p class="md:col-span-2 xl:col-span-3 text-sm text-slate-600">Nenhuma prova agendada para os proximos dias.</p>
            <?php endif; ?>
        </div>
    </section>

    <form method="post" action="<?= route('courses/exams/store'); ?>" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 lg:grid-cols-6">
        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">

        <select name="course_id" required class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option value="">Curso...</option>
            <?php foreach ($courses as $course): ?>
                <option value="<?= (int) $course['id']; ?>"><?= e($course['name']); ?></option>
            <?php endforeach; ?>
        </select>

        <input type="text" name="title" required placeholder="Titulo da prova" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <input type="text" name="description" placeholder="Descricao" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <input type="text" name="passing_score" value="7,0" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <input type="datetime-local" name="scheduled_at" class="rounded-lg border border-slate-200 px-3 py-2 text-sm" <?= $scheduleEnabled ? '' : 'disabled'; ?>>
        <select name="question_type" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option value="objective">Objetiva</option>
            <option value="essay">Dissertativa</option>
        </select>

        <input type="text" name="question_text" placeholder="Questao inicial (opcional)" class="rounded-lg border border-slate-200 px-3 py-2 text-sm lg:col-span-2">
        <textarea name="options_text" rows="2" placeholder="Opcoes (uma por linha) - para objetiva" class="rounded-lg border border-slate-200 px-3 py-2 text-sm lg:col-span-2"></textarea>
        <input type="text" name="correct_answer" placeholder="Resposta correta (texto exato)" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">

        <p class="text-xs text-slate-500 lg:col-span-5">Dica: para auto-correcao no portal do aluno, preencha questao objetiva com "Opcoes" e "Resposta correta".</p>
        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Criar Exame</button>
    </form>

    <form method="post" action="<?= route('courses/exams/result'); ?>" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 lg:grid-cols-5">
        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">

        <select name="exam_id" required class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option value="">Exame...</option>
            <?php foreach ($rows as $exam): ?>
                <?php $dateLabel = !empty($exam['scheduled_at']) ? date('d/m/Y H:i', strtotime((string) $exam['scheduled_at'])) : 'sem data'; ?>
                <option value="<?= (int) $exam['id']; ?>"><?= e($exam['title']); ?> (<?= e($exam['course_name']); ?> | <?= e($dateLabel); ?>)</option>
            <?php endforeach; ?>
        </select>

        <select name="student_id" required class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option value="">Aluno...</option>
            <?php foreach ($students as $student): ?>
                <option value="<?= (int) $student['id']; ?>"><?= e($student['full_name']); ?></option>
            <?php endforeach; ?>
        </select>

        <input type="text" name="score" required placeholder="Nota" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <input type="text" name="passing_score" value="7,0" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <button class="rounded-lg border border-slate-200 px-4 py-2 text-sm hover:bg-slate-50">Registrar Resultado</button>
    </form>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-3">ID</th>
                    <th class="px-3 py-3">Data da prova</th>
                    <th class="px-3 py-3">Curso</th>
                    <th class="px-3 py-3">Titulo</th>
                    <th class="px-3 py-3">Descricao</th>
                    <th class="px-3 py-3">Nota minima</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
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
                        <td class="px-3 py-3"><?= e($row['description']); ?></td>
                        <td class="px-3 py-3"><?= e($row['passing_score']); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?>
                    <tr><td colspan="6" class="px-3 py-6 text-center text-slate-500">Nenhum exame cadastrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
        <p>Total: <?= (int) $meta['total']; ?> registros | Pagina <?= (int) $meta['page']; ?>/<?= (int) $meta['pages']; ?></p>
        <div class="flex gap-2">
            <?php for ($p = 1; $p <= (int) $meta['pages']; $p++): ?>
                <a href="index.php?<?= build_query(['route' => 'courses/exams', 'page' => $p]); ?>" class="rounded px-3 py-1 <?= $p === (int) $meta['page'] ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white hover:bg-slate-50'; ?>"><?= $p; ?></a>
            <?php endfor; ?>
        </div>
    </div>
</section>
