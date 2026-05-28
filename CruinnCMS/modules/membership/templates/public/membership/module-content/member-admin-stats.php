<?php if (!empty($adminStats)): ?>
<div class="detail-card my-account-admin-stats">
    <h2>Site at a glance</h2>
    <div class="dash-quick-grid">
        <a href="<?= url('/admin/pages') ?>" class="dash-quick-link"><span class="dash-quick-icon">&#x1F4C4;</span><span><?= (int) ($adminStats['pages'] ?? 0) ?> Pages</span></a>
        <a href="<?= url('/admin/members') ?>" class="dash-quick-link"><span class="dash-quick-icon">&#x1F465;</span><span><?= (int) ($adminStats['members'] ?? 0) ?> Members</span></a>
        <a href="<?= url('/admin/users') ?>" class="dash-quick-link"><span class="dash-quick-icon">&#x1F511;</span><span><?= (int) ($adminStats['users'] ?? 0) ?> Users</span></a>
        <a href="<?= url('/admin/site-builder') ?>" class="dash-quick-link"><span class="dash-quick-icon">&#x1F3D7;&#xFE0F;</span><span>Site Builder</span></a>
        <a href="<?= url('/admin/settings/site') ?>" class="dash-quick-link"><span class="dash-quick-icon">&#x2699;&#xFE0F;</span><span>Settings</span></a>
    </div>
</div>
<?php endif; ?>
