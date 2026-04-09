<?php \IGA\Template::requireCss('admin-site-builder.css'); \IGA\Template::requireCss('admin-block-editor.css'); ?>
<div class="admin-article-edit">
    <h1><?= e($title) ?></h1>

    <?php if (!empty($errors)): ?>
    <div class="flash flash-error" role="alert">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Article Metadata Form -->
    <form method="post" action="<?= $article && isset($article['id']) ? '/admin/articles/' . (int)$article['id'] : '/admin/articles' ?>" class="form-article-meta">
        <?= csrf_field() ?>

        <div class="form-grid">
            <div class="form-section">
                <h3>Article Details</h3>

                <div class="form-group">
                    <label for="title">Title <span class="required">*</span></label>
                    <input type="text" id="title" name="title" required
                           value="<?= e($article['title'] ?? '') ?>" class="form-input">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="slug">URL Slug</label>
                        <div class="input-with-prefix">
                            <span class="input-prefix">/news/</span>
                            <input type="text" id="slug" name="slug"
                                   value="<?= e($article['slug'] ?? '') ?>"
                                   class="form-input" pattern="[a-z0-9\-]+"
                                   placeholder="auto-generated from title">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="subject_id">Subject</label>
                        <select id="subject_id" name="subject_id" class="form-input">
                            <option value="">— None —</option>
                            <?php foreach ($subjects as $s): ?>
                            <option value="<?= (int)$s['id'] ?>" <?= ($article['subject_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= e($s['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="excerpt">Excerpt <small>(short summary for listings, max 500 chars)</small></label>
                    <textarea id="excerpt" name="excerpt" rows="3"
                              maxlength="500" class="form-input"><?= e($article['excerpt'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="featured_image">Featured Image URL</label>
                    <input type="text" id="featured_image" name="featured_image"
                           value="<?= e($article['featured_image'] ?? '') ?>"
                           class="form-input" placeholder="/uploads/images/article-hero.jpg">
                    <?php if (!empty($article['featured_image'])): ?>
                        <img src="<?= e($article['featured_image']) ?>" alt="Featured image preview"
                             style="max-width:300px; margin-top:0.5rem; border-radius:4px;">
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-section">
                <h3>Publishing</h3>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-input">
                        <option value="draft" <?= ($article['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="published" <?= ($article['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
                        <option value="archived" <?= ($article['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Archived</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="published_at">Publish Date</label>
                    <input type="datetime-local" id="published_at" name="published_at"
                           value="<?= !empty($article['published_at']) ? date('Y-m-d\TH:i', strtotime($article['published_at'])) : '' ?>"
                           class="form-input">
                    <small>Leave blank to publish immediately when status is set to Published.</small>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <?= $article && isset($article['id']) ? 'Update Article' : 'Create Article' ?>
            </button>
            <a href="/admin/articles" class="btn btn-outline">Cancel</a>
            <?php if ($article && isset($article['id']) && ($article['status'] ?? '') === 'published'): ?>
                <a href="/news/<?= e($article['slug']) ?>" target="_blank" class="btn btn-outline">Preview</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($article && isset($article['id'])): ?>
    <?php
    $editorParentType = 'article';
    $editorParentId   = (int)$article['id'];
    $editorMode       = $article['editor_mode'] ?? 'structured';
    include __DIR__ . '/../components/block-editor.php';
    ?>

    <!-- Danger Zone -->
    <section class="danger-zone">
        <h3>Danger Zone</h3>
        <form method="post" action="/admin/articles/<?= (int)$article['id'] ?>/delete"
              onsubmit="return confirm('Are you sure you want to delete this article and all its content blocks? This cannot be undone.')">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger">Delete this article</button>
        </form>
    </section>
    <?php endif; ?>
</div>
