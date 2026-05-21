<div class="course-cards-grid">
    <?php foreach ($rows as $row): ?>
        <?php require __DIR__ . '/course_card.php'; ?>
    <?php endforeach; ?>

    <?php if ($rows === []): ?>
        <article class="course-catalog-panel rounded-2xl px-6 py-10 text-center text-sm text-slate-300">
            Nenhum curso encontrado no filtro atual.
        </article>
    <?php endif; ?>
</div>
