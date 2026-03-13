<?php
$total = (int) ($meta['total'] ?? 0);
$page = (int) ($meta['page'] ?? 1);
$pages = (int) ($meta['pages'] ?? 1);
$perPage = (int) ($meta['per_page'] ?? ($baseParams['per_page'] ?? 50));

$queryBase = array_merge($baseParams, [
    'route' => 'finance/reports',
    'tab' => $tab,
    'per_page' => $perPage,
]);

$start = max(1, $page - 2);
$end = min($pages, $page + 2);
?>
<div class="flex flex-wrap items-center justify-between gap-3 text-sm">
    <p>Total: <?= $total; ?> registros | Pagina <?= $page; ?>/<?= $pages; ?></p>
    <?php if ($pages > 1): ?>
        <div class="flex flex-wrap gap-2">
            <?php if ($page > 1): ?>
                <a href="index.php?<?= http_build_query(array_merge($queryBase, ['page' => $page - 1])); ?>" class="rounded border border-slate-200 bg-white px-3 py-1 hover:bg-slate-50">Anterior</a>
            <?php endif; ?>

            <?php if ($start > 1): ?>
                <a href="index.php?<?= http_build_query(array_merge($queryBase, ['page' => 1])); ?>" class="rounded border border-slate-200 bg-white px-3 py-1 hover:bg-slate-50">1</a>
                <?php if ($start > 2): ?>
                    <span class="px-1 py-1 text-slate-400">...</span>
                <?php endif; ?>
            <?php endif; ?>

            <?php for ($p = $start; $p <= $end; $p++): ?>
                <a href="index.php?<?= http_build_query(array_merge($queryBase, ['page' => $p])); ?>" class="rounded px-3 py-1 <?= $p === $page ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white hover:bg-slate-50'; ?>"><?= $p; ?></a>
            <?php endfor; ?>

            <?php if ($end < $pages): ?>
                <?php if ($end < $pages - 1): ?>
                    <span class="px-1 py-1 text-slate-400">...</span>
                <?php endif; ?>
                <a href="index.php?<?= http_build_query(array_merge($queryBase, ['page' => $pages])); ?>" class="rounded border border-slate-200 bg-white px-3 py-1 hover:bg-slate-50"><?= $pages; ?></a>
            <?php endif; ?>

            <?php if ($page < $pages): ?>
                <a href="index.php?<?= http_build_query(array_merge($queryBase, ['page' => $page + 1])); ?>" class="rounded border border-slate-200 bg-white px-3 py-1 hover:bg-slate-50">Proxima</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
