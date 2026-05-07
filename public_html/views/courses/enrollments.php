<section class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-semibold">Matriculas</h2>
            <p class="text-sm text-slate-500">Vincule alunos aos cursos e acompanhe status/progresso.</p>
        </div>
        <a href="<?= route('courses'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Voltar</a>
    </div>

    <form method="post" action="<?= route('courses/enrollments/store'); ?>" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-4">
        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">

        <select name="student_id" required class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option value="">Aluno...</option>
            <?php foreach ($students as $student): ?>
                <option value="<?= (int) $student['id']; ?>"><?= e($student['full_name']); ?></option>
            <?php endforeach; ?>
        </select>

        <select name="course_id" required class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option value="">Curso...</option>
            <?php foreach ($courses as $course): ?>
                <option value="<?= (int) $course['id']; ?>"><?= e($course['name']); ?></option>
            <?php endforeach; ?>
        </select>

        <select name="status" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option value="active">Ativa</option>
            <option value="cancelled">Cancelada</option>
        </select>

        <input type="date" name="started_at" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Matricular</button>

        <p class="rounded-lg border border-cyan-100 bg-cyan-50 px-3 py-2 text-xs text-cyan-800 md:col-span-4">
            O progresso e calculado automaticamente pelas aulas assistidas no portal do aluno.
        </p>
    </form>

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
                    <tr><td colspan="7" class="px-3 py-6 text-center text-slate-500">Nenhuma matricula registrada.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
        <p>Total: <?= (int) $meta['total']; ?> registros | Pagina <?= (int) $meta['page']; ?>/<?= (int) $meta['pages']; ?></p>
        <div class="flex gap-2">
            <?php for ($p = 1; $p <= (int) $meta['pages']; $p++): ?>
                <a href="index.php?<?= build_query(['route' => 'courses/enrollments', 'page' => $p]); ?>" class="rounded px-3 py-1 <?= $p === (int) $meta['page'] ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white hover:bg-slate-50'; ?>"><?= $p; ?></a>
            <?php endfor; ?>
        </div>
    </div>
</section>
