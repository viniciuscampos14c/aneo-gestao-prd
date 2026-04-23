<?php
$canManage = has_permission('arsenal.manage');
$tabs = [
    'items' => 'Itens',
    'categories' => 'Categorias',
    'bindings' => 'Vinculos',
    'access' => 'Acessos',
];
$materialTypeLabels = [
    'file' => 'Arquivo',
    'link' => 'Link',
];
$scopeLabels = [
    'global' => 'Global',
    'course' => 'Por curso',
    'student' => 'Por aluno',
];
$statusLabels = [
    'draft' => 'Rascunho',
    'published' => 'Publicado',
    'archived' => 'Arquivado',
];
$editingItem = $editingItem ?? null;
$materialTypeSelected = (string) ($editingItem['material_type'] ?? 'file');
$publishStartInput = !empty($editingItem['publish_start_at']) ? date('Y-m-d\TH:i', strtotime((string) $editingItem['publish_start_at'])) : '';
$publishEndInput = !empty($editingItem['publish_end_at']) ? date('Y-m-d\TH:i', strtotime((string) $editingItem['publish_end_at'])) : '';
?>
<section class="arsenal-shell space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold">Arsenal Digital</h2>
            <p class="text-sm text-slate-500">Acervo digital para apoiar a jornada do aluno.</p>
        </div>
        <?php if ($tab === 'items' && $editingItem): ?>
            <a href="<?= route('arsenal&tab=items'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Cancelar edicao</a>
        <?php endif; ?>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <article class="arsenal-kpi arsenal-kpi-total rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase text-slate-500">Total Itens</p>
            <p class="mt-2 text-2xl font-semibold"><?= (int) ($stats['items_total'] ?? 0); ?></p>
        </article>
        <article class="arsenal-kpi arsenal-kpi-published rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase text-slate-500">Publicados</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-700"><?= (int) ($stats['items_published'] ?? 0); ?></p>
        </article>
        <article class="arsenal-kpi arsenal-kpi-categories rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase text-slate-500">Categorias</p>
            <p class="mt-2 text-2xl font-semibold"><?= (int) ($stats['categories_total'] ?? 0); ?></p>
        </article>
        <article class="arsenal-kpi arsenal-kpi-files rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase text-slate-500">Arquivos</p>
            <p class="mt-2 text-2xl font-semibold"><?= (int) ($stats['files_total'] ?? 0); ?></p>
        </article>
        <article class="arsenal-kpi arsenal-kpi-links rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase text-slate-500">Links</p>
            <p class="mt-2 text-2xl font-semibold"><?= (int) ($stats['links_total'] ?? 0); ?></p>
        </article>
    </div>

    <div class="flex flex-wrap gap-2">
        <?php foreach ($tabs as $tabKey => $tabLabel): ?>
            <?php $active = $tab === $tabKey ? 'arsenal-tab-active bg-slate-900 text-white' : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50'; ?>
            <a href="<?= route('arsenal&tab=' . $tabKey); ?>" class="arsenal-tab-btn rounded-lg px-3 py-2 text-sm font-medium <?= $active; ?>"><?= e($tabLabel); ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (!$featureAvailable): ?>
        <article class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            Estrutura do Arsenal indisponivel no banco. Execute a migracao `migrations/20260313_arsenal_digital.sql`.
        </article>
    <?php endif; ?>

    <?php if ($tab === 'items'): ?>
        <?php if ($canManage): ?>
            <form method="post" action="<?= $editingItem ? route('arsenal/item/update') : route('arsenal/item/store'); ?>" enctype="multipart/form-data" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 lg:grid-cols-6">
                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                <?php if ($editingItem): ?>
                    <input type="hidden" name="id" value="<?= (int) $editingItem['id']; ?>">
                <?php endif; ?>
                <label class="block lg:col-span-3">
                    <span class="mb-1 block text-sm font-medium">Titulo *</span>
                    <input type="text" name="title" required value="<?= e((string) ($editingItem['title'] ?? '')); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>
                <label class="block lg:col-span-2">
                    <span class="mb-1 block text-sm font-medium">Categoria</span>
                    <select name="category_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option value="0">Sem categoria</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id']; ?>" <?= (int) ($editingItem['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : ''; ?>>
                                <?= e($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm font-medium">Ordem</span>
                    <input type="number" name="sort_order" value="<?= (int) ($editingItem['sort_order'] ?? 0); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium">Tipo *</span>
                    <select name="material_type" id="arsenal-material-type" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <?php foreach ($materialTypeLabels as $value => $label): ?>
                            <option value="<?= e($value); ?>" <?= $materialTypeSelected === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm font-medium">Escopo *</span>
                    <select name="visibility_scope" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <?php foreach ($scopeLabels as $value => $label): ?>
                            <option value="<?= e($value); ?>" <?= (string) ($editingItem['visibility_scope'] ?? 'global') === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm font-medium">Status *</span>
                    <select name="status" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <?php foreach ($statusLabels as $value => $label): ?>
                            <option value="<?= e($value); ?>" <?= (string) ($editingItem['status'] ?? 'draft') === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm font-medium">Publicar de</span>
                    <input type="datetime-local" name="publish_start_at" value="<?= e($publishStartInput); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm font-medium">Publicar ate</span>
                    <input type="datetime-local" name="publish_end_at" value="<?= e($publishEndInput); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>

                <label class="block lg:col-span-4">
                    <span class="mb-1 block text-sm font-medium">Descricao</span>
                    <input type="text" name="description" value="<?= e((string) ($editingItem['description'] ?? '')); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>
                <label id="arsenal-file-wrapper" class="block lg:col-span-2">
                    <span class="mb-1 block text-sm font-medium">Arquivo <?= $editingItem ? '' : '*'; ?></span>
                    <input type="file" name="material_file" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <?php if ($editingItem && !empty($editingItem['file_name'])): ?>
                        <p class="mt-1 text-xs text-slate-500">Atual: <?= e((string) $editingItem['file_name']); ?></p>
                    <?php endif; ?>
                </label>
                <label id="arsenal-link-wrapper" class="block lg:col-span-2">
                    <span class="mb-1 block text-sm font-medium">URL externa</span>
                    <input type="url" name="external_url" value="<?= e((string) ($editingItem['external_url'] ?? '')); ?>" placeholder="https://..." class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>

                <div class="flex items-end justify-end lg:col-span-6">
                    <button class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700">
                        <?= $editingItem ? 'Atualizar item' : 'Criar item'; ?>
                    </button>
                </div>
            </form>
        <?php endif; ?>

        <form method="get" action="index.php" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-6">
            <input type="hidden" name="route" value="arsenal">
            <input type="hidden" name="tab" value="items">
            <input type="text" name="q" value="<?= e($itemFilters['q'] ?? ''); ?>" placeholder="Buscar por titulo, descricao ou arquivo..." class="rounded-lg border border-slate-200 px-3 py-2 text-sm md:col-span-2">
            <select name="status" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Todos status</option>
                <?php foreach ($statusLabels as $value => $label): ?>
                    <option value="<?= e($value); ?>" <?= (string) ($itemFilters['status'] ?? '') === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="material_type" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Todos tipos</option>
                <?php foreach ($materialTypeLabels as $value => $label): ?>
                    <option value="<?= e($value); ?>" <?= (string) ($itemFilters['material_type'] ?? '') === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="visibility_scope" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Todos escopos</option>
                <?php foreach ($scopeLabels as $value => $label): ?>
                    <option value="<?= e($value); ?>" <?= (string) ($itemFilters['visibility_scope'] ?? '') === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="category_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Todas categorias</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id']; ?>" <?= (string) ($itemFilters['category_id'] ?? '') === (string) $category['id'] ? 'selected' : ''; ?>>
                        <?= e((string) $category['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="flex gap-2">
                <select name="per_page" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <?php foreach ($paginationOptions as $opt): ?>
                        <option value="<?= (int) $opt; ?>" <?= (int) ($itemsMeta['per_page'] ?? 50) === (int) $opt ? 'selected' : ''; ?>><?= (int) $opt; ?>/pagina</option>
                    <?php endforeach; ?>
                </select>
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrar</button>
            </div>
        </form>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-3 py-3">Item</th>
                        <th class="px-3 py-3">Tipo</th>
                        <th class="px-3 py-3">Escopo</th>
                        <th class="px-3 py-3">Categoria</th>
                        <th class="px-3 py-3">Status</th>
                        <th class="px-3 py-3">Vinculos</th>
                        <th class="px-3 py-3">Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php
                        $status = (string) ($item['status'] ?? 'draft');
                        $statusBadge = match ($status) {
                            'published' => 'bg-emerald-100 text-emerald-700',
                            'archived' => 'bg-slate-200 text-slate-700',
                            default => 'bg-amber-100 text-amber-700',
                        };
                        ?>
                        <tr class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="px-3 py-3">
                                <p class="font-medium"><?= e((string) ($item['title'] ?? '')); ?></p>
                                <p class="text-xs text-slate-500"><?= e((string) ($item['description'] ?? '')); ?></p>
                            </td>
                            <td class="px-3 py-3"><?= e($materialTypeLabels[(string) ($item['material_type'] ?? 'file')] ?? '-'); ?></td>
                            <td class="px-3 py-3"><?= e($scopeLabels[(string) ($item['visibility_scope'] ?? 'global')] ?? '-'); ?></td>
                            <td class="px-3 py-3"><?= e((string) ($item['category_name'] ?? '-')); ?></td>
                            <td class="px-3 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-semibold <?= $statusBadge; ?>">
                                    <?= e($statusLabels[$status] ?? $status); ?>
                                </span>
                            </td>
                            <td class="px-3 py-3 text-xs text-slate-600">
                                <p>Cursos: <?= (int) ($item['linked_courses'] ?? 0); ?></p>
                                <p>Alunos: <?= (int) ($item['linked_students'] ?? 0); ?></p>
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <a href="<?= route('arsenal/download&id=' . (int) $item['id']); ?>" class="rounded border border-cyan-200 bg-cyan-50 px-2 py-1 text-xs text-cyan-700 hover:bg-cyan-100" target="_blank" rel="noopener">Abrir</a>
                                    <a href="<?= route('arsenal&tab=bindings&item_id=' . (int) $item['id']); ?>" class="rounded border border-slate-200 bg-white px-2 py-1 text-xs hover:bg-slate-100">Vinculos</a>
                                    <?php if ($canManage): ?>
                                        <a href="<?= route('arsenal&tab=items&edit_id=' . (int) $item['id']); ?>" class="rounded border border-slate-200 bg-white px-2 py-1 text-xs hover:bg-slate-100">Editar</a>
                                        <form method="post" action="<?= route('arsenal/item/delete'); ?>" onsubmit="return confirm('Excluir item do Arsenal?');">
                                            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                            <input type="hidden" name="id" value="<?= (int) $item['id']; ?>">
                                            <button class="rounded border border-rose-200 bg-rose-50 px-2 py-1 text-xs text-rose-700 hover:bg-rose-100">Excluir</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($items === []): ?>
                        <tr>
                            <td colspan="7" class="px-3 py-6 text-center text-slate-500">Nenhum item encontrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
            <p>Total: <?= (int) ($itemsMeta['total'] ?? 0); ?> registros | Pagina <?= (int) ($itemsMeta['page'] ?? 1); ?>/<?= (int) ($itemsMeta['pages'] ?? 1); ?></p>
            <div class="flex gap-2">
                <?php for ($p = 1; $p <= (int) ($itemsMeta['pages'] ?? 1); $p++): ?>
                    <a href="index.php?<?= build_query(['route' => 'arsenal', 'tab' => 'items', 'page' => $p]); ?>" class="rounded px-3 py-1 <?= $p === (int) ($itemsMeta['page'] ?? 1) ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white hover:bg-slate-50'; ?>"><?= $p; ?></a>
                <?php endfor; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($tab === 'categories'): ?>
        <?php if ($canManage): ?>
            <form method="post" action="<?= route('arsenal/category/store'); ?>" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-5">
                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                <input type="text" name="name" required placeholder="Nome da categoria" class="rounded-lg border border-slate-200 px-3 py-2 text-sm md:col-span-2">
                <input type="text" name="description" placeholder="Descricao" class="rounded-lg border border-slate-200 px-3 py-2 text-sm md:col-span-2">
                <button class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700">Criar categoria</button>
            </form>
        <?php endif; ?>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-3 py-3">Nome</th>
                        <th class="px-3 py-3">Descricao</th>
                        <th class="px-3 py-3">Itens</th>
                        <th class="px-3 py-3">Status</th>
                        <th class="px-3 py-3">Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                        <tr class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="px-3 py-3 font-medium"><?= e((string) $category['name']); ?></td>
                            <td class="px-3 py-3"><?= e((string) ($category['description'] ?? '')); ?></td>
                            <td class="px-3 py-3"><?= (int) ($category['items_total'] ?? 0); ?></td>
                            <td class="px-3 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-semibold <?= (int) ($category['is_active'] ?? 0) === 1 ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700'; ?>">
                                    <?= (int) ($category['is_active'] ?? 0) === 1 ? 'Ativa' : 'Inativa'; ?>
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <?php if ($canManage): ?>
                                    <div class="flex flex-wrap gap-2">
                                        <form method="post" action="<?= route('arsenal/category/update'); ?>" class="flex flex-wrap items-center gap-2">
                                            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                            <input type="hidden" name="id" value="<?= (int) $category['id']; ?>">
                                            <input type="text" name="name" value="<?= e((string) $category['name']); ?>" class="rounded border border-slate-200 px-2 py-1 text-xs">
                                            <input type="text" name="description" value="<?= e((string) ($category['description'] ?? '')); ?>" class="rounded border border-slate-200 px-2 py-1 text-xs">
                                            <label class="inline-flex items-center gap-1 text-xs">
                                                <input type="checkbox" name="is_active" value="1" <?= (int) ($category['is_active'] ?? 0) === 1 ? 'checked' : ''; ?>>
                                                Ativa
                                            </label>
                                            <button class="rounded border border-slate-200 px-2 py-1 text-xs hover:bg-slate-100">Salvar</button>
                                        </form>
                                        <form method="post" action="<?= route('arsenal/category/delete'); ?>" onsubmit="return confirm('Excluir categoria?');">
                                            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                            <input type="hidden" name="id" value="<?= (int) $category['id']; ?>">
                                            <button class="rounded border border-rose-200 bg-rose-50 px-2 py-1 text-xs text-rose-700 hover:bg-rose-100">Excluir</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($categories === []): ?>
                        <tr>
                            <td colspan="5" class="px-3 py-6 text-center text-slate-500">Nenhuma categoria cadastrada.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($tab === 'bindings'): ?>
        <form method="get" action="index.php" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-[1fr_auto_auto]">
            <input type="hidden" name="route" value="arsenal">
            <input type="hidden" name="tab" value="bindings">
            <select name="item_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Selecione um item</option>
                <?php foreach ($allItemsForBinding as $itemOption): ?>
                    <option value="<?= (int) $itemOption['id']; ?>" <?= (int) ($bindingItem['id'] ?? 0) === (int) $itemOption['id'] ? 'selected' : ''; ?>>
                        #<?= (int) $itemOption['id']; ?> - <?= e((string) $itemOption['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Carregar</button>
            <a href="<?= route('arsenal&tab=bindings'); ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">Limpar</a>
        </form>

        <?php if (!$bindingItem): ?>
            <article class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-500">
                Selecione um item para gerenciar vinculos com cursos ou alunos.
            </article>
        <?php else: ?>
            <article class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-sm text-slate-500">Item selecionado</p>
                <h3 class="text-lg font-semibold text-slate-900"><?= e((string) $bindingItem['title']); ?></h3>
                <p class="text-xs text-slate-500">Escopo: <?= e($scopeLabels[(string) ($bindingItem['visibility_scope'] ?? 'global')] ?? '-'); ?></p>
            </article>

            <?php if ((string) ($bindingItem['visibility_scope'] ?? '') === 'global'): ?>
                <article class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">
                    Item com escopo global: todos os alunos com acesso ao portal podem visualizar.
                </article>
            <?php endif; ?>

            <?php if ((string) ($bindingItem['visibility_scope'] ?? '') === 'course'): ?>
                <?php if ($canManage): ?>
                    <form method="post" action="<?= route('arsenal/bind/course'); ?>" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-[1fr_auto]">
                        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                        <input type="hidden" name="item_id" value="<?= (int) $bindingItem['id']; ?>">
                        <select name="course_id" required class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <option value="">Selecione um curso</option>
                            <?php foreach ($availableCourses as $course): ?>
                                <option value="<?= (int) $course['id']; ?>"><?= e((string) $course['name']); ?> (<?= e((string) $course['status']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <button class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700">Vincular curso</button>
                    </form>
                <?php endif; ?>

                <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                                <th class="px-3 py-3">Curso</th>
                                <th class="px-3 py-3">Status</th>
                                <th class="px-3 py-3">Vinculado em</th>
                                <th class="px-3 py-3">Acoes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bindingCourses as $binding): ?>
                                <tr class="border-b border-slate-100 hover:bg-slate-50">
                                    <td class="px-3 py-3 font-medium"><?= e((string) $binding['course_name']); ?></td>
                                    <td class="px-3 py-3"><?= e((string) $binding['course_status']); ?></td>
                                    <td class="px-3 py-3"><?= e((string) $binding['created_at']); ?></td>
                                    <td class="px-3 py-3">
                                        <?php if ($canManage): ?>
                                            <form method="post" action="<?= route('arsenal/unbind/course'); ?>" onsubmit="return confirm('Remover vinculo deste curso?');">
                                                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                                <input type="hidden" name="item_id" value="<?= (int) $bindingItem['id']; ?>">
                                                <input type="hidden" name="course_id" value="<?= (int) $binding['course_id']; ?>">
                                                <button class="rounded border border-rose-200 bg-rose-50 px-2 py-1 text-xs text-rose-700 hover:bg-rose-100">Desvincular</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($bindingCourses === []): ?>
                                <tr>
                                    <td colspan="4" class="px-3 py-6 text-center text-slate-500">Nenhum curso vinculado.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ((string) ($bindingItem['visibility_scope'] ?? '') === 'student'): ?>
                <?php if ($canManage): ?>
                    <form method="post" action="<?= route('arsenal/bind/student'); ?>" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-[1fr_auto]">
                        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                        <input type="hidden" name="item_id" value="<?= (int) $bindingItem['id']; ?>">
                        <select name="student_id" required class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <option value="">Selecione um aluno</option>
                            <?php foreach ($availableStudents as $student): ?>
                                <option value="<?= (int) $student['id']; ?>"><?= e((string) $student['full_name']); ?><?= !empty($student['email_primary']) ? ' - ' . e((string) $student['email_primary']) : ''; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700">Vincular aluno</button>
                    </form>
                <?php endif; ?>

                <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                                <th class="px-3 py-3">Aluno</th>
                                <th class="px-3 py-3">Email</th>
                                <th class="px-3 py-3">Status</th>
                                <th class="px-3 py-3">Vinculado em</th>
                                <th class="px-3 py-3">Acoes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bindingStudents as $binding): ?>
                                <tr class="border-b border-slate-100 hover:bg-slate-50">
                                    <td class="px-3 py-3 font-medium"><?= e((string) $binding['student_name']); ?></td>
                                    <td class="px-3 py-3"><?= e((string) ($binding['student_email'] ?? '')); ?></td>
                                    <td class="px-3 py-3"><?= (int) ($binding['student_active'] ?? 0) === 1 ? 'Ativo' : 'Inativo'; ?></td>
                                    <td class="px-3 py-3"><?= e((string) $binding['created_at']); ?></td>
                                    <td class="px-3 py-3">
                                        <?php if ($canManage): ?>
                                            <form method="post" action="<?= route('arsenal/unbind/student'); ?>" onsubmit="return confirm('Remover vinculo deste aluno?');">
                                                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                                <input type="hidden" name="item_id" value="<?= (int) $bindingItem['id']; ?>">
                                                <input type="hidden" name="student_id" value="<?= (int) $binding['student_id']; ?>">
                                                <button class="rounded border border-rose-200 bg-rose-50 px-2 py-1 text-xs text-rose-700 hover:bg-rose-100">Desvincular</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($bindingStudents === []): ?>
                                <tr>
                                    <td colspan="5" class="px-3 py-6 text-center text-slate-500">Nenhum aluno vinculado.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($tab === 'access'): ?>
        <form method="get" action="index.php" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-5">
            <input type="hidden" name="route" value="arsenal">
            <input type="hidden" name="tab" value="access">
            <input type="text" name="log_q" value="<?= e($accessFilters['q'] ?? ''); ?>" placeholder="Buscar por item, aluno, email ou IP..." class="rounded-lg border border-slate-200 px-3 py-2 text-sm md:col-span-2">
            <select name="log_action" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Todas acoes</option>
                <option value="open_file" <?= (string) ($accessFilters['action'] ?? '') === 'open_file' ? 'selected' : ''; ?>>open_file</option>
                <option value="open_link" <?= (string) ($accessFilters['action'] ?? '') === 'open_link' ? 'selected' : ''; ?>>open_link</option>
            </select>
            <select name="log_per_page" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <?php foreach ($paginationOptions as $opt): ?>
                    <option value="<?= (int) $opt; ?>" <?= (int) ($accessMeta['per_page'] ?? 50) === (int) $opt ? 'selected' : ''; ?>><?= (int) $opt; ?>/pagina</option>
                <?php endforeach; ?>
            </select>
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrar</button>
        </form>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-3 py-3">Data</th>
                        <th class="px-3 py-3">Aluno</th>
                        <th class="px-3 py-3">Item</th>
                        <th class="px-3 py-3">Acao</th>
                        <th class="px-3 py-3">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($accessRows as $log): ?>
                        <tr class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="px-3 py-3"><?= e((string) ($log['created_at'] ?? '')); ?></td>
                            <td class="px-3 py-3">
                                <p class="font-medium"><?= e((string) ($log['student_name'] ?? '')); ?></p>
                                <p class="text-xs text-slate-500"><?= e((string) ($log['student_email'] ?? '')); ?></p>
                            </td>
                            <td class="px-3 py-3"><?= e((string) ($log['item_title'] ?? '')); ?></td>
                            <td class="px-3 py-3"><?= e((string) ($log['action'] ?? '')); ?></td>
                            <td class="px-3 py-3"><?= e((string) ($log['ip_address'] ?? '-')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($accessRows === []): ?>
                        <tr>
                            <td colspan="5" class="px-3 py-6 text-center text-slate-500">Nenhum acesso registrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
            <p>Total: <?= (int) ($accessMeta['total'] ?? 0); ?> registros | Pagina <?= (int) ($accessMeta['page'] ?? 1); ?>/<?= (int) ($accessMeta['pages'] ?? 1); ?></p>
            <div class="flex gap-2">
                <?php for ($p = 1; $p <= (int) ($accessMeta['pages'] ?? 1); $p++): ?>
                    <a href="index.php?<?= build_query(['route' => 'arsenal', 'tab' => 'access', 'log_page' => $p]); ?>" class="rounded px-3 py-1 <?= $p === (int) ($accessMeta['page'] ?? 1) ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white hover:bg-slate-50'; ?>"><?= $p; ?></a>
                <?php endfor; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<script>
(() => {
    const typeSelect = document.getElementById('arsenal-material-type');
    const fileWrapper = document.getElementById('arsenal-file-wrapper');
    const linkWrapper = document.getElementById('arsenal-link-wrapper');
    if (!typeSelect || !fileWrapper || !linkWrapper) {
        return;
    }

    const syncVisibility = () => {
        const isFile = typeSelect.value === 'file';
        fileWrapper.style.display = isFile ? '' : 'none';
        linkWrapper.style.display = isFile ? 'none' : '';
    };

    typeSelect.addEventListener('change', syncVisibility);
    syncVisibility();
})();
</script>
