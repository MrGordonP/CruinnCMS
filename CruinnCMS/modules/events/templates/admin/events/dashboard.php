<?php
\Cruinn\Template::requireCss('admin-panel-layout.css');
\Cruinn\Template::requireCss('admin-site-builder.css');
$GLOBALS['admin_flush_layout'] = true;

$settings = $settings ?? [];
$recentEvents = $recentEvents ?? [];
$listPage = $listPage ?? null;
?>

<div class="panel-layout no-detail" id="events-layout">
<div class="pl-panel pl-panel-left">
    <div class="pl-panel-header">
        <h3>Events</h3>
        <a href="<?= url('/admin/events/new') ?>" class="btn btn-sm btn-primary">+ New</a>
    </div>
    <div class="pl-panel-body" style="padding:0">
        <div class="pl-nav-section">Manage</div>
        <a class="pl-nav-item active" href="<?= url('/admin/events') ?>">Overview</a>
        <a class="pl-nav-item" href="<?= url('/admin/events/list') ?>">Events</a>
        <a class="pl-nav-item" href="<?= url('/admin/events/profiles') ?>">Profiles</a>
        <a class="pl-nav-item" href="<?= url('/admin/events/settings') ?>">Settings</a>
    </div>
</div>
<div class="pl-main">
    <div class="pl-main-toolbar">
        <span class="pl-main-title">Events</span>
        <div class="pl-main-toolbar-actions">
            <a href="<?= url('/admin/events/new') ?>" class="btn btn-small btn-primary">+ New Event</a>
        </div>
    </div>
    <div class="pl-main-scroll">

    <div class="dash-quick-grid" style="margin-bottom:1.5rem;">
        <a href="/admin/events/list?status=published" class="dash-quick-link">
            <span class="dash-quick-icon">📅</span>
            <strong class="dash-stat-num"><?= (int) ($publishedCount ?? 0) ?></strong>
            <span>Published</span>
        </a>
        <a href="/admin/events/list?status=draft" class="dash-quick-link">
            <span class="dash-quick-icon">📝</span>
            <strong class="dash-stat-num"><?= (int) ($draftCount ?? 0) ?></strong>
            <span>Drafts</span>
        </a>
        <a href="/admin/events/settings" class="dash-quick-link">
            <span class="dash-quick-icon">⚙️</span>
            <strong class="dash-stat-num"><?= (int) ($settings['default_events_per_page'] ?? 10) ?></strong>
            <span>Events per page</span>
        </a>
        <a href="/admin/events/profiles" class="dash-quick-link">
            <span class="dash-quick-icon">🧩</span>
            <strong class="dash-stat-num"><?= (int) ($profileCount ?? 0) ?></strong>
            <span>Profiles</span>
        </a>
    </div>

    <div class="form-grid">
        <section class="form-section">
            <h3>Current Setup</h3>
            <dl style="display:grid;grid-template-columns:max-content 1fr;gap:0.75rem 1rem;">
                <dt><strong>List page</strong></dt>
                <dd style="margin:0;">
                    <?php if ($listPage): ?>
                        <?= e($listPage['title'] ?? 'Untitled') ?> (<?= e('/' . ltrim((string) ($listPage['slug'] ?? ''), '/')) ?>)
                    <?php else: ?>
                        <span style="color:var(--color-danger, #b91c1c);">Not configured</span>
                    <?php endif; ?>
                </dd>

                <dt><strong>Detail shell</strong></dt>
                <dd style="margin:0;"><?= !empty($settings['detail_page_id']) ? 'Custom page selected' : 'Reuses list page' ?></dd>

                <dt><strong>Default filter</strong></dt>
                <dd style="margin:0;"><?= e(ucfirst((string) ($settings['default_filter'] ?? 'upcoming'))) ?></dd>

                <dt><strong>Return to list</strong></dt>
                <dd style="margin:0;"><?= !empty($settings['show_return_to_list']) ? 'Enabled' : 'Disabled' ?></dd>

                <dt><strong>Event navigation</strong></dt>
                <dd style="margin:0;"><?= !empty($settings['show_event_navigation']) ? 'Enabled' : 'Disabled' ?></dd>
            </dl>

            <?php if (!$listPage): ?>
            <p style="margin-top:1rem;color:var(--color-danger, #b91c1c);">The public events path is not configured yet. Set an Events List page in Settings.</p>
            <?php endif; ?>
        </section>

        <section class="form-section">
            <h3>Recent Events</h3>
            <?php if (empty($recentEvents)): ?>
                <p class="admin-empty">No events yet.</p>
            <?php else: ?>
                <ul class="comms-article-list">
                    <?php foreach ($recentEvents as $item): ?>
                    <li>
                        <a href="<?= e('/admin/events/' . (int) ($item['id'] ?? 0)) ?>"><?= e($item['title'] ?? 'Untitled') ?></a>
                        <span class="badge badge-<?= ($item['status'] ?? '') === 'published' ? 'success' : (($item['status'] ?? '') === 'draft' ? 'warning' : 'muted') ?>">
                            <?= e(ucfirst((string) ($item['status'] ?? 'draft'))) ?>
                        </span>
                        <?php if (!empty($item['date_start'])): ?>
                        <time class="text-muted"><?= format_date($item['date_start'], 'j M') ?></time>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    </div><!-- /pl-main-scroll -->
</div><!-- /pl-main -->
</div><!-- /panel-layout -->
