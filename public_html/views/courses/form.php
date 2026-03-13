<?php
$materialFiles = $materialFiles ?? [];
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
</section>
