<?php /** @var string $title */ /** @var array $stats */ /** @var string $primary_url */ ?>
<div class="activity-header"><h2><?= e($title ?? 'Notifications') ?></h2></div>
<div class="dash-quick-grid">
    <?php foreach (($stats ?? []) as $stat): ?>
    <a href="<?= e($primary_url ?? '#') ?>" class="dash-quick-link">
        <strong class="dash-stat-num"><?= (int) ($stat['value'] ?? 0) ?></strong>
        <span><?= e($stat['label'] ?? 'Metric') ?></span>
    </a>
    <?php endforeach; ?>
</div>
