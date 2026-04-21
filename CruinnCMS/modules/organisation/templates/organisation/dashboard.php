<div class="panel-layout" id="org-layout">

<!-- ── Left: Navigation ──────────────────────────────────────── -->
<div class="pl-sidebar">
    <div class="pl-sidebar-header"><h3>Workspace</h3></div>
    <div class="pl-sidebar-scroll">
        <a class="pl-nav-item active" href="/organisation">🏠 Dashboard</a>
        <a class="pl-nav-item" href="/organisation/documents">📄 Documents</a>
        <a class="pl-nav-item" href="/organisation/discussions">💬 Discussions
            <?php if (!empty($stats['discussions'])): ?>
            <span class="pl-nav-count"><?= (int)$stats['discussions'] ?></span>
            <?php endif; ?>
        </a>
        <a class="pl-nav-item" href="/organisation/inbox">📥 Inbox</a>
    </div>
    <?php if (\Cruinn\Auth::hasRole('admin')): ?>
    <div class="pl-sidebar-footer">
        <a href="/admin" class="btn btn-sm btn-outline" style="width:100%;text-align:center">Admin Panel</a>
    </div>
    <?php endif; ?>
</div>

<!-- ── Middle: Dashboard content ─────────────────────────────── -->
<div class="pl-main">
    <div class="pl-main-toolbar">
        <span class="pl-main-title">Organisation Workspace</span>
        <div class="pl-main-toolbar-actions">
            <a href="/organisation/discussions/new" class="btn btn-sm btn-secondary">+ Discussion</a>
        </div>
    </div>
    <div class="pl-main-scroll">

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-number"><?= (int)($stats['documents'] ?? 0) ?></span>
            <span class="stat-label">Documents</span>
            <a href="/organisation/documents" class="stat-link">View All</a>
        </div>
        <div class="stat-card stat-card-warning">
            <span class="stat-number"><?= (int)($stats['pending'] ?? 0) ?></span>
            <span class="stat-label">Pending Approval</span>
            <a href="/organisation/documents?status=submitted" class="stat-link">Review</a>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?= (int)($stats['discussions'] ?? 0) ?></span>
            <span class="stat-label">Discussions</span>
            <a href="/organisation/discussions" class="stat-link">View All</a>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?= (int)($stats['posts'] ?? 0) ?></span>
            <span class="stat-label">Posts</span>
        </div>
    </div>

    <!-- Recent Documents -->
    <h3 style="font-size:.8rem;text-transform:uppercase;letter-spacing:.07em;color:#888;margin:1.25rem 0 .4rem">Recent Documents</h3>
    <?php if (empty($recentDocuments)): ?>
        <p class="text-muted" style="font-size:.85rem">No documents yet. <a href="/organisation/documents/upload">Upload one.</a></p>
    <?php else: ?>
        <table class="pl-table" style="margin-bottom:1.5rem">
            <thead><tr><th>Title</th><th>Category</th><th>Status</th><th>Updated</th></tr></thead>
            <tbody>
            <?php foreach ($recentDocuments as $doc): ?>
            <tr onclick="location.href='/organisation/documents/<?= (int)$doc['id'] ?>'">
                <td><?= e($doc['title']) ?></td>
                <td><span class="badge badge-category"><?= e(ucfirst($doc['category'])) ?></span></td>
                <td><span class="badge badge-doc-<?= e($doc['status']) ?>"><?= e(ucfirst($doc['status'])) ?></span></td>
                <td style="color:#888;font-size:.8rem"><?= format_date($doc['updated_at'], 'j M Y') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Active Discussions -->
    <h3 style="font-size:.8rem;text-transform:uppercase;letter-spacing:.07em;color:#888;margin:0 0 .4rem">Active Discussions</h3>
    <?php if (empty($activeDiscussions)): ?>
        <p class="text-muted" style="font-size:.85rem">No discussions yet. <a href="/organisation/discussions/new">Start one.</a></p>
    <?php else: ?>
        <table class="pl-table">
            <thead><tr><th>Topic</th><th>Posts</th><th>Last Activity</th></tr></thead>
            <tbody>
            <?php foreach ($activeDiscussions as $disc): ?>
            <tr onclick="location.href='/organisation/discussions/<?= (int)$disc['id'] ?>'">
                <td>
                    <?php if ($disc['pinned']): ?><span title="Pinned">📌</span> <?php endif; ?>
                    <?php if ($disc['locked']): ?><span title="Locked">🔒</span> <?php endif; ?>
                    <?= e($disc['title']) ?>
                </td>
                <td><?= (int)$disc['post_count'] ?></td>
                <td style="color:#888;font-size:.8rem">
                    <?= $disc['last_post_at'] ? format_date($disc['last_post_at'], 'j M H:i') : '<span class="text-muted">—</span>' ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    </div><!-- pl-main-scroll -->
</div><!-- pl-main -->

<!-- ── Right: Summary ─────────────────────────────────────────── -->
<div class="pl-detail">
    <div class="pl-detail-header"><h3>At a Glance</h3></div>
    <div class="pl-detail-scroll">

        <table class="pl-meta" style="margin-bottom:1.25rem">
            <tr><th>Documents</th><td><?= (int)($stats['documents'] ?? 0) ?></td></tr>
            <tr><th>Pending</th><td><?php $p = (int)($stats['pending'] ?? 0); echo $p > 0 ? "<span style='color:#d97706;font-weight:600'>{$p}</span>" : '0'; ?></td></tr>
            <tr><th>Discussions</th><td><?= (int)($stats['discussions'] ?? 0) ?></td></tr>
            <tr><th>Posts</th><td><?= (int)($stats['posts'] ?? 0) ?></td></tr>
        </table>

        <?php if ((int)($stats['pending'] ?? 0) > 0): ?>
        <a href="/organisation/documents?status=submitted" class="btn btn-sm btn-primary" style="width:100%;text-align:center;margin-bottom:.75rem">
            Review <?= (int)$stats['pending'] ?> Pending
        </a>
        <?php endif; ?>

        <div class="pl-detail-actions">
            <a href="/organisation/documents/upload" class="btn btn-sm btn-outline">⬆ Upload Doc</a>
            <a href="/organisation/discussions/new" class="btn btn-sm btn-outline">+ Discussion</a>
        </div>
    </div>
</div>

</div><!-- panel-layout -->
