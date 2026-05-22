<?php \Cruinn\Template::requireCss('admin-site-builder.css'); ?>
<?php $blogNav = 'profiles'; ?>
<?php include dirname(__DIR__) . '/_nav.php'; ?>

<?php $profiles = $profiles ?? []; ?>

<div class="admin-article-list">
    <div class="admin-list-header">
        <h1>Blog Profiles</h1>
        <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
            <a href="/admin/blog/profiles/new" class="btn btn-primary">+ New Profile</a>
            <a href="/admin/blog" class="btn btn-outline">Back to Blog</a>
        </div>
    </div>

    <?php if (empty($profiles)): ?>
        <div class="admin-empty">
            <p>No blog profiles yet.</p>
            <p class="text-muted">Create reusable blog rendering presets here, then select them from module-content blocks in the editor.</p>
        </div>
    <?php else: ?>
        <div class="form-grid">
            <?php foreach ($profiles as $profile): ?>
            <section class="form-section">
                <div style="display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;flex-wrap:wrap;">
                    <div>
                        <h3 style="margin-bottom:0.25rem;"><?= e($profile['name'] ?? 'Untitled') ?></h3>
                        <p class="text-muted" style="margin:0;"><?= e($profile['slug'] ?? '') ?></p>
                    </div>
                    <span class="badge badge-muted"><?= e(ucfirst((string) ($profile['display_mode'] ?? 'both'))) ?></span>
                </div>

                <?php if (!empty($profile['description'])): ?>
                <p style="margin-top:0.75rem;"><?= nl2br(e($profile['description'])) ?></p>
                <?php endif; ?>

                <dl style="display:grid;grid-template-columns:max-content 1fr;gap:0.5rem 1rem;margin:1rem 0 0;">
                    <dt><strong>Posts per page</strong></dt>
                    <dd style="margin:0;"><?= (int) ($profile['posts_per_page'] ?? 10) ?></dd>
                    <dt><strong>Return to list</strong></dt>
                    <dd style="margin:0;"><?= !empty($profile['show_return_to_list']) ? 'Enabled' : 'Disabled' ?></dd>
                    <dt><strong>Previous / next</strong></dt>
                    <dd style="margin:0;"><?= !empty($profile['show_post_navigation']) ? 'Enabled' : 'Disabled' ?></dd>
                </dl>

                <div class="form-actions" style="margin-top:1rem;">
                    <a href="<?= e('/admin/blog/profiles/' . (int) ($profile['id'] ?? 0) . '/edit') ?>" class="btn btn-outline">Edit</a>
                    <form method="post" action="<?= e('/admin/blog/profiles/' . (int) ($profile['id'] ?? 0) . '/delete') ?>" onsubmit="return confirm('Delete this blog profile?');" style="display:inline;">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline btn-danger">Delete</button>
                    </form>
                </div>
            </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
