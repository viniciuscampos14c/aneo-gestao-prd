<?php
$materialFiles = $materialFiles ?? [];
$lmsFeatureAvailable = $lmsFeatureAvailable ?? false;
$courseModules = $courseModules ?? [];
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

    <div class="rounded-xl border border-slate-200 bg-white p-4">
        <div class="mb-4">
            <h3 class="text-sm font-semibold text-slate-800">Trilha LMS (Modulos e Aulas)</h3>
            <p class="mt-1 text-xs text-slate-500">Configure a ordem dos modulos e aulas. O portal do aluno bloqueia o proximo modulo ate concluir o anterior.</p>
        </div>

        <?php if (!$lmsFeatureAvailable): ?>
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                Execute a migration de LMS para habilitar esta area: <code>migrations/20260317_lms_learning_path.sql</code>.
            </div>
        <?php elseif (!$course): ?>
            <p class="text-xs text-slate-500">Salve o curso primeiro para cadastrar modulos e aulas.</p>
        <?php else: ?>
            <form method="post" action="<?= route('courses/modules/store'); ?>" class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 lg:grid-cols-4">
                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                <input type="hidden" name="course_id" value="<?= (int) $course['id']; ?>">
                <input type="text" name="title" required placeholder="Novo modulo (titulo)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm lg:col-span-2">
                <input type="number" name="display_order" min="1" placeholder="Ordem" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                <div class="flex items-center gap-2">
                    <label class="flex items-center gap-2 text-xs text-slate-600">
                        <input type="checkbox" name="is_active" value="1" checked>
                        Ativo
                    </label>
                    <button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">Adicionar modulo</button>
                </div>
                <textarea name="description" rows="2" placeholder="Descricao do modulo (opcional)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm lg:col-span-4"></textarea>
            </form>

            <div class="mt-4 space-y-4">
                <?php foreach ($courseModules as $module): ?>
                    <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <form method="post" action="<?= route('courses/modules/update'); ?>" class="grid gap-2 lg:grid-cols-5">
                            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                            <input type="hidden" name="course_id" value="<?= (int) $course['id']; ?>">
                            <input type="hidden" name="module_id" value="<?= (int) $module['id']; ?>">
                            <input type="text" name="title" required value="<?= e((string) $module['title']); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm lg:col-span-2">
                            <input type="number" name="display_order" min="1" value="<?= (int) $module['display_order']; ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                            <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600">
                                <input type="checkbox" name="is_active" value="1" <?= !empty($module['is_active']) ? 'checked' : ''; ?>>
                                Modulo ativo
                            </label>
                            <div class="flex items-center gap-2">
                                <button class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium hover:bg-slate-100">Salvar modulo</button>
                            </div>
                            <textarea name="description" rows="2" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm lg:col-span-5"><?= e((string) ($module['description'] ?? '')); ?></textarea>
                        </form>
                        <form method="post" action="<?= route('courses/modules/delete'); ?>" onsubmit="return confirm('Remover modulo e todas as aulas?');" class="mt-2 flex justify-end">
                            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                            <input type="hidden" name="course_id" value="<?= (int) $course['id']; ?>">
                            <input type="hidden" name="module_id" value="<?= (int) $module['id']; ?>">
                            <button class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700 hover:bg-rose-100">Excluir modulo</button>
                        </form>

                        <div class="mt-3 space-y-2">
                            <?php foreach (($module['lessons'] ?? []) as $lesson): ?>
                                <form method="post" action="<?= route('courses/lessons/update'); ?>" class="grid gap-2 rounded-lg border border-slate-200 bg-white p-3 lg:grid-cols-12">
                                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                    <input type="hidden" name="course_id" value="<?= (int) $course['id']; ?>">
                                    <input type="hidden" name="lesson_id" value="<?= (int) $lesson['id']; ?>">
                                    <input type="text" name="title" required value="<?= e((string) $lesson['title']); ?>" placeholder="Titulo da aula" class="rounded-lg border border-slate-200 px-3 py-2 text-sm lg:col-span-3">
                                    <input type="text" name="video_url" required value="<?= e((string) ($lesson['video_url'] ?? '')); ?>" placeholder="URL direta do video" class="rounded-lg border border-slate-200 px-3 py-2 text-sm lg:col-span-5">
                                    <input type="number" name="duration_seconds" min="0" value="<?= (int) ($lesson['duration_seconds'] ?? 0); ?>" placeholder="Duracao(s)" class="rounded-lg border border-slate-200 px-3 py-2 text-sm lg:col-span-1">
                                    <input type="number" name="min_progress_percent" min="1" max="100" value="<?= (int) ($lesson['min_progress_percent'] ?? 70); ?>" placeholder="% minimo" class="rounded-lg border border-slate-200 px-3 py-2 text-sm lg:col-span-1">
                                    <input type="number" name="display_order" min="1" value="<?= (int) ($lesson['display_order'] ?? 1); ?>" placeholder="Ordem" class="rounded-lg border border-slate-200 px-3 py-2 text-sm lg:col-span-1">
                                    <div class="flex items-center gap-3 lg:col-span-1">
                                        <label class="flex items-center gap-1 text-[11px] text-slate-600">
                                            <input type="checkbox" name="is_required" value="1" <?= !empty($lesson['is_required']) ? 'checked' : ''; ?>>
                                            Obrig.
                                        </label>
                                        <label class="flex items-center gap-1 text-[11px] text-slate-600">
                                            <input type="checkbox" name="is_active" value="1" <?= !empty($lesson['is_active']) ? 'checked' : ''; ?>>
                                            Ativa
                                        </label>
                                    </div>
                                    <textarea name="description" rows="2" placeholder="Descricao da aula (opcional)" class="rounded-lg border border-slate-200 px-3 py-2 text-sm lg:col-span-9"><?= e((string) ($lesson['description'] ?? '')); ?></textarea>
                                    <div class="flex items-center justify-end gap-2 lg:col-span-3">
                                        <button class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium hover:bg-slate-100">Salvar aula</button>
                                    </div>
                                </form>
                                <form method="post" action="<?= route('courses/lessons/delete'); ?>" onsubmit="return confirm('Excluir esta aula?');" class="mt-1 flex justify-end">
                                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                    <input type="hidden" name="course_id" value="<?= (int) $course['id']; ?>">
                                    <input type="hidden" name="lesson_id" value="<?= (int) $lesson['id']; ?>">
                                    <button class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700 hover:bg-rose-100">Excluir aula</button>
                                </form>
                            <?php endforeach; ?>

                            <form method="post" action="<?= route('courses/lessons/store'); ?>" class="grid gap-2 rounded-lg border border-dashed border-slate-300 bg-white p-3 lg:grid-cols-12">
                                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                <input type="hidden" name="course_id" value="<?= (int) $course['id']; ?>">
                                <input type="hidden" name="module_id" value="<?= (int) $module['id']; ?>">
                                <input type="text" name="title" required placeholder="Nova aula (titulo)" class="rounded-lg border border-slate-200 px-3 py-2 text-sm lg:col-span-3">
                                <input type="text" name="video_url" required placeholder="URL direta do video (MP4/WebM)" class="rounded-lg border border-slate-200 px-3 py-2 text-sm lg:col-span-5">
                                <input type="number" name="duration_seconds" min="0" placeholder="Duracao(s)" class="rounded-lg border border-slate-200 px-3 py-2 text-sm lg:col-span-1">
                                <input type="number" name="min_progress_percent" min="1" max="100" value="70" class="rounded-lg border border-slate-200 px-3 py-2 text-sm lg:col-span-1">
                                <input type="number" name="display_order" min="1" placeholder="Ordem" class="rounded-lg border border-slate-200 px-3 py-2 text-sm lg:col-span-1">
                                <div class="flex items-center gap-3 lg:col-span-1">
                                    <label class="flex items-center gap-1 text-[11px] text-slate-600">
                                        <input type="checkbox" name="is_required" value="1" checked>
                                        Obrig.
                                    </label>
                                    <label class="flex items-center gap-1 text-[11px] text-slate-600">
                                        <input type="checkbox" name="is_active" value="1" checked>
                                        Ativa
                                    </label>
                                </div>
                                <textarea name="description" rows="2" placeholder="Descricao da nova aula (opcional)" class="rounded-lg border border-slate-200 px-3 py-2 text-sm lg:col-span-9"></textarea>
                                <div class="flex items-center justify-end lg:col-span-3">
                                    <button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">Adicionar aula</button>
                                </div>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>

                <?php if ($courseModules === []): ?>
                    <p class="rounded-lg border border-dashed border-slate-300 px-3 py-4 text-center text-xs text-slate-500">Nenhum modulo cadastrado ainda.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
