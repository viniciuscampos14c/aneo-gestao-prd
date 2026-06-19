<?php
$materialTypeLabels = [
    'file' => 'Arquivo',
    'link' => 'Link',
];
$scopeLabels = [
    'global' => 'Global',
    'course' => 'Curso',
    'student' => 'Aluno',
];
?>
<section class="space-y-6">
    <div>
        <h2 class="text-2xl font-semibold">Arsenal do Aluno</h2>
        <p class="text-sm text-slate-500">Biblioteca de livros digitais e materiais de apoio liberados para você.</p>
    </div>

    <form method="get" action="index.php" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-4">
        <input type="hidden" name="route" value="student/arsenal">
        <input type="text" name="q" value="<?= e((string) ($filters['q'] ?? '')); ?>" placeholder="Buscar material..." class="rounded-lg border border-slate-200 px-3 py-2 text-sm md:col-span-2">

        <select name="material_type" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option value="">Todos tipos</option>
            <?php foreach ($materialTypeLabels as $key => $label): ?>
                <option value="<?= e($key); ?>" <?= (string) ($filters['material_type'] ?? '') === $key ? 'selected' : ''; ?>><?= e($label); ?></option>
            <?php endforeach; ?>
        </select>

        <select name="category_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option value="0">Todas categorias</option>
            <?php foreach ($categories as $categoryId => $categoryName): ?>
                <option value="<?= (int) $categoryId; ?>" <?= (int) ($filters['category_id'] ?? 0) === (int) $categoryId ? 'selected' : ''; ?>><?= e((string) $categoryName); ?></option>
            <?php endforeach; ?>
        </select>

        <div class="md:col-span-4 flex justify-end">
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrar</button>
        </div>
    </form>

    <div class="grid gap-4">
        <?php foreach ($rows as $row): ?>
            <article class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900"><?= e((string) ($row['title'] ?? '')); ?></h3>
                        <p class="mt-1 text-sm text-slate-600"><?= e((string) ($row['description'] ?? '')); ?></p>
                        <p class="mt-2 text-xs text-slate-500">
                            Categoria: <?= e((string) ($row['category_name'] ?? 'Sem categoria')); ?>
                            | Tipo: <?= e($materialTypeLabels[(string) ($row['material_type'] ?? 'file')] ?? '-'); ?>
                            | Escopo: <?= e($scopeLabels[(string) ($row['visibility_scope'] ?? 'global')] ?? '-'); ?>
                        </p>
                    </div>
                    <a href="<?= route('student/arsenal/open&id=' . (int) $row['id']); ?>" target="_blank" rel="noopener" class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700">
                        Abrir material
                    </a>
                </div>
            </article>
        <?php endforeach; ?>

        <?php if ($rows === []): ?>
            <article class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
                Nenhum material disponível com os filtros atuais.
            </article>
        <?php endif; ?>
    </div>
</section>
