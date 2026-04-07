<?php
/**
 * Widget: Recent Activity
 * Displays the activity log table.
 * Data keys: activities[]
 */
$activities = $data['activities'] ?? [];
?>
<div class="activity-header">
    <h2>Recent Activity</h2>
    <a href="/admin/activity" class="btn btn-outline btn-small">View All</a>
</div>
<?php if (empty($activities)): ?>
    <p>No activity recorded yet.</p>
<?php else: ?>
<div class="activity-scroll">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Who</th>
                    <th>Action</th>
                    <th>What</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activities as $activity): ?>
                <tr>
                    <td><time datetime="<?= e($activity['created_at']) ?>"><?= format_date($activity['created_at'], 'j M H:i') ?></time></td>
                    <td><?= e($activity['display_name'] ?? 'System') ?></td>
                    <td><span class="badge badge-<?= e($activity['action']) ?>"><?= e(ucfirst($activity['action'])) ?></span></td>
                    <td><?= e(ucfirst($activity['entity_type'])) ?> <?= $activity['entity_id'] ? '#' . $activity['entity_id'] : '' ?></td>
                    <td><?= e(truncate($activity['details'] ?? '', 60)) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
</div>
<?php endif; ?>
