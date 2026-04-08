<div class="page-home">
    <?php if (!empty($blocks)): ?>
        <?php foreach ($blocks as $block): ?>
            <?php include __DIR__ . '/../components/block.php'; ?>
        <?php endforeach; ?>
    <?php else: ?>
        <section class="hero">
            <div class="container">
                <h1><?= e($site_name ?? 'Welcome') ?></h1>
                <?php if (!empty($site_tagline)): ?>
                <p class="hero-text"><?= e($site_tagline) ?></p>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
