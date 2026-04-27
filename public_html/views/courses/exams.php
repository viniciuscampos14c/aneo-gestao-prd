<?php
$scheduleEnabled = $examScheduleEnabled ?? false;
$externalFeatureAvailable = $externalExamFeatureAvailable ?? false;
$externalLinksRows = $externalLinks ?? [];
?>
<section class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-semibold">Exames / Avaliacoes</h2>
            <p class="text-sm text-slate-500">Crie provas, agende datas, vincule provas externas e registre resultados.</p>
        </div>
        <a href="<?= route('courses'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Voltar</a>
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

    <?php if ($externalFeatureAvailable): ?>
        <form method="post" action="<?= route('courses/exams/external-link/store'); ?>" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 lg:grid-cols-6">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">

            <select name="exam_id" required class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Exame...</option>
                <?php foreach ($rows as $exam): ?>
                    <option value="<?= (int) $exam['id']; ?>"><?= e($exam['title']); ?> (<?= e($exam['course_name']); ?>)</option>
                <?php endforeach; ?>
            </select>

            <select name="delivery_scope" id="external-link-scope" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="course">Enviar para todos alunos do curso</option>
                <option value="student">Enviar para aluno especifico</option>
            </select>

            <select name="student_id" id="external-link-student" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Aluno...</option>
                <?php foreach ($students as $student): ?>
                    <option value="<?= (int) $student['id']; ?>"><?= e($student['full_name']); ?></option>
                <?php endforeach; ?>
            </select>

            <input type="url" name="external_url" required placeholder="URL da prova externa (Forms/Quiz etc)" class="rounded-lg border border-slate-200 px-3 py-2 text-sm lg:col-span-2">
            <input type="datetime-local" name="due_at" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <button class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700">Vincular Prova Externa</button>

            <p id="external-link-hint" class="text-xs text-slate-500 lg:col-span-6">A prova externa sera vinculada automaticamente para todos os alunos ativos/concluidos do curso da prova.</p>
            <textarea name="instructions" rows="2" placeholder="Instrucoes para o aluno (opcional)" class="rounded-lg border border-slate-200 px-3 py-2 text-sm lg:col-span-6"></textarea>
        </form>

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

        <script>
            (() => {
                const scopeField = document.getElementById('external-link-scope');
                const studentField = document.getElementById('external-link-student');
                const hintField = document.getElementById('external-link-hint');

                if (!scopeField || !studentField) {
                    return;
                }

                const syncScope = () => {
                    const byStudent = scopeField.value === 'student';
                    studentField.disabled = !byStudent;
                    studentField.required = byStudent;

                    if (!byStudent) {
                        studentField.value = '';
                    }

                    if (hintField) {
                        hintField.textContent = byStudent
                            ? 'Selecione o aluno para enviar uma prova externa individual.'
                            : 'A prova externa sera vinculada automaticamente para todos os alunos ativos/concluidos do curso da prova.';
                    }
                };

                scopeField.addEventListener('change', syncScope);
                syncScope();
            })();
        </script>
    <?php endif; ?>

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
                    <th class="px-3 py-3">Links externos</th>
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
                        <td class="px-3 py-3">
                            <span class="inline-flex rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700"><?= (int) ($row['external_links_total'] ?? 0); ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?>
                    <tr><td colspan="7" class="px-3 py-6 text-center text-slate-500">Nenhum exame cadastrado.</td></tr>
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
