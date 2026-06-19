<?php
$isEditing = is_array($editing ?? null);
$formAction = $isEditing ? route('practice-units/update') : route('practice-units/store');
$formTitle = $isEditing ? 'Editar unidade' : 'Nova unidade';
$formButton = $isEditing ? 'Salvar alteracoes' : 'Salvar unidade';
?>
<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold">Unidades / Hospitais</h2>
            <p class="text-sm text-slate-500">Centralize aqui o cadastro e a manutencao das unidades usadas no cadastro de alunos e na Escala Aluno.</p>
        </div>
        <a href="<?= route('escala-aluno'); ?>" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Voltar para Escala</a>
    </div>

    <?php if (!$featureAvailable): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Módulo de unidades/hospitais indisponivel no banco. Execute a migration <code>migrations/20260505_student_duty_schedule.sql</code>.
        </div>
    <?php else: ?>
        <div class="grid gap-6 xl:grid-cols-[1.05fr_1.95fr]">
            <form method="post" action="<?= $formAction; ?>" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                <?php if ($isEditing): ?>
                    <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0); ?>">
                <?php endif; ?>

                <div class="mb-4">
                    <h3 class="text-lg font-semibold"><?= e($formTitle); ?></h3>
                    <p class="text-xs text-slate-500"><?= $isEditing ? 'Atualize os dados operacionais da unidade sem afetar os alunos vinculados.' : 'Cadastre novas unidades para uso em alunos e escalas.'; ?></p>
                </div>

                <div class="grid gap-4">
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium">Nome da unidade *</span>
                        <input type="text" name="name" required value="<?= e((string) ($editing['name'] ?? '')); ?>" placeholder="Ex.: Hospital Regional de Barueri" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    </label>

                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium">Cidade</span>
                            <input type="text" name="city" value="<?= e((string) ($editing['city'] ?? '')); ?>" placeholder="Cidade" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium">UF</span>
                            <input type="text" name="state" maxlength="10" value="<?= e((string) ($editing['state'] ?? '')); ?>" placeholder="UF" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm uppercase">
                        </label>
                    </div>

                    <div class="flex flex-wrap gap-3 pt-2">
                        <button class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700"><?= e($formButton); ?></button>
                        <?php if ($isEditing): ?>
                            <a href="<?= route('practice-units'); ?>" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar edicao</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h3 class="text-lg font-semibold">Unidades cadastradas</h3>
                    <p class="text-xs text-slate-500">Ative, inative e ajuste os dados das unidades sem sair do fluxo de cadastro.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                                <th class="px-4 py-3">Unidade</th>
                                <th class="px-4 py-3">Localidade</th>
                                <th class="px-4 py-3">Alunos</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Acoes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <?php $isActive = (int) ($row['is_active'] ?? 0) === 1; ?>
                                <tr class="border-b border-slate-100 align-top">
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-slate-900"><?= e((string) ($row['name'] ?? '')); ?></p>
                                        <p class="text-xs text-slate-500">ID #<?= (int) ($row['id'] ?? 0); ?></p>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">
                                        <?= e(trim((string) (($row['city'] ?? '') . ((trim((string) ($row['city'] ?? '')) !== '' && trim((string) ($row['state'] ?? '')) !== '') ? ' / ' : '') . ($row['state'] ?? '')))); ?>
                                    </td>
                                    <td class="px-4 py-3"><?= (int) ($row['linked_students'] ?? 0); ?></td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2 py-1 text-xs font-semibold <?= $isActive ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'; ?>">
                                            <?= $isActive ? 'Ativa' : 'Inativa'; ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <a href="<?= route('practice-units&edit=' . (int) ($row['id'] ?? 0)); ?>" class="rounded border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50">Editar</a>
                                            <form method="post" action="<?= route('practice-units/toggle'); ?>">
                                                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                                <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0); ?>">
                                                <input type="hidden" name="active" value="<?= $isActive ? '0' : '1'; ?>">
                                                <button class="rounded border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50"><?= $isActive ? 'Inativar' : 'Ativar'; ?></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($rows === []): ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-slate-500">Nenhuma unidade cadastrada.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>
