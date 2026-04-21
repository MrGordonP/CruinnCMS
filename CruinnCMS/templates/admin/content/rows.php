<?php
/**
 * Content Sets — Row data manager
 *
 * Left:   set info + field list (read-only reference)
 * Middle: row table with inline edit / add row
 * Right:  add/edit row form (slides in on row click)
 */
\Cruinn\Template::requireCss('admin-content.css');

$fields     = $set['fields'] ?? [];
$editRowId  = (int)($_GET['edit'] ?? 0);
$editRow    = null;
if ($editRowId) {
    foreach ($rows as $r) {
        if ((int)$r['id'] === $editRowId) { $editRow = $r; break; }
    }
}
?>

<div class="content-set-rows" id="rows-app">

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
            <button type="button" class="btn btn-sm btn-primary" onclick="openAddRow()">+ Add Row</button>
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
                                    onclick="openEditRow(<?= (int)$row['id'] ?>)">Edit</button>
                            <form method="post"
                                  action="/admin/content/<?= (int)$set['id'] ?>/rows/<?= (int)$row['id'] ?>/delete"
                                  class="inline-form"
                                  onsubmit="return confirm('Delete this row?')">
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
                    <button type="button" class="btn btn-outline" onclick="openAddRow()">Cancel</button>
                </div>
            </form>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<script>
var _activeEditId = <?= $editRowId ?: 'null' ?>;

function openAddRow() {
    _activeEditId = null;
    document.getElementById('row-form-title').textContent = 'Add Row';
    document.getElementById('add-row-form').style.display = '';
    document.querySelectorAll('[id^="edit-row-form-"]').forEach(f => f.style.display = 'none');
    document.querySelectorAll('.cse-data-row').forEach(r => r.classList.remove('selected'));
}

function openEditRow(id) {
    _activeEditId = id;
    document.getElementById('row-form-title').textContent = 'Edit Row';
    document.getElementById('add-row-form').style.display = 'none';
    document.querySelectorAll('[id^="edit-row-form-"]').forEach(f => f.style.display = 'none');
    const form = document.getElementById('edit-row-form-' + id);
    if (form) { form.style.display = ''; }
    document.querySelectorAll('.cse-data-row').forEach(r => {
        r.classList.toggle('selected', parseInt(r.dataset.rowId) === id);
    });
}

// If arriving with ?edit= pre-selected
<?php if ($editRowId): ?>
openEditRow(<?= $editRowId ?>);
<?php endif; ?>

// Row click to open edit
document.querySelectorAll('.cse-data-row').forEach(row => {
    row.addEventListener('click', function (e) {
        if (e.target.closest('form') || e.target.closest('button') || e.target.closest('a')) { return; }
        openEditRow(parseInt(this.dataset.rowId));
    });
});

// Drag-to-reorder rows (AJAX)
(function () {
    const tbody = document.getElementById('row-tbody');
    if (!tbody) { return; }
    let dragging = null;

    tbody.addEventListener('dragstart', e => {
        dragging = e.target.closest('tr');
        dragging?.classList.add('dragging');
    });
    tbody.addEventListener('dragend', () => {
        dragging?.classList.remove('dragging');
        dragging = null;
        saveRowOrder();
    });
    tbody.addEventListener('dragover', e => {
        e.preventDefault();
        const over = e.target.closest('tr');
        if (over && over !== dragging) {
            const rect = over.getBoundingClientRect();
            const after = e.clientY > rect.top + rect.height / 2;
            tbody.insertBefore(dragging, after ? over.nextSibling : over);
        }
    });

    function saveRowOrder() {
        const order = [...tbody.querySelectorAll('tr[data-row-id]')]
            .map(r => parseInt(r.dataset.rowId));
        fetch('/admin/content/<?= (int)$set['id'] ?>/rows/reorder', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content ?? ''
            },
            body: JSON.stringify({ order })
        });
    }
}());
</script>
