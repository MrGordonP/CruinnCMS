<?php
\Cruinn\Template::requireCss('admin-panel-layout.css');
\Cruinn\Template::requireCss('admin-site-builder.css');
$GLOBALS['admin_flush_layout'] = true;
$profiles = $profiles ?? [];
?>

<div class="panel-layout no-detail" id="events-layout">
<div class="pl-panel pl-panel-left">
    <div class="pl-panel-header">
        <h3>Events</h3>
        <a href="<?= url('/admin/events/profiles/new') ?>" class="btn btn-sm btn-primary">+ New</a>
    </div>
    <div class="pl-panel-body" style="padding:0">
        <div class="pl-nav-section">Manage</div>
        <a class="pl-nav-item" href="<?= url('/admin/events') ?>">Overview</a>
        <a class="pl-nav-item" href="<?= url('/admin/events/list') ?>">Events</a>
        <a class="pl-nav-item active" href="<?= url('/admin/events/profiles') ?>">Profiles</a>
        <a class="pl-nav-item" href="<?= url('/admin/events/settings') ?>">Settings</a>
    </div>
</div>
<div class="pl-main">
    <div class="pl-main-toolbar">
        <span class="pl-main-title">Events Profiles</span>
        <div class="pl-main-toolbar-actions">
            <a href="<?= url('/admin/events/profiles/new') ?>" class="btn btn-small btn-primary">+ New Profile</a>
        </div>
    </div>
    <div class="pl-main-scroll">

    <?php if (empty($profiles)): ?>
        <div class="admin-empty">
            <p>No events profiles yet.</p>
            <p class="text-muted">Create reusable event rendering presets here, then select them from module-content blocks in the editor.</p>
        </div>
    <?php else: ?>
        <div class="form-grid">
            <?php foreach ($profiles as $profile): ?>
            <section class="form-section">
                <div style="display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;flex-wrap:wrap;">
                    <div>
                        <h3 style="margin-bottom:0.25rem;"><?= e($profile['name'] ?? 'Untitled') ?></h3>
                        <p class="text-muted" style="margin:0;"><?= e($profile['slug'] ?? '') ?></p>
                    </div>
                    <span class="badge badge-muted"><?= e(ucfirst((string) ($profile['display_mode'] ?? 'both'))) ?></span>
                </div>

                <?php if (!empty($profile['description'])): ?>
                <p style="margin-top:0.75rem;"><?= nl2br(e($profile['description'])) ?></p>
                <?php endif; ?>

                <dl style="display:grid;grid-template-columns:max-content 1fr;gap:0.5rem 1rem;margin:1rem 0 0;">
                    <dt><strong>Events per page</strong></dt>
                    <dd style="margin:0;"><?= (int) ($profile['events_per_page'] ?? 10) ?></dd>
                    <dt><strong>Default filter</strong></dt>
                    <dd style="margin:0;"><?= e(ucfirst((string) ($profile['default_filter'] ?? 'upcoming'))) ?></dd>
                    <dt><strong>Return to list</strong></dt>
                    <dd style="margin:0;"><?= !empty($profile['show_return_to_list']) ? 'Enabled' : 'Disabled' ?></dd>
                    <dt><strong>Previous / next</strong></dt>
                    <dd style="margin:0;"><?= !empty($profile['show_event_navigation']) ? 'Enabled' : 'Disabled' ?></dd>
                </dl>

                <div class="form-actions" style="margin-top:1rem;">
                    <a href="<?= e('/admin/events/profiles/' . (int) ($profile['id'] ?? 0) . '/edit') ?>" class="btn btn-outline">Edit</a>
                    <form method="post" action="<?= e('/admin/events/profiles/' . (int) ($profile['id'] ?? 0) . '/delete') ?>" onsubmit="return confirm('Delete this events profile?');" style="display:inline;">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline btn-danger">Delete</button>
                    </form>
                </div>
            </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    </div><!-- /pl-main-scroll -->
</div><!-- /pl-main -->
</div><!-- /panel-layout -->
