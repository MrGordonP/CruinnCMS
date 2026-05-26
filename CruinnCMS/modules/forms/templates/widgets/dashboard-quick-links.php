<?php /** @var array $links */ ?>
<div class="dash-quick-grid">
    <?php foreach (($links ?? []) as $link): ?>
    <a href="<?= e($link['url'] ?? '#') ?>" class="dash-quick-link">
        <span class="dash-quick-icon"><?= e($link['icon'] ?? '🔗') ?></span>
        <span><?= e($link['label'] ?? 'Link') ?></span>
    </a>
    <?php endforeach; ?>
</div>
