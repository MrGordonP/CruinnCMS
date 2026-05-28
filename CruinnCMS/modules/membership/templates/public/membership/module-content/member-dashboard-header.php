<?php
$member = $member ?? null;
$current_user = $current_user ?? null;
$isAdmin = !empty($isAdmin);
$isCouncil = !empty($isCouncil);

$userName = $member
    ? e(trim((string) ($member['forenames'] ?? '') . ' ' . (string) ($member['surnames'] ?? '')))
    : e($current_user['display_name'] ?? $current_user['name'] ?? $current_user['email'] ?? 'there');
?>
<div class="my-account-header">
    <div class="my-account-greeting">
        <h1>Hello, <?= $userName ?></h1>
        <?php if ($member): ?>
        <span class="badge badge-<?= e((string) ($member['status'] ?? '')) ?>"><?= e(ucfirst((string) ($member['status'] ?? ''))) ?></span>
        <?php if (!empty($member['type_name'])): ?><span class="badge badge-outline"><?= e((string) $member['type_name']) ?></span><?php endif; ?>
        <?php endif; ?>
    </div>
    <?php if ($isAdmin): ?>
    <a href="<?= url('/admin') ?>" class="btn btn-primary">&#x2699; Admin Panel</a>
    <?php elseif ($isCouncil): ?>
    <a href="<?= url('/council') ?>" class="btn btn-primary">Council Workspace &rarr;</a>
    <?php endif; ?>
</div>
