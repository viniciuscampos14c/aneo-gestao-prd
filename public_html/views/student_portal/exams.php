<?php
$scheduleEnabled = $examScheduleEnabled ?? false;
$calendarRows = $examCalendar ?? [];
?>
<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold">Avaliacoes</h2>
            <p class="text-sm text-slate-500">Responda provas internas, acesse provas externas e acompanhe seu historico.</p>
        </div>
        <a href="<?= route('student/academic-history'); ?>" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            Historico Academico
        </a>
    </div>

    <section class="rounded-xl border border-sky-100 bg-sky-50/70 p-4">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-sky-900">Calendario das proximas provas</h3>
            <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-sky-700"><?= count($calendarRows); ?> agendada(s)</span>
        </div>
        <?php if (!$scheduleEnabled): ?>
            <p class="text-sm text-slate-600">A equipe ainda nao habilitou o calendario de provas neste ambiente.</p>
        <?php else: ?>
            <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                <?php foreach ($calendarRows as $calendarExam): ?>
                    <article class="rounded-lg border border-sky-100 bg-white px-3 py-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-sky-700"><?= e(date('d/m/Y H:i', strtotime((string) $calendarExam['scheduled_at']))); ?></p>
                        <p class="mt-1 text-sm font-semibold text-slate-900"><?= e($calendarExam['title']); ?></p>
                        <p class="text-xs text-slate-500"><?= e($calendarExam['course_name']); ?></p>
                    </article>
                <?php endforeach; ?>
                <?php if ($calendarRows === []): ?>
                    <p class="md:col-span-2 xl:col-span-3 text-sm text-slate-600">Nenhuma prova futura marcada para seus cursos.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white/90 p-4">
        <h3 class="mb-3 text-lg font-semibold">Provas disponiveis para responder</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-3 py-2">Curso</th>
                        <th class="px-3 py-2">Avaliacao</th>
                        <th class="px-3 py-2">Data da prova</th>
                        <th class="px-3 py-2">Questoes</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Acao</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($availableExams as $exam): ?>
                        <?php
                        $hasResult = !empty($exam['result_id']);
                        $hasSubmission = !empty($exam['submission_id']);
                        $hasExternal = trim((string) ($exam['external_url'] ?? '')) !== '';
                        $scheduledAt = trim((string) ($exam['scheduled_at'] ?? ''));
                        $scheduledTs = $scheduledAt !== '' ? strtotime($scheduledAt) : false;
                        $isLockedBySchedule = $scheduledTs !== false && $scheduledTs > time();
                        $canOpenExternal = !$hasResult && $hasExternal && !$isLockedBySchedule;
                        $canTakeInternal = !$hasResult && !$hasExternal && !$hasSubmission && (int) $exam['questions_total'] > 0 && !$isLockedBySchedule;
                        $externalDueAt = trim((string) ($exam['external_due_at'] ?? ''));
                        ?>
                        <tr class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="px-3 py-2 font-medium"><?= e($exam['course_name']); ?></td>
                            <td class="px-3 py-2"><?= e($exam['title']); ?></td>
                            <td class="px-3 py-2">
                                <?php if ($scheduledTs !== false): ?>
                                    <span class="inline-flex rounded-full border border-sky-400 bg-sky-200 px-2 py-1 text-xs font-semibold text-sky-950"><?= e(date('d/m/Y H:i', $scheduledTs)); ?></span>
                                <?php else: ?>
                                    <span class="text-xs text-slate-500">Nao definida</span>
                                <?php endif; ?>
                                <?php if ($externalDueAt !== ''): ?>
                                    <p class="mt-1 text-xs text-slate-500">Prazo externo: <?= e(date('d/m/Y H:i', strtotime($externalDueAt))); ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2"><?= (int) $exam['questions_total']; ?></td>
                            <td class="px-3 py-2">
                                <?php if ($hasResult): ?>
                                    <span class="inline-flex rounded-full border border-emerald-400 bg-emerald-200 px-2 py-1 text-xs font-semibold text-emerald-950">Resultado publicado</span>
                                <?php elseif ($hasExternal): ?>
                                    <span class="inline-flex rounded-full border border-cyan-400 bg-cyan-200 px-2 py-1 text-xs font-semibold text-cyan-950">Prova externa</span>
                                <?php elseif ($hasSubmission): ?>
                                    <span class="inline-flex rounded-full border border-amber-400 bg-amber-200 px-2 py-1 text-xs font-semibold text-amber-950">Enviada (aguardando)</span>
                                <?php elseif ($isLockedBySchedule): ?>
                                    <span class="inline-flex rounded-full border border-sky-400 bg-sky-200 px-2 py-1 text-xs font-semibold text-sky-950">Agendada</span>
                                <?php elseif ((int) $exam['questions_total'] <= 0): ?>
                                    <span class="inline-flex rounded-full border border-slate-400 bg-slate-200 px-2 py-1 text-xs font-semibold text-slate-950">Sem questoes</span>
                                <?php else: ?>
                                    <span class="inline-flex rounded-full border border-cyan-400 bg-cyan-200 px-2 py-1 text-xs font-semibold text-cyan-950">Disponivel</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2">
                                <?php if ($canOpenExternal): ?>
                                    <a href="<?= route('student/exams/external&id=' . (int) $exam['id']); ?>" target="_blank" rel="noopener noreferrer" class="inline-flex rounded-lg border border-cyan-400 bg-cyan-200 px-3 py-1.5 text-xs font-semibold text-cyan-950 hover:bg-cyan-300">Abrir prova externa</a>
                                    <?php if (trim((string) ($exam['external_instructions'] ?? '')) !== ''): ?>
                                        <p class="mt-1 text-xs text-slate-500"><?= e((string) $exam['external_instructions']); ?></p>
                                    <?php endif; ?>
                                <?php elseif ($canTakeInternal): ?>
                                    <a href="<?= route('student/exams/take&id=' . (int) $exam['id']); ?>" class="inline-flex rounded-lg border border-cyan-400 bg-cyan-200 px-3 py-1.5 text-xs font-semibold text-cyan-950 hover:bg-cyan-300">Responder agora</a>
                                <?php elseif ($isLockedBySchedule): ?>
                                    <span class="text-xs text-slate-500">Liberada em <?= e(date('d/m H:i', (int) $scheduledTs)); ?></span>
                                <?php elseif ($hasExternal && !$hasResult): ?>
                                    <span class="text-xs text-slate-500">Aguardando realizacao/nota</span>
                                <?php else: ?>
                                    <span class="text-xs text-slate-500">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($availableExams === []): ?>
                        <tr>
                            <td colspan="6" class="px-3 py-6 text-center text-slate-500">Nenhuma prova vinculada aos seus cursos publicados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-4">
        <h3 class="mb-3 text-lg font-semibold">Provas enviadas aguardando correcao</h3>
        <div class="space-y-2 text-sm">
            <?php foreach ($pendingSubmissions as $pending): ?>
                <div class="rounded-lg border border-amber-300 bg-amber-100 px-3 py-2">
                    <p class="font-medium"><?= e($pending['exam_title']); ?> <span class="text-slate-500">(<?= e($pending['course_name']); ?>)</span></p>
                    <p class="text-xs font-medium text-amber-950">Enviada em <?= e(date('d/m/Y H:i', strtotime((string) $pending['submitted_at']))); ?>.</p>
                </div>
            <?php endforeach; ?>
            <?php if ($pendingSubmissions === []): ?>
                <p class="text-slate-500">Nenhuma prova pendente de correcao.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-4">
        <h3 class="mb-3 text-lg font-semibold">Historico de Avaliacoes</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-3 py-3">Data</th>
                        <th class="px-3 py-3">Curso</th>
                        <th class="px-3 py-3">Avaliacao</th>
                        <th class="px-3 py-3">Nota minima</th>
                        <th class="px-3 py-3">Sua nota</th>
                        <th class="px-3 py-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php $approved = (string) $row['status'] === 'approved'; ?>
                        <tr class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="px-3 py-3"><?= e(date('d/m/Y H:i', strtotime((string) $row['submitted_at']))); ?></td>
                            <td class="px-3 py-3 font-medium"><?= e($row['course_name']); ?></td>
                            <td class="px-3 py-3"><?= e($row['exam_title']); ?></td>
                            <td class="px-3 py-3"><?= e(number_format((float) $row['passing_score'], 2, ',', '.')); ?></td>
                            <td class="px-3 py-3"><?= e(number_format((float) $row['score'], 2, ',', '.')); ?></td>
                            <td class="px-3 py-3">
                                <span class="inline-flex rounded-full border px-2 py-1 text-xs font-semibold <?= $approved ? 'border-emerald-400 bg-emerald-200 text-emerald-950' : 'border-rose-400 bg-rose-200 text-rose-950'; ?>">
                                    <?= $approved ? 'Aprovado' : 'Reprovado'; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($rows === []): ?>
                        <tr>
                            <td colspan="6" class="px-3 py-8 text-center text-slate-500">Nenhum resultado de avaliacao registrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</section>
