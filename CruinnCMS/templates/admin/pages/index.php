<?php
\Cruinn\Template::requireCss('admin-panel-layout.css');
$GLOBALS['admin_flush_layout'] = true;

// Group pages by status and mode for sidebar counts
$byStatus = ['published' => 0, 'draft' => 0, 'archived' => 0];
$byMode   = ['block' => 0, 'html' => 0, 'file' => 0];
foreach ($pages as $pg) {
    $s = $pg['status'] ?? 'published';
    $m = $pg['render_mode'] ?? 'block';
    if (isset($byStatus[$s])) $byStatus[$s]++;
    if (isset($byMode[$m])) $byMode[$m]++;
}
?>

<div class="panel-layout" id="pages-layout"
     data-csrf="<?= e(\Cruinn\CSRF::getToken()) ?>"
     data-templates="<?= e(json_encode(array_map(fn($t) => ['slug' => $t['slug'], 'name' => $t['name']], $templates ?? []))) ?>">

    <!-- ── Left: Filters ──────────────────────────────────────── -->
    <div class="pl-sidebar">
        <div class="pl-sidebar-header">
            <h3>Pages</h3>
            <a href="/admin/pages/new" class="btn btn-sm btn-primary">+ New</a>
        </div>
        <div class="pl-sidebar-scroll">
            <span class="pl-nav-section">Status</span>
            <a class="pl-nav-item active" data-filter="all" href="#">
                All <span class="pl-nav-count"><?= count($pages) ?></span>
            </a>
            <a class="pl-nav-item" data-filter="status:published" href="#">
                Published <span class="pl-nav-count"><?= $byStatus['published'] ?></span>
            </a>
            <a class="pl-nav-item" data-filter="status:draft" href="#">
                Draft <span class="pl-nav-count"><?= $byStatus['draft'] ?></span>
            </a>
            <a class="pl-nav-item" data-filter="status:archived" href="#">
                Archived <span class="pl-nav-count"><?= $byStatus['archived'] ?></span>
            </a>

            <span class="pl-nav-section">Mode</span>
            <a class="pl-nav-item" data-filter="mode:block" href="#">
                Block <span class="pl-nav-count"><?= $byMode['block'] ?></span>
            </a>
            <a class="pl-nav-item" data-filter="mode:html" href="#">
                HTML <span class="pl-nav-count"><?= $byMode['html'] ?></span>
            </a>
            <a class="pl-nav-item" data-filter="mode:file" href="#">
                File <span class="pl-nav-count"><?= $byMode['file'] ?></span>
            </a>
        </div>
    </div>

    <!-- ── Middle: Page list ──────────────────────────────────── -->
    <div class="pl-main">
        <div class="pl-main-toolbar">
            <span class="pl-main-title" id="pages-filter-label">All Pages</span>
            <div class="pl-main-toolbar-actions">
                <a href="/admin/pages/new" class="btn btn-sm btn-primary">+ New Page</a>
            </div>
        </div>
        <div class="pl-main-search">
            <input type="search" class="pl-search-input" id="pages-search" placeholder="Search pages…" autocomplete="off">
        </div>
        <div class="pl-main-scroll">
            <?php if (empty($pages)): ?>
                <div class="pl-empty">No pages yet. <a href="/admin/pages/new">Create your first page</a>.</div>
            <?php else: ?>
            <table class="pl-table" id="pages-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>URL</th>
                        <th>Mode</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pages as $pg):
                    $mode   = $pg['render_mode'] ?? 'block';
                    $status = $pg['status'] ?? 'published';
                    $modeColour = match($mode) {
                        'file'  => 'background:#d97706;color:#fff',
                        'html'  => 'background:#7c3aed;color:#fff',
                        default => 'background:#1d9e75;color:#fff',
                    };
                ?>
                <tr data-id="<?= (int)$pg['id'] ?>"
                    data-status="<?= e($status) ?>"
                    data-mode="<?= e($mode) ?>"
                    data-title="<?= e(strtolower($pg['title'])) ?>"
                    data-slug="<?= e(strtolower($pg['slug'])) ?>"
                    data-page='<?= e(json_encode([
                        'id'              => (int)$pg['id'],
                        'title'           => $pg['title'],
                        'slug'            => $pg['slug'],
                        'mode'            => $mode,
                        'status'          => $status,
                        'template'        => $pg['template'] ?? 'default',
                        'meta_description'=> $pg['meta_description'] ?? '',
                        'author'          => $pg['author_name'] ?? '—',
                        'updated'         => format_date($pg['updated_at'], 'j M Y'),
                    ])) ?>'>
                    <td>
                        <?= e($pg['title']) ?>
                        <?php if ($status !== 'published'): ?>
                        <span class="badge" style="background:#d97706;color:#fff;font-size:.68rem;margin-left:.3rem"><?= e($status) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><code style="font-size:.8rem">/<?= e($pg['slug']) ?></code></td>
                    <td><span class="badge" style="<?= $modeColour ?>;font-size:.68rem"><?= e($mode) ?></span></td>
                    <td style="color:#888;font-size:.8rem"><?= format_date($pg['updated_at'], 'j M Y') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Right: Page detail ─────────────────────────────────── -->
    <div class="pl-detail" id="pages-detail">
        <div class="pl-detail-header"><h3>Details</h3></div>
        <div class="pl-detail-scroll">
            <div class="pl-detail-placeholder" id="pages-detail-placeholder">
                <div class="pl-detail-placeholder-icon">📄</div>
                <span>Select a page to see details</span>
            </div>
            <div id="pages-detail-content" style="display:none"></div>
        </div>
    </div>

</div>

<?php \Cruinn\Template::requireJs('pages.js'); ?>
