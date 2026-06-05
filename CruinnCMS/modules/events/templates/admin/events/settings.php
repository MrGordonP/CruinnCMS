<?php
\Cruinn\Template::requireCss('admin-panel-layout.css');
\Cruinn\Template::requireCss('admin-site-builder.css');
$GLOBALS['admin_flush_layout'] = true;
$settings = $settings ?? [];
?>

<div class="panel-layout no-detail" id="events-layout">
<div class="pl-sidebar">
    <div class="pl-sidebar-header"><h3>Events</h3></div>
    <div class="pl-sidebar-scroll" style="padding:0">
        <div class="pl-nav-section">Manage</div>
        <a class="pl-nav-item" href="<?= url('/admin/events') ?>">Overview</a>
        <a class="pl-nav-item" href="<?= url('/admin/events/list') ?>">Events</a>
        <a class="pl-nav-item" href="<?= url('/admin/events/profiles') ?>">Profiles</a>
        <a class="pl-nav-item active" href="<?= url('/admin/events/settings') ?>">Settings</a>
    </div>
</div>
<div class="pl-main">
    <div class="pl-main-toolbar">
        <span class="pl-main-title">Events Settings</span>
    </div>
    <div class="pl-main-scroll">

    <form method="post" action="/admin/events/settings" class="form-article-meta">
        <?= csrf_field() ?>

        <div class="form-grid">
            <section class="form-section">
                <h3>Public Routing</h3>

                <div class="form-group">
                    <label for="events-list-page">Events List Page</label>
                    <select id="events-list-page" name="list_page_id" class="form-input">
                        <option value="">— Select page —</option>
                        <?php foreach (($pages ?? []) as $page): ?>
                        <option value="<?= (int) ($page['id'] ?? 0) ?>"<?= (int) ($settings['list_page_id'] ?? 0) === (int) ($page['id'] ?? 0) ? ' selected' : '' ?>>
                            <?= e(($page['title'] ?? 'Untitled') . ' (' . ($page['slug'] ?? '') . ')') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small>The published page that owns the public events list path.</small>
                </div>

                <div class="form-group">
                    <label for="events-detail-page">Event Detail Shell Page</label>
                    <select id="events-detail-page" name="detail_page_id" class="form-input">
                        <option value="">— Reuse list page —</option>
                        <?php foreach (($pages ?? []) as $page): ?>
                        <option value="<?= (int) ($page['id'] ?? 0) ?>"<?= (int) ($settings['detail_page_id'] ?? 0) === (int) ($page['id'] ?? 0) ? ' selected' : '' ?>>
                            <?= e(($page['title'] ?? 'Untitled') . ' (' . ($page['slug'] ?? '') . ')') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Optional shell page for single events under the list path.</small>
                </div>
            </section>

            <section class="form-section">
                <h3>Defaults</h3>

                <div class="form-group">
                    <label for="events-default-count">Default Events Per Page</label>
                    <input type="number" id="events-default-count" name="default_events_per_page" min="1" max="100" class="form-input" value="<?= (int) ($settings['default_events_per_page'] ?? 10) ?>">
                </div>

                <div class="form-group">
                    <label for="events-default-filter">Default Filter</label>
                    <select id="events-default-filter" name="default_filter" class="form-input">
                        <option value="upcoming"<?= ($settings['default_filter'] ?? 'upcoming') === 'upcoming' ? ' selected' : '' ?>>Upcoming</option>
                        <option value="past"<?= ($settings['default_filter'] ?? '') === 'past' ? ' selected' : '' ?>>Past</option>
                    </select>
                </div>

                <label class="form-checkbox">
                    <input type="hidden" name="show_return_to_list" value="0">
                    <input type="checkbox" name="show_return_to_list" value="1"<?= !empty($settings['show_return_to_list']) ? ' checked' : '' ?>>
                    Show “Return to list” on events by default
                </label>

                <label class="form-checkbox">
                    <input type="hidden" name="show_event_navigation" value="0">
                    <input type="checkbox" name="show_event_navigation" value="1"<?= !empty($settings['show_event_navigation']) ? ' checked' : '' ?>>
                    Show previous / next event navigation by default
                </label>
            </section>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Events Settings</button>
            <a href="/admin/events" class="btn btn-outline">Back to Events</a>
        </div>
    </form>

    </div><!-- /pl-main-scroll -->
</div><!-- /pl-main -->
</div><!-- /panel-layout -->
