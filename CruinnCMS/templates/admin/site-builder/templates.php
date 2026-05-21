<?php
\Cruinn\Template::requireCss('admin-panel-layout.css');
\Cruinn\Template::requireCss('admin-site-builder.css');
\Cruinn\Template::requireJs('templates.js');
$GLOBALS['admin_flush_layout'] = true;

$activePanel = $activePanel ?? 'page-templates';
$isLayoutsPanel = $activePanel === 'template-layouts';

include __DIR__ . '/_tabs.php';
?>

<div class="panel-layout" id="templates-layout">
    <div class="pl-sidebar">
        <div class="pl-sidebar-header">
            <h3>Templates</h3>
        </div>
        <div class="pl-sidebar-scroll" style="padding: 0">
            <div class="pl-nav-section">Sections</div>
            <a class="pl-nav-item<?= !$isLayoutsPanel ? ' active' : '' ?>" href="<?= url('/admin/templates?panel=page-templates') ?>">
                Page Templates
                <span class="pl-nav-count"><?= count($templates ?? []) ?></span>
            </a>
            <a class="pl-nav-item<?= $isLayoutsPanel ? ' active' : '' ?>" href="<?= url('/admin/templates?panel=template-layouts') ?>">
                Template Layouts
                <span class="pl-nav-count"><?= count($templateLayouts ?? []) ?></span>
            </a>
        </div>
    </div>

    <div class="pl-main">
        <div class="pl-main-toolbar">
            <span class="pl-main-title"><?= $isLayoutsPanel ? 'Template Layouts' : 'Page Templates' ?></span>
            <div class="pl-main-toolbar-actions">
                <?php if ($isLayoutsPanel): ?>
                <a href="#new-template-layout" class="btn btn-small btn-primary">+ New Template Layout</a>
                <?php else: ?>
                <button type="button" class="btn btn-small btn-primary" id="tpl-open-create-btn">+ New Template</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="pl-main-scroll" style="padding:0">
            <?php if ($isLayoutsPanel): ?>
            <table class="pl-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Slug</th>
                        <th>Status</th>
                        <th>Used By</th>
                        <th>Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($templateLayouts)): ?>
                    <tr>
                        <td colspan="6" class="pl-empty">No template layouts yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($templateLayouts as $layout): ?>
                    <?php
                    $layoutData = [
                        'id' => (int)($layout['id'] ?? 0),
                        'title' => (string)($layout['title'] ?? ''),
                        'slug' => (string)($layout['slug'] ?? ''),
                        'status' => (string)($layout['status'] ?? 'published'),
                        'usage_count' => (int)($layout['usage_count'] ?? 0),
                        'updated_at' => (string)($layout['updated_at'] ?? ''),
                    ];
                    ?>
                    <tr class="js-template-layout-row" data-layout='<?= e(json_encode($layoutData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>'>
                        <td><strong><?= e($layout['title']) ?></strong></td>
                        <td><code><?= e($layout['slug']) ?></code></td>
                        <td><?= e($layout['status'] ?? 'published') ?></td>
                        <td><?= (int)($layout['usage_count'] ?? 0) ?></td>
                        <td><?= !empty($layout['updated_at']) ? e(date('Y-m-d H:i', strtotime((string)$layout['updated_at']))) : '—' ?></td>
                        <td class="sb-actions">
                            <a href="<?= url('/admin/editor/' . (int)$layout['id'] . '/edit') ?>" class="btn btn-small">Edit</a>
                            <?php if ((int)($layout['usage_count'] ?? 0) === 0): ?>
                            <form method="post" action="<?= url('/admin/templates/layouts/' . (int)$layout['id'] . '/delete') ?>" style="display:inline">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-small btn-danger" data-confirm="Delete template layout '<?= e($layout['title']) ?>'?">Delete</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
            <?php else: ?>
            <table class="pl-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Zones</th>
                        <th>Pages Using</th>
                        <th>Type</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($templates as $tpl): ?>
                    <?php
                    $tplZones = is_array($tpl['zones'] ?? null) ? $tpl['zones'] : ['main'];
                    $layoutTitle = '';
                    if (!empty($tpl['layout_page_id'])) {
                        foreach (($templateLayouts ?? []) as $layoutRow) {
                            if ((int)($layoutRow['id'] ?? 0) === (int)$tpl['layout_page_id']) {
                                $layoutTitle = (string)($layoutRow['title'] ?? '');
                                break;
                            }
                        }
                    }
                    $tplData = [
                        'id' => (int)$tpl['id'],
                        'name' => (string)$tpl['name'],
                        'slug' => (string)$tpl['slug'],
                        'description' => (string)($tpl['description'] ?? ''),
                        'page_count' => (int)$tpl['page_count'],
                        'template_type' => (string)($tpl['template_type'] ?? 'page'),
                        'layout_page_id' => (int)($tpl['layout_page_id'] ?? 0),
                        'layout_title' => $layoutTitle,
                        'zones' => array_values($tplZones),
                        'canvas_page_id' => (int)($tpl['canvas_page_id'] ?? 0),
                        'is_system' => (int)($tpl['is_system'] ?? 0),
                    ];
                    ?>
                    <tr class="js-page-template-row" data-template='<?= e(json_encode($tplData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>'>
                        <td>
                            <strong><?= e($tpl['name']) ?></strong>
                            <?php if ($tpl['description']): ?>
                                <br><small class="text-muted"><?= e($tpl['description']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><code><?= e($tpl['slug']) ?></code></td>
                        <td>
                            <?php if (is_array($tplZones)):
                                foreach ($tplZones as $z): ?>
                                    <span class="badge badge-info"><?= e($z) ?></span>
                                <?php endforeach;
                            endif; ?>
                        </td>
                        <td><?= (int)$tpl['page_count'] ?></td>
                        <td>
                            <?php if ($tpl['is_system']): ?>
                                <span class="badge badge-published">System</span>
                            <?php elseif (($tpl['template_type'] ?? 'page') === 'content'): ?>
                                <span class="badge badge-info">Content</span>
                            <?php else: ?>
                                <span class="badge badge-draft">Page</span>
                            <?php endif; ?>
                        </td>
                        <td class="sb-actions">
                            <form method="post" action="<?= url('/admin/templates/' . (int)$tpl['id'] . '/canvas') ?>" style="display:inline">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-small">Edit</button>
                            </form>
                            <?php if (!$tpl['is_system']): ?>
                                <form method="post" action="<?= url('/admin/templates/' . (int)$tpl['id'] . '/delete') ?>" style="display:inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-small btn-danger" data-confirm="Delete template '<?= e($tpl['name']) ?>'?">Delete</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="pl-detail">
        <div class="pl-detail-header">
            <h3><?= $isLayoutsPanel ? 'Template Layout Details' : 'New Page Template' ?></h3>
        </div>
        <div class="pl-detail-scroll">
            <?php if ($isLayoutsPanel): ?>
            <div class="sb-info-box" id="tpl-layout-selected-panel">
                <h3>Selected Template Layout</h3>
                <p id="tpl-layout-selected-empty">Select a template layout from the list.</p>
                <div id="tpl-layout-selected-content" style="display:none">
                    <p><strong id="tpl-layout-selected-title"></strong></p>
                    <p><small id="tpl-layout-selected-slug"></small></p>
                    <p><strong>Status:</strong> <span id="tpl-layout-selected-status"></span></p>
                    <p><strong>Used By:</strong> <span id="tpl-layout-selected-usage"></span> page template(s)</p>
                    <p><strong>Updated:</strong> <span id="tpl-layout-selected-updated"></span></p>
                    <div class="form-actions" style="margin-top:.6rem">
                        <a href="#" class="btn btn-small" id="tpl-layout-selected-edit-link">Edit Template Layout</a>
                        <form method="post" action="#" id="tpl-layout-selected-delete-form" style="display:none">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-small btn-danger" id="tpl-layout-selected-delete-btn">Delete Template Layout</button>
                        </form>
                    </div>
                </div>
            </div>
            <p class="sb-subtitle" style="margin-top:1rem">Use the + New Template Layout button in the top toolbar to create a new layout.</p>
            <?php else: ?>
            <p class="sb-subtitle">Templates define which zones are available and how page content is arranged.</p>
            <div id="tpl-page-template-detail-mode">
                <div class="sb-info-box" id="tpl-selected-panel">
                    <h3>Selected Page Template</h3>
                    <p id="tpl-selected-empty">Select a page template from the list.</p>
                    <div id="tpl-selected-content" style="display:none">
                        <p><strong id="tpl-selected-name"></strong></p>
                        <p><small id="tpl-selected-slug"></small></p>
                        <p id="tpl-selected-desc" style="margin-bottom:.6rem"></p>
                        <p><strong>Type:</strong> <span id="tpl-selected-type"></span></p>
                        <p><strong>Template Layout:</strong> <span id="tpl-selected-layout"></span></p>
                        <p><strong>Zones:</strong> <span id="tpl-selected-zones"></span></p>
                        <p><strong>Pages Using:</strong> <span id="tpl-selected-pages"></span></p>
                        <div class="form-actions" style="margin-top:.6rem">
                            <a href="#" class="btn btn-small" id="tpl-selected-edit-link" style="display:none">Edit Template Canvas</a>
                        </div>
                    </div>
                </div>
            </div>

            <div id="tpl-page-template-create-mode" style="display:none">
                <div class="sb-info-box">
                    <h3>Create New Page Template</h3>
                    <p class="sb-subtitle">Create a page template and assign a Template Layout.</p>
                </div>
                <form method="post" action="<?= url('/admin/templates') ?>" class="sb-create-form" id="sb-create-template">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label>Template Type</label>
                        <div class="radio-group" id="tpl_type_group">
                            <label class="radio-label">
                                <input type="radio" name="template_type" value="page" checked> Page template
                                <small class="text-muted"> — applies a Template Layout and rendering rules to regular CMS pages</small>
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="template_type" value="content"> Content template
                                <small class="text-muted"> — used to render dynamic content (e.g. blog post, article list)</small>
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="tpl_name">Name</label>
                        <input type="text" id="tpl_name" name="name" required class="form-input" placeholder="e.g. Two Sidebar">
                    </div>
                    <div class="form-group">
                        <label for="tpl_slug">Slug</label>
                        <input type="text" id="tpl_slug" name="slug" required class="form-input" placeholder="e.g. two-sidebar" pattern="[a-z0-9\-]+">
                    </div>
                    <div class="form-group">
                        <label for="tpl_description">Description</label>
                        <input type="text" id="tpl_description" name="description" class="form-input" placeholder="Brief description of this template layout">
                    </div>
                    <div id="tpl_page_fields">
                        <div class="form-group">
                            <label for="tpl_layout_page_id">Template Layout</label>
                            <select id="tpl_layout_page_id" name="layout_page_id" class="form-input" required>
                                <option value="">— Select template layout —</option>
                                <?php foreach (($templateLayouts ?? []) as $_layout): ?>
                                <option value="<?= (int)$_layout['id'] ?>"><?= e($_layout['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-help">Zones are sourced only from the selected Template Layout's zone blocks.</small>
                        </div>
                        <div class="form-group">
                            <label for="tpl_css_class">CSS Class</label>
                            <input type="text" id="tpl_css_class" name="css_class" class="form-input" placeholder="Optional class for template wrapper">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Create Template</button>
                        <button type="button" class="btn btn-outline" id="tpl-cancel-create-btn">Cancel</button>
                    </div>
                </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/_tabs_close.php'; ?>
