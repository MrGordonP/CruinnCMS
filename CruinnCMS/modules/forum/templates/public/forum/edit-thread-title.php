<section class="container forum-page">
    <header class="forum-header">
        <nav class="forum-breadcrumbs">
            <a href="<?= url('/forum') ?>">Forum</a>
            <span class="sep">›</span>
            <a href="<?= url('/forum/thread/' . (int)$thread['id']) ?>"><?= e($thread['title']) ?></a>
            <span class="sep">›</span>
            <span class="current">Rename</span>
        </nav>
        <h1>Edit Thread Title</h1>
    </header>

    <form method="post" action="<?= url('/forum/thread/' . (int)$thread['id'] . '/edit-title') ?>" class="forum-edit-form">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="title">Thread Title</label>
            <input type="text" id="title" name="title" value="<?= e($thread['title']) ?>" class="form-input" required minlength="5">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Title</button>
            <a href="<?= url('/forum/thread/' . (int)$thread['id']) ?>" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</section>
