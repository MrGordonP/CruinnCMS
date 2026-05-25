<?php $forumBasePath = trim((string) ($forumBasePath ?? '')); ?>

<div class="admin-page">
    <header class="admin-page-header">
        <h1>Edit Post</h1>
        <p>
            Thread:
            <?php if ($forumBasePath !== ''): ?>
            <a href="<?= e(rtrim($forumBasePath, '/') . '/thread/' . (int) $post['thread_id']) ?>"><?= e($post['thread_title']) ?></a>
            <?php else: ?>
            <?= e($post['thread_title']) ?>
            <?php endif; ?>
        </p>
    </header>

    <form method="post" action="<?= url('/admin/forum/post/' . (int)$post['id'] . '/edit') ?>">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="body_html">Post Content</label>
            <textarea id="body_html" name="body_html" class="form-input" rows="12" required><?= e($post['body_html']) ?></textarea>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="<?= e($forumBasePath !== '' ? rtrim($forumBasePath, '/') . '/thread/' . (int) $post['thread_id'] . '#post-' . (int) $post['id'] : '/admin/forum') ?>" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>
