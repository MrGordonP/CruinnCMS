<div class="admin-page">
    <header class="admin-page-header">
        <h1>Move Thread</h1>
        <p>Moving: <strong><?= e($thread['title']) ?></strong></p>
    </header>

    <form method="post" action="<?= url('/admin/forum/' . (int)$thread['id'] . '/move') ?>">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="category_id">Destination Category</label>
            <select id="category_id" name="category_id" required>
                <option value="">— Select category —</option>
                <?php foreach ($categories as $cat): ?>
                    <?php if ((int)$cat['id'] === (int)$thread['category_id']) continue; ?>
                    <option value="<?= (int)$cat['id'] ?>">
                        <?= $cat['parent_id'] ? '&nbsp;&nbsp;↳ ' : '' ?><?= e($cat['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Move Thread</button>
            <a href="<?= url('/forum/thread/' . (int)$thread['id']) ?>" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>
