<?php
/**
 * Content Sets — Schema editor (new or edit)
 *
 * Left:   set metadata (name, slug, description, type)
 * Middle: field list (manual) OR query builder (query)
 * Right:  field type reference / query help
 */
\Cruinn\Template::requireCss('admin-content.css');
\Cruinn\Template::requireJs('content-set-editor.js');
$GLOBALS['admin_flush_layout'] = true;

$isNew       = empty($set['id']);
$action      = $isNew ? '/admin/content' : '/admin/content/' . (int)$set['id'];
$fields      = $set['fields']       ?? [];
$setType     = $set['type']         ?? 'manual';
$queryConfig = $set['query_config'] ?? [];
$dbTables    = $dbTables            ?? [];

$DL_FILTER_OPS = ['=', '!=', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'IS NULL', 'IS NOT NULL'];
?>

<div class="content-set-editor" id="set-editor"
     data-set-type="<?= e($setType) ?>"
     data-tables="<?= e(json_encode($dbTables, JSON_HEX_TAG | JSON_HEX_AMP)) ?>"
     data-filter-ops="<?= e(json_encode($DL_FILTER_OPS)) ?>"
     <?= (!$isNew && $setType === 'query') ? 'data-preview-url="/admin/content/' . (int)$set['id'] . '/preview"' : '' ?>>

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
                           <?= $isNew ? '' : 'data-edited="1"' ?>>
                </div>

                <div class="form-group">
                    <label for="slug">Slug <span class="required">*</span></label>
                    <input type="text" name="slug" id="slug" class="form-input"
                           value="<?= e($set['slug'] ?? '') ?>" required
                           pattern="[a-z0-9\-]+" title="Lowercase letters, numbers and hyphens only">
                    <p class="form-help">Used to reference this set in dynamic blocks.</p>
                </div>

                <?php if ($isNew): ?>
                <div class="form-group">
                    <label for="set-type">Type <span class="required">*</span></label>
                    <select name="type" id="set-type" class="form-select">
                        <option value="manual">Manual — editors enter rows</option>
                        <option value="query">Query — pulls live data from a table</option>
                    </select>
                    <p class="form-help">Cannot be changed after creation.</p>
                </div>
                <?php else: ?>
                <input type="hidden" name="type" value="<?= e($setType) ?>">
                <div class="form-group">
                    <label>Type</label>
                    <p class="form-help" style="margin:0"><?= $setType === 'query' ? '🔍 Query set — pulls live data' : '✏️ Manual set — editors enter rows' ?></p>
                </div>
                <?php endif; ?>

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
                  data-confirm="Delete this content set and all its rows? This cannot be undone.">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger btn-small">Delete Set</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Middle: Field definitions / Query builder ────────── -->
    <div class="cse-fields-panel">

        <!-- Manual mode -->
        <div id="cse-manual-panel" style="display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden<?= ($setType === 'query') ? ';display:none' : '' ?>">
            <div class="cse-panel-header">
                <h3>Fields</h3>
                <button type="button" class="btn btn-sm btn-primary" id="cse-add-field-btn">+ Add Field</button>
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
                        <button type="button" class="cse-field-remove" title="Remove field">✕</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="cse-field-add-hint" id="field-empty" <?= !empty($fields) ? 'style="display:none"' : '' ?>>
                    <p>No fields yet. Click <strong>+ Add Field</strong> to define the columns of this set.</p>
                </div>
            </div>
        </div>

        <!-- Query mode -->
        <div id="cse-query-panel" style="display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden<?= ($setType !== 'query') ? ';display:none' : '' ?>">
            <div class="cse-panel-header">
                <h3>Query</h3>
            </div>
            <div class="cse-panel-scroll" style="padding:0.75rem 1rem">
                <input type="hidden" name="query_config" id="cse-query-config-input" form="set-form" value="<?= htmlspecialchars(json_encode($queryConfig), ENT_QUOTES, 'UTF-8') ?>">

                <!-- Primary table -->
                <div class="form-group">
                    <label>Primary Table</label>
                    <select class="form-select" id="cse-table">
                        <option value="">— select table —</option>
                        <?php foreach ($dbTables as $tbl): ?>
                        <option value="<?= e($tbl) ?>" <?= ($queryConfig['table'] ?? '') === $tbl ? 'selected' : '' ?>><?= e($tbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Joins -->
                <div class="form-group">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.4rem">
                        <label style="margin:0">Joins</label>
                        <button type="button" class="btn btn-small btn-outline" id="cse-add-join-btn">+ Join</button>
                    </div>
                    <div id="cse-joins"></div>
                </div>

                <!-- Filters -->
                <div class="form-group">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.4rem">
                        <label style="margin:0">Filters</label>
                        <button type="button" class="btn btn-small btn-outline" id="cse-add-filter-btn">+ Filter</button>
                    </div>
                    <div id="cse-filters"></div>
                </div>

                <!-- Fields -->
                <div class="form-group">
                    <label>Fields (tokens)</label>
                    <div id="cse-fields" style="font-size:0.82rem;color:#6b7280">Select a table to see fields.</div>
                </div>

                <!-- Order / Limit -->
                <div style="display:flex;gap:0.5rem;align-items:flex-end">
                    <div style="flex:1">
                        <label style="font-size:0.82rem">Order by</label>
                        <select id="cse-orderby" class="form-select" style="font-size:0.82rem">
                            <option value="">— none —</option>
                        </select>
                    </div>
                    <div style="width:70px">
                        <label style="font-size:0.82rem">Dir</label>
                        <select id="cse-orderdir" class="form-select" style="font-size:0.82rem">
                            <option value="ASC">ASC</option>
                            <option value="DESC">DESC</option>
                        </select>
                    </div>
                    <div style="width:70px">
                        <label style="font-size:0.82rem">Limit</label>
                        <input type="number" id="cse-limit" class="form-input" value="100" min="1" max="1000" style="font-size:0.82rem">
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- ── Right: Output results ──────────────────────────────── -->
    <div class="cse-ref-panel">
        <div class="cse-panel-header" style="justify-content:space-between">
            <h3 id="cse-ref-title"><?= $setType === 'query' ? 'Results' : 'Rows' ?></h3>
            <?php if (!$isNew && $setType === 'query'): ?>
            <button type="button" class="btn btn-small btn-outline" id="cse-preview-btn">&#9654; Run</button>
            <?php endif; ?>
        </div>

        <!-- Manual: rows table -->
        <div class="cse-panel-scroll cse-ref-body" id="cse-ref-manual">
        <?php if ($isNew): ?>
            <p class="form-help" style="padding:0.75rem">Save the set first, then add rows.</p>
        <?php elseif (empty($rows)): ?>
            <p class="form-help" style="padding:0.75rem">No rows yet. <a href="<?= e('/admin/content/' . (int)$set['id'] . '/rows') ?>">Add rows →</a></p>
        <?php else: ?>
            <div style="padding:0.5rem 0.75rem;font-size:0.75rem;color:var(--color-text-muted)"><?= count($rows) ?> row<?= count($rows) !== 1 ? 's' : '' ?> · <a href="<?= e('/admin/content/' . (int)$set['id'] . '/rows') ?>">Manage →</a></div>
            <div style="overflow-x:auto">
            <table style="font-size:0.75rem;border-collapse:collapse;width:100%">
                <thead>
                <tr>
                    <?php foreach ($fields as $f): ?>
                    <th style="text-align:left;padding:3px 8px;border-bottom:1px solid #e5e7eb;white-space:nowrap;color:var(--color-text-muted)"><?= e($f['label'] ?? $f['name']) ?></th>
                    <?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <?php foreach ($fields as $f): ?>
                    <td style="padding:3px 8px;border-bottom:1px solid #f1f5f9;vertical-align:top;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= e((string)($row['data'][$f['name']] ?? '')) ?>">
                        <?= e((string)($row['data'][$f['name']] ?? '')) ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
        </div>

        <!-- Query: preview area -->
        <div class="cse-panel-scroll cse-ref-body" id="cse-ref-query" style="<?= $setType !== 'query' ? 'display:none' : '' ?>">
            <?php if ($isNew): ?>
            <p class="form-help" style="padding:0.75rem">Save the set first to preview results.</p>
            <?php else: ?>
            <div id="cse-preview-area" style="font-size:0.78rem"></div>
            <?php endif; ?>
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
        <button type="button" class="cse-field-remove" title="Remove field">✕</button>
    </div>
</template>
