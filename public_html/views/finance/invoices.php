<?php
$canInvoiceCreate = has_permission('finance.invoice.create');
$canInvoiceDelete = has_permission('finance.invoice.delete');
$canInvoiceSettle = has_permission('finance.invoice.settle');
$canInvoiceNfe = has_permission('finance.invoice.nfe');
$canInvoiceExport = has_permission('finance.invoice.export');
$canInvoiceRecurrence = has_permission('finance.invoice.recurrence');
$canInvoiceWhatsapp = has_permission('finance.invoice.whatsapp');
$canBoletoGenerate = has_permission('finance.invoice.boleto.generate');
$canBoletoSync = has_permission('finance.invoice.boleto.sync');
$canChatOpen = has_permission('chat.open');
$invoicePaymentMethodsAvailable = !empty($invoicePaymentMethodsAvailable);
?>
<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold">Faturas</h2>
            <p class="text-sm text-slate-500">Gestao de cobranca, inadimplencia, baixas e emissao fiscal.</p>
        </div>
        <div class="flex gap-2">
            <?php if ($canInvoiceCreate): ?>
                <a href="<?= route('finance/invoices/create'); ?>" class="rounded-lg bg-cyan-600 px-3 py-2 text-sm font-semibold text-white hover:bg-cyan-700">Criar nova fatura</a>
            <?php endif; ?>
            <a href="<?= route('finance/payments'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Pagamentos em lote</a>
            <a href="<?= route('finance/reports'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Relatorios</a>
            <a href="<?= route('finance/payment-methods'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Formas de pagamento</a>
            <?php if ($canInvoiceExport): ?>
                <a href="<?= route('finance/invoices/export&q=' . urlencode($filters['q'])); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Exportar</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$invoicePaymentMethodsAvailable): ?>
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700">
            Formas de pagamento ainda nao disponiveis no banco. Execute a migration
            <code>migrations/20260424_finance_payment_methods.sql</code>.
        </div>
    <?php endif; ?>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <article class="finance-card-open rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs uppercase text-slate-500">Em aberto</p><p class="mt-2 text-2xl font-semibold"><?= (int) $stats['open']; ?></p></article>
        <article class="finance-card-paid rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs uppercase text-slate-500">Pago</p><p class="mt-2 text-2xl font-semibold"><?= (int) $stats['paid']; ?></p></article>
        <article class="finance-card-partial rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs uppercase text-slate-500">Parcial</p><p class="mt-2 text-2xl font-semibold"><?= (int) $stats['partial']; ?></p></article>
        <article class="finance-card-overdue rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs uppercase text-slate-500">Vencido</p><p class="mt-2 text-2xl font-semibold"><?= (int) $stats['overdue']; ?></p></article>
        <article class="finance-card-draft rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs uppercase text-slate-500">Rascunho</p><p class="mt-2 text-2xl font-semibold"><?= (int) $stats['draft']; ?></p></article>
    </div>

    <div class="grid gap-4 sm:grid-cols-3 xl:grid-cols-5">
        <article class="finance-card-paid-value rounded-xl border border-emerald-200 bg-emerald-50 p-4"><p class="text-xs uppercase text-emerald-700">Faturas pagas (R$)</p><p class="mt-2 text-xl font-semibold text-emerald-700"><?= e(format_currency($stats['paid_value'])); ?></p></article>
        <article class="finance-card-overdue-value rounded-xl border border-rose-200 bg-rose-50 p-4"><p class="text-xs uppercase text-rose-700">Faturas vencidas (R$)</p><p class="mt-2 text-xl font-semibold text-rose-700"><?= e(format_currency($stats['overdue_value'])); ?></p></article>
        <article class="finance-card-pending-value rounded-xl border border-amber-200 bg-amber-50 p-4"><p class="text-xs uppercase text-amber-700">Faturas pendentes (R$)</p><p class="mt-2 text-xl font-semibold text-amber-700"><?= e(format_currency($stats['pending_value'])); ?></p></article>
        <article class="finance-card-settled rounded-xl border border-cyan-200 bg-cyan-50 p-4"><p class="text-xs uppercase text-cyan-700">Baixadas hoje</p><p class="mt-2 text-xl font-semibold text-cyan-700"><?= (int) ($stats['settled_today'] ?? 0); ?></p></article>
        <article class="finance-card-nfe rounded-xl border border-indigo-200 bg-indigo-50 p-4"><p class="text-xs uppercase text-indigo-700">NF-e emitidas / pendentes</p><p class="mt-2 text-xl font-semibold text-indigo-700"><?= (int) ($stats['nfe_issued'] ?? 0); ?> / <?= (int) ($stats['nfe_pending'] ?? 0); ?></p></article>
    </div>

    <div class="grid gap-4 rounded-xl border border-slate-200 bg-white p-4 lg:grid-cols-[1fr_auto]">
        <form method="get" action="index.php" class="grid gap-3 md:grid-cols-4">
            <input type="hidden" name="route" value="finance/invoices">
            <input type="text" name="q" value="<?= e($filters['q']); ?>" placeholder="Buscar faturas..." class="rounded-lg border border-slate-200 px-3 py-2 text-sm">

            <select name="status" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Todos os status</option>
                <?php foreach (['draft' => 'Rascunho', 'open' => 'Em aberto', 'partial' => 'Parcial', 'paid' => 'Pago', 'overdue' => 'Vencido'] as $k => $v): ?>
                    <option value="<?= $k; ?>" <?= $filters['status'] === $k ? 'selected' : ''; ?>><?= $v; ?></option>
                <?php endforeach; ?>
            </select>

            <select name="student_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Todos os alunos</option>
                <?php foreach ($students as $student): ?>
                    <option value="<?= (int) $student['id']; ?>" <?= (string) $filters['student_id'] === (string) $student['id'] ? 'selected' : ''; ?>><?= e($student['full_name']); ?></option>
                <?php endforeach; ?>
            </select>

            <select name="per_page" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <?php foreach ($paginationOptions as $opt): ?>
                    <option value="<?= (int) $opt; ?>" <?= (int) $meta['per_page'] === (int) $opt ? 'selected' : ''; ?>><?= (int) $opt; ?>/pagina</option>
                <?php endforeach; ?>
            </select>

            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrar</button>
        </form>

        <?php if ($canInvoiceRecurrence): ?>
            <form method="post" action="<?= route('finance/invoices/recurring'); ?>" class="flex items-center gap-2">
                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                <input type="month" name="reference_month" value="<?= date('Y-m'); ?>" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <button class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Gerar Recorrentes</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="finance-table-wrap overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="finance-table min-w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-3">Numero</th>
                    <th class="px-3 py-3">Aluno</th>
                    <th class="px-3 py-3">Vencimento</th>
                    <th class="px-3 py-3">Quantia</th>
                    <th class="px-3 py-3">Forma</th>
                    <th class="px-3 py-3">Status</th>
                    <th class="px-3 py-3">Recebimento</th>
                    <th class="px-3 py-3">Boleto API</th>
                    <th class="px-3 py-3">NF-e</th>
                    <th class="px-3 py-3">Criacao</th>
                    <th class="px-3 py-3">Imposto</th>
                    <th class="px-3 py-3">Tags</th>
                    <th class="px-3 py-3">Projeto</th>
                    <th class="px-3 py-3">Acoes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $boletoLink = $row['boleto_url'] ?: ($row['bank_slip_url'] ?? '') ?: ($row['boleto_pdf_url'] ?? '');
                    $boletoStatus = (string) ($row['boleto_status'] ?? '');
                    $boletoStatusLabel = match ($boletoStatus) {
                        'issued', 'registered' => 'Emitido',
                        'processing' => 'Processando',
                        'paid', 'received' => 'Pago',
                        'overdue' => 'Vencido',
                        'cancelled' => 'Cancelado',
                        'failed' => 'Falhou',
                        'pending' => 'Pendente',
                        default => 'Nao gerado',
                    };
                    $boletoStatusColor = match ($boletoStatus) {
                        'issued', 'registered' => 'bg-emerald-100 text-emerald-700',
                        'processing', 'pending' => 'bg-amber-100 text-amber-700',
                        'paid', 'received' => 'bg-cyan-100 text-cyan-700',
                        'overdue', 'failed', 'cancelled' => 'bg-rose-100 text-rose-700',
                        default => 'bg-slate-100 text-slate-700',
                    };
                    $boletoToneClass = match ($boletoStatus) {
                        'issued', 'registered' => 'finance-boleto-issued',
                        'processing', 'pending' => 'finance-boleto-pending',
                        'paid', 'received' => 'finance-boleto-paid',
                        'overdue', 'failed', 'cancelled' => 'finance-boleto-overdue',
                        default => 'finance-boleto-default',
                    };
                    $paymentMethodName = trim((string) ($row['payment_method_name'] ?? ''));
                    $paymentMethodMode = trim((string) ($row['payment_method_mode'] ?? ''));
                    $paymentMethodLabel = $paymentMethodName !== '' ? $paymentMethodName : 'Nao definido';
                    $canBoletoAutomation = !$invoicePaymentMethodsAvailable || $paymentMethodName === '' || $paymentMethodMode === 'integrated';
                    ?>
                    <tr class="finance-row border-b border-slate-100 hover:bg-slate-50 align-top">
                        <td class="px-3 py-3 font-medium"><?= e($row['invoice_number']); ?></td>
                        <td class="px-3 py-3"><?= e($row['student_name']); ?></td>
                        <td class="px-3 py-3"><?= e($row['due_date']); ?></td>
                        <td class="px-3 py-3"><?= e(format_currency($row['amount'])); ?></td>
                        <td class="px-3 py-3">
                            <span class="rounded-full px-2 py-1 text-xs font-semibold <?= $paymentMethodMode === 'integrated' ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-700'; ?>">
                                <?= e($paymentMethodLabel); ?>
                            </span>
                        </td>
                        <td class="px-3 py-3">
                            <?php
                            $statusColor = match ($row['status']) {
                                'paid' => 'bg-emerald-100 text-emerald-700',
                                'partial' => 'bg-amber-100 text-amber-700',
                                'overdue' => 'bg-rose-100 text-rose-700',
                                'draft' => 'bg-slate-100 text-slate-700',
                                default => 'bg-blue-100 text-blue-700',
                            };
                            $statusToneClass = match ($row['status']) {
                                'paid' => 'finance-status-paid',
                                'partial' => 'finance-status-partial',
                                'overdue' => 'finance-status-overdue',
                                'draft' => 'finance-status-draft',
                                default => 'finance-status-open',
                            };
                            $statusLabel = invoice_status_label((string) $row['status']);
                            ?>
                            <span class="finance-status-pill rounded-full px-2 py-1 text-xs font-semibold <?= $statusColor; ?> <?= $statusToneClass; ?>"><?= e($statusLabel); ?></span>
                        </td>

                        <td class="px-3 py-3">
                            <?php if ($row['status'] === 'paid'): ?>
                                <div class="finance-paid-box rounded-lg border border-emerald-200 bg-emerald-50 p-2 text-xs">
                                    <p class="font-semibold text-emerald-700">Conta baixada</p>
                                    <p class="text-emerald-700">Pago em: <?= e($row['paid_at'] ?: 'Sem data'); ?></p>
                                </div>
                            <?php else: ?>
                                <?php if ($canInvoiceSettle): ?>
                                    <form method="post" action="<?= route('finance/invoices/settle'); ?>" class="space-y-1" onsubmit="return confirm('Confirmar baixa total desta fatura?');">
                                        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                        <input type="hidden" name="invoice_id" value="<?= (int) $row['id']; ?>">
                                        <input type="hidden" name="method" value="<?= e($paymentMethodName !== '' ? $paymentMethodName : 'PIX'); ?>">
                                        <input type="hidden" name="payment_method_id" value="<?= (int) ($row['payment_method_id'] ?? 0); ?>">
                                        <input type="hidden" name="paid_at" value="<?= date('Y-m-d'); ?>">
                                        <input type="hidden" name="notes" value="Baixa manual pelo modulo de contas a receber.">
                                        <button class="finance-btn finance-btn-settle rounded-lg border border-cyan-200 bg-cyan-50 px-2 py-1 text-xs font-semibold text-cyan-700 hover:bg-cyan-100">Efetuar baixa</button>
                                        <p class="finance-helper-text text-[11px] text-slate-500">Baixa total do saldo em aberto.</p>
                                    </form>
                                <?php else: ?>
                                    <p class="text-xs text-slate-400">Sem permissao para baixa.</p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>

                        <td class="px-3 py-3">
                            <?php if (!$canBoletoAutomation): ?>
                                <p class="text-xs text-slate-400">Forma manual (sem automacao de boleto).</p>
                            <?php elseif (empty($boletosAvailable)): ?>
                                <p class="text-xs text-slate-400">Estrutura de boleto nao instalada.</p>
                            <?php else: ?>
                                <div class="space-y-1">
                                    <span class="finance-boleto-pill rounded-full px-2 py-1 text-xs font-semibold <?= $boletoStatusColor; ?> <?= $boletoToneClass; ?>"><?= e($boletoStatusLabel); ?></span>

                                    <?php if ($boletoLink): ?>
                                        <div>
                                            <a target="_blank" rel="noopener" href="<?= e($boletoLink); ?>" class="finance-link text-xs text-indigo-700 underline">Abrir boleto</a>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($row['boleto_digitable_line'])): ?>
                                        <p class="max-w-[240px] break-words text-[11px] text-slate-500"><?= e($row['boleto_digitable_line']); ?></p>
                                    <?php endif; ?>

                                    <?php if ($canBoletoGenerate && $row['status'] !== 'paid'): ?>
                                        <form method="post" action="<?= route('finance/invoices/boleto-generate'); ?>">
                                            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                            <input type="hidden" name="invoice_id" value="<?= (int) $row['id']; ?>">
                                            <button class="finance-btn finance-btn-boleto rounded border border-blue-200 bg-blue-50 px-2 py-1 text-xs text-blue-700 hover:bg-blue-100">
                                                <?= $row['boleto_id'] ? 'Regerar boleto' : 'Gerar boleto API'; ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($canBoletoSync && !empty($row['boleto_id'])): ?>
                                        <form method="post" action="<?= route('finance/invoices/boleto-sync'); ?>">
                                            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                            <input type="hidden" name="invoice_id" value="<?= (int) $row['id']; ?>">
                                            <button class="finance-btn finance-btn-sync rounded border border-slate-200 bg-white px-2 py-1 text-xs hover:bg-slate-50">Sincronizar status</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if (!empty($row['boleto_error_message'])): ?>
                                        <p class="text-[11px] text-rose-600"><?= e($row['boleto_error_message']); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <td class="px-3 py-3">
                            <?php
                            $fiscalStatus = $row['fiscal_status'] ?? null;
                            $fiscalStatusLabel = match ($fiscalStatus) {
                                'issued' => 'Emitida',
                                'processing' => 'Processando',
                                'failed' => 'Falhou',
                                'pending' => 'Pendente',
                                default => 'Nao solicitada',
                            };
                            ?>
                            <?php if ($row['status'] !== 'paid'): ?>
                                <p class="text-xs text-slate-400">Disponivel apos pagamento.</p>
                            <?php else: ?>
                                <?php if ($fiscalStatus === 'issued'): ?>
                                    <div class="finance-nfe-issued rounded-lg border border-indigo-200 bg-indigo-50 p-2 text-xs text-indigo-700">
                                        <p class="font-semibold">NF-e emitida</p>
                                        <p>Numero: <?= e($row['fiscal_number'] ?: 'N/D'); ?></p>
                                    </div>
                                <?php else: ?>
                                    <div class="space-y-1">
                                        <p class="text-xs text-slate-600">Status: <strong><?= e($fiscalStatusLabel); ?></strong></p>
                                        <?php if ($canInvoiceNfe): ?>
                                            <form method="post" action="<?= route('finance/invoices/fiscal-generate'); ?>" onsubmit="return confirm('Gerar nota fiscal de saida para esta fatura?');">
                                                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                                <input type="hidden" name="invoice_id" value="<?= (int) $row['id']; ?>">
                                                <button class="finance-btn finance-btn-nfe rounded-lg border border-indigo-200 bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Gerar Nota Fiscal de Saida</button>
                                            </form>
                                        <?php else: ?>
                                            <p class="text-xs text-slate-400">Sem permissao para NF-e.</p>
                                        <?php endif; ?>
                                        <?php if (!empty($row['fiscal_error_message'])): ?>
                                            <p class="text-[11px] text-rose-600"><?= e($row['fiscal_error_message']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>

                        <td class="px-3 py-3"><?= e($row['created_at']); ?></td>
                        <td class="px-3 py-3"><?= e(format_currency($row['tax_amount'])); ?></td>
                        <td class="px-3 py-3"><?= e($row['tags']); ?></td>
                        <td class="px-3 py-3"><?= e($row['project_name']); ?></td>
                        <td class="px-3 py-3">
                            <?php
                            $amountToCollect = max(0, (float) $row['amount'] - (float) $row['paid_amount']);
                            $waMessage = "Ola " . ($row['student_name'] ?: 'aluno') . ", segue boleto da fatura " . $row['invoice_number'] . ".\n";
                            $waMessage .= "Vencimento: " . $row['due_date'] . "\n";
                            $waMessage .= "Valor: " . format_currency($amountToCollect);
                            if (!empty($boletoLink)) {
                                $waMessage .= "\nLink do boleto: " . $boletoLink;
                            }
                            $invoiceWhatsappLink = whatsapp_link((string) ($row['student_phone'] ?? ''), $waMessage);
                            ?>
                            <?php if ($canInvoiceWhatsapp && $row['status'] !== 'paid' && $invoiceWhatsappLink): ?>
                                <a target="_blank" rel="noopener" href="<?= e($invoiceWhatsappLink); ?>" class="finance-btn finance-btn-whatsapp mb-2 inline-flex rounded border border-emerald-200 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50">Enviar boleto WhatsApp</a>
                            <?php endif; ?>
                            <?php if ($canChatOpen && (int) $row['student_id'] > 0): ?>
                                <form method="post" action="<?= route('chatwoot/open-student'); ?>" class="mb-2">
                                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                    <input type="hidden" name="student_id" value="<?= (int) $row['student_id']; ?>">
                                    <input type="hidden" name="return_route" value="finance/invoices">
                                    <button class="finance-btn finance-btn-chatwoot rounded border border-cyan-200 px-2 py-1 text-xs text-cyan-700 hover:bg-cyan-50">Atender no Chatwoot</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($canInvoiceDelete): ?>
                                <form method="post" action="<?= route('finance/invoices/delete'); ?>" onsubmit="return confirm('Excluir fatura?');">
                                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                    <input type="hidden" name="id" value="<?= (int) $row['id']; ?>">
                                    <button class="finance-btn finance-btn-danger rounded border border-rose-200 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50">Excluir</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($canInvoiceWhatsapp && !$invoiceWhatsappLink): ?>
                                <p class="mt-1 text-[11px] text-slate-400">Sem telefone valido para WhatsApp.</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?>
                    <tr>
                        <td colspan="14" class="px-3 py-6 text-center text-slate-500">Nenhuma fatura encontrada.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
        <p>Total: <?= (int) $meta['total']; ?> registros | Pagina <?= (int) $meta['page']; ?>/<?= (int) $meta['pages']; ?></p>
        <div class="flex gap-2">
            <?php for ($p = 1; $p <= (int) $meta['pages']; $p++): ?>
                <a href="index.php?<?= build_query(['route' => 'finance/invoices', 'page' => $p]); ?>" class="rounded px-3 py-1 <?= $p === (int) $meta['page'] ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white hover:bg-slate-50'; ?>"><?= $p; ?></a>
            <?php endfor; ?>
        </div>
    </div>
</section>
