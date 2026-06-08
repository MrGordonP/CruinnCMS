<?php
\Cruinn\Template::requireCss('admin-panel-layout.css');
\Cruinn\Template::requireCss('admin-site-builder.css');
$GLOBALS['admin_flush_layout'] = true;
$settings = $settings ?? [];
?>

<div class="panel-layout no-detail" id="blog-layout">
<div class="pl-panel pl-panel-left">
    <div class="pl-panel-header"><h3>Blog</h3></div>
    <div class="pl-panel-body" style="padding:0">
        <div class="pl-nav-section">Manage</div>
        <a class="pl-nav-item" href="<?= url('/admin/blog') ?>">Overview</a>
        <a class="pl-nav-item" href="<?= url('/admin/blog/posts') ?>">Posts</a>
        <a class="pl-nav-item" href="<?= url('/admin/blog/profiles') ?>">Profiles</a>
        <a class="pl-nav-item active" href="<?= url('/admin/blog/settings') ?>">Settings</a>
    </div>
</div>
<div class="pl-main">
    <div class="pl-main-toolbar">
        <span class="pl-main-title">Blog Settings</span>
    </div>
    <div class="pl-main-scroll">

    <form method="post" action="/admin/blog/settings" class="form-article-meta">
        <?= csrf_field() ?>

        <div class="form-grid">
            <section class="form-section">
                <h3>Public Routing</h3>

                <div class="form-group">
                    <label for="blog-list-page">Blog List Page</label>
                    <select id="blog-list-page" name="list_page_id" class="form-input">
                        <option value="">— Select page —</option>
                        <?php foreach (($pages ?? []) as $page): ?>
                        <option value="<?= (int) ($page['id'] ?? 0) ?>"<?= (int) ($settings['list_page_id'] ?? 0) === (int) ($page['id'] ?? 0) ? ' selected' : '' ?>>
                            <?= e(($page['title'] ?? 'Untitled') . ' (' . ($page['slug'] ?? '') . ')') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small>The published page that owns the public blog list path.</small>
                </div>

                <div class="form-group">
                    <label for="blog-post-page">Blog Post Shell Page</label>
                    <select id="blog-post-page" name="post_page_id" class="form-input">
                        <option value="">— Reuse list page —</option>
                        <?php foreach (($pages ?? []) as $page): ?>
                        <option value="<?= (int) ($page['id'] ?? 0) ?>"<?= (int) ($settings['post_page_id'] ?? 0) === (int) ($page['id'] ?? 0) ? ' selected' : '' ?>>
                            <?= e(($page['title'] ?? 'Untitled') . ' (' . ($page['slug'] ?? '') . ')') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Optional shell page for single posts under the list path.</small>
                </div>
            </section>

            <section class="form-section">
                <h3>Defaults</h3>

                <div class="form-group">
                    <label for="blog-default-posts">Default Posts Per Page</label>
                    <input type="number" id="blog-default-posts" name="default_posts_per_page" min="1" max="100" class="form-input" value="<?= (int) ($settings['default_posts_per_page'] ?? 10) ?>">
                    <small>Used by the blog list unless a block overrides it.</small>
                </div>

                <label class="form-checkbox">
                    <input type="hidden" name="show_return_to_list" value="0">
                    <input type="checkbox" name="show_return_to_list" value="1"<?= !empty($settings['show_return_to_list']) ? ' checked' : '' ?>>
                    Show “Return to list” on posts by default
                </label>

                <label class="form-checkbox">
                    <input type="hidden" name="show_post_navigation" value="0">
                    <input type="checkbox" name="show_post_navigation" value="1"<?= !empty($settings['show_post_navigation']) ? ' checked' : '' ?>>
                    Show previous / next post navigation by default
                </label>
            </section>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Blog Settings</button>
            <a href="/admin/blog" class="btn btn-outline">Back to Blog</a>
        </div>
    </form>

    </div><!-- /pl-main-scroll -->
</div><!-- /pl-main -->
</div><!-- /panel-layout -->
