<?php
$status = (string) ($schedule['status'] ?? 'draft');
$statusClass = match ($status) {
    'published' => 'bg-emerald-100 text-emerald-700',
    'archived' => 'border border-slate-500/40 bg-slate-700 text-slate-100',
    default => 'bg-amber-100 text-amber-700',
};
$isProfessorView = is_professor();
$canManageSchedule = has_permission('student_schedule.manage') && !$isProfessorView;
$canExportSchedule = has_permission('student_schedule.export');
?>
<section class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <div class="mb-2 flex flex-wrap items-center gap-2">
                <span class="rounded-full px-2 py-1 text-xs font-semibold <?= $statusClass; ?>"><?= e($statuses[$status] ?? 'Rascunho'); ?></span>
                <span class="text-xs uppercase tracking-[0.2em] text-slate-400"><?= e((string) ($schedule['unit_name'] ?? '')); ?></span>
            </div>
            <h2 class="text-2xl font-semibold"><?= e((string) $schedule['title']); ?></h2>
            <p class="text-sm text-slate-500">Periodo: <?= e(date('d/m/Y', strtotime((string) $schedule['start_date']))); ?> ate <?= e(date('d/m/Y', strtotime((string) $schedule['end_date']))); ?></p>
        </div>
        <div class="flex flex-wrap gap-2">
            <?php if ($canManageSchedule): ?>
                <a href="<?= route('escala-aluno/edit&id=' . (int) $schedule['id']); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Editar</a>
            <?php endif; ?>
            <?php if ($canExportSchedule): ?>
                <a href="<?= route('escala-aluno/export&id=' . (int) $schedule['id']); ?>" target="_blank" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Imprimir</a>
            <?php endif; ?>
            <?php if ($canManageSchedule && $status !== 'published'): ?>
                <form method="post" action="<?= route('escala-aluno/publish'); ?>">
                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                    <input type="hidden" name="id" value="<?= (int) $schedule['id']; ?>">
                    <button class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Publicar</button>
                </form>
            <?php endif; ?>
            <?php if ($canManageSchedule && $status !== 'archived'): ?>
                <form method="post" action="<?= route('escala-aluno/archive'); ?>" onsubmit="return confirm('Encerrar esta escala? Ela ficara bloqueada para edicao ate ser desarquivada.');">
                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                    <input type="hidden" name="id" value="<?= (int) $schedule['id']; ?>">
                    <button class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm hover:bg-slate-50">Encerrar escala</button>
                </form>
            <?php elseif ($canManageSchedule): ?>
                <form method="post" action="<?= route('escala-aluno/unarchive'); ?>" onsubmit="return confirm('Reabrir esta escala para edicao?');">
                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                    <input type="hidden" name="id" value="<?= (int) $schedule['id']; ?>">
                    <button class="rounded-lg border border-cyan-400/40 bg-cyan-950/40 px-3 py-2 text-sm font-medium text-cyan-200 hover:bg-cyan-900/60">Desarquivar</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($status === 'archived'): ?>
        <div class="rounded-xl border border-slate-600/40 bg-slate-800/70 px-4 py-3 text-sm text-slate-100">
            Esta escala esta encerrada. Para editar semanas, vagas ou alocacoes, use o botao <strong>Desarquivar</strong>.
        </div>
    <?php endif; ?>

    <?php if (!empty($schedule['notes'])): ?>
        <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600"><?= nl2br(e((string) $schedule['notes'])); ?></div>
    <?php endif; ?>

    <?php if ($canManageSchedule && $status !== 'archived'): ?>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="mb-3">
                <h3 class="text-lg font-semibold">Gerar semanas</h3>
                <p class="text-xs text-slate-500">Se voce regenerar as semanas, as alocacoes atuais serao substituidas.</p>
            </div>
            <form method="post" action="<?= route('escala-aluno/weeks/generate'); ?>" class="grid gap-3 md:grid-cols-4">
                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                <input type="hidden" name="schedule_id" value="<?= (int) $schedule['id']; ?>">
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-slate-500">Vagas R3</span>
                    <input type="number" min="0" name="r3_slots" value="1" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-slate-500">Vagas R2</span>
                    <input type="number" min="0" name="r2_slots" value="1" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-slate-500">Vagas R1</span>
                    <input type="number" min="0" name="r1_slots" value="2" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>
                <div class="flex items-end">
                    <button class="w-full rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Gerar grade</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <?php if (($availableMonths ?? []) !== []): ?>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="flex flex-wrap items-center gap-2">
                <a href="<?= route('escala-aluno/show&id=' . (int) $schedule['id']); ?>" class="rounded-full px-3 py-1.5 text-xs font-semibold <?= ($selectedMonth ?? '') === '' ? 'bg-slate-900 text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-50'; ?>">Todos os meses</a>
                <?php foreach (($availableMonths ?? []) as $monthOption): ?>
                    <a href="<?= route('escala-aluno/show&id=' . (int) $schedule['id'] . '&month_ref=' . urlencode((string) $monthOption)); ?>" class="rounded-full px-3 py-1.5 text-xs font-semibold <?= ($selectedMonth ?? '') === (string) $monthOption ? 'bg-cyan-600 text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-50'; ?>">
                        <?= e((string) $monthOption); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($weeksByMonth === []): ?>
        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">
            Nenhuma semana criada ainda. Gere a grade para comecar a montar a escala.
        </div>
    <?php else: ?>
        <div class="overflow-x-auto rounded-xl border border-slate-300 bg-white">
            <table class="min-w-[1080px] w-full border-collapse text-sm">
                <thead>
                    <tr class="bg-slate-900 text-white">
                        <th class="border border-slate-700 px-3 py-3 text-left">Mes</th>
                        <th class="border border-slate-700 px-3 py-3 text-left">Datas</th>
                        <th class="border border-slate-700 px-3 py-3 text-left">R3</th>
                        <th class="border border-slate-700 px-3 py-3 text-left">R2</th>
                        <th class="border border-slate-700 px-3 py-3 text-left">R1</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($weeksByMonth as $monthRef => $monthWeeks): ?>
                        <?php $monthRowspan = count($monthWeeks); ?>
                        <?php foreach ($monthWeeks as $index => $week): ?>
                            <tr class="align-top <?= $index % 2 === 0 ? 'bg-slate-50' : 'bg-white'; ?>">
                                <?php if ($index === 0): ?>
                                    <td rowspan="<?= (int) $monthRowspan; ?>" class="border border-slate-300 bg-sky-50 px-3 py-4 font-semibold text-sky-900"><?= e((string) $monthRef); ?></td>
                                <?php endif; ?>
                                <td class="border border-slate-300 px-3 py-3">
                                    <p class="font-semibold"><?= e(date('d', strtotime((string) $week['start_date'])) . ' a ' . date('d', strtotime((string) $week['end_date']))); ?></p>
                                    <p class="mt-1 text-xs text-slate-500"><?= e(date('d/m', strtotime((string) $week['start_date'])) . ' - ' . date('d/m', strtotime((string) $week['end_date']))); ?></p>
                                    <?php if ($canManageSchedule && $status !== 'archived'): ?>
                                        <form method="post" action="<?= route('escala-aluno/weeks/update'); ?>" class="mt-3 space-y-2 rounded-lg border border-slate-200 bg-white p-2">
                                            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                            <input type="hidden" name="week_id" value="<?= (int) $week['id']; ?>">
                                            <div class="grid gap-2 md:grid-cols-3">
                                                <input type="number" min="0" name="r3_slots" value="<?= (int) $week['r3_slots']; ?>" class="rounded border border-slate-200 px-2 py-1 text-xs" placeholder="R3">
                                                <input type="number" min="0" name="r2_slots" value="<?= (int) $week['r2_slots']; ?>" class="rounded border border-slate-200 px-2 py-1 text-xs" placeholder="R2">
                                                <input type="number" min="0" name="r1_slots" value="<?= (int) $week['r1_slots']; ?>" class="rounded border border-slate-200 px-2 py-1 text-xs" placeholder="R1">
                                            </div>
                                            <textarea name="notes" rows="2" class="w-full rounded border border-slate-200 px-2 py-1 text-xs" placeholder="Observacao da semana..."><?= e((string) ($week['notes'] ?? '')); ?></textarea>
                                            <button class="w-full rounded border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50">Salvar semana</button>
                                        </form>
                                    <?php elseif (!empty($week['notes'])): ?>
                                        <div class="mt-3 rounded-lg border border-slate-200 bg-white p-2 text-xs text-slate-600">
                                            <?= nl2br(e((string) $week['notes'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <?php foreach (['R3', 'R2', 'R1'] as $group): ?>
                                    <?php $slotLimit = (int) ($week[strtolower($group) . '_slots'] ?? 0); ?>
                                    <?php
                                    $studentPool = $eligibleByWeek[(int) $week['id']] ?? [];
                                    $eligibleOptions = array_values(array_filter($studentPool, static fn (array $studentOption): bool => (string) ($studentOption['residency_level'] ?? '') === $group && (bool) ($studentOption['is_eligible'] ?? false) && !(bool) ($studentOption['has_conflict'] ?? false)));
                                    $pendingOptions = array_values(array_filter($studentPool, static fn (array $studentOption): bool => (string) ($studentOption['residency_level'] ?? '') === $group && !(bool) ($studentOption['is_eligible'] ?? false)));
                                    $conflictOptions = array_values(array_filter($studentPool, static fn (array $studentOption): bool => (string) ($studentOption['residency_level'] ?? '') === $group && (bool) ($studentOption['has_conflict'] ?? false)));
                                    ?>
                                    <td class="border border-slate-300 px-3 py-3">
                                        <div class="space-y-2">
                                            <div class="flex items-center justify-between rounded-lg bg-slate-50 px-2 py-1 text-[11px] font-medium uppercase tracking-wide text-slate-500">
                                                <span><?= e($group); ?></span>
                                                <span><?= count($week['assignments'][$group] ?? []); ?>/<?= $slotLimit; ?> alocados</span>
                                            </div>
                                            <?php foreach ($week['assignments'][$group] ?? [] as $assignment): ?>
                                                <div class="flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-2 py-2">
                                                    <div>
                                                        <p class="text-sm font-semibold"><?= e((string) $assignment['student_name']); ?></p>
                                                        <p class="text-[11px] uppercase tracking-wide text-slate-400"><?= e((string) $assignment['residency_level_snapshot']); ?></p>
                                                    </div>
                                                    <?php if ($canManageSchedule && $status !== 'archived'): ?>
                                                        <form method="post" action="<?= route('escala-aluno/assignments/delete'); ?>">
                                                            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                                            <input type="hidden" name="week_id" value="<?= (int) $week['id']; ?>">
                                                            <input type="hidden" name="assignment_id" value="<?= (int) $assignment['id']; ?>">
                                                            <button class="rounded border border-rose-200 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50">Remover</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>

                                            <?php if ($canManageSchedule && $status !== 'archived' && count($week['assignments'][$group] ?? []) < $slotLimit): ?>
                                                <form method="post" action="<?= route('escala-aluno/assignments/store'); ?>" class="rounded-lg border border-dashed border-slate-300 bg-white p-2">
                                                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                                    <input type="hidden" name="week_id" value="<?= (int) $week['id']; ?>">
                                                    <input type="hidden" name="slot_group" value="<?= e($group); ?>">
                                                    <select name="student_id" required class="mb-2 w-full rounded border border-slate-200 px-2 py-1 text-xs">
                                                        <option value="">Adicionar <?= e($group); ?>...</option>
                                                        <?php foreach ($eligibleOptions as $studentOption): ?>
                                                            <option value="<?= (int) $studentOption['id']; ?>">
                                                                <?= e((string) $studentOption['full_name']); ?><?= !empty($studentOption['eligible_since']) ? ' - elegivel desde ' . e(date('d/m/Y', strtotime((string) $studentOption['eligible_since']))) : ''; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                        <?php if ($conflictOptions !== []): ?>
                                                            <option value="" disabled>------------------------------</option>
                                                            <option value="" disabled>Em conflito nesta semana</option>
                                                            <?php foreach ($conflictOptions as $studentOption): ?>
                                                                <option value="" disabled>
                                                                    <?= e((string) $studentOption['full_name']); ?> - conflito com <?= e((string) ($studentOption['conflict_schedule_title'] ?: 'outra escala')); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </select>
                                                    <button class="w-full rounded bg-cyan-600 px-2 py-1 text-xs font-semibold text-white hover:bg-cyan-700">Alocar</button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($slotLimit > 0 && count($week['assignments'][$group] ?? []) < $slotLimit && $eligibleOptions === []): ?>
                                                <div class="rounded-lg border border-amber-200 bg-amber-50 px-2 py-2 text-xs text-amber-800">
                                                    <?php if ($pendingOptions !== []): ?>
                                                        Nenhum <?= e($group); ?> elegivel nesta semana. <?= count($pendingOptions); ?> aluno(s) deste nivel ainda aguardam os 40 dias.
                                                    <?php else: ?>
                                                        Nenhum aluno <?= e($group); ?> disponivel para esta semana nesta unidade.
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($conflictOptions !== []): ?>
                                                <div class="rounded-lg border border-rose-200 bg-rose-50 px-2 py-2 text-xs text-rose-800">
                                                    <p class="font-semibold">Conflitos detectados nesta semana:</p>
                                                    <ul class="mt-1 space-y-1">
                                                        <?php foreach ($conflictOptions as $studentOption): ?>
                                                            <li>
                                                                <?= e((string) $studentOption['full_name']); ?>
                                                                em conflito com
                                                                <strong><?= e((string) ($studentOption['conflict_schedule_title'] ?: 'outra escala')); ?></strong>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($slotLimit === 0): ?>
                                                <p class="text-xs text-slate-400">Sem vagas configuradas.</p>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
