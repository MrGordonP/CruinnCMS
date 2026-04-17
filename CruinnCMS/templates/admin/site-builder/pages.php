<?php \Cruinn\Template::requireCss('admin-site-builder.css'); ?>

<div class="admin-page-header">
    <h2>🏗️ Site Builder</h2>
    <a href="<?= url(!empty($homePageId) ? '/admin/editor/' . (int)$homePageId . '/edit' : '/admin/editor') ?>" class="btn btn-primary">Open Site Editor →</a>
</div>

<!-- ── Hub cards ──────────────────────────────────────────────── -->
<div class="sb-hub sb-full-width">

    <a href="#sb-content-pages" class="sb-hub-card">
        <span class="sb-hub-icon">📄</span>
        <span class="sb-hub-label">Content Pages</span>
        <span class="sb-hub-count"><?= count($contentPages) ?></span>
        <span class="sb-hub-action">
            <a href="<?= url('/admin/pages/new') ?>" class="btn btn-primary btn-small" onclick="event.stopPropagation()">+ New</a>
        </span>
    </a>

    <div class="sb-hub-card">
        <span class="sb-hub-icon">🔝</span>
        <span class="sb-hub-label">Headers</span>
        <span class="sb-hub-count"><?= count($headerPages) ?></span>
        <span class="sb-hub-links">
            <?php foreach ($headerPages as $_hp): ?>
            <a href="<?= url('/admin/editor/' . (int)$_hp['id'] . '/edit') ?>"><?= e($_hp['template_name'] ?? $_hp['title']) ?> →</a>
            <?php endforeach; ?>
            <?php if (empty($headerPages)): ?>
            <a href="<?= url('/admin/site-builder/global-header') ?>">Create global header →</a>
            <?php endif; ?>
        </span>
    </div>

    <div class="sb-hub-card">
        <span class="sb-hub-icon">🔚</span>
        <span class="sb-hub-label">Footers</span>
        <span class="sb-hub-count"><?= count($footerPages) ?></span>
        <span class="sb-hub-links">
            <?php foreach ($footerPages as $_fp): ?>
            <a href="<?= url('/admin/editor/' . (int)$_fp['id'] . '/edit') ?>"><?= e($_fp['template_name'] ?? $_fp['title']) ?> →</a>
            <?php endforeach; ?>
            <?php if (empty($footerPages)): ?><span class="text-muted">None configured</span><?php endif; ?>
        </span>
    </div>

    <a href="<?= url('/admin/templates') ?>" class="sb-hub-card">
        <span class="sb-hub-icon">📐</span>
        <span class="sb-hub-label">Templates</span>
        <span class="sb-hub-count"><?= $templateCount ?></span>
    </a>

    <a href="<?= url('/admin/menus') ?>" class="sb-hub-card">
        <span class="sb-hub-icon">☰</span>
        <span class="sb-hub-label">Menus</span>
        <span class="sb-hub-count"><?= $menuCount ?></span>
    </a>

    <a href="<?= url('/admin/site-builder/structure') ?>" class="sb-hub-card">
        <span class="sb-hub-icon">🗺️</span>
        <span class="sb-hub-label">Structure</span>
        <span class="sb-hub-meta">Page tree</span>
    </a>

    <a href="<?= url('/admin/template-editor') ?>" class="sb-hub-card">
        <span class="sb-hub-icon">🧩</span>
        <span class="sb-hub-label">PHP Templates</span>
        <span class="sb-hub-meta">View files</span>
    </a>

</div>

<!-- ── Content pages list ─────────────────────────────────────── -->
<div id="sb-content-pages" class="sb-section sb-full-width">
    <div class="sb-section-header">
        <h2>Content Pages</h2>
        <a href="<?= url('/admin/pages/new') ?>" class="btn btn-primary btn-small">+ New Page</a>
    </div>

    <?php
    $published = array_filter($contentPages, fn($p) => $p['status'] === 'published');
    $drafts    = array_filter($contentPages, fn($p) => $p['status'] === 'draft');
    $archived  = array_filter($contentPages, fn($p) => $p['status'] === 'archived');
    $groups    = array_filter(['Published' => $published, 'Drafts' => $drafts, 'Archived' => $archived]);
    ?>

    <?php if (empty($contentPages)): ?>
        <div class="sb-empty">
            <p>No content pages yet.</p>
            <a href="<?= url('/admin/pages/new') ?>" class="btn btn-primary">Create First Page</a>
        </div>
    <?php else: ?>
        <?php foreach ($groups as $_groupLabel => $_groupPages): ?>
        <h3 class="sb-group-heading"><?= $_groupLabel ?> <span class="sb-group-count"><?= count($_groupPages) ?></span></h3>
        <table class="admin-table sb-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>URL</th>
                    <th>Template</th>
                    <th>Updated</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($_groupPages as $_pg): ?>
                <tr>
                    <td>
                        <a href="<?= url('/admin/editor/' . (int)$_pg['id'] . '/edit') ?>" class="sb-page-title">
                            <?= e($_pg['title']) ?>
                        </a>
                        <?php if ($_pg['slug'] === 'home'): ?><span class="badge badge-info">🏠 Home</span><?php endif; ?>
                    </td>
                    <td><code>/<?= e($_pg['slug']) ?></code></td>
                    <td><?= e(ucfirst(str_replace('-', ' ', $_pg['template'] ?? 'default'))) ?></td>
                    <td><?= format_date($_pg['updated_at'], 'j M Y') ?></td>
                    <td class="sb-actions">
                        <a href="<?= url('/admin/editor/' . (int)$_pg['id'] . '/edit') ?>" class="btn btn-small">Edit</a>
                        <a href="<?= url('/' . e($_pg['slug'])) ?>" target="_blank" class="btn btn-small btn-outline">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

