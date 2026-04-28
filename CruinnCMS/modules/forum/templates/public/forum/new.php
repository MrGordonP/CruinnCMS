<section class="container forum-page">
    <header class="forum-header">
        <nav class="forum-breadcrumbs">
            <a href="<?= url('/forum') ?>">Forum</a>
            <?php foreach ($breadcrumbs as $crumb): ?>
                <span class="sep">›</span>
                <a href="<?= url('/forum/' . $crumb['slug']) ?>"><?= e($crumb['title']) ?></a>
            <?php endforeach; ?>
        </nav>
        <h1>New Thread</h1>
    </header>

    <form method="post" action="<?= url('/forum/' . $category['slug'] . '/new') ?>" class="form-register forum-thread-form">
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="title">Title</label>
            <input id="title" name="title" class="form-input" maxlength="255" required value="<?= e($old['title'] ?? '') ?>">
            <?php if (!empty($errors['title'])): ?><p class="form-help required"><?= e($errors['title']) ?></p><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="body_html">Message</label>
            <textarea id="body_html" name="body_html" class="form-input" rows="10" required><?= e($old['body_html'] ?? '') ?></textarea>
            <?php if (!empty($errors['body_html'])): ?><p class="form-help required"><?= e($errors['body_html']) ?></p><?php endif; ?>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create Thread</button>
            <a href="<?= url('/forum/' . $category['slug']) ?>" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</section>
