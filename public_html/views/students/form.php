<?php
$canStudentWhatsapp = has_permission('students.whatsapp');
$studentWhatsappLink = $student ? whatsapp_link((string) ($student['phone'] ?? ''), 'Ola ' . ($student['full_name'] ?? '') . ', tudo bem?') : null;
$portalAvailable = isset($portalAvailable) ? (bool) $portalAvailable : false;
$portalAccount = $portalAccount ?? null;
$portalLogin = (string) ($portalAccount['login'] ?? '');
$portalIsActive = isset($portalAccount['is_active']) ? (int) $portalAccount['is_active'] : 0;
$photoFeatureAvailable = isset($photoFeatureAvailable) ? (bool) $photoFeatureAvailable : true;
$practiceScheduleAvailable = isset($practiceScheduleAvailable) ? (bool) $practiceScheduleAvailable : false;
$practiceUnits = isset($practiceUnits) && is_array($practiceUnits) ? $practiceUnits : [];
$residencyLevel = strtoupper((string) ($student['residency_level'] ?? 'R1'));
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
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-semibold"><?= e($student ? 'Editar Aluno' : 'Novo Aluno'); ?></h2>
            <p class="text-sm text-slate-500">Dados pessoais, academicos e administrativos.</p>
        </div>
        <div class="flex gap-2">
            <?php if ($canStudentWhatsapp && $studentWhatsappLink): ?>
                <a target="_blank" rel="noopener" href="<?= e($studentWhatsappLink); ?>" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-100">Conversar no WhatsApp</a>
            <?php endif; ?>
            <a href="<?= route('students'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Voltar</a>
        </div>
    </div>

    <form method="post" action="<?= e($action); ?>" enctype="multipart/form-data" class="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 lg:grid-cols-2">
        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Nome completo *</span>
            <input type="text" name="full_name" required value="<?= e($student['full_name'] ?? ''); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Contato principal</span>
            <input type="text" name="primary_contact" value="<?= e($student['primary_contact'] ?? ''); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Email principal</span>
            <input type="email" name="email_primary" value="<?= e($student['email_primary'] ?? ''); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Telefone</span>
            <input type="text" name="phone" value="<?= e($student['phone'] ?? ''); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>

        <label class="block lg:col-span-2">
            <span class="mb-1 block text-sm font-medium">Foto do aluno (perfil)</span>
            <div class="flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
                <?php $studentPhoto = trim((string) ($student['profile_photo'] ?? '')); ?>
                <?php if ($studentPhoto !== '' && media_path_available($studentPhoto)): ?>
                    <img src="<?= e($studentPhoto); ?>" alt="Foto do aluno" class="h-16 w-16 rounded-full object-cover ring-2 ring-white shadow">
                <?php else: ?>
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold text-slate-500">Sem foto</div>
                <?php endif; ?>
                <div class="flex-1 min-w-[220px]">
                    <input type="hidden" name="profile_photo_current" value="<?= e($student['profile_photo'] ?? ''); ?>">
                    <input type="file" name="student_photo" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" <?= $photoFeatureAvailable ? '' : 'disabled'; ?>>
                    <p class="mt-1 text-xs text-slate-500">Formatos: PNG, JPG, JPEG ou WEBP (maximo 5MB).</p>
                    <?php if (!$photoFeatureAvailable): ?>
                        <p class="mt-1 text-xs text-amber-700">Foto indisponivel no banco atual. Execute a migracao de foto de perfil para habilitar.</p>
                    <?php endif; ?>
                </div>
            </div>
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">RA</span>
            <input type="text" name="ra" value="<?= e($student['ra'] ?? ''); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Data de nascimento</span>
            <input type="date" name="birth_date" value="<?= e($student['birth_date'] ?? ''); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">RG</span>
            <input type="text" name="rg" value="<?= e($student['rg'] ?? ''); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">CRO</span>
            <input type="text" name="cro" value="<?= e($student['cro'] ?? ''); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Status Kanban</span>
            <select name="kanban_status_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Padrao</option>
                <?php foreach ($statuses as $st): ?>
                    <option value="<?= (int) $st['id']; ?>" <?= (string) ($student['kanban_status_id'] ?? '') === (string) $st['id'] ? 'selected' : ''; ?>>
                        <?= e($st['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Ativo?</span>
            <select name="is_active" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="1" <?= (int) ($student['is_active'] ?? 1) === 1 ? 'selected' : ''; ?>>Ativo</option>
                <option value="0" <?= isset($student) && (int) ($student['is_active'] ?? 1) === 0 ? 'selected' : ''; ?>>Inativo</option>
            </select>
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Mensalidade (recorrencia)</span>
            <input type="text" name="monthly_fee" value="<?= e((string) ($student['monthly_fee'] ?? '')); ?>" placeholder="0,00" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Dia do vencimento</span>
            <input type="number" min="1" max="31" name="billing_day" value="<?= e((string) ($student['billing_day'] ?? '')); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Data de entrada</span>
            <input type="date" name="enrolled_at" value="<?= e($student['enrolled_at'] ?? ''); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <span class="mt-1 block text-xs text-slate-400">Usada como base para rematricula semestral automatica.</span>
        </label>

        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4 lg:col-span-2">
            <h3 class="text-sm font-semibold text-slate-800">Escala pratica</h3>
            <?php if (!$practiceScheduleAvailable): ?>
                <p class="mt-2 text-xs text-amber-700">Os campos da Escala Aluno ainda nao estao disponiveis no banco. Execute a migration do modulo para habilitar.</p>
            <?php else: ?>
                <div class="mt-3 grid gap-3 md:grid-cols-3">
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium">Unidade / Hospital</span>
                        <select name="practice_unit_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <option value="">Selecione...</option>
                            <?php foreach ($practiceUnits as $practiceUnit): ?>
                                <option value="<?= (int) $practiceUnit['id']; ?>" <?= (string) ($student['practice_unit_id'] ?? '') === (string) $practiceUnit['id'] ? 'selected' : ''; ?>><?= e((string) $practiceUnit['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium">Nivel de residencia</span>
                        <select name="residency_level" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <?php foreach (['R1', 'R2', 'R3'] as $level): ?>
                                <option value="<?= e($level); ?>" <?= $residencyLevel === $level ? 'selected' : ''; ?>><?= e($level); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Elegivel para escala</p>
                        <p class="mt-1 font-semibold text-slate-800"><?= e($eligibleSince ?? 'Sera calculado apos informar a data de entrada'); ?></p>
                        <p class="mt-1 text-xs text-slate-400">Regra atual: 40 dias apos a entrada do aluno.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <label class="block lg:col-span-2">
            <span class="mb-1 block text-sm font-medium">Informacoes Adm (tags/flags)</span>
            <input type="text" name="admin_info" value="<?= e($student['admin_info'] ?? ''); ?>" placeholder="<?= e(implode(', ', $flags)); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>

        <label class="block lg:col-span-2">
            <span class="mb-1 block text-sm font-medium">Observacoes internas</span>
            <textarea name="notes" rows="4" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><?= e($student['notes'] ?? ''); ?></textarea>
        </label>

        <label class="block lg:col-span-2">
            <span class="mb-1 block text-sm font-medium">Documento (upload)</span>
            <input type="file" name="document" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>

        <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-slate-50/70 p-4">
            <h3 class="text-sm font-semibold text-slate-800">Acesso do Portal do Aluno</h3>
            <p class="mt-1 text-xs text-slate-500">Permite login separado para o aluno acessar Meus Cursos, Aulas ao Vivo, Materiais, Progresso e Avaliacoes.</p>

            <?php if (!$portalAvailable): ?>
                <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                    Estrutura do portal ainda nao encontrada no banco. Execute o `database.sql` atualizado para liberar esse cadastro.
                </p>
            <?php endif; ?>

            <div class="mt-3 grid gap-3 md:grid-cols-3">
                <label class="block">
                    <span class="mb-1 block text-sm font-medium">Login do aluno</span>
                    <input type="text" name="portal_login" value="<?= e($portalLogin); ?>" placeholder="ex: aluno.maria" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" <?= $portalAvailable ? '' : 'disabled'; ?>>
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium"><?= $student ? 'Nova senha (opcional)' : 'Senha inicial'; ?></span>
                    <input type="password" name="portal_password" placeholder="<?= $student ? 'Preencha para alterar a senha' : 'Obrigatoria ao ativar'; ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" <?= $portalAvailable ? '' : 'disabled'; ?>>
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium">Status do acesso</span>
                    <select name="portal_is_active" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" <?= $portalAvailable ? '' : 'disabled'; ?>>
                        <option value="0" <?= $portalIsActive === 0 ? 'selected' : ''; ?>>Inativo</option>
                        <option value="1" <?= $portalIsActive === 1 ? 'selected' : ''; ?>>Ativo</option>
                    </select>
                </label>
            </div>
        </div>

        <div class="lg:col-span-2 flex justify-end gap-2">
            <a href="<?= route('students'); ?>" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm hover:bg-slate-50">Cancelar</a>
            <button class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700">Salvar</button>
        </div>
    </form>
</section>
