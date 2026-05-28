<?php
$filters = $filters ?? ['q' => ''];
$students = $students ?? [];
$selectedStudent = is_array($selectedStudent ?? null) ? $selectedStudent : null;
$profile = is_array($profile ?? null) ? $profile : [];
$documents = $documents ?? [];
$courses = $courses ?? [];
$history = $history ?? [];
$terms = $terms ?? [];
$summary = $summary ?? [];
$selectedStudentId = (int) ($selectedStudentId ?? 0);
$studentName = trim((string) ($profile['full_name'] ?? ($selectedStudent['full_name'] ?? '')));
$studentStatus = trim((string) ($selectedStudent['kanban_status_name'] ?? ''));
$studentRa = trim((string) ($summary['ra'] ?? ''));
?>

<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold">Perfil de certificador</h2>
            <p class="text-sm text-slate-500">Acompanhe dados academicos, notas e documentos dos alunos para envio a certificadora.</p>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[320px_minmax(0,1fr)]">
        <aside class="space-y-4 rounded-xl border border-slate-200 bg-white p-4">
            <form method="get" action="<?= route('certification'); ?>" class="space-y-3">
                <input type="hidden" name="route" value="certification">
                <label class="block">
                    <span class="mb-1 block text-sm font-medium">Buscar aluno</span>
                    <input type="text" name="q" value="<?= e((string) ($filters['q'] ?? '')); ?>" placeholder="Nome, email, RA, RG, CRO..." class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>
                <button class="w-full rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">Pesquisar</button>
            </form>

            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.14em] text-slate-500">Alunos</h3>
                    <span class="text-xs text-slate-400"><?= count($students); ?> na pagina</span>
                </div>

                <?php foreach ($students as $student): ?>
                    <?php
                    $studentId = (int) ($student['id'] ?? 0);
                    $isActiveCard = $studentId === $selectedStudentId;
                    ?>
                    <a href="<?= route('certification&q=' . urlencode((string) ($filters['q'] ?? '')) . '&student_id=' . $studentId); ?>"
                       class="block rounded-xl border px-3 py-3 transition <?= $isActiveCard ? 'border-cyan-500 bg-cyan-50' : 'border-slate-200 hover:bg-slate-50'; ?>">
                        <p class="font-semibold text-slate-900"><?= e((string) ($student['full_name'] ?? 'Aluno')); ?></p>
                        <p class="mt-1 text-xs text-slate-500">RA: <?= e((string) ($student['ra'] ?? '-')); ?></p>
                        <p class="text-xs text-slate-500">Status: <?= e((string) ($student['kanban_status_name'] ?? '-')); ?></p>
                    </a>
                <?php endforeach; ?>

                <?php if ($students === []): ?>
                    <p class="rounded-xl border border-dashed border-slate-200 px-3 py-6 text-center text-sm text-slate-500">Nenhum aluno encontrado para a busca.</p>
                <?php endif; ?>
            </div>
        </aside>

        <div class="space-y-6">
            <?php if ($selectedStudent): ?>
                <section class="rounded-xl border border-slate-200 bg-white p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-600">Aluno selecionado</p>
                            <h3 class="text-2xl font-semibold text-slate-900"><?= e($studentName !== '' ? $studentName : 'Aluno'); ?></h3>
                            <p class="mt-1 text-sm text-slate-500">RA <?= e($studentRa !== '' ? $studentRa : '-'); ?> | Status <?= e($studentStatus !== '' ? $studentStatus : '-'); ?></p>
                        </div>
                        <div class="grid min-w-[220px] grid-cols-2 gap-3 text-sm">
                            <div class="rounded-xl bg-slate-50 p-3">
                                <p class="text-slate-500">Resultados</p>
                                <p class="text-xl font-semibold"><?= (int) ($summary['total_results'] ?? 0); ?></p>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-3">
                                <p class="text-slate-500">Media</p>
                                <p class="text-xl font-semibold"><?= e(number_format((float) ($summary['average_score'] ?? 0), 2, ',', '.')); ?></p>
                            </div>
                            <div class="rounded-xl bg-emerald-50 p-3">
                                <p class="text-emerald-700">Aprovados</p>
                                <p class="text-xl font-semibold text-emerald-900"><?= (int) ($summary['approved_count'] ?? 0); ?></p>
                            </div>
                            <div class="rounded-xl bg-rose-50 p-3">
                                <p class="text-rose-700">Reprovados</p>
                                <p class="text-xl font-semibold text-rose-900"><?= (int) ($summary['failed_count'] ?? 0); ?></p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="grid gap-6 xl:grid-cols-2">
                    <article class="rounded-xl border border-slate-200 bg-white p-5">
                        <h3 class="text-lg font-semibold">Dados do aluno</h3>
                        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                            <div><dt class="text-slate-500">Nome</dt><dd class="font-medium"><?= e($studentName !== '' ? $studentName : '-'); ?></dd></div>
                            <div><dt class="text-slate-500">RA</dt><dd class="font-medium"><?= e($studentRa !== '' ? $studentRa : '-'); ?></dd></div>
                            <div><dt class="text-slate-500">Email</dt><dd class="font-medium"><?= e((string) ($profile['email_primary'] ?? '-')); ?></dd></div>
                            <div><dt class="text-slate-500">Telefone</dt><dd class="font-medium"><?= e((string) ($profile['phone'] ?? '-')); ?></dd></div>
                            <div><dt class="text-slate-500">Nascimento</dt><dd class="font-medium"><?= e(!empty($profile['birth_date']) ? date('d/m/Y', strtotime((string) $profile['birth_date'])) : '-'); ?></dd></div>
                            <div><dt class="text-slate-500">RG</dt><dd class="font-medium"><?= e((string) ($profile['rg'] ?? '-')); ?></dd></div>
                            <div><dt class="text-slate-500">CRO</dt><dd class="font-medium"><?= e((string) ($profile['cro'] ?? '-')); ?></dd></div>
                            <div><dt class="text-slate-500">Instituicao</dt><dd class="font-medium"><?= e((string) (($profile['company_trade_name'] ?? $profile['company_legal_name'] ?? '-') ?: '-')); ?></dd></div>
                        </dl>
                    </article>

                    <article class="rounded-xl border border-slate-200 bg-white p-5">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-lg font-semibold">Documentos</h3>
                            <span class="text-xs text-slate-400"><?= count($documents); ?> arquivo(s)</span>
                        </div>
                        <div class="mt-4 space-y-2 text-sm">
                            <?php foreach ($documents as $doc): ?>
                                <a href="<?= e((string) ($doc['file_path'] ?? '#')); ?>" target="_blank" rel="noopener" class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-3 hover:bg-slate-50">
                                    <span class="font-medium text-slate-800"><?= e((string) ($doc['file_name'] ?? 'Documento')); ?></span>
                                    <span class="text-xs text-slate-400">Abrir</span>
                                </a>
                            <?php endforeach; ?>
                            <?php if ($documents === []): ?>
                                <p class="rounded-lg border border-dashed border-slate-200 px-3 py-6 text-center text-slate-500">Nenhum documento anexado para este aluno.</p>
                            <?php endif; ?>
                        </div>
                    </article>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-5">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-lg font-semibold">Curriculo e cursos</h3>
                        <span class="text-xs text-slate-400">Carga horaria total: <?= e(number_format((float) ($summary['total_workload'] ?? 0), 0, ',', '.')); ?>h</span>
                    </div>
                    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <?php foreach ($courses as $course): ?>
                            <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <p class="font-semibold text-slate-900"><?= e((string) ($course['name'] ?? 'Curso')); ?></p>
                                <p class="mt-1 text-sm text-slate-500"><?= e((string) ($course['category_name'] ?? 'Sem categoria')); ?></p>
                                <div class="mt-3 flex items-center justify-between text-xs text-slate-500">
                                    <span><?= e(number_format((float) ($course['workload_hours'] ?? 0), 0, ',', '.')); ?>h</span>
                                    <span><?= e((string) ($course['enrollment_status'] ?? '-')); ?></span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                        <?php if ($courses === []): ?>
                            <p class="rounded-xl border border-dashed border-slate-200 px-3 py-6 text-center text-sm text-slate-500 md:col-span-2 xl:col-span-3">Nenhum curso vinculado a este aluno.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-5">
                    <h3 class="text-lg font-semibold">Historico academico</h3>
                    <div class="mt-4 space-y-5">
                        <?php foreach ($terms as $term): ?>
                            <article class="rounded-xl border border-slate-200">
                                <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                                    <h4 class="font-semibold text-slate-900"><?= e((string) ($term['term_label'] ?? 'Periodo')); ?></h4>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-white text-left text-slate-500">
                                            <tr>
                                                <th class="px-4 py-3 font-medium">Curso / avaliacao</th>
                                                <th class="px-4 py-3 font-medium">Data</th>
                                                <th class="px-4 py-3 font-medium">Carga horaria</th>
                                                <th class="px-4 py-3 font-medium">Nota</th>
                                                <th class="px-4 py-3 font-medium">Situacao</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (($term['rows'] ?? []) as $row): ?>
                                                <?php
                                                $label = trim((string) ($row['course_name'] ?? ''));
                                                $examTitle = trim((string) ($row['exam_title'] ?? ''));
                                                if ($examTitle !== '') {
                                                    $label .= ($label !== '' ? ' - ' : '') . $examTitle;
                                                }
                                                $approved = (string) ($row['status'] ?? '') === 'approved';
                                                ?>
                                                <tr class="border-t border-slate-100">
                                                    <td class="px-4 py-3 font-medium text-slate-800"><?= e($label !== '' ? $label : '-'); ?></td>
                                                    <td class="px-4 py-3 text-slate-500"><?= e(!empty($row['date']) ? date('d/m/Y H:i', strtotime((string) $row['date'])) : '-'); ?></td>
                                                    <td class="px-4 py-3 text-slate-500"><?= e(number_format((float) ($row['workload'] ?? 0), 0, ',', '.')); ?>h</td>
                                                    <td class="px-4 py-3 text-slate-800"><?= e(number_format((float) ($row['score'] ?? 0), 2, ',', '.')); ?></td>
                                                    <td class="px-4 py-3">
                                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold <?= $approved ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'; ?>">
                                                            <?= $approved ? 'Aprovado' : 'Reprovado'; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </article>
                        <?php endforeach; ?>

                        <?php if ($terms === []): ?>
                            <p class="rounded-xl border border-dashed border-slate-200 px-3 py-6 text-center text-sm text-slate-500">Nenhuma nota registrada para este aluno ate o momento.</p>
                        <?php endif; ?>
                    </div>
                </section>
            <?php else: ?>
                <section class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center">
                    <h3 class="text-xl font-semibold text-slate-900">Selecione um aluno</h3>
                    <p class="mt-2 text-sm text-slate-500">Assim que voce escolher um aluno, esta area mostrara documentos, curriculo e historico academico.</p>
                </section>
            <?php endif; ?>
        </div>
    </div>
</section>
