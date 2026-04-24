<?php
$paymentMethods = is_array($paymentMethods ?? null) ? $paymentMethods : [];
$paymentMethodsAvailable = !empty($paymentMethodsAvailable);
?>
<section class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-semibold">Nova Fatura</h2>
            <p class="text-sm text-slate-500">Crie fatura avulsa ou recorrente por aluno.</p>
        </div>
        <a href="<?= route('finance/invoices'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Voltar</a>
    </div>

    <form method="post" action="<?= e($action); ?>" class="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 lg:grid-cols-2">
        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">

        <?php if (!$paymentMethodsAvailable): ?>
            <div class="lg:col-span-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700">
                O cadastro de formas de pagamento ainda nao esta disponivel neste banco. Execute a migration
                <code>migrations/20260424_finance_payment_methods.sql</code>.
            </div>
        <?php endif; ?>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Aluno *</span>
            <select name="student_id" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Selecione...</option>
                <?php foreach ($students as $student): ?>
                    <option value="<?= (int) $student['id']; ?>"><?= e($student['full_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Forma de pagamento *</span>
            <select name="payment_method_id" <?= $paymentMethodsAvailable ? 'required' : ''; ?> class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value=""><?= $paymentMethodsAvailable ? 'Selecione...' : 'Nao disponivel'; ?></option>
                <?php foreach ($paymentMethods as $method): ?>
                    <?php
                    $methodId = (int) ($method['id'] ?? 0);
                    $methodName = trim((string) ($method['name'] ?? ''));
                    if ($methodName === '') {
                        continue;
                    }
                    ?>
                    <option value="<?= $methodId > 0 ? $methodId : ''; ?>">
                        <?= e($methodName); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Vencimento *</span>
            <input type="date" name="due_date" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Quantia *</span>
            <input type="text" name="amount" required placeholder="0,00" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Imposto</span>
            <input type="text" name="tax_amount" placeholder="0,00" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Status</span>
            <select name="status" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="open">Em aberto</option>
                <option value="draft">Rascunho</option>
            </select>
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Projeto</span>
            <input type="text" name="project_name" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Link do boleto</span>
            <input type="url" name="boleto_url" placeholder="https://..." class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Tags</span>
            <input type="text" name="tags" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="mensalidade, acordo...">
        </label>

        <div class="space-y-2 rounded-lg border border-slate-200 p-3 text-sm">
            <label class="flex items-center gap-2"><input type="checkbox" name="is_recurring" value="1"> Fatura recorrente</label>
            <select name="recurrence_interval" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="monthly">Mensal</option>
                <option value="quarterly">Trimestral</option>
                <option value="yearly">Anual</option>
            </select>
        </div>

        <div class="lg:col-span-2 flex justify-end gap-2">
            <a href="<?= route('finance/invoices'); ?>" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm hover:bg-slate-50">Cancelar</a>
            <button class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700">Salvar Fatura</button>
        </div>
    </form>
</section>
