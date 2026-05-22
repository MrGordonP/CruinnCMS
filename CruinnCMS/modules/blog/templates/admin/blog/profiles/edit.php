<?php \Cruinn\Template::requireCss('admin-site-builder.css'); ?>
<?php $blogNav = 'profiles'; ?>
<?php include dirname(__DIR__) . '/_nav.php'; ?>

<?php
$profile = $profile ?? [];
$errors = $errors ?? [];
$isEdit = !empty($profile['id']);
$action = $isEdit
    ? '/admin/blog/profiles/' . (int) $profile['id']
    : '/admin/blog/profiles';
?>

<div class="admin-article-edit">
    <h1><?= $isEdit ? 'Edit Blog Profile' : 'New Blog Profile' ?></h1>

    <form method="post" action="<?= e($action) ?>" class="form-article-meta">
        <?= csrf_field() ?>

        <div class="form-grid">
            <section class="form-section">
                <h3>Identity</h3>

                <div class="form-group">
                    <label for="blog-profile-name">Profile Name</label>
                    <input type="text" id="blog-profile-name" name="name" class="form-input" value="<?= e($profile['name'] ?? '') ?>" required>
                    <?php if (!empty($errors['name'])): ?><small style="color:var(--color-danger, #b91c1c);"><?= e($errors['name']) ?></small><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="blog-profile-slug">Slug</label>
                    <input type="text" id="blog-profile-slug" name="slug" class="form-input" value="<?= e($profile['slug'] ?? '') ?>">
                    <?php if (!empty($errors['slug'])): ?><small style="color:var(--color-danger, #b91c1c);"><?= e($errors['slug']) ?></small><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="blog-profile-description">Description</label>
                    <textarea id="blog-profile-description" name="description" class="form-input" rows="5"><?= e($profile['description'] ?? '') ?></textarea>
                    <small>Internal note to explain where this profile is meant to be used.</small>
                </div>
            </section>

            <section class="form-section">
                <h3>Rendering Defaults</h3>

                <div class="form-group">
                    <label for="blog-profile-display-mode">Display Mode</label>
                    <select id="blog-profile-display-mode" name="display_mode" class="form-input">
                        <option value="both"<?= ($profile['display_mode'] ?? 'both') === 'both' ? ' selected' : '' ?>>List and single post</option>
                        <option value="list"<?= ($profile['display_mode'] ?? '') === 'list' ? ' selected' : '' ?>>List only</option>
                        <option value="post"<?= ($profile['display_mode'] ?? '') === 'post' ? ' selected' : '' ?>>Single post only</option>
                    </select>
                    <small>Used by combined blog content blocks unless the block overrides it.</small>
                </div>

                <div class="form-group">
                    <label for="blog-profile-posts">Posts Per Page</label>
                    <input type="number" id="blog-profile-posts" name="posts_per_page" min="1" max="100" class="form-input" value="<?= (int) ($profile['posts_per_page'] ?? 10) ?>">
                </div>

                <label class="form-checkbox">
                    <input type="hidden" name="show_return_to_list" value="0">
                    <input type="checkbox" name="show_return_to_list" value="1"<?= !empty($profile['show_return_to_list']) ? ' checked' : '' ?>>
                    Show “Return to list” by default
                </label>

                <label class="form-checkbox">
                    <input type="hidden" name="show_post_navigation" value="0">
                    <input type="checkbox" name="show_post_navigation" value="1"<?= !empty($profile['show_post_navigation']) ? ' checked' : '' ?>>
                    Show previous / next navigation by default
                </label>
            </section>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Profile' : 'Create Profile' ?></button>
            <a href="/admin/blog/profiles" class="btn btn-outline">Back to Profiles</a>
        </div>
    </form>
</div>
