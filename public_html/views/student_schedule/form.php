<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold"><?= e($schedule ? 'Editar Escala' : 'Nova Escala'); ?></h2>
            <p class="text-sm text-slate-500">Configure a unidade, o período e deixe a grade pronta para montar os plantões.</p>
        </div>
        <a href="<?= route('escala-aluno'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Voltar</a>
    </div>

    <?php if (!$featureAvailable): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Modulo indisponivel no banco. Execute a migration <code>migrations/20260505_student_duty_schedule.sql</code>.
        </div>
    <?php else: ?>
        <form method="post" action="<?= e($action); ?>" class="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 lg:grid-cols-2">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">

            <label class="block">
                <span class="mb-1 block text-sm font-medium">Unidade / Hospital *</span>
                <select name="unit_id" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <option value="">Selecione...</option>
                    <?php foreach ($units as $unit): ?>
                        <?php if ((int) ($unit['is_active'] ?? 0) !== 1 && (string) ($schedule['unit_id'] ?? '') !== (string) $unit['id']) { continue; } ?>
                        <option value="<?= (int) $unit['id']; ?>" <?= (string) ($schedule['unit_id'] ?? '') === (string) $unit['id'] ? 'selected' : ''; ?>><?= e((string) $unit['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-medium">Título da escala *</span>
                <input type="text" name="title" required value="<?= e((string) ($schedule['title'] ?? '')); ?>" placeholder="Ex.: Escala de Plantões 2026 - 2027 - DF" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-medium">Data inicial *</span>
                <input type="date" name="start_date" required value="<?= e((string) ($schedule['start_date'] ?? '')); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-medium">Data final *</span>
                <input type="date" name="end_date" required value="<?= e((string) ($schedule['end_date'] ?? '')); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </label>

            <label class="block lg:col-span-2">
                <span class="mb-1 block text-sm font-medium">Observações</span>
                <textarea name="notes" rows="4" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><?= e((string) ($schedule['notes'] ?? '')); ?></textarea>
            </label>

            <div class="lg:col-span-2 flex justify-end gap-2">
                <a href="<?= route('escala-aluno'); ?>" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm hover:bg-slate-50">Cancelar</a>
                <button class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700">Salvar escala</button>
            </div>
        </form>
    <?php endif; ?>
</section>
