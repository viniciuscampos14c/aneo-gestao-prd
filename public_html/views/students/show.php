<?php
$canStudentWhatsapp = has_permission('students.whatsapp');
$canChatOpen = has_permission('chat.open');
$studentWhatsappLink = whatsapp_link((string) ($student['phone'] ?? ''), 'Ola ' . ($student['full_name'] ?? '') . ', tudo bem?');
$portalAvailable = isset($portalAvailable) ? (bool) $portalAvailable : false;
$portalAccount = $portalAccount ?? null;
$practiceScheduleAvailable = isset($practiceScheduleAvailable) ? (bool) $practiceScheduleAvailable : false;
$eligibleSince = null;
if (!empty($student['enrolled_at'])) {
    try {
        $eligibleSince = (new DateTimeImmutable((string) $student['enrolled_at']))->modify('+40 days')->format('d/m/Y');
    } catch (Throwable $e) {
        $eligibleSince = null;
    }
}
?>
<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <?php $studentPhoto = trim((string) ($student['profile_photo'] ?? '')); ?>
            <?php if ($studentPhoto !== '' && media_path_available($studentPhoto)): ?>
                <img src="<?= e($studentPhoto); ?>" alt="Foto do aluno" class="h-14 w-14 rounded-full object-cover ring-2 ring-white shadow">
            <?php else: ?>
                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold text-slate-600">Sem foto</div>
            <?php endif; ?>
            <div>
                <h2 class="text-2xl font-semibold"><?= e($student['full_name']); ?></h2>
                <p class="text-sm text-slate-500">Aluno #<?= (int) $student['id']; ?> | Status: <?= e($student['kanban_status_name'] ?? ''); ?></p>
            </div>
        </div>
        <div class="flex gap-2">
            <?php if ($canChatOpen): ?>
                <form method="post" action="<?= route('chatwoot/open-student'); ?>">
                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                    <input type="hidden" name="student_id" value="<?= (int) $student['id']; ?>">
                    <input type="hidden" name="return_route" value="<?= e('students/show&id=' . (int) $student['id']); ?>">
                    <button class="rounded-lg border border-cyan-200 bg-cyan-50 px-3 py-2 text-sm font-medium text-cyan-700 hover:bg-cyan-100">Atender no Chatwoot</button>
                </form>
            <?php endif; ?>
            <?php if ($canStudentWhatsapp && $studentWhatsappLink): ?>
                <a target="_blank" rel="noopener" href="<?= e($studentWhatsappLink); ?>" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-100">Conversar no WhatsApp</a>
            <?php endif; ?>
            <a href="<?= route('students/edit&id=' . (int) $student['id']); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Editar</a>
            <a href="<?= route('students'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Voltar</a>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <section class="rounded-xl border border-slate-200 bg-white p-4 xl:col-span-2">
            <h3 class="mb-4 text-lg font-semibold">Dados Gerais</h3>
            <dl class="grid gap-3 text-sm md:grid-cols-2">
                <div><dt class="text-slate-500">Contato principal</dt><dd class="font-medium"><?= e($student['primary_contact']); ?></dd></div>
                <div><dt class="text-slate-500">Email</dt><dd class="font-medium"><?= e($student['email_primary']); ?></dd></div>
                <div>
                    <dt class="text-slate-500">Telefone</dt>
                    <dd class="font-medium">
                        <?= e($student['phone']); ?>
                        <?php if ($canChatOpen): ?>
                            <form method="post" action="<?= route('chatwoot/open-student'); ?>" class="ml-2 inline">
                                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                <input type="hidden" name="student_id" value="<?= (int) $student['id']; ?>">
                                <input type="hidden" name="return_route" value="<?= e('students/show&id=' . (int) $student['id']); ?>">
                                <button class="text-xs text-cyan-700 underline">Chatwoot</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($canStudentWhatsapp && $studentWhatsappLink): ?>
                            <a target="_blank" rel="noopener" href="<?= e($studentWhatsappLink); ?>" class="ml-2 text-xs text-emerald-700 underline">WhatsApp</a>
                        <?php endif; ?>
                    </dd>
                </div>
                <div><dt class="text-slate-500">RA</dt><dd class="font-medium"><?= e($student['ra']); ?></dd></div>
                <div><dt class="text-slate-500">Nascimento</dt><dd class="font-medium"><?= e($student['birth_date']); ?></dd></div>
                <div><dt class="text-slate-500">RG</dt><dd class="font-medium"><?= e($student['rg']); ?></dd></div>
                <div><dt class="text-slate-500">CRO</dt><dd class="font-medium"><?= e($student['cro']); ?></dd></div>
                <div><dt class="text-slate-500">Mensalidade</dt><dd class="font-medium"><?= e(format_currency($student['monthly_fee'])); ?></dd></div>
                <div><dt class="text-slate-500">Unidade pratica</dt><dd class="font-medium"><?= e((string) ($student['practice_unit_name'] ?? '-')); ?></dd></div>
                <div><dt class="text-slate-500">Nivel de residencia</dt><dd class="font-medium"><?= e((string) ($student['residency_level'] ?? 'R1')); ?></dd></div>
                <div><dt class="text-slate-500">Elegivel para escala</dt><dd class="font-medium"><?= e($eligibleSince ?? '-'); ?></dd></div>
                <div>
                    <dt class="text-slate-500">Portal do aluno</dt>
                    <dd class="font-medium">
                        <?php if (!$portalAvailable): ?>
                            Nao configurado no banco
                        <?php elseif (!$portalAccount): ?>
                            Sem acesso cadastrado
                        <?php else: ?>
                            <?= (int) $portalAccount['is_active'] === 1 ? 'Ativo' : 'Inativo'; ?>
                            (<?= e($portalAccount['login']); ?>)
                        <?php endif; ?>
                    </dd>
                </div>
            </dl>
            <?php if (!$practiceScheduleAvailable): ?>
                <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">Os dados da Escala Aluno ainda nao estao disponiveis no banco.</p>
            <?php endif; ?>
            <p class="mt-4 text-sm"><strong>Informacoes Adm:</strong> <?= e($student['admin_info']); ?></p>
            <p class="mt-2 text-sm"><strong>Observacoes:</strong> <?= nl2br(e($student['notes'])); ?></p>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-4">
            <h3 class="mb-4 text-lg font-semibold">Documentos</h3>

            <form method="post" action="<?= route('students/upload-document'); ?>" enctype="multipart/form-data" class="mb-4 space-y-2">
                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                <input type="hidden" name="student_id" value="<?= (int) $student['id']; ?>">
                <input type="file" name="document" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <button class="w-full rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">Anexar</button>
            </form>

            <div class="space-y-2 text-sm">
                <?php foreach ($documents as $doc): ?>
                    <a href="<?= e($doc['file_path']); ?>" target="_blank" class="block rounded-lg border border-slate-200 px-3 py-2 hover:bg-slate-50">
                        <?= e($doc['file_name']); ?>
                    </a>
                <?php endforeach; ?>
                <?php if ($documents === []): ?>
                    <p class="text-slate-500">Nenhum documento anexado.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-xl border border-slate-200 bg-white p-4">
            <h3 class="mb-4 text-lg font-semibold">Historico Financeiro</h3>
            <div class="space-y-2 text-sm">
                <?php foreach ($financeHistory as $row): ?>
                    <div class="rounded-lg border border-slate-100 px-3 py-2">
                        <p class="font-medium"><?= e($row['invoice_number']); ?> - <?= e($row['status']); ?></p>
                        <p class="text-slate-500">Vencimento: <?= e($row['due_date']); ?> | Valor: <?= e(format_currency($row['amount'])); ?> | Pago: <?= e(format_currency($row['paid_amount'])); ?></p>
                    </div>
                <?php endforeach; ?>
                <?php if ($financeHistory === []): ?>
                    <p class="text-slate-500">Sem registros financeiros.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-4">
            <h3 class="mb-4 text-lg font-semibold">Historico de Status (Kanban)</h3>
            <div class="space-y-2 text-sm">
                <?php foreach ($kanbanHistory as $row): ?>
                    <div class="rounded-lg border border-slate-100 px-3 py-2">
                        <p><strong><?= e($row['from_status'] ?: 'Inicio'); ?></strong> -> <strong><?= e($row['to_status']); ?></strong></p>
                        <p class="text-slate-500"><?= e($row['created_at']); ?> | <?= e($row['changed_by_name']); ?> | <?= e($row['reason']); ?></p>
                    </div>
                <?php endforeach; ?>
                <?php if ($kanbanHistory === []): ?>
                    <p class="text-slate-500">Sem historico de status.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>
</section>
