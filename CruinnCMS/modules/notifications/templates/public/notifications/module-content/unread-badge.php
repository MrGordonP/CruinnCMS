<?php /** @var int $count */ /** @var string $label */ /** @var string $basePath */ ?>
<a href="<?= url($basePath ?? '/notifications') ?>" class="badge badge-primary" title="<?= e($label ?? 'Unread Notifications') ?>">
    <?= (int) ($count ?? 0) ?>
</a>
