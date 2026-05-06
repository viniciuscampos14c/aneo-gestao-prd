<?php
$types = is_array($types ?? null) ? $types : [];
$batches = is_array($batches ?? null) ? $batches : [];
$selectedBatch = $selectedBatch ?? null;
$selectedRows = is_array($selectedRows ?? null) ? $selectedRows : [];
$canUpload = (bool) ($canUpload ?? false);
$canConfirm = (bool) ($canConfirm ?? false);

$typeLabel = function (string $type) use ($types): string {
    return (string) ($types[$type]['label'] ?? $type);
};

$statusLabel = function (string $status): string {
    return match ($status) {
        'uploaded' => 'Enviado',
        'validated' => 'Validado',
        'completed' => 'Concluido',
        'failed' => 'Falhou',
        default => ucfirst($status),
    };
};

$statusClass = function (string $status): string {
    return match ($status) {
        'completed' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'validated' => 'border-cyan-200 bg-cyan-50 text-cyan-700',
        'failed' => 'border-rose-200 bg-rose-50 text-rose-700',
        default => 'border-slate-200 bg-slate-50 text-slate-600',
    };
};

$decode = function ($json): array {
    $decoded = json_decode((string) $json, true);
    return is_array($decoded) ? $decoded : [];
};

$selectedBatchId = (int) ($selectedBatch['id'] ?? 0);
?>
<section class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-400">Cadastro</p>
            <h2 class="text-2xl font-semibold">Importacao de Dados</h2>
            <p class="text-sm text-slate-500">Suba dados mestres por planilha, valide as linhas e confirme a carga com seguranca.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <?php foreach ($types as $typeKey => $type): ?>
                <a href="<?= route('data-imports/template&type=' . urlencode((string) $typeKey)); ?>" class="rounded-lg border border-cyan-200 bg-cyan-50 px-3 py-2 text-sm font-semibold text-cyan-700 hover:bg-cyan-100">
                    Modelo <?= e((string) $type['label']); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <?php foreach ($types as $typeKey => $type): ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-800"><?= e((string) $type['label']); ?></h3>
                        <p class="mt-1 text-sm text-slate-500"><?= e((string) $type['description']); ?></p>
                    </div>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">CSV</span>
                </div>
                <p class="mt-4 text-xs text-slate-500">
                    Fluxo: baixar modelo, preencher, enviar, revisar erros e confirmar a carga.
                </p>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold text-slate-800">Nova importacao</h3>
                <p class="text-sm text-slate-500">Nesta fase inicial, o sistema aceita CSV separado por ponto e virgula.</p>
            </div>
            <?php if (!$canUpload): ?>
                <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">Sem permissao para enviar</span>
            <?php endif; ?>
        </div>

        <form method="post" action="<?= route('data-imports/upload'); ?>" enctype="multipart/form-data" class="grid gap-3 lg:grid-cols-[260px_1fr_auto]">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
            <label class="block">
                <span class="mb-1 block text-sm font-medium text-slate-700">Tipo de carga</span>
                <select name="import_type" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" <?= $canUpload ? '' : 'disabled'; ?>>
                    <?php foreach ($types as $typeKey => $type): ?>
                        <option value="<?= e((string) $typeKey); ?>"><?= e((string) $type['label']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="block">
                <span class="mb-1 block text-sm font-medium text-slate-700">Arquivo CSV</span>
                <input type="file" name="csv_file" accept=".csv,text/csv" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" <?= $canUpload ? '' : 'disabled'; ?>>
            </label>
            <div class="flex items-end">
                <button class="w-full rounded-lg bg-slate-900 px-5 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50" <?= $canUpload ? '' : 'disabled'; ?>>
                    Validar arquivo
                </button>
            </div>
        </form>

        <div class="mt-4 rounded-xl border border-cyan-100 bg-cyan-50/70 px-4 py-3 text-xs leading-relaxed text-cyan-800">
            A pre-validacao tambem confere duplicidades dentro da propria planilha: alunos com mesmo email ou RA na mesma filial serao bloqueados, usuarios com mesmo email ou login tambem, unidades com mesmo nome tambem, e aulas repetidas no mesmo curso/modulo nao poderao ser confirmadas.
        </div>
    </div>

    <?php if ($selectedBatch): ?>
        <?php
        $canConfirmBatch = $canConfirm
            && (string) ($selectedBatch['status'] ?? '') === 'validated'
            && (int) ($selectedBatch['error_rows'] ?? 0) === 0
            && (int) ($selectedBatch['valid_rows'] ?? 0) > 0;
        ?>
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-lg font-semibold text-slate-800">Lote #<?= (int) $selectedBatch['id']; ?></h3>
                            <span class="rounded-full border px-3 py-1 text-xs font-semibold <?= e($statusClass((string) $selectedBatch['status'])); ?>">
                                <?= e($statusLabel((string) $selectedBatch['status'])); ?>
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-slate-500">
                            <?= e($typeLabel((string) $selectedBatch['import_type'])); ?> -
                            <?= e((string) ($selectedBatch['original_filename'] ?? 'arquivo.csv')); ?>
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="<?= route('data-imports'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Limpar selecao</a>
                        <?php if ($canConfirmBatch): ?>
                            <form method="post" action="<?= route('data-imports/confirm'); ?>" onsubmit="return confirm('Confirmar importacao deste lote?');">
                                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                <input type="hidden" name="batch_id" value="<?= (int) $selectedBatch['id']; ?>">
                                <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Confirmar carga</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-4">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Linhas</p>
                        <p class="mt-1 text-xl font-semibold text-slate-800"><?= (int) $selectedBatch['total_rows']; ?></p>
                    </div>
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-emerald-700">Validas</p>
                        <p class="mt-1 text-xl font-semibold text-emerald-800"><?= (int) $selectedBatch['valid_rows']; ?></p>
                    </div>
                    <div class="rounded-xl border border-rose-200 bg-rose-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-rose-700">Com erro</p>
                        <p class="mt-1 text-xl font-semibold text-rose-800"><?= (int) $selectedBatch['error_rows']; ?></p>
                    </div>
                    <div class="rounded-xl border border-cyan-200 bg-cyan-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-cyan-700">Gravados</p>
                        <p class="mt-1 text-xl font-semibold text-cyan-800"><?= (int) $selectedBatch['created_count'] + (int) $selectedBatch['updated_count']; ?></p>
                    </div>
                </div>

                <?php if ((int) $selectedBatch['error_rows'] > 0): ?>
                    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        Existem linhas com erro. Corrija a planilha e envie novamente antes de confirmar a carga.
                    </div>
                <?php elseif ((string) $selectedBatch['status'] === 'validated'): ?>
                    <div class="mt-4 rounded-xl border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm text-cyan-800">
                        Nenhum dado mestre foi gravado ainda. Revise a previa e clique em Confirmar carga.
                    </div>
                <?php endif; ?>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Linha</th>
                            <th class="px-4 py-3 text-left">Acao</th>
                            <th class="px-4 py-3 text-left">Chave</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">Mensagens</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($selectedRows as $row): ?>
                            <?php
                            $errors = $decode($row['errors_json'] ?? '');
                            $warnings = $decode($row['warnings_json'] ?? '');
                            $rowStatus = (string) ($row['status'] ?? '');
                            $pill = match ($rowStatus) {
                                'imported' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                'valid' => 'border-cyan-200 bg-cyan-50 text-cyan-700',
                                'error' => 'border-rose-200 bg-rose-50 text-rose-700',
                                default => 'border-slate-200 bg-slate-50 text-slate-600',
                            };
                            ?>
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-4 py-3 font-semibold text-slate-700"><?= (int) $row['row_number']; ?></td>
                                <td class="px-4 py-3"><?= e((string) $row['action']); ?></td>
                                <td class="px-4 py-3 text-slate-500"><?= e((string) ($row['source_key'] ?? '-')); ?></td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full border px-2.5 py-1 text-xs font-semibold <?= e($pill); ?>"><?= e($rowStatus); ?></span>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if ($errors === [] && $warnings === []): ?>
                                        <span class="text-slate-400">Sem mensagens.</span>
                                    <?php endif; ?>
                                    <?php foreach ($errors as $message): ?>
                                        <p class="text-xs font-medium text-rose-700"><?= e((string) $message); ?></p>
                                    <?php endforeach; ?>
                                    <?php foreach ($warnings as $message): ?>
                                        <p class="text-xs font-medium text-amber-700"><?= e((string) $message); ?></p>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($selectedRows === []): ?>
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">Nenhuma linha registrada neste lote.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4">
            <h3 class="text-lg font-semibold text-slate-800">Historico de importacoes</h3>
            <p class="text-sm text-slate-500">Acompanhe os lotes enviados e reabra a previa quando necessario.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Lote</th>
                        <th class="px-4 py-3 text-left">Tipo</th>
                        <th class="px-4 py-3 text-left">Arquivo</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Resumo</th>
                        <th class="px-4 py-3 text-left">Data</th>
                        <th class="px-4 py-3 text-right">Acoes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($batches as $batch): ?>
                        <tr class="<?= (int) $batch['id'] === $selectedBatchId ? 'bg-cyan-50/60' : 'hover:bg-slate-50/70'; ?>">
                            <td class="px-4 py-3 font-semibold text-slate-800">#<?= (int) $batch['id']; ?></td>
                            <td class="px-4 py-3"><?= e($typeLabel((string) $batch['import_type'])); ?></td>
                            <td class="max-w-xs truncate px-4 py-3 text-slate-500"><?= e((string) ($batch['original_filename'] ?? '-')); ?></td>
                            <td class="px-4 py-3">
                                <span class="rounded-full border px-2.5 py-1 text-xs font-semibold <?= e($statusClass((string) $batch['status'])); ?>">
                                    <?= e($statusLabel((string) $batch['status'])); ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500">
                                <?= (int) $batch['valid_rows']; ?> validas /
                                <?= (int) $batch['error_rows']; ?> erros /
                                <?= (int) $batch['created_count']; ?> criados /
                                <?= (int) $batch['updated_count']; ?> atualizados
                            </td>
                            <td class="px-4 py-3 text-slate-500"><?= e((string) $batch['created_at']); ?></td>
                            <td class="px-4 py-3 text-right">
                                <a href="<?= route('data-imports&batch_id=' . (int) $batch['id']); ?>" class="rounded border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold hover:bg-slate-50">Abrir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($batches === []): ?>
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">Nenhuma importacao registrada ainda.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
