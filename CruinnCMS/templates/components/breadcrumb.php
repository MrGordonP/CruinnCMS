<?php
/**
 * Breadcrumb Component
 *
 * Renders a breadcrumb navigation trail.
 * 
 * Usage in templates:
 *   <?php $breadcrumbs = [['Admin', '/admin'], ['Users', '/admin/users'], ['John Smith']]; ?>
 *   <?php include __DIR__ . '/../components/breadcrumb.php'; ?>
 *
 * Each item is an array: [label] or [label, url]
 * The last item is assumed to be the current page (no link).
 */
if (!empty($breadcrumbs)):
?>
<nav class="breadcrumb" aria-label="Breadcrumb">
    <ol class="breadcrumb-list">
        <?php foreach ($breadcrumbs as $i => $crumb): ?>
            <li class="breadcrumb-item<?= $i === count($breadcrumbs) - 1 ? ' breadcrumb-current' : '' ?>">
                <?php if (isset($crumb[1]) && $i < count($breadcrumbs) - 1): ?>
                    <a href="<?= e($crumb[1]) ?>"><?= e($crumb[0]) ?></a>
                <?php else: ?>
                    <span><?= e($crumb[0]) ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
<?php endif; ?>
