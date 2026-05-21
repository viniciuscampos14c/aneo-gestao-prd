<?php
$isProfessorView = is_professor();
$canManageSchedule = has_permission('student_schedule.manage') && !$isProfessorView;
?>
<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold">Escala Aluno</h2>
            <p class="text-sm text-slate-500"><?= $canManageSchedule ? 'Organize os plantoes praticos por unidade, semana e nivel de residencia.' : 'Consulte a grade de escalas e as unidades ja cadastradas.'; ?></p>
        </div>
        <?php if ($featureAvailable && $canManageSchedule): ?>
            <a href="<?= route('escala-aluno/create'); ?>" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Nova escala</a>
        <?php endif; ?>
    </div>

    <?php if (!$featureAvailable): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Modulo indisponivel no banco. Execute a migration <code>migrations/20260505_student_duty_schedule.sql</code>.
        </div>
    <?php else: ?>
        <div class="grid gap-6 xl:grid-cols-[1.1fr_1.9fr]">
            <div class="space-y-4">
                <?php if ($canManageSchedule): ?>
                    <form method="post" action="<?= route('escala-aluno/unit/store'); ?>" class="rounded-xl border border-slate-200 bg-white p-4">
                        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                        <div class="mb-3">
                            <h3 class="text-lg font-semibold">Unidades / Hospitais</h3>
                            <p class="text-xs text-slate-500">Cadastre a unidade onde o aluno realiza os plantoes.</p>
                        </div>
                        <div class="grid gap-3">
                            <input type="text" name="name" required placeholder="Nome da unidade" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <div class="grid gap-3 md:grid-cols-2">
                                <input type="text" name="city" placeholder="Cidade" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                <input type="text" name="state" placeholder="UF" maxlength="10" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            </div>
                            <button class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700">Salvar unidade</button>
                        </div>
                    </form>
                <?php endif; ?>

                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                                <th class="px-3 py-3">Unidade</th>
                                <th class="px-3 py-3">Alunos</th>
                                <th class="px-3 py-3">Status</th>
                                <?php if ($canManageSchedule): ?>
                                    <th class="px-3 py-3">Acao</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($units as $unit): ?>
                                <tr class="border-b border-slate-100">
                                    <td class="px-3 py-3">
                                        <p class="font-medium"><?= e((string) $unit['name']); ?></p>
                                        <p class="text-xs text-slate-500"><?= e(trim(((string) ($unit['city'] ?? '')) . ' ' . ((string) ($unit['state'] ?? '')))); ?></p>
                                    </td>
                                    <td class="px-3 py-3"><?= (int) ($unit['linked_students'] ?? 0); ?></td>
                                    <td class="px-3 py-3">
                                        <span class="rounded-full px-2 py-1 text-xs font-semibold <?= (int) ($unit['is_active'] ?? 0) === 1 ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'; ?>">
                                            <?= (int) ($unit['is_active'] ?? 0) === 1 ? 'Ativa' : 'Inativa'; ?>
                                        </span>
                                    </td>
                                    <?php if ($canManageSchedule): ?>
                                        <td class="px-3 py-3">
                                            <form method="post" action="<?= route('escala-aluno/unit/toggle'); ?>">
                                                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                                <input type="hidden" name="id" value="<?= (int) $unit['id']; ?>">
                                                <input type="hidden" name="active" value="<?= (int) ($unit['is_active'] ?? 0) === 1 ? 0 : 1; ?>">
                                                <button class="rounded border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50"><?= (int) ($unit['is_active'] ?? 0) === 1 ? 'Inativar' : 'Ativar'; ?></button>
                                            </form>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($units === []): ?>
                                <tr><td colspan="<?= $canManageSchedule ? '4' : '3'; ?>" class="px-3 py-6 text-center text-slate-500">Nenhuma unidade cadastrada.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="space-y-4">
                <form method="get" action="<?= route('escala-aluno'); ?>" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-4">
                    <input type="hidden" name="route" value="escala-aluno">
                    <input type="text" name="q" value="<?= e((string) ($filters['q'] ?? '')); ?>" placeholder="Buscar titulo ou unidade..." class="rounded-lg border border-slate-200 px-3 py-2 text-sm md:col-span-2">
                    <select name="unit_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option value="">Todas as unidades</option>
                        <?php foreach ($units as $unit): ?>
                            <option value="<?= (int) $unit['id']; ?>" <?= (string) ($filters['unit_id'] ?? '') === (string) $unit['id'] ? 'selected' : ''; ?>><?= e((string) $unit['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option value="">Todos os status</option>
                        <?php foreach (['draft' => 'Rascunho', 'published' => 'Publicada', 'archived' => 'Arquivada'] as $statusKey => $statusLabel): ?>
                            <option value="<?= e($statusKey); ?>" <?= (string) ($filters['status'] ?? '') === $statusKey ? 'selected' : ''; ?>><?= e($statusLabel); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="md:col-span-4">
                        <button class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm hover:bg-slate-50">Filtrar</button>
                    </div>
                </form>

                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                                <th class="px-3 py-3">Escala</th>
                                <th class="px-3 py-3">Periodo</th>
                                <th class="px-3 py-3">Semanas</th>
                                <th class="px-3 py-3">Status</th>
                                <th class="px-3 py-3">Acoes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <?php
                                $status = (string) ($row['status'] ?? 'draft');
                                $statusClass = match ($status) {
                                    'published' => 'bg-emerald-100 text-emerald-700',
                                    'archived' => 'border border-slate-500/40 bg-slate-700 text-slate-100',
                                    default => 'bg-amber-100 text-amber-700',
                                };
                                ?>
                                <tr class="border-b border-slate-100">
                                    <td class="px-3 py-3">
                                        <p class="font-medium"><?= e((string) $row['title']); ?></p>
                                        <p class="text-xs text-slate-500"><?= e((string) $row['unit_name']); ?></p>
                                    </td>
                                    <td class="px-3 py-3"><?= e(date('d/m/Y', strtotime((string) $row['start_date']))); ?> ate <?= e(date('d/m/Y', strtotime((string) $row['end_date']))); ?></td>
                                    <td class="px-3 py-3"><?= (int) ($row['total_weeks'] ?? 0); ?></td>
                                    <td class="px-3 py-3"><span class="rounded-full px-2 py-1 text-xs font-semibold <?= $statusClass; ?>"><?= e($status === 'published' ? 'Publicada' : ($status === 'archived' ? 'Arquivada' : 'Rascunho')); ?></span></td>
                                    <td class="px-3 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <a href="<?= route('escala-aluno/show&id=' . (int) $row['id']); ?>" class="rounded border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50">Abrir</a>
                                            <?php if ($canManageSchedule): ?>
                                                <a href="<?= route('escala-aluno/edit&id=' . (int) $row['id']); ?>" class="rounded border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50">Editar</a>
                                            <?php endif; ?>
                                            <a href="<?= route('escala-aluno/export&id=' . (int) $row['id']); ?>" target="_blank" class="rounded border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50">Imprimir</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($rows === []): ?>
                                <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">Nenhuma escala cadastrada.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>
