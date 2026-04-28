<?php
/** @var array $sidebar_widgets Collected from active modules via ModuleRegistry::collectWidgets() */
if (empty($sidebar_widgets)) {
    return;
}
?>
<aside class="site-sidebar" aria-label="Sidebar">
    <?php foreach ($sidebar_widgets as $widget): ?>
    <section class="widget">
        <?php if (!empty($widget['title'])): ?>
        <h2 class="widget-title"><?= htmlspecialchars($widget['title'], ENT_QUOTES, 'UTF-8') ?></h2>
        <?php endif; ?>
        <div class="widget-body">
            <?= $widget['html'] ?? '' ?>
        </div>
    </section>
    <?php endforeach; ?>
</aside>

