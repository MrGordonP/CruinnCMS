<div class="admin-page">
    <header class="admin-page-header">
        <h1>Edit Post</h1>
        <p>Thread: <a href="<?= url('/forum/thread/' . (int)$post['thread_id']) ?>"><?= e($post['thread_title']) ?></a></p>
    </header>

    <form method="post" action="<?= url('/admin/forum/post/' . (int)$post['id'] . '/edit') ?>">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="body_html">Post Content</label>
            <textarea id="body_html" name="body_html" class="form-input" rows="12" required><?= e($post['body_html']) ?></textarea>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="<?= url('/forum/thread/' . (int)$post['thread_id'] . '#post-' . (int)$post['id']) ?>" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>
