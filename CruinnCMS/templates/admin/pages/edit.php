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

// Existing pages: should not reach here (editPage redirects to Cruinn editor).
// If somehow loaded, send them there.
?>
<script>window.location.href = '/admin/editor/<?= (int)$page['id'] ?>/edit';</script>
