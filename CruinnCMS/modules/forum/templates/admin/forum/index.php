<?php
\Cruinn\Template::requireCss('admin-panel-layout.css');
$GLOBALS['admin_flush_layout'] = true;
$forumBasePath = trim((string) ($forumBasePath ?? ''));
$status = (string)($filters['status'] ?? 'all');
$categoryTree = is_array($categoryTree ?? null) ? $categoryTree : [];
$categoryOptions = is_array($categoryOptions ?? null) ? $categoryOptions : [];
$hasActiveFilter = trim((string) ($filters['q'] ?? '')) !== ''
    || (int) ($filters['category_id'] ?? 0) > 0
    || $status !== 'all';

$renderSection = function (array $section, int $depth = 0) use (&$renderSection, $forumBasePath, $categoryOptions, $hasActiveFilter): string {
    $children = is_array($section['children'] ?? null) ? $section['children'] : [];
    $threads = is_array($section['threads'] ?? null) ? $section['threads'] : [];
    $sectionId = (int) ($section['id'] ?? 0);
    $depthIndent = max(0, $depth) * 1.25;
    $publicSectionUrl = $forumBasePath !== ''
        ? rtrim($forumBasePath, '/') . '/' . ltrim((string) ($section['slug'] ?? ''), '/')
        : '';

    ob_start();
    ?>
    <details class="forum-category-section" style="margin-top: var(--space-md); margin-left: <?= $depthIndent ?>rem;"<?= $hasActiveFilter ? ' open' : '' ?>>
        <summary class="forum-category-header" style="cursor:pointer;">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:var(--space-md);flex-wrap:wrap;">
                <div>
                    <h3 style="margin:0 0 .2rem 0;font-size:1rem;">
                        <?php if ($publicSectionUrl !== ''): ?>
                            <a href="<?= e($publicSectionUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e((string) ($section['title'] ?? 'Untitled Section')) ?></a>
                        <?php else: ?>
                            <?= e((string) ($section['title'] ?? 'Untitled Section')) ?>
                        <?php endif; ?>
                    </h3>
                    <?php if (!empty($section['description'])): ?>
                        <p class="forum-category-desc" style="margin:0;"><?= e((string) $section['description']) ?></p>
                    <?php endif; ?>
                    <p class="text-muted" style="margin:.35rem 0 0 0;font-size:.8rem;">
                        Slug: /<?= e((string) ($section['slug'] ?? '')) ?>
                        | Access: <?= e((string) ($section['access_role'] ?? 'public')) ?>
                        | Sort: <?= (int) ($section['sort_order'] ?? 0) ?>
                        | <?= (int) ($section['is_active'] ?? 1) === 1 ? 'Active' : 'Inactive' ?>
                    </p>
                </div>
                <div class="forum-stats" style="text-align:right;">
                    <span class="forum-stat-item"><strong><?= (int) ($section['thread_count'] ?? 0) ?></strong> Topics</span>
                    <span class="forum-stat-item"><strong><?= (int) ($section['post_count'] ?? 0) ?></strong> Posts</span>
                </div>
            </div>
        </summary>

        <div class="forum-category-forums" style="padding: var(--space-sm) var(--space-md); border-top: 1px solid var(--color-border);">
            <div style="display:flex;gap:var(--space-sm);flex-wrap:wrap;margin-bottom:var(--space-sm);">
                <details>
                    <summary class="btn btn-small btn-outline" style="cursor:pointer;display:inline-block;">Edit Section</summary>
                    <div class="card" style="margin-top:var(--space-sm);padding:var(--space-md);min-width:min(56rem,92vw);">
                        <form method="post" action="/admin/forum/category/<?= $sectionId ?>/update">
                            <?= csrf_field() ?>
                            <input type="hidden" name="is_active" value="0">
                            <div class="form-grid" style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:var(--space-md);">
                                <div class="form-group" style="margin:0;">
                                    <label>Title</label>
                                    <input type="text" name="title" class="form-input" value="<?= e((string) ($section['title'] ?? '')) ?>" required>
                                </div>
                                <div class="form-group" style="margin:0;">
                                    <label>Slug</label>
                                    <input type="text" name="slug" class="form-input" value="<?= e((string) ($section['slug'] ?? '')) ?>" pattern="[a-z0-9-]+">
                                </div>
                                <div class="form-group" style="margin:0;">
                                    <label>Sort Order</label>
                                    <input type="number" name="sort_order" class="form-input" value="<?= (int) ($section['sort_order'] ?? 0) ?>" min="0">
                                </div>
                                <div class="form-group" style="margin:0;grid-column:1 / -1;">
                                    <label>Description</label>
                                    <input type="text" name="description" class="form-input" value="<?= e((string) ($section['description'] ?? '')) ?>">
                                </div>
                                <div class="form-group" style="margin:0;">
                                    <label>Parent Section</label>
                                    <select name="parent_id" class="form-input">
                                        <option value="0">Top level</option>
                                        <?php foreach ($categoryOptions as $opt): ?>
                                            <?php if ((int) ($opt['id'] ?? 0) === $sectionId) { continue; } ?>
                                            <option value="<?= (int) ($opt['id'] ?? 0) ?>" <?= (int) ($section['parent_id'] ?? 0) === (int) ($opt['id'] ?? 0) ? 'selected' : '' ?>>
                                                <?= e(str_repeat('-- ', (int) ($opt['depth'] ?? 0)) . (string) ($opt['title'] ?? '')) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group" style="margin:0;">
                                    <label>Access Role</label>
                                    <select name="access_role" class="form-input">
                                        <?php foreach (['public', 'member', 'council', 'admin'] as $role): ?>
                                            <option value="<?= e($role) ?>" <?= (string) ($section['access_role'] ?? 'public') === $role ? 'selected' : '' ?>><?= e($role) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group" style="margin:0;display:flex;align-items:flex-end;">
                                    <label style="display:flex;align-items:center;gap:.4rem;margin:0;">
                                        <input type="checkbox" name="is_active" value="1" <?= (int) ($section['is_active'] ?? 1) === 1 ? 'checked' : '' ?>> Active
                                    </label>
                                </div>
                            </div>
                            <div class="form-actions" style="margin-top:var(--space-md);display:flex;justify-content:space-between;align-items:center;gap:var(--space-sm);">
                                <button type="submit" class="btn btn-primary btn-small">Save Section</button>
                            </div>
                        </form>
                        <form method="post" action="/admin/forum/category/<?= $sectionId ?>/delete" onsubmit="return confirm('Delete this section? It must have no sub-forums and no threads.')" style="margin-top:var(--space-sm);">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-small btn-danger">Delete Section</button>
                        </form>
                    </div>
                </details>

                <details>
                    <summary class="btn btn-small btn-outline" style="cursor:pointer;display:inline-block;">Add Sub-forum</summary>
                    <div class="card" style="margin-top:var(--space-sm);padding:var(--space-md);min-width:min(42rem,92vw);">
                        <form method="post" action="/admin/forum/category/new">
                            <?= csrf_field() ?>
                            <input type="hidden" name="parent_id" value="<?= $sectionId ?>">
                            <input type="hidden" name="is_active" value="1">
                            <div class="form-grid" style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:var(--space-md);">
                                <div class="form-group" style="margin:0;">
                                    <label>Title</label>
                                    <input type="text" name="title" class="form-input" required>
                                </div>
                                <div class="form-group" style="margin:0;">
                                    <label>Slug</label>
                                    <input type="text" name="slug" class="form-input" pattern="[a-z0-9-]+" placeholder="Optional">
                                </div>
                                <div class="form-group" style="margin:0;">
                                    <label>Sort Order</label>
                                    <input type="number" name="sort_order" class="form-input" value="0" min="0">
                                </div>
                                <div class="form-group" style="margin:0;grid-column:1 / -1;">
                                    <label>Description</label>
                                    <input type="text" name="description" class="form-input">
                                </div>
                                <div class="form-group" style="margin:0;">
                                    <label>Access Role</label>
                                    <select name="access_role" class="form-input">
                                        <option value="public">public</option>
                                        <option value="member">member</option>
                                        <option value="council">council</option>
                                        <option value="admin">admin</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-actions" style="margin-top:var(--space-md);">
                                <button type="submit" class="btn btn-primary btn-small">Create Sub-forum</button>
                            </div>
                        </form>
                    </div>
                </details>
            </div>

            <?php if (empty($threads)): ?>
                <p class="text-muted" style="margin:0 0 var(--space-sm) 0;">No threads in this section for the current filter.</p>
            <?php else: ?>
                <div class="table-wrap" style="margin-bottom:var(--space-sm);">
                    <table class="admin-table">
                        <thead>
                        <tr>
                            <th>Select</th>
                            <th>Thread</th>
                            <th>Author</th>
                            <th>Replies</th>
                            <th>Last Post</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($threads as $thread): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="thread_ids[]" value="<?= (int)$thread['id'] ?>" form="forum-bulk-moderation" aria-label="Select thread <?= (int)$thread['id'] ?>">
                                </td>
                                <td>
                                    <?php if ($forumBasePath !== ''): ?>
                                        <a href="<?= e(rtrim($forumBasePath, '/') . '/thread/' . (int) $thread['id']) ?>" target="_blank" rel="noopener noreferrer">
                                            <?= e((string) ($thread['title'] ?? 'Untitled Thread')) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= e((string) ($thread['title'] ?? 'Untitled Thread')) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= e((string) ($thread['author_name'] ?? 'Unknown')) ?></td>
                                <td><?= (int) ($thread['reply_count'] ?? 0) ?></td>
                                <td>
                                    <?php if (!empty($thread['last_post_at'])): ?>
                                        <time datetime="<?= e((string) $thread['last_post_at']) ?>"><?= format_date((string) $thread['last_post_at'], 'j M Y H:i') ?></time>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ((int) ($thread['is_pinned'] ?? 0) === 1): ?>
                                        <span class="badge badge-info">Pinned</span>
                                    <?php endif; ?>
                                    <?php if ((int) ($thread['is_locked'] ?? 0) === 1): ?>
                                        <span class="badge badge-warning">Locked</span>
                                    <?php endif; ?>
                                    <?php if ((int) ($thread['is_pinned'] ?? 0) !== 1 && (int) ($thread['is_locked'] ?? 0) !== 1): ?>
                                        <span class="badge">Open</span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions-cell">
                                    <form method="post" action="/admin/forum/<?= (int)$thread['id'] ?>/pin" class="inline-form">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-small btn-outline"><?= (int) ($thread['is_pinned'] ?? 0) === 1 ? 'Unpin' : 'Pin' ?></button>
                                    </form>

                                    <form method="post" action="/admin/forum/<?= (int)$thread['id'] ?>/lock" class="inline-form">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-small btn-outline"><?= (int) ($thread['is_locked'] ?? 0) === 1 ? 'Unlock' : 'Lock' ?></button>
                                    </form>

                                    <form method="post" action="/admin/forum/<?= (int)$thread['id'] ?>/delete" class="inline-form" onsubmit="return confirm('Delete this thread and all replies? This cannot be undone.')">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-small btn-danger">Delete</button>
                                    </form>

                                    <a href="/admin/forum/<?= (int)$thread['id'] ?>/move" class="btn btn-small btn-outline">Move</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php foreach ($children as $child): ?>
                <?= $renderSection($child, $depth + 1) ?>
            <?php endforeach; ?>
        </div>
    </details>
    <?php

    return (string) ob_get_clean();
};
?>

<div class="panel-layout no-detail" id="forum-layout">
<div class="pl-sidebar">
    <div class="pl-sidebar-header"><h3>Forum</h3></div>
    <div class="pl-sidebar-scroll" style="padding:0">
        <div class="pl-nav-section">Moderation</div>
        <a class="pl-nav-item active" href="<?= url('/admin/forum') ?>">Categories &amp; Threads</a>
        <a class="pl-nav-item" href="<?= url('/admin/forum/reports') ?>">Post Reports</a>
    </div>
</div>
<div class="pl-main">
    <div class="pl-main-toolbar">
        <span class="pl-main-title">Forum Moderation</span>
    </div>
    <div class="pl-main-scroll">

<form method="get" action="/admin/forum" class="card" style="margin-bottom: var(--space-lg);">
    <div class="form-grid" style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:var(--space-md);align-items:end;">
        <div class="form-group" style="margin:0;">
            <label for="q">Search title</label>
            <input id="q" name="q" type="search" class="form-input" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Search threads">
        </div>

        <div class="form-group" style="margin:0;">
            <label for="category_id">Category</label>
            <select id="category_id" name="category_id" class="form-input">
                <option value="0">All categories</option>
                <?php foreach ($categoryOptions as $option): ?>
                    <option value="<?= (int) ($option['id'] ?? 0) ?>" <?= (int) ($filters['category_id'] ?? 0) === (int) ($option['id'] ?? 0) ? 'selected' : '' ?>>
                        <?= e(str_repeat('-- ', (int) ($option['depth'] ?? 0)) . (string) ($option['title'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin:0;">
            <label for="status">Status</label>
            <select id="status" name="status" class="form-input">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
                <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>Open only</option>
                <option value="locked" <?= $status === 'locked' ? 'selected' : '' ?>>Locked only</option>
                <option value="pinned" <?= $status === 'pinned' ? 'selected' : '' ?>>Pinned only</option>
            </select>
        </div>

        <div>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="/admin/forum" class="btn btn-outline">Reset</a>
        </div>
    </div>
</form>

<div class="card" id="forum-create-top-level" style="margin-bottom: var(--space-md);">
    <h2 style="font-size:1.05rem;margin:0 0 var(--space-sm) 0;">Create Top-Level Section</h2>
    <form method="post" action="/admin/forum/category/new">
        <?= csrf_field() ?>
        <input type="hidden" name="parent_id" value="0">
        <input type="hidden" name="is_active" value="1">
        <div class="form-grid" style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:var(--space-md);align-items:end;">
            <div class="form-group" style="margin:0;">
                <label>Title</label>
                <input type="text" name="title" class="form-input" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label>Slug</label>
                <input type="text" name="slug" class="form-input" pattern="[a-z0-9-]+" placeholder="Optional">
            </div>
            <div class="form-group" style="margin:0;">
                <label>Sort Order</label>
                <input type="number" name="sort_order" class="form-input" value="0" min="0">
            </div>
            <div class="form-group" style="margin:0;grid-column:1 / -1;">
                <label>Description</label>
                <input type="text" name="description" class="form-input">
            </div>
            <div class="form-group" style="margin:0;max-width:16rem;">
                <label>Access Role</label>
                <select name="access_role" class="form-input">
                    <option value="public">public</option>
                    <option value="member">member</option>
                    <option value="council">council</option>
                    <option value="admin">admin</option>
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-primary">Create Section</button>
            </div>
        </div>
    </form>
</div>

<form method="post" action="/admin/forum/bulk" id="forum-bulk-moderation" class="card" style="margin-bottom: var(--space-md);">
    <?= csrf_field() ?>
    <div class="form-grid" style="display:grid;grid-template-columns:2fr 1fr auto;gap:var(--space-md);align-items:end;">
        <div class="form-group" style="margin:0;">
            <label for="bulk_action">Mass Moderation Action</label>
            <select id="bulk_action" name="bulk_action" class="form-input" required>
                <option value="">Choose action...</option>
                <option value="pin">Pin selected</option>
                <option value="unpin">Unpin selected</option>
                <option value="lock">Lock selected</option>
                <option value="unlock">Unlock selected</option>
                <option value="move">Move selected</option>
                <option value="delete">Delete selected</option>
            </select>
        </div>

        <div class="form-group" style="margin:0;">
            <label for="target_category_id">Move destination</label>
            <select id="target_category_id" name="target_category_id" class="form-input">
                <option value="0">Choose category (move only)</option>
                <?php foreach ($categoryOptions as $option): ?>
                    <?php if ((int) ($option['is_active'] ?? 0) !== 1) { continue; } ?>
                    <option value="<?= (int) ($option['id'] ?? 0) ?>"><?= e(str_repeat('-- ', (int) ($option['depth'] ?? 0)) . (string) ($option['title'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <button type="submit" class="btn btn-primary">Apply To Selected</button>
        </div>
    </div>
</form>

<?php if (empty($categoryTree)): ?>
    <div class="card">
        <p style="margin:0;">No forum sections exist yet. Use the Create Top-Level Section form to start structuring the forum.</p>
    </div>
<?php else: ?>
    <?php foreach ($categoryTree as $section): ?>
        <?= $renderSection($section, 0) ?>
    <?php endforeach; ?>
<?php endif; ?>

    </div><!-- /pl-main-scroll -->
</div><!-- /pl-main -->
</div><!-- /panel-layout -->
