<?php
\Cruinn\Template::requireCss('admin-panel-layout.css');
\Cruinn\Template::requireCss('admin-site-builder.css');
$GLOBALS['admin_flush_layout'] = true;

$settings = $settings ?? [];
$recentArticles = $recentArticles ?? [];
$listPage = $listPage ?? null;
?>

<div class="panel-layout no-detail" id="blog-layout">
<div class="pl-panel pl-panel-left">
    <div class="pl-panel-header">
        <h3>Blog</h3>
        <a href="<?= url('/admin/blog/posts/new') ?>" class="btn btn-sm btn-primary">+ New</a>
    </div>
    <div class="pl-panel-body" style="padding:0">
        <div class="pl-nav-section">Manage</div>
        <a class="pl-nav-item active" href="<?= url('/admin/blog') ?>">Overview</a>
        <a class="pl-nav-item" href="<?= url('/admin/blog/posts') ?>">Posts</a>
        <a class="pl-nav-item" href="<?= url('/admin/blog/profiles') ?>">Profiles</a>
        <a class="pl-nav-item" href="<?= url('/admin/blog/settings') ?>">Settings</a>
    </div>
</div>
<div class="pl-main">
    <div class="pl-main-toolbar">
        <span class="pl-main-title">Blog</span>
        <div class="pl-main-toolbar-actions">
            <a href="<?= url('/admin/blog/posts/new') ?>" class="btn btn-small btn-primary">+ New Post</a>
        </div>
    </div>
    <div class="pl-main-scroll">

    <div class="dash-quick-grid" style="margin-bottom:1.5rem;">
        <a href="/admin/blog/posts" class="dash-quick-link">
            <span class="dash-quick-icon">📰</span>
            <strong class="dash-stat-num"><?= (int) ($publishedCount ?? 0) ?></strong>
            <span>Published</span>
        </a>
        <a href="/admin/blog/posts?status=draft" class="dash-quick-link">
            <span class="dash-quick-icon">📝</span>
            <strong class="dash-stat-num"><?= (int) ($draftCount ?? 0) ?></strong>
            <span>Drafts</span>
        </a>
        <a href="/admin/blog/settings" class="dash-quick-link">
            <span class="dash-quick-icon">⚙️</span>
            <strong class="dash-stat-num"><?= (int) ($settings['default_posts_per_page'] ?? 10) ?></strong>
            <span>Posts per page</span>
        </a>
        <a href="/admin/blog/profiles" class="dash-quick-link">
            <span class="dash-quick-icon">🧩</span>
            <strong class="dash-stat-num"><?= (int) ($profileCount ?? 0) ?></strong>
            <span>Profiles</span>
        </a>
    </div>

    <div class="form-grid">
        <section class="form-section">
            <h3>Current Setup</h3>
            <dl class="blog-settings-summary" style="display:grid;grid-template-columns:max-content 1fr;gap:0.75rem 1rem;">
                <dt><strong>List page</strong></dt>
                <dd style="margin:0;">
                    <?php if ($listPage): ?>
                        <?= e($listPage['title'] ?? 'Untitled') ?> (<?= e('/' . ltrim((string) ($listPage['slug'] ?? ''), '/')) ?>)
                    <?php else: ?>
                        <span style="color:var(--color-danger, #b91c1c);">Not configured</span>
                    <?php endif; ?>
                </dd>

                <dt><strong>Post shell</strong></dt>
                <dd style="margin:0;"><?= !empty($settings['post_page_id']) ? 'Custom page selected' : 'Reuses list page' ?></dd>

                <dt><strong>Return to list</strong></dt>
                <dd style="margin:0;"><?= !empty($settings['show_return_to_list']) ? 'Enabled' : 'Disabled' ?></dd>

                <dt><strong>Previous / next</strong></dt>
                <dd style="margin:0;"><?= !empty($settings['show_post_navigation']) ? 'Enabled' : 'Disabled' ?></dd>
            </dl>

            <?php if (!$listPage): ?>
            <p style="margin-top:1rem;color:var(--color-danger, #b91c1c);">The public blog path is not configured yet. Set a Blog List page in Settings.</p>
            <?php endif; ?>
        </section>

        <section class="form-section">
            <h3>Recent Blog Posts</h3>
            <?php if (empty($recentArticles)): ?>
                <p class="admin-empty">No blog posts yet.</p>
            <?php else: ?>
                <ul class="comms-article-list">
                    <?php foreach ($recentArticles as $item): ?>
                    <li>
                        <a href="<?= e('/admin/blog/posts/' . (int) ($item['id'] ?? 0) . '/edit') ?>"><?= e($item['title'] ?? 'Untitled') ?></a>
                        <span class="badge badge-<?= ($item['status'] ?? '') === 'published' ? 'success' : (($item['status'] ?? '') === 'draft' ? 'warning' : 'muted') ?>">
                            <?= e(ucfirst((string) ($item['status'] ?? 'draft'))) ?>
                        </span>
                        <?php if (!empty($item['updated_at'])): ?>
                        <time class="text-muted"><?= format_date($item['updated_at'], 'j M') ?></time>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    </div><!-- /pl-main-scroll -->
</div><!-- /pl-main -->
</div><!-- /panel-layout -->
