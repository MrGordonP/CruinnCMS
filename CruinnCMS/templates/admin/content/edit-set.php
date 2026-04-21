<?php
/**
 * Content Sets — Schema editor (new or edit)
 *
 * Left:   set metadata (name, slug, description)
 * Middle: field list (add/remove/reorder fields)
 * Right:  field type reference
 */
\Cruinn\Template::requireCss('admin-content.css');

$isNew  = empty($set['id']);
$action = $isNew ? '/admin/content' : '/admin/content/' . (int)$set['id'];
$fields = $set['fields'] ?? [];
?>

<div class="content-set-editor" id="set-editor">

    <!-- ── Left: Set metadata ─────────────────────────────────── -->
    <div class="cse-meta-panel">
        <div class="cse-panel-header">
            <h3><?= $isNew ? 'New Content Set' : 'Set Details' ?></h3>
        </div>
        <div class="cse-panel-scroll">
            <form method="post" action="<?= e($action) ?>" id="set-form">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="name">Name <span class="required">*</span></label>
                    <input type="text" name="name" id="name" class="form-input"
                           value="<?= e($set['name'] ?? '') ?>" required
                           <?= $isNew ? 'oninput="autoSlug(this)"' : '' ?>>
                </div>

                <div class="form-group">
                    <label for="slug">Slug <span class="required">*</span></label>
                    <input type="text" name="slug" id="slug" class="form-input"
                           value="<?= e($set['slug'] ?? '') ?>" required
                           pattern="[a-z0-9\-]+" title="Lowercase letters, numbers and hyphens only">
                    <p class="form-help">Used to reference this set in dynamic blocks.</p>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea name="description" id="description" class="form-input" rows="3"><?= e($set['description'] ?? '') ?></textarea>
                </div>

                <div class="cse-form-actions">
                    <button type="submit" class="btn btn-primary">
                        <?= $isNew ? 'Create Set' : 'Save Changes' ?>
                    </button>
                    <a href="/admin/content" class="btn btn-outline">Cancel</a>
                </div>
            </form>

            <?php if (!$isNew): ?>
            <hr class="form-divider">
            <form method="post" action="/admin/content/<?= (int)$set['id'] ?>/delete"
                  onsubmit="return confirm('Delete this content set and all its rows? This cannot be undone.')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger btn-small">Delete Set</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Middle: Field definitions ─────────────────────────── -->
    <div class="cse-fields-panel">
        <div class="cse-panel-header">
            <h3>Fields</h3>
            <button type="button" class="btn btn-sm btn-primary" onclick="addField()">+ Add Field</button>
        </div>
        <div class="cse-panel-scroll">
            <p class="form-help" style="padding:0.6rem 1rem 0">
                Fields define the columns of data in this set. Drag to reorder.
                Changes take effect when you save the set above.
            </p>
            <div id="field-list">
                <?php foreach ($fields as $i => $field): ?>
                <div class="cse-field-row" draggable="true">
                    <span class="cse-drag-handle" title="Drag to reorder">⠿</span>
                    <div class="cse-field-inputs">
                        <input type="text" name="field_name[]" class="form-input form-input-sm"
                               placeholder="field_name" value="<?= e($field['name'] ?? '') ?>"
                               form="set-form">
                        <input type="text" name="field_label[]" class="form-input form-input-sm"
                               placeholder="Label" value="<?= e($field['label'] ?? '') ?>"
                               form="set-form">
                        <select name="field_type[]" class="form-select form-input-sm" form="set-form">
                            <?php foreach (['text','richtext','image','url','date'] as $t): ?>
                            <option value="<?= $t ?>" <?= ($field['type'] ?? 'text') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" class="cse-field-remove" onclick="removeField(this)" title="Remove field">✕</button>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="cse-field-add-hint" id="field-empty" <?= !empty($fields) ? 'style="display:none"' : '' ?>>
                <p>No fields yet. Click <strong>+ Add Field</strong> to define the columns of this set.</p>
            </div>
        </div>
    </div>

    <!-- ── Right: Reference ───────────────────────────────────── -->
    <div class="cse-ref-panel">
        <div class="cse-panel-header">
            <h3>Field Types</h3>
        </div>
        <div class="cse-panel-scroll cse-ref-body">
            <dl class="cse-type-list">
                <dt>text</dt><dd>Single-line plain text</dd>
                <dt>richtext</dt><dd>Multi-line formatted text (rendered as HTML)</dd>
                <dt>image</dt><dd>URL to an image (stored as path/URL string)</dd>
                <dt>url</dt><dd>A hyperlink URL</dd>
                <dt>date</dt><dd>ISO date (YYYY-MM-DD)</dd>
            </dl>
            <hr class="form-divider">
            <p class="form-help"><strong>Using in the editor</strong><br>
            Drop a <em>Data List</em> block onto the canvas, pick this set, and bind individual child block fields to <code>{{field_name}}</code> tokens.</p>
        </div>
    </div>

</div>

<template id="field-row-template">
    <div class="cse-field-row" draggable="true">
        <span class="cse-drag-handle" title="Drag to reorder">⠿</span>
        <div class="cse-field-inputs">
            <input type="text" name="field_name[]" class="form-input form-input-sm" placeholder="field_name" form="set-form">
            <input type="text" name="field_label[]" class="form-input form-input-sm" placeholder="Label" form="set-form">
            <select name="field_type[]" class="form-select form-input-sm" form="set-form">
                <option value="text">Text</option>
                <option value="richtext">Richtext</option>
                <option value="image">Image</option>
                <option value="url">URL</option>
                <option value="date">Date</option>
            </select>
        </div>
        <button type="button" class="cse-field-remove" onclick="removeField(this)" title="Remove field">✕</button>
    </div>
</template>

<script>
function addField() {
    const tmpl  = document.getElementById('field-row-template').content.cloneNode(true);
    document.getElementById('field-list').appendChild(tmpl);
    document.getElementById('field-empty').style.display = 'none';
    // focus first input in the new row
    document.getElementById('field-list').lastElementChild.querySelector('input').focus();
}

function removeField(btn) {
    const row = btn.closest('.cse-field-row');
    row.remove();
    if (!document.querySelector('.cse-field-row')) {
        document.getElementById('field-empty').style.display = '';
    }
}

// Auto-generate slug from name (new sets only)
function autoSlug(input) {
    const slugEl = document.getElementById('slug');
    if (!slugEl || slugEl.dataset.edited) { return; }
    slugEl.value = input.value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}
document.getElementById('slug')?.addEventListener('input', function () {
    this.dataset.edited = '1';
});

// Drag-to-reorder field rows
(function () {
    const list = document.getElementById('field-list');
    let dragging = null;

    list.addEventListener('dragstart', e => {
        dragging = e.target.closest('.cse-field-row');
        dragging?.classList.add('dragging');
    });
    list.addEventListener('dragend', () => {
        dragging?.classList.remove('dragging');
        dragging = null;
    });
    list.addEventListener('dragover', e => {
        e.preventDefault();
        const over = e.target.closest('.cse-field-row');
        if (over && over !== dragging) {
            const rect = over.getBoundingClientRect();
            const after = e.clientY > rect.top + rect.height / 2;
            list.insertBefore(dragging, after ? over.nextSibling : over);
        }
    });
}());
</script>
