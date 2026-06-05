<?php
\Cruinn\Template::requireCss('admin-panel-layout.css');
\Cruinn\Template::requireCss('admin-events.css');
$GLOBALS['admin_flush_layout'] = true;

$eventBasePath = trim((string) ($eventBasePath ?? ''));
?>

<div class="panel-layout no-detail" id="events-layout">
<div class="pl-sidebar">
    <div class="pl-sidebar-header">
        <h3>Events</h3>
        <a href="<?= url('/admin/events/new') ?>" class="btn btn-sm btn-primary">+ New</a>
    </div>
    <div class="pl-sidebar-scroll" style="padding:0">
        <div class="pl-nav-section">Manage</div>
        <a class="pl-nav-item" href="<?= url('/admin/events') ?>">Overview</a>
        <a class="pl-nav-item active" href="<?= url('/admin/events/list') ?>">Events</a>
        <a class="pl-nav-item" href="<?= url('/admin/events/profiles') ?>">Profiles</a>
        <a class="pl-nav-item" href="<?= url('/admin/events/settings') ?>">Settings</a>
    </div>
</div>
<div class="pl-main">
    <div class="pl-main-toolbar">
        <span class="pl-main-title">Events <span style="font-weight:400;color:#aaa">(<?= (int)$totalCount ?>)</span></span>
        <div class="pl-main-toolbar-actions">
            <a href="<?= url('/admin/events/new') ?>" class="btn btn-small btn-primary">+ New Event</a>
        </div>
    </div>
    <div class="pl-main-scroll">

    <!-- Search & Filters -->
    <form class="admin-search-bar" method="get" action="/admin/events/list">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search events…">
        <select name="status">
            <option value="">All Statuses</option>
            <?php foreach (['draft', 'published', 'cancelled', 'completed'] as $s): ?>
            <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="type">
            <option value="">All Types</option>
            <?php foreach (['fieldtrip', 'lecture', 'conference', 'workshop', 'social', 'other'] as $t): ?>
            <option value="<?= $t ?>" <?= $type === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-small">Filter</button>
        <?php if ($search || $status || $type): ?>
            <a href="/admin/events/list" class="btn btn-small btn-outline">Clear</a>
        <?php endif; ?>
    </form>

    <?php if (empty($events)): ?>
        <p class="empty-state">No events found. <a href="/admin/events/new">Create one</a>.</p>
    <?php else: ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Title</th>
                <th>Type</th>
                <th>Date</th>
                <th>Location</th>
                <th>Price</th>
                <th>Registrations</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($events as $event): ?>
            <tr>
                <td><a href="/admin/events/<?= (int) $event['id'] ?>"><?= e($event['title']) ?></a></td>
                <td><span class="badge badge-type"><?= e(ucfirst($event['event_type'])) ?></span></td>
                <td>
                    <time datetime="<?= e($event['date_start']) ?>"><?= format_date($event['date_start'], 'j M Y') ?></time>
                    <?php if (!empty($event['date_end']) && date('Y-m-d', strtotime($event['date_end'])) !== date('Y-m-d', strtotime($event['date_start']))): ?>
                        <br><small>– <?= format_date($event['date_end'], 'j M Y') ?></small>
                    <?php endif; ?>
                </td>
                <td><?= e($event['location'] ?? '—') ?></td>
                <td><?= $event['price'] > 0 ? '€' . number_format($event['price'], 2) : '<span class="text-muted">Free</span>' ?></td>
                <td>
                    <?= (int) $event['reg_count'] ?>
                    <?php if ($event['capacity'] > 0): ?>
                        / <?= (int) $event['capacity'] ?>
                    <?php endif; ?>
                </td>
                <td><span class="badge badge-<?= e($event['status']) ?>"><?= e(ucfirst($event['status'])) ?></span></td>
                <td>
                    <a href="/admin/events/<?= (int) $event['id'] ?>" class="btn btn-small btn-outline">View</a>
                    <a href="/admin/events/<?= (int) $event['id'] ?>/edit" class="btn btn-small">Edit</a>
                    <?php if ($event['status'] === 'published' && $eventBasePath !== ''): ?>
                        <a href="<?= e($eventBasePath . '/' . ($event['slug'] ?? '')) ?>" target="_blank" class="btn btn-small btn-outline">Public</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn btn-small">&laquo; Prev</a>
        <?php endif; ?>
        <span class="page-info">Page <?= $page ?> of <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn btn-small">Next &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>

    </div><!-- /pl-main-scroll -->
</div><!-- /pl-main -->
</div><!-- /panel-layout -->
