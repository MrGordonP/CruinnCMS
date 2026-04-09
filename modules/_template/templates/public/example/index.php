<h1><?= e($title ?? 'Example') ?></h1>

<?php if (empty($items)): ?>
<p>No content has been published yet.</p>
<?php else: ?>
<ul>
    <?php foreach ($items as $item): ?>
    <li><?= e($item['title']) ?></li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>
