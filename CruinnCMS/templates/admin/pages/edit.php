<?php
/**
 * New Page Form
 *
 * This template only serves the "create new page" form.
 * Editing existing pages redirects to the Cruinn editor (/admin/editor/{id}/edit).
 */
\Cruinn\Template::requireCss('admin-site-builder.css');

if (!$page) {
    ?>
    <div class="admin-page-editor">
        <h1>New Page</h1>
        <form method="post" action="/admin/pages" class="form-page-meta">
            <?= csrf_field() ?>
            <div class="form-row">
                <div class="form-group form-group-wide">
                    <label for="title">Page Title</label>
                    <input type="text" id="title" name="title" required value="" class="form-input">
                </div>
                <div class="form-group">
                    <label for="slug">URL Slug</label>
                    <div class="input-with-prefix">
                        <span class="input-prefix">/</span>
                        <input type="text" id="slug" name="slug" required value="" class="form-input" pattern="[a-z0-9\-]+">
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-input">
                        <option value="draft" selected>Draft</option>
                        <option value="published">Published</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="render_mode">Render Mode</label>
                    <select id="render_mode" name="render_mode" class="form-input">
                        <option value="cruinn" selected>Cruinn (block editor)</option>
                        <option value="html">HTML (code editor)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="template">Template</label>
                    <select id="template" name="template" class="form-input">
                        <?php foreach ($templates ?? [] as $tpl): ?>
                        <option value="<?= e($tpl['slug']) ?>"><?= e($tpl['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Create Page</button>
            </div>
        </form>
    </div>
    <?php
    return;
}

// Existing page — metadata edit form.
?>
<div class="admin-page-editor">
    <h1>Edit Page</h1>
    <form method="post" action="/admin/pages/<?= (int)$page['id'] ?>" class="form-page-meta">
        <?= csrf_field() ?>
        <div class="form-row">
            <div class="form-group form-group-wide">
                <label for="title">Page Title</label>
                <input type="text" id="title" name="title" required
                       value="<?= e($page['title']) ?>" class="form-input">
            </div>
            <div class="form-group">
                <label for="slug">URL Slug</label>
                <div class="input-with-prefix">
                    <span class="input-prefix">/</span>
                    <input type="text" id="slug" name="slug" required
                           value="<?= e($page['slug']) ?>" class="form-input" pattern="[a-z0-9\/\-]+">
                </div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status" class="form-input">
                    <option value="published" <?= ($page['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
                    <option value="draft"     <?= ($page['status'] ?? '') === 'draft'     ? 'selected' : '' ?>>Draft</option>
                    <option value="archived"  <?= ($page['status'] ?? '') === 'archived'  ? 'selected' : '' ?>>Archived</option>
                </select>
            </div>
            <div class="form-group">
                <label for="render_mode">Render Mode</label>
                <select id="render_mode" name="render_mode" class="form-input">
                    <option value="block" <?= ($page['render_mode'] ?? '') === 'block' ? 'selected' : '' ?>>Cruinn (block editor)</option>
                    <option value="html"  <?= ($page['render_mode'] ?? '') === 'html'  ? 'selected' : '' ?>>HTML (code editor)</option>
                    <option value="file"  <?= ($page['render_mode'] ?? '') === 'file'  ? 'selected' : '' ?>>File</option>
                </select>
            </div>
            <div class="form-group">
                <label for="template">Template</label>
                <select id="template" name="template" class="form-input">
                    <?php foreach ($templates ?? [] as $tpl): ?>
                    <option value="<?= e($tpl['slug']) ?>" <?= ($page['template'] ?? '') === $tpl['slug'] ? 'selected' : '' ?>><?= e($tpl['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label for="meta_description">Meta Description</label>
            <input type="text" id="meta_description" name="meta_description"
                   value="<?= e($page['meta_description'] ?? '') ?>" class="form-input">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <?php
            $editorUrl = ($page['render_mode'] ?? 'block') === 'html'
                ? '/admin/pages/' . (int)$page['id'] . '/html'
                : '/admin/editor/' . (int)$page['id'] . '/edit';
            ?>
            <a href="<?= $editorUrl ?>" class="btn btn-outline">Edit Content</a>
            <a href="/admin/pages" class="btn btn-outline">Back to Pages</a>
        </div>
    </form>
</div>
