<?php
/**
 * Content Sets — Row data manager
 *
 * Left:   set info + field list (read-only reference)
 * Middle: row table with inline edit / add row
 * Right:  add/edit row form (slides in on row click)
 */
\Cruinn\Template::requireCss('admin-content.css');
\Cruinn\Template::requireJs('content-rows.js');

$fields     = $set['fields'] ?? [];
$editRowId  = (int)($_GET['edit'] ?? 0);
$editRow    = null;
if ($editRowId) {
    foreach ($rows as $r) {
        if ((int)$r['id'] === $editRowId) { $editRow = $r; break; }
    }
}
?>

<div class="content-set-rows" id="rows-app"
     data-set-id="<?= (int)$set['id'] ?>"
     data-edit-row-id="<?= $editRowId ? (int)$editRowId : '' ?>">

    <!-- ── Left: Set info ────────────────────────────────────── -->
    <div class="cse-meta-panel">
        <div class="cse-panel-header">
            <h3><?= e($set['name']) ?></h3>
            <a href="/admin/content/<?= (int)$set['id'] ?>/edit" class="btn btn-sm btn-outline">Schema</a>
        </div>
        <div class="cse-panel-scroll cse-ref-body">
            <?php if (!empty($set['description'])): ?>
                <p class="form-help" style="padding:0.75rem 1rem 0"><?= e($set['description']) ?></p>
            <?php endif; ?>
            <p class="form-help" style="padding:0.5rem 1rem 0">
                <strong><?= count($rows) ?></strong> row<?= count($rows) !== 1 ? 's' : '' ?> &nbsp;·&nbsp;
                <strong><?= count($fields) ?></strong> field<?= count($fields) !== 1 ? 's' : '' ?>
            </p>
            <hr class="form-divider" style="margin:0.5rem 0">
            <dl class="cse-type-list" style="padding:0 1rem">
                <?php foreach ($fields as $field): ?>
                    <dt><?= e($field['name']) ?></dt>
                    <dd><?= e($field['label']) ?> <em>(<?= e($field['type']) ?>)</em></dd>
                <?php endforeach; ?>
            </dl>
            <div style="padding:1rem">
                <a href="/admin/content" class="btn btn-outline btn-small">← All Sets</a>
            </div>
        </div>
    </div>

    <!-- ── Middle: Row list ──────────────────────────────────── -->
    <div class="cse-fields-panel">
        <div class="cse-panel-header">
            <h3>Rows</h3>
            <button type="button" class="btn btn-sm btn-primary" id="rows-add-btn">+ Add Row</button>
        </div>
        <div class="cse-panel-scroll">
            <?php if (empty($rows)): ?>
                <div class="cse-field-add-hint">
                    <p>No rows yet. Click <strong>+ Add Row</strong> to enter your first record.</p>
                </div>
            <?php else: ?>
            <table class="admin-table cse-row-table" id="row-table">
                <thead>
                    <tr>
                        <th class="cse-drag-col"></th>
                        <?php foreach ($fields as $field): ?>
                            <th><?= e($field['label']) ?></th>
                        <?php endforeach; ?>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="row-tbody">
                <?php foreach ($rows as $row): ?>
                    <tr data-row-id="<?= (int)$row['id'] ?>" class="cse-data-row<?= (int)$row['id'] === $editRowId ? ' selected' : '' ?>">
                        <td class="cse-drag-handle" draggable="true" title="Drag to reorder">⠿</td>
                        <?php foreach ($fields as $field):
                            $val = $row['data'][$field['name']] ?? '';
                            $display = ($field['type'] === 'image' && $val)
                                ? '<img src="' . e($val) . '" class="cse-thumb" alt="">'
                                : e(mb_strimwidth($val, 0, 60, '…'));
                        ?>
                            <td><?= $display ?></td>
                        <?php endforeach; ?>
                        <td class="table-actions">
                            <button type="button" class="btn btn-outline btn-small"
                                    data-edit-row="<?= (int)$row['id'] ?>">Edit</button>
                            <form method="post"
                                  action="/admin/content/<?= (int)$set['id'] ?>/rows/<?= (int)$row['id'] ?>/delete"
                                  class="inline-form"
                                  data-confirm="Delete this row?">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-danger btn-small">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Right: Add / Edit row form ────────────────────────── -->
    <div class="cse-ref-panel" id="row-form-panel">
        <div class="cse-panel-header">
            <h3 id="row-form-title">Add Row</h3>
        </div>
        <div class="cse-panel-scroll cse-ref-body">
            <!-- Add form -->
            <form method="post" action="/admin/content/<?= (int)$set['id'] ?>/rows" id="add-row-form"
                  style="<?= $editRowId ? 'display:none' : '' ?>">
                <?= csrf_field() ?>
                <?php foreach ($fields as $field): ?>
                <div class="form-group">
                    <label><?= e($field['label']) ?></label>
                    <?php if ($field['type'] === 'richtext'): ?>
                        <textarea name="field_<?= e($field['name']) ?>" class="form-input" rows="4"></textarea>
                    <?php else: ?>
                        <input type="<?= $field['type'] === 'date' ? 'date' : 'text' ?>"
                               name="field_<?= e($field['name']) ?>"
                               class="form-input"
                               <?= $field['type'] === 'image' ? 'placeholder="/uploads/..."' : '' ?>
                               <?= $field['type'] === 'url' ? 'placeholder="https://..."' : '' ?>>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php if (empty($fields)): ?>
                    <p class="form-help">This set has no fields yet. <a href="/admin/content/<?= (int)$set['id'] ?>/edit">Edit the schema</a> to add fields first.</p>
                <?php else: ?>
                    <div class="cse-form-actions">
                        <button type="submit" class="btn btn-primary">Add Row</button>
                    </div>
                <?php endif; ?>
            </form>

            <!-- Edit forms (one per row, shown/hidden via JS) -->
            <?php foreach ($rows as $row): ?>
            <form method="post"
                  action="/admin/content/<?= (int)$set['id'] ?>/rows/<?= (int)$row['id'] ?>"
                  id="edit-row-form-<?= (int)$row['id'] ?>"
                  style="display:none">
                <?= csrf_field() ?>
                <?php foreach ($fields as $field):
                    $val = $row['data'][$field['name']] ?? '';
                ?>
                <div class="form-group">
                    <label><?= e($field['label']) ?></label>
                    <?php if ($field['type'] === 'richtext'): ?>
                        <textarea name="field_<?= e($field['name']) ?>" class="form-input" rows="4"><?= e($val) ?></textarea>
                    <?php else: ?>
                        <input type="<?= $field['type'] === 'date' ? 'date' : 'text' ?>"
                               name="field_<?= e($field['name']) ?>"
                               class="form-input"
                               value="<?= e($val) ?>">
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <div class="cse-form-actions">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <button type="button" class="btn btn-outline rows-cancel-btn">Cancel</button>
                </div>
            </form>
            <?php endforeach; ?>
        </div>
    </div>

</div>
