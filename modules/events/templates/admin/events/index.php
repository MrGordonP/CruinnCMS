<?php \Cruinn\Template::requireCss('admin-events.css'); ?>
<div class="admin-events">
    <div class="admin-header-row">
        <h1>Events <span class="count">(<?= (int) $totalCount ?>)</span></h1>
        <a href="/admin/events/new" class="btn btn-primary">+ New Event</a>
    </div>

    <!-- Search & Filters -->
    <form class="admin-search-bar" method="get" action="/admin/events">
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
            <a href="/admin/events" class="btn btn-small btn-outline">Clear</a>
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
                    <a href="/admin/events/<?= (int) $event['id'] ?>/edit" class="btn btn-small">Edit</a>
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
</div>
