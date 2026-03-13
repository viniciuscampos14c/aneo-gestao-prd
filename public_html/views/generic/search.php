<section class="space-y-6">
    <div>
        <h2 class="text-2xl font-semibold">Busca Global</h2>
        <p class="text-sm text-slate-500">Resultados para "<?= e($query); ?>"</p>
    </div>

    <?php $groups = [
        'students' => ['label' => 'Alunos', 'route' => 'students/show&id=', 'link_type' => 'id'],
        'leads' => ['label' => 'Leads', 'route' => 'leads/edit&id=', 'link_type' => 'id'],
        'invoices' => ['label' => 'Faturas', 'route' => 'finance/invoices&q=', 'link_type' => 'title'],
        'courses' => ['label' => 'Cursos', 'route' => 'courses/edit&id=', 'link_type' => 'id'],
    ]; ?>

    <div class="grid gap-4 xl:grid-cols-2">
        <?php foreach ($groups as $key => $cfg): ?>
            <section class="rounded-xl border border-slate-200 bg-white p-4">
                <h3 class="mb-3 text-lg font-semibold"><?= e($cfg['label']); ?></h3>
                <div class="space-y-2 text-sm">
                    <?php foreach ($results[$key] as $item): ?>
                        <?php
                        $link = $cfg['link_type'] === 'id'
                            ? route($cfg['route'] . (int) $item['id'])
                            : route($cfg['route'] . urlencode((string) $item['title']));
                        ?>
                        <a href="<?= $link; ?>" class="block rounded-lg border border-slate-100 px-3 py-2 hover:bg-slate-50">
                            <p class="font-medium"><?= e($item['title']); ?></p>
                            <p class="text-xs text-slate-500"><?= e($item['subtitle']); ?></p>
                        </a>
                    <?php endforeach; ?>
                    <?php if (empty($results[$key])): ?>
                        <p class="text-slate-500">Sem resultados.</p>
                    <?php endif; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</section>
