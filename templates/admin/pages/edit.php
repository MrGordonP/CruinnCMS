<?php
/**
 * Page Editor — Full-Window 3-Panel Site Editor
 *
 * Left:   Page settings + block palette
 * Centre: Block editor canvas
 * Right:  Block properties panel
 */
\Cruinn\Template::requireCss('admin-site-builder.css');
\Cruinn\Template::requireCss('admin-block-editor.css');
\Cruinn\Template::requireJs('block-editor/core.js', 'block-editor/undo.js', 'block-editor/properties.js', 'block-editor/drag.js', 'site-editor.js');
?>

if (!$page) {
    // New page — show simple form (not the full editor)
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
?>

<form method="post" action="/admin/pages/<?= (int)$page['id'] ?>" class="form-page-meta" id="pageSettingsForm">
<?= csrf_field() ?>

<div class="site-editor" data-editor-for="page" data-entity-id="<?= (int)$page['id'] ?>">

    <!-- ── Top Toolbar ──────────────────────────────────── -->
    <div class="site-editor-toolbar">
        <div class="se-toolbar-left">
            <a href="<?= url('/admin/pages') ?>" class="btn btn-outline btn-small">&larr; Pages</a>
            <h2 class="se-toolbar-title"><?= e($page['title']) ?></h2>
            <span class="badge badge-<?= $page['status'] === 'published' ? 'published' : 'draft' ?>"><?= e($page['status']) ?></span>
        </div>
        <div class="se-toolbar-right">
            <a href="/<?= e($page['slug']) ?>" target="_blank" class="btn btn-outline btn-small">Preview</a>
            <button type="button" id="se-revert-btn" class="btn btn-outline btn-small btn-revert" title="Discard all changes since last save">Revert</button>
            <button type="submit" class="btn btn-primary btn-small">Save Page</button>
        </div>
    </div>

    <!-- ── Left Panel ───────────────────────────────────── -->
    <div class="site-editor-left" id="site-editor-left">

        <!-- Page Settings -->
        <div class="se-section">
            <div class="se-section-header se-section-header-static">Page Settings</div>
            <div class="se-section-body se-section-body-open">
                <div class="se-field">
                    <label>Title</label>
                    <input type="text" id="title" name="title" required value="<?= e($page['title']) ?>" class="se-input">
                </div>
                <div class="se-field">
                    <label>URL Slug</label>
                    <div style="display:flex;align-items:center;gap:2px">
                        <span style="color:#94a3b8;font-size:0.82rem">/</span>
                        <input type="text" id="slug" name="slug" required
                               value="<?= e($page['slug']) ?>"
                               class="se-input" pattern="[a-z0-9\-]+" style="flex:1">
                    </div>
                </div>
                <div class="se-field">
                    <label>Status</label>
                    <select name="status" class="se-input">
                        <option value="draft" <?= $page['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="published" <?= $page['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                        <option value="archived" <?= $page['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
                    </select>
                </div>
                <div class="se-field">
                    <label>Template</label>
                    <select name="template" class="se-input">
                        <?php $currentTemplate = $page['template'] ?? 'default'; ?>
                        <?php foreach ($templates ?? [] as $tpl): ?>
                        <option value="<?= e($tpl['slug']) ?>" <?= $currentTemplate === $tpl['slug'] ? 'selected' : '' ?>>
                            <?= e($tpl['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="se-field">
                    <label>Meta Description</label>
                    <textarea name="meta_description" class="se-input" rows="2"
                              maxlength="320"><?= e($page['meta_description'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Block Palette -->
        <div class="se-section">
            <div class="se-section-header se-section-header-static">Add Block</div>
            <div class="se-section-body se-section-body-open">
                <div class="se-block-palette">
                    <div class="se-palette-group">
                        <span class="se-palette-label">Content</span>
                        <button type="button" class="se-palette-btn" data-type="text">&#9998; Text</button>
                        <button type="button" class="se-palette-btn" data-type="heading">H Heading</button>
                        <button type="button" class="se-palette-btn" data-type="image">&#128248; Image</button>
                        <button type="button" class="se-palette-btn" data-type="gallery">&#128247; Gallery</button>
                        <button type="button" class="se-palette-btn" data-type="html">&lt;/&gt; HTML</button>
                    </div>
                    <div class="se-palette-group">
                        <span class="se-palette-label">Layout</span>
                        <button type="button" class="se-palette-btn" data-type="row">&#8862; Row</button>
                        <button type="button" class="se-palette-btn" data-type="container">&#9634; Container</button>
                        <button type="button" class="se-palette-btn" data-type="divider">&mdash; Divider</button>
                    </div>
                    <div class="se-palette-group">
                        <span class="se-palette-label">Navigation</span>
                        <button type="button" class="se-palette-btn" data-type="nav-menu">&#9776; Nav Menu</button>
                    </div>
                    <div class="se-palette-group">
                        <span class="se-palette-label">Dynamic</span>
                        <button type="button" class="se-palette-btn" data-type="event-list">&#128197; Events</button>
                        <button type="button" class="se-palette-btn" data-type="map">&#128205; Map</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Danger Zone -->
        <div class="se-section se-danger-section">
            <button type="button" class="se-section-header" data-toggle="se-page-danger">
                Danger Zone <span class="se-chevron">&#9662;</span>
            </button>
            <div class="se-section-body se-section-collapsed" id="se-page-danger">
                <p class="se-danger-text">Permanently delete this page.</p>
                <form method="post" action="/admin/pages/<?= (int)$page['id'] ?>/delete"
                      onsubmit="return confirm('Are you sure you want to delete this page?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger btn-small">Delete Page</button>
                </form>
            </div>
        </div>

    </div>

    <!-- ── Centre Panel (Block Canvas) ──────────────────── -->
    <div class="site-editor-center">
        <?php
        $editorParentType = 'page';
        $editorParentId   = (int)$page['id'];
        $editorMode       = $page['editor_mode'] ?? 'structured';
        $editorEmbedded   = true;
        include __DIR__ . '/../components/block-editor.php';
        ?>
    </div>

    <!-- ── Right Panel (Properties) ─────────────────────── -->
    <div class="site-editor-right" id="site-editor-right">
        <div class="block-props-panel se-props-embedded" id="block-props-panel">
            <div class="se-props-placeholder" id="se-props-placeholder">
                <p>Select a block to edit its properties.</p>
            </div>
            <div class="se-props-content">
                <?php include __DIR__ . '/../components/block-props-panel.php'; ?>
            </div>
        </div>
    </div>

</div>
</form>
