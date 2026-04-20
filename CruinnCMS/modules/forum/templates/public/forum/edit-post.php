<section class="container forum-page">
    <header class="forum-header">
        <nav class="forum-breadcrumbs">
            <a href="<?= url('/forum') ?>">Forum</a>
            <?php foreach ($breadcrumbs as $crumb): ?>
                <span class="sep">›</span>
                <a href="<?= url('/forum/' . $crumb['slug']) ?>"><?= e($crumb['title']) ?></a>
            <?php endforeach; ?>
            <span class="sep">›</span>
            <a href="<?= url('/forum/thread/' . (int)$post['thread_id']) ?>">Thread</a>
        </nav>
        <h1>Edit Post</h1>
    </header>

    <form method="post" action="<?= url('/forum/post/' . (int)$post['id'] . '/edit') ?>" class="forum-edit-form">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="body_html">Post Content</label>
            <textarea id="body_html" name="body_html" class="form-input" rows="10" required><?= e($post['body_html']) ?></textarea>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="<?= url('/forum/thread/' . (int)$post['thread_id'] . '#post-' . (int)$post['id']) ?>" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</section>
