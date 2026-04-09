<?php
$recentDocuments = $data['recent_documents'] ?? [];
$recentDiscussions = $data['recent_discussions'] ?? [];
?>
<div class="activity-header">
    <h2>Organisation Workspace</h2>
    <a href="<?= url('/organisation') ?>" class="btn btn-primary btn-small">Open Workspace</a>
</div>

<div class="dash-quick-grid">
    <a href="<?= url('/documents') ?>" class="dash-quick-link">
        <span class="dash-quick-icon">📄</span><span>Documents <?= (int) ($data['documents'] ?? 0) ?></span>
    </a>
    <a href="<?= url('/documents?status=submitted') ?>" class="dash-quick-link">
        <span class="dash-quick-icon">⏳</span><span>Pending <?= (int) ($data['pending'] ?? 0) ?></span>
    </a>
    <a href="<?= url('/organisation/discussions') ?>" class="dash-quick-link">
        <span class="dash-quick-icon">💬</span><span>Discussions <?= (int) ($data['discussions'] ?? 0) ?></span>
    </a>
    <div class="dash-quick-link">
        <span class="dash-quick-icon">📝</span><span>Posts <?= (int) ($data['posts'] ?? 0) ?></span>
    </div>
</div>

<div class="dashboard-widget-grid" style="margin-top:1rem;">
    <div class="dashboard-widget widget-half">
        <div class="activity-header">
            <h3>Recent Documents</h3>
            <a href="<?= url('/documents/new') ?>" class="btn btn-primary btn-small">Upload</a>
        </div>
        <?php if (empty($recentDocuments)): ?>
        <p class="text-muted">No documents yet.</p>
        <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentDocuments as $doc): ?>
                <tr>
                    <td><a href="<?= url('/documents/' . (int) $doc['id']) ?>"><?= e($doc['title']) ?></a></td>
                    <td><?= e(ucfirst((string) $doc['status'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div class="dashboard-widget widget-half">
        <div class="activity-header">
            <h3>Recent Discussions</h3>
            <a href="<?= url('/organisation/discussions/new') ?>" class="btn btn-primary btn-small">New Thread</a>
        </div>
        <?php if (empty($recentDiscussions)): ?>
        <p class="text-muted">No discussions yet.</p>
        <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Topic</th>
                    <th>Posts</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentDiscussions as $discussion): ?>
                <tr>
                    <td>
                        <?php if (!empty($discussion['pinned'])): ?><span title="Pinned">📌</span> <?php endif; ?>
                        <?php if (!empty($discussion['locked'])): ?><span title="Locked">🔒</span> <?php endif; ?>
                        <a href="<?= url('/organisation/discussions/' . (int) $discussion['id']) ?>"><?= e($discussion['title']) ?></a>
                    </td>
                    <td><?= (int) $discussion['post_count'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
