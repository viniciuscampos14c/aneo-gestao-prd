<?php
/** @var array|null $session        null = criar; array = editar */
/** @var array      $courses        cursos publicados */
/** @var bool       $zoomConfigured credenciais Zoom cadastradas */

$isEdit      = $session !== null;
$action      = $isEdit ? 'courses/live-sessions/update' : 'courses/live-sessions/store';
$title       = $isEdit ? 'Editar Aula Online' : 'Nova Aula Online';
$btnLabel    = $isEdit ? 'Salvar Alterações' : 'Criar Reunião Zoom';

// Prepara scheduled_at para datetime-local (Y-m-d\TH:i)
$scheduledAt = '';
if ($isEdit && !empty($session['scheduled_at'])) {
    $scheduledAt = date('Y-m-d\TH:i', strtotime($session['scheduled_at']));
}
?>
<div class="space-y-6">

    <!-- Cabeçalho -->
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold text-slate-800"><?= e($title); ?></h2>
            <p class="text-sm text-slate-500">
                <?= $isEdit
                    ? 'Atualize os dados da aula. O link do Zoom não será alterado.'
                    : 'Preencha os dados abaixo e o sistema criará a reunião automaticamente no Zoom.'; ?>
            </p>
        </div>
        <a href="<?= route('courses/live-sessions'); ?>"
           class="text-sm text-slate-500 hover:text-slate-700">← Voltar para a lista</a>
    </div>

    <?php if (!$zoomConfigured && !$isEdit): ?>
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
            <strong>Credenciais Zoom não configuradas.</strong>
            <a href="<?= route('courses/live-sessions/zoom-settings'); ?>" class="underline ml-1">Configure aqui</a>
            antes de criar aulas.
        </div>
    <?php endif; ?>

    <form method="POST" action="index.php?route=<?= e($action); ?>" class="space-y-5 max-w-2xl">
        <input type="hidden" name="route" value="<?= e($action); ?>">
        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= (int) $session['id']; ?>">
        <?php endif; ?>

        <?php if (!$isEdit): ?>
            <label class="course-live-global-option flex gap-3 rounded-lg border px-3 py-3 text-sm">
                <input type="checkbox" name="is_global" value="1" class="mt-0.5 h-4 w-4 rounded border-cyan-300 text-cyan-600 focus:ring-cyan-500">
                <span>
                    <strong class="block">Aula global para todas as unidades deste curso</strong>
                    <span class="course-live-global-help mt-1 block text-xs">
                        Selecione abaixo um curso-base. O sistema criara uma unica reunião Zoom e vinculara o mesmo link aos cursos equivalentes das empresas ativas.
                    </span>
                </span>
            </label>
        <?php endif; ?>

        <!-- Curso -->
        <label class="block">
            <span class="mb-1 block text-sm font-medium text-slate-700">Curso-base <span class="text-rose-500">*</span></span>
            <select name="course_id" required
                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-100">
                <option value="">Selecione o curso</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?= (int) $c['id']; ?>"
                            <?= (int) ($session['course_id'] ?? 0) === (int) $c['id'] ? 'selected' : ''; ?>>
                        <?= e($c['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <!-- Título -->
        <label class="block">
            <span class="mb-1 block text-sm font-medium text-slate-700">Título da aula <span class="text-rose-500">*</span></span>
            <input type="text" name="title" required maxlength="180"
                   value="<?= e($session['title'] ?? ''); ?>"
                   placeholder="Ex: Aula 01 — Introdução ao módulo"
                   class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-100">
        </label>

        <!-- Data e horário -->
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="mb-1 block text-sm font-medium text-slate-700">Data e horário <span class="text-rose-500">*</span></span>
                <input type="datetime-local" name="scheduled_at" required
                       value="<?= e($scheduledAt); ?>"
                       class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-100">
                <span class="mt-1 block text-xs text-slate-400">Horário de Brasília (GMT-3)</span>
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-medium text-slate-700">Duração (minutos) <span class="text-rose-500">*</span></span>
                <input type="number" name="duration_minutes" required min="15" max="480"
                       value="<?= (int) ($session['duration_minutes'] ?? 60); ?>"
                       class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-100">
                <span class="mt-1 block text-xs text-slate-400">Entre 15 e 480 minutos</span>
            </label>
        </div>

        <!-- Observações -->
        <label class="block">
            <span class="mb-1 block text-sm font-medium text-slate-700">Observações</span>
            <textarea name="notes" rows="3" placeholder="Informações adicionais para os alunos (opcional)"
                      class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-100"><?= e($session['notes'] ?? ''); ?></textarea>
        </label>

        <?php if ($isEdit && !empty($session['zoom_meeting_id'])): ?>
            <div class="rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-800 space-y-1">
                <p class="font-semibold">Dados atuais da reunião Zoom:</p>
                <p>Meeting ID: <span class="font-mono font-bold"><?= e($session['zoom_meeting_id']); ?></span></p>
                <p>Senha: <span class="font-mono font-bold"><?= e($session['zoom_password'] ?? '—'); ?></span></p>
                <p class="text-xs text-sky-600 mt-1">Editar a aula <strong>não</strong> recria a reunião no Zoom — apenas atualiza os dados no ERP.</p>
            </div>
        <?php endif; ?>

        <!-- Botões -->
        <div class="flex items-center gap-3 pt-2">
            <button type="submit"
                    <?= (!$isEdit && !$zoomConfigured) ? 'disabled' : ''; ?>
                    class="rounded-lg bg-sky-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-sky-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                <?= e($btnLabel); ?>
            </button>
            <a href="<?= route('courses/live-sessions'); ?>"
               class="text-sm text-slate-500 hover:text-slate-700">Cancelar</a>
        </div>
    </form>

</div>
