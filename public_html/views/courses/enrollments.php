<?php $currentCourseFilter = (int) (($filters['course_id'] ?? 0)); ?>
<section class="courses-enrollments-shell space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-semibold text-white">Matrículas</h2>
            <p class="text-sm text-slate-300">Vincule alunos aos cursos e acompanhe status/progresso.</p>
        </div>
        <a href="<?= route('courses'); ?>" class="rounded-lg border border-slate-600 bg-slate-900/70 px-3 py-2 text-sm text-slate-100 transition hover:border-cyan-400/50 hover:bg-slate-800">Voltar</a>
    </div>

    <form method="post" action="<?= route('courses/enrollments/store'); ?>" class="courses-enrollments-create-panel grid gap-3 rounded-xl border border-slate-700 bg-slate-900/90 p-4 shadow-sm md:grid-cols-4">
        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">

        <select name="student_id" required class="rounded-lg border border-slate-600 bg-slate-950/75 px-3 py-2 text-sm text-white focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-500/20">
            <option value="">Aluno...</option>
            <?php foreach ($students as $student): ?>
                <option value="<?= (int) $student['id']; ?>"><?= e($student['full_name']); ?></option>
            <?php endforeach; ?>
        </select>

        <select name="course_id" required class="rounded-lg border border-slate-600 bg-slate-950/75 px-3 py-2 text-sm text-white focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-500/20">
            <option value="">Curso...</option>
            <?php foreach ($courses as $course): ?>
                <option value="<?= (int) $course['id']; ?>" <?= $currentCourseFilter === (int) $course['id'] ? 'selected' : ''; ?>><?= e($course['name']); ?></option>
            <?php endforeach; ?>
        </select>

        <select name="status" class="rounded-lg border border-slate-600 bg-slate-950/75 px-3 py-2 text-sm text-white focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-500/20">
            <option value="active">Ativa</option>
            <option value="cancelled">Cancelada</option>
        </select>

        <input type="date" name="started_at" class="rounded-lg border border-slate-600 bg-slate-950/75 px-3 py-2 text-sm text-white focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-500/20">
        <button class="rounded-lg border border-cyan-300/60 bg-cyan-300 px-4 py-2 text-sm font-semibold text-slate-950 shadow-sm shadow-cyan-950/25 transition hover:bg-cyan-200">Matricular</button>

        <p class="rounded-lg border border-cyan-400/30 bg-cyan-400/8 px-3 py-2 text-xs text-cyan-100 md:col-span-4">
            O progresso e calculado automaticamente pelas aulas assistidas no portal do aluno.
        </p>
    </form>

    <?php if ($currentCourseFilter > 0): ?>
        <div class="courses-enrollments-filter-alert rounded-xl border border-cyan-400/30 bg-cyan-400/8 px-4 py-3 text-sm text-cyan-100">
            Listagem filtrada por curso. <a href="<?= route('courses/enrollments'); ?>" class="font-semibold underline">Limpar filtro</a>
        </div>
    <?php endif; ?>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-3">ID</th>
                    <th class="px-3 py-3">Aluno</th>
                    <th class="px-3 py-3">Curso</th>
                    <th class="px-3 py-3">Status</th>
                    <th class="px-3 py-3">Progresso</th>
                    <th class="px-3 py-3">Inicio</th>
                    <th class="px-3 py-3">Conclusao</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr class="border-b border-slate-100 hover:bg-slate-50">
                        <td class="px-3 py-3"><?= (int) $row['id']; ?></td>
                        <td class="px-3 py-3"><?= e($row['student_name']); ?></td>
                        <td class="px-3 py-3"><?= e($row['course_name']); ?></td>
                        <td class="px-3 py-3"><?= e($row['status']); ?></td>
                        <td class="px-3 py-3">
                            <?php $progress = max(0, min(100, (int) ($row['progress_percent'] ?? 0))); ?>
                            <div class="flex min-w-44 items-center gap-2">
                                <div class="h-2 flex-1 rounded-full bg-slate-200">
                                    <div class="h-2 rounded-full bg-cyan-600" style="width: <?= $progress; ?>%"></div>
                                </div>
                                <span class="w-10 text-right text-xs font-semibold text-slate-700"><?= $progress; ?>%</span>
                            </div>
                            <span class="mt-1 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500">Automatico</span>
                        </td>
                        <td class="px-3 py-3"><?= e($row['started_at']); ?></td>
                        <td class="px-3 py-3"><?= e($row['completed_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?>
                    <tr><td colspan="7" class="px-3 py-6 text-center text-slate-500">Nenhuma matrícula registrada.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
        <p>Total: <?= (int) $meta['total']; ?> registros | Página <?= (int) $meta['page']; ?>/<?= (int) $meta['pages']; ?></p>
        <div class="flex gap-2">
            <?php for ($p = 1; $p <= (int) $meta['pages']; $p++): ?>
                <a href="index.php?<?= build_query(['route' => 'courses/enrollments', 'page' => $p, 'course_id' => $currentCourseFilter]); ?>" class="rounded px-3 py-1 <?= $p === (int) $meta['page'] ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white hover:bg-slate-50'; ?>"><?= $p; ?></a>
            <?php endfor; ?>
        </div>
    </div>
</section>
