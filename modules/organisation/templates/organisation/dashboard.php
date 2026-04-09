<div class="organisation-dashboard">
    <h1>Organisation Workspace</h1>

    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-number"><?= (int)($stats['documents'] ?? 0) ?></span>
            <span class="stat-label">Documents</span>
            <a href="/documents" class="stat-link">View All</a>
        </div>
        <div class="stat-card stat-card-warning">
            <span class="stat-number"><?= (int)($stats['pending'] ?? 0) ?></span>
            <span class="stat-label">Pending Approval</span>
            <a href="/documents?status=submitted" class="stat-link">Review</a>
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

    <div class="organisation-grid">
        <!-- Recent Documents -->
        <section class="organisation-section">
            <div class="section-header">
                <h2>Recent Documents</h2>
                <a href="/documents/new" class="btn btn-primary btn-sm">Upload New</a>
            </div>
            <?php if (empty($recentDocuments)): ?>
                <p class="text-muted">No documents yet.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentDocuments as $doc): ?>
                        <tr>
                            <td><a href="/documents/<?= (int)$doc['id'] ?>"><?= e($doc['title']) ?></a></td>
                            <td><span class="badge badge-category"><?= e(ucfirst($doc['category'])) ?></span></td>
                            <td><span class="badge badge-doc-<?= e($doc['status']) ?>"><?= e(ucfirst($doc['status'])) ?></span></td>
                            <td><time datetime="<?= e($doc['updated_at']) ?>"><?= format_date($doc['updated_at'], 'j M Y') ?></time></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <!-- Active Discussions -->
        <section class="organisation-section">
            <div class="section-header">
                <h2>Active Discussions</h2>
                <a href="/organisation/discussions/new" class="btn btn-primary btn-sm">New Thread</a>
            </div>
            <?php if (empty($activeDiscussions)): ?>
                <p class="text-muted">No discussions yet.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Topic</th>
                            <th>Posts</th>
                            <th>Last Activity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activeDiscussions as $disc): ?>
                        <tr>
                            <td>
                                <?php if ($disc['pinned']): ?><span class="pin-icon" title="Pinned">📌</span> <?php endif; ?>
                                <?php if ($disc['locked']): ?><span class="lock-icon" title="Locked">🔒</span> <?php endif; ?>
                                <a href="/organisation/discussions/<?= (int)$disc['id'] ?>"><?= e($disc['title']) ?></a>
                            </td>
                            <td><?= (int)$disc['post_count'] ?></td>
                            <td>
                                <?php if ($disc['last_post_at']): ?>
                                    <time datetime="<?= e($disc['last_post_at']) ?>"><?= format_date($disc['last_post_at'], 'j M H:i') ?></time>
                                <?php else: ?>
                                    <span class="text-muted">No posts</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </div>
</div>
