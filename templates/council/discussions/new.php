<div class="council-discussion-new">
    <div class="page-header">
        <a href="/council/discussions" class="back-link">&larr; All Discussions</a>
        <h1>New Discussion</h1>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="form-errors">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="post" action="/council/discussions" class="council-form">
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="title">Topic Title <span class="required">*</span></label>
            <input type="text" name="title" id="title" class="form-input"
                   value="<?= e($discussion['title'] ?? '') ?>" required
                   placeholder="e.g. Budget review for Q2 2025">
        </div>

        <div class="form-group">
            <label for="category">Category</label>
            <input type="text" name="category" id="category" class="form-input"
                   value="<?= e($discussion['category'] ?? '') ?>"
                   placeholder="e.g. Finance, Events, General"
                   list="category-list">
            <datalist id="category-list">
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= e($cat) ?>">
                <?php endforeach; ?>
            </datalist>
            <p class="form-help">Choose an existing category or type a new one.</p>
        </div>

        <div class="form-group">
            <label for="body">Opening Post</label>
            <textarea name="body" id="body" class="form-input" rows="8"
                      placeholder="Optional: write the first post to start the discussion"><?= e($discussion['body'] ?? '') ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create Discussion</button>
            <a href="/council/discussions" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
