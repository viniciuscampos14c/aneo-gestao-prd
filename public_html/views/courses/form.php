<?php
$materialFiles       = $materialFiles       ?? [];
$lmsFeatureAvailable = $lmsFeatureAvailable ?? false;
$courseModules       = $courseModules       ?? [];
$zoomConfigured      = $zoomConfigured      ?? false;
$courseZoomSessions  = $courseZoomSessions  ?? [];
$backToCourse        = $course ? 'courses/edit&id=' . (int) $course['id'] : 'courses';
$focusedModuleId     = (int) request('lms_module', 0);
$lmsTotalLessons = 0;
$lmsRequiredLessons = 0;
$lmsActiveModules = 0;
$lmsDurationSeconds = 0;
foreach ($courseModules as $module) {
    if (!empty($module['is_active'])) {
        $lmsActiveModules++;
    }
    foreach (($module['lessons'] ?? []) as $lesson) {
        $lmsTotalLessons++;
        if (!empty($lesson['is_required'])) {
            $lmsRequiredLessons++;
        }
        $lmsDurationSeconds += (int) ($lesson['duration_seconds'] ?? 0);
    }
}
$formatLessonDuration = static function (int $seconds): string {
    if ($seconds <= 0) {
        return 'sem duracao';
    }
    $minutes = (int) ceil($seconds / 60);
    if ($minutes < 60) {
        return $minutes . ' min';
    }
    $hours = intdiv($minutes, 60);
    $remaining = $minutes % 60;
    return $hours . 'h' . ($remaining > 0 ? ' ' . $remaining . 'min' : '');
};
?>
<section class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-semibold"><?= e($course ? 'Editar Curso' : 'Criar Curso'); ?></h2>
            <p class="text-sm text-slate-500">Cadastro completo de curso EAD com links de aulas ao vivo.</p>
        </div>
        <a href="<?= route('courses'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Voltar</a>
    </div>

    <form method="post" action="<?= e($action); ?>" enctype="multipart/form-data" class="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 lg:grid-cols-2">
        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Nome do curso *</span>
            <input type="text" name="name" required value="<?= e($course['name'] ?? ''); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Categoria</span>
            <select name="category_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Sem categoria</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int) $cat['id']; ?>" <?= (string) ($course['category_id'] ?? '') === (string) $cat['id'] ? 'selected' : ''; ?>><?= e($cat['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="block lg:col-span-2">
            <span class="mb-1 block text-sm font-medium">Descricao</span>
            <textarea name="description" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><?= e($course['description'] ?? ''); ?></textarea>
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Capa (upload)</span>
            <input type="file" name="cover_file" accept="image/*" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Capa (URL/caminho)</span>
            <input type="text" name="cover_image" value="<?= e($course['cover_image'] ?? ''); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Situacao</span>
            <select name="status" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="draft" <?= ($course['status'] ?? 'draft') === 'draft' ? 'selected' : ''; ?>>Rascunho</option>
                <option value="published" <?= ($course['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Publicado</option>
            </select>
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Carga horaria</span>
            <input type="number" name="workload_hours" value="<?= e((string) ($course['workload_hours'] ?? '')); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>

        <label class="block lg:col-span-2">
            <span class="mb-1 block text-sm font-medium">Grade (modulos/aulas)</span>
            <textarea name="curriculum" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><?= e($course['curriculum'] ?? ''); ?></textarea>
        </label>

        <label class="block lg:col-span-2">
            <span class="mb-1 block text-sm font-medium">Materiais (PDF, links, downloads)</span>
            <textarea name="materials" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><?= e($course['materials'] ?? ''); ?></textarea>
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Link da aula ao vivo (manual)</span>
            <input type="url" name="live_link" value="<?= e($course['live_link'] ?? ''); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Senha da sala</span>
            <input type="text" name="live_password" value="<?= e($course['live_password'] ?? ''); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">ID da reuniao</span>
            <input type="text" name="live_meeting_id" value="<?= e($course['live_meeting_id'] ?? ''); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium">Data e horario da aula</span>
            <input type="datetime-local" name="live_datetime" value="<?= e(isset($course['live_datetime']) ? str_replace(' ', 'T', substr((string) $course['live_datetime'], 0, 16)) : ''); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </label>

        <div class="lg:col-span-2 flex justify-end gap-2">
            <a href="<?= route('courses'); ?>" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm hover:bg-slate-50">Cancelar</a>
            <button class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700">Salvar Curso</button>
        </div>
    </form>

    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
        <h3 class="text-sm font-semibold text-slate-800">Arquivos de Material</h3>
        <?php if (!$course): ?>
            <p class="mt-2 text-xs text-slate-500">Salve o curso primeiro para habilitar upload de arquivos (PDF, DOC, XLS, PPT, ZIP, MP4 etc.).</p>
        <?php else: ?>
            <form method="post" action="<?= route('courses/materials/upload&id=' . (int) $course['id']); ?>" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-center gap-2">
                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                <input type="file" name="material_files[]" multiple class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                <button class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-100">Enviar arquivo(s)</button>
            </form>

            <div class="mt-3 space-y-2">
                <?php foreach ($materialFiles as $file): ?>
                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                        <a href="<?= e($file['file_path']); ?>" target="_blank" rel="noopener" class="font-medium text-cyan-700 hover:underline"><?= e($file['file_name']); ?></a>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-slate-500"><?= e($file['created_at']); ?></span>
                            <form method="post" action="<?= route('courses/materials/delete'); ?>" onsubmit="return confirm('Remover este arquivo?');">
                                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                <input type="hidden" name="course_id" value="<?= (int) $course['id']; ?>">
                                <input type="hidden" name="upload_id" value="<?= (int) $file['id']; ?>">
                                <button class="rounded border border-rose-200 bg-rose-50 px-2 py-1 text-xs font-medium text-rose-700 hover:bg-rose-100">Remover</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if ($materialFiles === []): ?>
                    <p class="text-xs text-slate-500">Nenhum arquivo de material anexado.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="admin-lms-builder overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="admin-lms-builder-head border-b border-slate-200 bg-gradient-to-r from-slate-950 via-slate-900 to-cyan-900 p-5 text-white">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="admin-lms-builder-eyebrow text-xs font-semibold uppercase tracking-[0.28em] text-cyan-200">Builder EAD</p>
                    <h3 class="admin-lms-builder-title mt-2 text-xl font-semibold">Trilha LMS</h3>
                    <p class="admin-lms-builder-copy mt-1 max-w-2xl text-sm text-cyan-100">Monte a jornada do aluno por modulos, aulas obrigatorias e criterios de conclusao automatica.</p>
                </div>
                <?php if ($lmsFeatureAvailable && $course): ?>
                    <span class="admin-lms-builder-pill inline-flex items-center rounded-full border border-cyan-300/40 bg-cyan-300/15 px-3 py-1 text-xs font-semibold text-cyan-100">
                        <?= count($courseModules); ?> modulo(s) configurado(s)
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($lmsFeatureAvailable && $course): ?>
                <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="admin-lms-kpi-card rounded-2xl border border-white/10 bg-white/10 p-3">
                        <p class="admin-lms-kpi-label text-xs uppercase tracking-wide text-cyan-100">Modulos ativos</p>
                        <p class="admin-lms-kpi-value mt-1 text-2xl font-semibold"><?= $lmsActiveModules; ?>/<?= count($courseModules); ?></p>
                    </div>
                    <div class="admin-lms-kpi-card rounded-2xl border border-white/10 bg-white/10 p-3">
                        <p class="admin-lms-kpi-label text-xs uppercase tracking-wide text-cyan-100">Aulas</p>
                        <p class="admin-lms-kpi-value mt-1 text-2xl font-semibold"><?= $lmsTotalLessons; ?></p>
                    </div>
                    <div class="admin-lms-kpi-card rounded-2xl border border-white/10 bg-white/10 p-3">
                        <p class="admin-lms-kpi-label text-xs uppercase tracking-wide text-cyan-100">Obrigatorias</p>
                        <p class="admin-lms-kpi-value mt-1 text-2xl font-semibold"><?= $lmsRequiredLessons; ?></p>
                    </div>
                    <div class="admin-lms-kpi-card rounded-2xl border border-white/10 bg-white/10 p-3">
                        <p class="admin-lms-kpi-label text-xs uppercase tracking-wide text-cyan-100">Carga em video</p>
                        <p class="admin-lms-kpi-value mt-1 text-2xl font-semibold"><?= e($formatLessonDuration($lmsDurationSeconds)); ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="admin-lms-builder-body p-4">
        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="admin-lms-section-title text-sm font-semibold text-slate-800">Estrutura do curso</h3>
                <p class="admin-lms-section-copy mt-1 text-xs text-slate-500">Use esta area como quadro de montagem: uma etapa por modulo, aulas em sequencia e regras de conclusao por video.</p>
            </div>
            <?php if ($lmsFeatureAvailable && $course): ?>
                <span class="admin-lms-status-pill inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                    Progresso automatico ativo
                </span>
            <?php endif; ?>
        </div>

        <?php if (!$lmsFeatureAvailable): ?>
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                Execute a migration de LMS para habilitar esta area: <code>migrations/20260317_lms_learning_path.sql</code>.
            </div>
        <?php elseif (!$course): ?>
            <p class="text-xs text-slate-500">Salve o curso primeiro para cadastrar modulos e aulas.</p>
        <?php else: ?>
            <div class="admin-lms-help-strip mb-4 grid gap-2 rounded-xl border border-cyan-200 bg-cyan-50/70 p-3 text-xs text-cyan-800 md:grid-cols-3">
                <p><span class="font-semibold">Passo 1:</span> Crie o modulo com nome e ordem.</p>
                <p><span class="font-semibold">Passo 2:</span> Dentro do modulo, cadastre as aulas com URL do video.</p>
                <p><span class="font-semibold">Passo 3:</span> Marque como obrigatoria/ativa e salve.</p>
            </div>

            <form method="post" action="<?= route('courses/modules/store'); ?>" class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 lg:grid-cols-12">
                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                <input type="hidden" name="course_id" value="<?= (int) $course['id']; ?>">

                <label class="block lg:col-span-5">
                    <span class="mb-1 block text-xs font-semibold text-slate-700">Nome do modulo *</span>
                    <input type="text" name="title" required placeholder="Ex: Modulo 1 - Fundamentos" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                </label>

                <label class="block lg:col-span-2">
                    <span class="mb-1 block text-xs font-semibold text-slate-700">Ordem</span>
                    <input type="number" name="display_order" min="1" placeholder="1" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                </label>

                <div class="flex items-end gap-3 lg:col-span-5">
                    <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700">
                        <input type="checkbox" name="is_active" value="1" checked>
                        Modulo ativo
                    </label>
                    <button class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Criar modulo</button>
                </div>

                <label class="block lg:col-span-12">
                    <span class="mb-1 block text-xs font-semibold text-slate-700">Descricao do modulo (opcional)</span>
                    <textarea name="description" rows="2" placeholder="Ex: Objetivos e resumo deste modulo." class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"></textarea>
                </label>
            </form>

            <div class="mt-4 space-y-4">
                <?php foreach ($courseModules as $moduleIndex => $module): ?>
                    <?php
                    $moduleLessons = is_array($module['lessons'] ?? null) ? $module['lessons'] : [];
                    $moduleLessonCount = count($moduleLessons);
                    $moduleRequiredLessons = 0;
                    $moduleDurationSeconds = 0;
                    foreach ($moduleLessons as $lessonMetric) {
                        if (!empty($lessonMetric['is_required'])) {
                            $moduleRequiredLessons++;
                        }
                        $moduleDurationSeconds += (int) ($lessonMetric['duration_seconds'] ?? 0);
                    }
                    ?>
                    <details id="lms-module-<?= (int) $module['id']; ?>" class="rounded-2xl border border-slate-200 bg-slate-50/80 p-3 shadow-sm" <?= ($focusedModuleId > 0 ? $focusedModuleId === (int) $module['id'] : $moduleIndex === 0) ? 'open' : ''; ?>>
                        <summary class="cursor-pointer list-none rounded-xl border border-slate-200 bg-white px-4 py-3 transition hover:border-cyan-200 hover:bg-cyan-50/40">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-900 text-sm font-semibold text-white">
                                        <?= (int) ($module['display_order'] ?? ($moduleIndex + 1)); ?>
                                    </span>
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900"><?= e((string) ($module['title'] ?? 'Sem titulo')); ?></p>
                                        <p class="text-xs text-slate-500">
                                            <?= $moduleLessonCount; ?> aula(s) | <?= $moduleRequiredLessons; ?> obrigatoria(s) | <?= e($formatLessonDuration($moduleDurationSeconds)); ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <?php if (!empty($module['is_active'])): ?>
                                        <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700">Ativo</span>
                                    <?php else: ?>
                                        <span class="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-700">Inativo</span>
                                    <?php endif; ?>
                                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600">
                                        Editar etapa
                                    </span>
                                </div>
                            </div>
                        </summary>

                        <div class="mt-3 space-y-3">
                            <form method="post" action="<?= route('courses/modules/update'); ?>" class="grid gap-3 rounded-lg border border-slate-200 bg-white p-3 lg:grid-cols-12">
                                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                <input type="hidden" name="course_id" value="<?= (int) $course['id']; ?>">
                                <input type="hidden" name="module_id" value="<?= (int) $module['id']; ?>">

                                <label class="block lg:col-span-5">
                                    <span class="mb-1 block text-xs font-semibold text-slate-700">Nome do modulo *</span>
                                    <input type="text" name="title" required value="<?= e((string) $module['title']); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                </label>

                                <label class="block lg:col-span-2">
                                    <span class="mb-1 block text-xs font-semibold text-slate-700">Ordem</span>
                                    <input type="number" name="display_order" min="1" value="<?= (int) $module['display_order']; ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                </label>

                                <div class="flex items-end gap-3 lg:col-span-5">
                                    <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-700">
                                        <input type="checkbox" name="is_active" value="1" <?= !empty($module['is_active']) ? 'checked' : ''; ?>>
                                        Modulo ativo
                                    </label>
                                    <button class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium hover:bg-slate-100">Salvar modulo</button>
                                </div>

                                <label class="block lg:col-span-12">
                                    <span class="mb-1 block text-xs font-semibold text-slate-700">Descricao (opcional)</span>
                                    <textarea name="description" rows="2" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><?= e((string) ($module['description'] ?? '')); ?></textarea>
                                </label>
                            </form>

                            <form method="post" action="<?= route('courses/modules/delete'); ?>" onsubmit="return confirm('Remover modulo e todas as aulas?');" class="flex justify-end">
                                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                <input type="hidden" name="course_id" value="<?= (int) $course['id']; ?>">
                                <input type="hidden" name="module_id" value="<?= (int) $module['id']; ?>">
                                <button class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700 hover:bg-rose-100">Excluir modulo</button>
                            </form>

                            <div class="space-y-3 rounded-lg border border-slate-200 bg-white p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Aulas deste modulo</p>
                                    <span class="text-xs text-slate-500"><?= $moduleLessonCount; ?> item(ns)</span>
                                </div>

                                <?php foreach ($moduleLessons as $lesson): ?>
                                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                            <div class="flex items-center gap-2">
                                                <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600 ring-1 ring-slate-200">
                                                    Aula <?= (int) ($lesson['display_order'] ?? 1); ?>
                                                </span>
                                                <span class="rounded-full <?= !empty($lesson['is_required']) ? 'bg-cyan-50 text-cyan-700 ring-cyan-200' : 'bg-slate-100 text-slate-600 ring-slate-200'; ?> px-2.5 py-1 text-[11px] font-semibold ring-1">
                                                    <?= !empty($lesson['is_required']) ? 'Obrigatoria' : 'Opcional'; ?>
                                                </span>
                                                <span class="rounded-full <?= !empty($lesson['is_active']) ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-amber-50 text-amber-700 ring-amber-200'; ?> px-2.5 py-1 text-[11px] font-semibold ring-1">
                                                    <?= !empty($lesson['is_active']) ? 'Publicada na trilha' : 'Oculta'; ?>
                                                </span>
                                            </div>
                                            <p class="text-xs text-slate-500"><?= e($formatLessonDuration((int) ($lesson['duration_seconds'] ?? 0))); ?> | minimo <?= (int) ($lesson['min_progress_percent'] ?? 70); ?>%</p>
                                        </div>
                                        <form method="post" action="<?= route('courses/lessons/update'); ?>" class="grid gap-3 lg:grid-cols-12">
                                            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                            <input type="hidden" name="course_id" value="<?= (int) $course['id']; ?>">
                                            <input type="hidden" name="module_id" value="<?= (int) $module['id']; ?>">
                                            <input type="hidden" name="lesson_id" value="<?= (int) $lesson['id']; ?>">

                                            <label class="block lg:col-span-3">
                                                <span class="mb-1 block text-xs font-semibold text-slate-700">Titulo da aula *</span>
                                                <input type="text" name="title" required value="<?= e((string) $lesson['title']); ?>" placeholder="Ex: Aula 1" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                            </label>

                                            <label class="block lg:col-span-5">
                                                <span class="mb-1 block text-xs font-semibold text-slate-700">URL do video *</span>
                                                <input type="text" name="video_url" required value="<?= e((string) ($lesson['video_url'] ?? '')); ?>" placeholder="YouTube, Vimeo ou MP4/WebM" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                            </label>

                                            <label class="block lg:col-span-1">
                                                <span class="mb-1 block text-xs font-semibold text-slate-700">Duracao (s)</span>
                                                <input type="number" name="duration_seconds" min="0" value="<?= (int) ($lesson['duration_seconds'] ?? 0); ?>" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                            </label>

                                            <label class="block lg:col-span-1">
                                                <span class="mb-1 block text-xs font-semibold text-slate-700">% minimo</span>
                                                <input type="number" name="min_progress_percent" min="1" max="100" value="<?= (int) ($lesson['min_progress_percent'] ?? 70); ?>" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                            </label>

                                            <label class="block lg:col-span-1">
                                                <span class="mb-1 block text-xs font-semibold text-slate-700">Ordem</span>
                                                <input type="number" name="display_order" min="1" value="<?= (int) ($lesson['display_order'] ?? 1); ?>" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                            </label>

                                            <div class="flex items-end gap-3 lg:col-span-1">
                                                <label class="inline-flex items-center gap-1 text-[11px] text-slate-700">
                                                    <input type="checkbox" name="is_required" value="1" <?= !empty($lesson['is_required']) ? 'checked' : ''; ?>>
                                                    Obrigatoria
                                                </label>
                                                <label class="inline-flex items-center gap-1 text-[11px] text-slate-700">
                                                    <input type="checkbox" name="is_active" value="1" <?= !empty($lesson['is_active']) ? 'checked' : ''; ?>>
                                                    Ativa
                                                </label>
                                            </div>

                                            <label class="block lg:col-span-9">
                                                <span class="mb-1 block text-xs font-semibold text-slate-700">Descricao da aula (opcional)</span>
                                                <textarea name="description" rows="2" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"><?= e((string) ($lesson['description'] ?? '')); ?></textarea>
                                            </label>

                                            <div class="flex items-end justify-end lg:col-span-3">
                                                <button class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium hover:bg-slate-100">Salvar aula</button>
                                            </div>
                                        </form>

                                        <form method="post" action="<?= route('courses/lessons/delete'); ?>" onsubmit="return confirm('Excluir esta aula?');" class="mt-2 flex justify-end">
                                            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                            <input type="hidden" name="course_id" value="<?= (int) $course['id']; ?>">
                                            <input type="hidden" name="module_id" value="<?= (int) $module['id']; ?>">
                                            <input type="hidden" name="lesson_id" value="<?= (int) $lesson['id']; ?>">
                                            <button class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700 hover:bg-rose-100">Excluir aula</button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>

                                <form method="post" action="<?= route('courses/lessons/store'); ?>" class="grid gap-3 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-3 lg:grid-cols-12">
                                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                    <input type="hidden" name="course_id" value="<?= (int) $course['id']; ?>">
                                    <input type="hidden" name="module_id" value="<?= (int) $module['id']; ?>">

                                    <label class="block lg:col-span-3">
                                        <span class="mb-1 block text-xs font-semibold text-slate-700">Nova aula *</span>
                                        <input type="text" name="title" required placeholder="Ex: Aula 3 - Revisao" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                    </label>

                                    <label class="block lg:col-span-5">
                                        <span class="mb-1 block text-xs font-semibold text-slate-700">URL do video *</span>
                                        <input type="text" name="video_url" required placeholder="Cole aqui o link da aula" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                    </label>

                                    <label class="block lg:col-span-1">
                                        <span class="mb-1 block text-xs font-semibold text-slate-700">Duracao (s)</span>
                                        <input type="number" name="duration_seconds" min="0" placeholder="0" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                    </label>

                                    <label class="block lg:col-span-1">
                                        <span class="mb-1 block text-xs font-semibold text-slate-700">% minimo</span>
                                        <input type="number" name="min_progress_percent" min="1" max="100" value="70" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                    </label>

                                    <label class="block lg:col-span-1">
                                        <span class="mb-1 block text-xs font-semibold text-slate-700">Ordem</span>
                                        <input type="number" name="display_order" min="1" placeholder="<?= (int) $moduleLessonCount + 1; ?>" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                    </label>

                                    <div class="flex items-end gap-3 lg:col-span-1">
                                        <label class="inline-flex items-center gap-1 text-[11px] text-slate-700">
                                            <input type="checkbox" name="is_required" value="1" checked>
                                            Obrigatoria
                                        </label>
                                        <label class="inline-flex items-center gap-1 text-[11px] text-slate-700">
                                            <input type="checkbox" name="is_active" value="1" checked>
                                            Ativa
                                        </label>
                                    </div>

                                    <label class="block lg:col-span-9">
                                        <span class="mb-1 block text-xs font-semibold text-slate-700">Descricao da nova aula (opcional)</span>
                                        <textarea name="description" rows="2" placeholder="Resumo rapido do que sera ensinado." class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"></textarea>
                                    </label>

                                    <div class="flex items-end justify-end lg:col-span-3">
                                        <button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">Adicionar aula</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </details>
                <?php endforeach; ?>

                <?php if ($courseModules === []): ?>
                    <p class="rounded-lg border border-dashed border-slate-300 px-3 py-4 text-center text-xs text-slate-500">Nenhum modulo cadastrado ainda. Comece criando o primeiro modulo acima.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        </div>
    </div>

    <?php if ($course): ?>
    <!-- ------------------------------------------------------------------ -->
    <!-- Seção: Aulas Online (Zoom)                                          -->
    <!-- ------------------------------------------------------------------ -->
    <div class="rounded-xl border border-slate-200 bg-white p-4">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-slate-800">Aulas Online (Zoom)</h3>
                <p class="mt-0.5 text-xs text-slate-500">Crie reuniões Zoom para este curso. Os alunos matriculados verão no portal.</p>
            </div>
            <?php if (!$zoomConfigured): ?>
                <a href="<?= route('courses/live-sessions/zoom-settings'); ?>"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-100 transition">
                    ⚙ Configurar credenciais Zoom
                </a>
            <?php endif; ?>
        </div>

        <?php if (!$zoomConfigured): ?>
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                Configure as credenciais Zoom antes de criar aulas online.
            </div>
        <?php else: ?>

        <!-- Formulário de nova aula online -->
        <details class="mb-4 rounded-xl border border-sky-300 bg-sky-100/80 shadow-sm">
            <summary class="cursor-pointer select-none rounded-xl border border-sky-400 bg-sky-200 px-4 py-3 text-sm font-semibold text-sky-950 hover:bg-sky-300 transition">
                <span class="inline-flex items-center gap-2">
                    <span class="text-sky-700">+</span>
                    <span>Nova Aula Online (Zoom)</span>
                </span>
            </summary>
            <div class="border-t border-sky-200 p-4">
                <form method="POST" action="index.php?route=courses/live-sessions/store" class="space-y-3">
                    <input type="hidden" name="route" value="courses/live-sessions/store">
                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                    <input type="hidden" name="course_id" value="<?= (int) $course['id']; ?>">
                    <input type="hidden" name="redirect_to" value="<?= e($backToCourse); ?>">

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block sm:col-span-2">
                            <span class="mb-1 block text-xs font-medium text-slate-700">Título da aula <span class="text-rose-500">*</span></span>
                            <input type="text" name="title" required maxlength="180"
                                   placeholder="Ex: Aula 01 — Introdução ao módulo"
                                   class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-100">
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-xs font-medium text-slate-700">Data e horário <span class="text-rose-500">*</span></span>
                            <input type="datetime-local" name="scheduled_at" required
                                   class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-100">
                            <span class="mt-0.5 block text-xs text-slate-400">Horário de Brasília (GMT-3)</span>
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-xs font-medium text-slate-700">Duração (minutos)</span>
                            <input type="number" name="duration_minutes" min="15" max="480" value="60"
                                   class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-100">
                        </label>

                        <label class="block sm:col-span-2">
                            <span class="mb-1 block text-xs font-medium text-slate-700">Observações (opcional)</span>
                            <textarea name="notes" rows="2"
                                      class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-100"></textarea>
                        </label>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit"
                                class="rounded-lg bg-sky-600 px-5 py-2 text-sm font-semibold text-white hover:bg-sky-700 transition">
                            Criar Reunião Zoom
                        </button>
                        <span class="text-xs text-slate-400">A reunião será criada automaticamente no Zoom.</span>
                    </div>
                </form>
            </div>
        </details>

        <?php endif; // zoomConfigured ?>

        <!-- Lista de sessões deste curso -->
        <?php if ($courseZoomSessions !== []): ?>
            <div class="space-y-2">
                <?php
                $sbadge = ['scheduled' => 'bg-emerald-100 text-emerald-700', 'cancelled' => 'bg-rose-100 text-rose-700'];
                $slabel = ['scheduled' => 'Agendada', 'cancelled' => 'Cancelada'];
                foreach ($courseZoomSessions as $zs):
                    $st      = (string) ($zs['status'] ?? 'scheduled');
                    $sched   = (string) ($zs['scheduled_at'] ?? '');
                    $schedFmt = $sched !== '' ? date('d/m/Y H:i', strtotime($sched)) : '—';
                ?>
                <div class="flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-slate-800 truncate"><?= e($zs['title'] ?? '—'); ?></p>
                        <p class="text-xs text-slate-500">
                            <?= e($schedFmt); ?> · <?= (int) ($zs['duration_minutes'] ?? 60); ?>min
                            <?php if (!empty($zs['zoom_meeting_id'])): ?>
                                · ID: <span class="font-mono"><?= e($zs['zoom_meeting_id']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($zs['zoom_password'])): ?>
                                · Senha: <span class="font-mono"><?= e($zs['zoom_password']); ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $sbadge[$st] ?? 'bg-slate-100 text-slate-600'; ?>">
                        <?= $slabel[$st] ?? $st; ?>
                    </span>
                    <?php if (!empty($zs['join_url'])): ?>
                        <a href="<?= e($zs['join_url']); ?>" target="_blank" rel="noopener"
                           class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-100 transition">
                            Entrar
                        </a>
                    <?php endif; ?>
                    <?php if ($st === 'scheduled'): ?>
                        <form method="POST" action="index.php?route=courses/live-sessions/cancel"
                              onsubmit="return confirm('Cancelar esta aula? A reunião também será removida do Zoom.')">
                            <input type="hidden" name="route" value="courses/live-sessions/cancel">
                            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                            <input type="hidden" name="id" value="<?= (int) $zs['id']; ?>">
                            <input type="hidden" name="redirect_to" value="<?= e($backToCourse); ?>">
                            <button type="submit"
                                    class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100 transition">
                                Cancelar
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php elseif ($zoomConfigured): ?>
            <p class="text-xs text-slate-400">Nenhuma aula online agendada para este curso ainda.</p>
        <?php endif; ?>
    </div>
    <?php endif; // $course ?>
</section>

<?php if ($focusedModuleId > 0): ?>
<script>
(function () {
    const details = document.getElementById('lms-module-<?= (int) $focusedModuleId; ?>');
    if (!details) {
        return;
    }

    details.open = true;
    window.requestAnimationFrame(function () {
        details.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
})();
</script>
<?php endif; ?>
