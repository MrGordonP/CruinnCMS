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
                <a href="#sb-create-template" class="btn btn-small btn-primary">+ New Template</a>
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
                    <tr>
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
                    <tr>
                        <td>
                            <strong><?= e($tpl['name']) ?></strong>
                            <?php if ($tpl['description']): ?>
                                <br><small class="text-muted"><?= e($tpl['description']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><code><?= e($tpl['slug']) ?></code></td>
                        <td>
                            <?php
                            $zones = json_decode($tpl['zones'] ?? '["main"]', true);
                            if (is_array($zones)):
                                foreach ($zones as $z): ?>
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
                            <?php if ($tpl['canvas_page_id']): ?>
                                <a href="<?= url('/admin/editor/' . (int)$tpl['canvas_page_id'] . '/edit') ?>" class="btn btn-small">Edit</a>
                            <?php endif; ?>
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
            <h3><?= $isLayoutsPanel ? 'New Template Layout' : 'New Page Template' ?></h3>
        </div>
        <div class="pl-detail-scroll">
            <?php if ($isLayoutsPanel): ?>
            <p class="sb-subtitle">Create a standalone template layout page (template shell) and open it directly in the editor.</p>
            <form method="post" action="<?= url('/admin/templates/layouts/new') ?>" id="new-template-layout">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="tpl_layout_title">Template Layout Name</label>
                    <input type="text" id="tpl_layout_title" name="title" required class="form-input" placeholder="e.g. Standard Template Layout">
                </div>
                <div class="form-group">
                    <label for="tpl_layout_slug">Template Layout Slug <small>(optional)</small></label>
                    <input type="text" id="tpl_layout_slug" name="slug" class="form-input" pattern="[a-z0-9_-]+" placeholder="e.g. standard-layout">
                    <small class="form-help">If blank, one is generated automatically.</small>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Create Template Layout</button>
                </div>
            </form>
            <?php else: ?>
            <p class="sb-subtitle">Templates define which zones are available and how page content is arranged.</p>
            <form method="post" action="<?= url('/admin/templates') ?>" class="sb-create-form" id="sb-create-template">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label>Template Type</label>
                    <div class="radio-group" id="tpl_type_group">
                        <label class="radio-label">
                            <input type="radio" name="template_type" value="page" checked> Page template
                            <small class="text-muted"> — defines zones for regular CMS pages</small>
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
                        <label for="tpl_zones">Zones <small>(JSON array)</small></label>
                        <input type="text" id="tpl_zones" name="zones" class="form-input" value='["main"]' placeholder='["main", "sidebar"]'>
                        <small class="form-help">e.g. ["main"], ["main", "sidebar"], ["header", "main", "footer"]</small>
                    </div>
                    <div class="form-group">
                        <label for="tpl_css_class">CSS Class</label>
                        <input type="text" id="tpl_css_class" name="css_class" class="form-input" placeholder="Optional class for template wrapper">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Create Template</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/_tabs_close.php'; ?>
