<?php
/**
 * Documents — Admin Document List
 * Full list across all statuses with workflow action buttons.
 */
?>

<div class="admin-section">
    <div class="admin-section-header">
        <h1>Documents — Admin</h1>
        <div class="admin-section-header-actions">
            <a href="/admin/documents/categories" class="btn btn-secondary btn-sm">Manage Categories</a>
            <a href="/documents/new" class="btn btn-primary btn-sm">Upload Document</a>
        </div>
    </div>

    <?php if ($flash = \Cruinn\Auth::getFlash('success')): ?>
        <div class="alert alert-success"><?= $this->escape($flash) ?></div>
    <?php endif; ?>
    <?php if ($flash = \Cruinn\Auth::getFlash('error')): ?>
        <div class="alert alert-error"><?= $this->escape($flash) ?></div>
    <?php endif; ?>

    <!-- Filters -->
    <form class="filter-bar" method="get" action="/admin/documents">
        <select name="status" class="form-select">
            <option value="">All Statuses</option>
            <?php foreach (['draft', 'submitted', 'approved', 'archived'] as $s): ?>
                <option value="<?= $this->escape($s) ?>" <?= $status === $s ? 'selected' : '' ?>>
                    <?= $this->escape(ucfirst($s)) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="category_id" class="form-select">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= (int)$cat['id'] ?>" <?= (string)$categoryId === (string)$cat['id'] ? 'selected' : '' ?>>
                    <?= $this->escape($cat['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="q" value="<?= $this->escape($search) ?>" placeholder="Search…" class="form-input">
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if ($status || $categoryId || $search): ?>
            <a href="/admin/documents" class="btn btn-link">Clear</a>
        <?php endif; ?>
    </form>

    <?php if ($total === 0): ?>
        <p class="empty-state">No documents found.</p>
    <?php else: ?>
        <p class="result-count"><?= (int)$total ?> document<?= $total !== 1 ? 's' : '' ?></p>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Version</th>
                    <th>Status</th>
                    <th>Uploaded By</th>
                    <th>Updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($documents as $doc): ?>
                <tr>
                    <td>
                        <a href="/documents/<?= (int)$doc['id'] ?>"><?= $this->escape($doc['title']) ?></a>
                        <?php if ($doc['description']): ?>
                            <br><small class="text-muted"><?= $this->escape(mb_strimwidth($doc['description'], 0, 80, '…')) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= $this->escape($doc['category_name'] ?? $doc['category'] ?? '—') ?></td>
                    <td>v<?= (int)$doc['version'] ?></td>
                    <td>
                        <span class="badge badge-<?= $this->escape($doc['status']) ?>">
                            <?= $this->escape(ucfirst(str_replace('_', ' ', $doc['status']))) ?>
                        </span>
                    </td>
                    <td><?= $this->escape($doc['uploader_name'] ?? '—') ?></td>
                    <td><?= $this->escape(substr($doc['updated_at'], 0, 10)) ?></td>
                    <td class="actions">
                        <?php if ($doc['status'] === 'submitted'): ?>
                            <form method="post" action="/admin/documents/<?= (int)$doc['id'] ?>/approve" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?= \Cruinn\CSRF::getToken() ?>">
                                <button type="submit" class="btn btn-xs btn-success"
                                        onclick="return confirm('Approve this document?')">Approve</button>
                            </form>
                            <form method="post" action="/admin/documents/<?= (int)$doc['id'] ?>/reject" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?= \Cruinn\CSRF::getToken() ?>">
                                <button type="submit" class="btn btn-xs btn-warning"
                                        onclick="return confirm('Return to draft?')">Reject</button>
                            </form>
                        <?php endif; ?>
                        <?php if (!in_array($doc['status'], ['archived'], true)): ?>
                            <form method="post" action="/admin/documents/<?= (int)$doc['id'] ?>/archive" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?= \Cruinn\CSRF::getToken() ?>">
                                <button type="submit" class="btn btn-xs btn-secondary"
                                        onclick="return confirm('Archive this document?')">Archive</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <?php
                        $qs = http_build_query(array_filter([
                            'status'      => $status,
                            'category_id' => $categoryId,
                            'q'           => $search,
                            'page'        => $p > 1 ? $p : null,
                        ]));
                    ?>
                    <a href="/admin/documents<?= $qs ? '?' . $qs : '' ?>"
                       class="pagination-link <?= $p === $page ? 'active' : '' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
