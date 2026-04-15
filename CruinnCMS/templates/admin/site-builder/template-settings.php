<?php
/**
 * Template Settings Editor
 *
 * Clean settings form for a page template. Block content is edited
 * per-page in the Cruinn editor — templates define layout settings only.
 */
\Cruinn\Template::requireCss('admin-acp.css');
\Cruinn\Template::requireCss('admin-site-builder.css');

$s          = $tpl['settings'] ?? [];
$zones      = json_decode($tpl['zones'] ?? '["main"]', true) ?: ['main'];
$hasSidebar = in_array('sidebar', $zones);
$isGlobalHeader = ($tpl['slug'] === '_global_header');
?>
<?php include __DIR__ . '/_tabs.php'; ?>

<div class="sb-toolbar">
    <div>
        <h2 style="margin:0"><?= e($tpl['name']) ?>&nbsp;
            <?php if ($isGlobalHeader): ?>
                <span class="badge badge-published">Global Default Header</span>
            <?php elseif ($tpl['is_system']): ?>
                <span class="badge badge-published">System</span>
            <?php else: ?>
                <span class="badge badge-draft">Custom</span>
            <?php endif; ?>
        </h2>
    </div>
    <div style="display:flex;gap:0.5rem;align-items:center">
        <?php if (!$isGlobalHeader): ?>
        <a href="<?= url('/admin/templates/' . (int)$tpl['id'] . '/preview') ?>"
           target="tpl-preview" class="btn btn-outline btn-small">Preview</a>
        <?php endif; ?>
        <?php if (!empty($tpl['canvas_page_id'])): ?>
            <a href="<?= url('/admin/editor/' . (int)$tpl['canvas_page_id'] . '/edit') ?>"
               class="btn btn-primary btn-small">Edit Layout</a>
        <?php else: ?>
            <form method="post" action="<?= url('/admin/templates/' . (int)$tpl['id'] . '/canvas') ?>" style="display:inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary btn-small" title="Create a Cruinn canvas for this template">Edit Layout</button>
            </form>
        <?php endif; ?>
        <a href="<?= url('/admin/templates') ?>" class="btn btn-outline btn-small">&larr; Templates</a>
    </div>
</div>

<form method="post" action="<?= url('/admin/templates/' . (int)$tpl['id']) ?>">
    <?= csrf_field() ?>

    <div class="form-row" style="align-items:flex-start;gap:2rem">

        <!-- Main settings -->
        <div style="flex:1;min-width:0">

            <h3>Identity</h3>

            <div class="form-group">
                <label for="tpl_name">Name</label>
                <input type="text" id="tpl_name" name="name"
                       value="<?= e($tpl['name']) ?>"
                       class="form-input" required>
            </div>

            <div class="form-group">
                <label for="tpl_slug">Slug</label>
                <?php if ($tpl['is_system']): ?>
                    <input type="text" id="tpl_slug" value="<?= e($tpl['slug']) ?>"
                           class="form-input" disabled>
                    <small class="form-help">System template slugs cannot be changed.</small>
                <?php else: ?>
                    <input type="text" id="tpl_slug" name="slug"
                           value="<?= e($tpl['slug']) ?>"
                           class="form-input" required pattern="[a-z0-9\-]+">
                    <small class="form-help">Lowercase letters, numbers and hyphens only.</small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="tpl_desc">Description</label>
                <input type="text" id="tpl_desc" name="description"
                       value="<?= e($tpl['description'] ?? '') ?>"
                       class="form-input" placeholder="Brief description">
            </div>

            <h3 style="margin-top:1.5rem">Zones</h3>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="hidden" name="show_header" value="0">
                    <input type="checkbox" name="show_header" value="1"
                           <?= ($s['show_header'] ?? true) ? 'checked' : '' ?>>
                    Header
                </label>
            </div>

            <div class="form-group">
                <label for="tpl_header_source">Header Source</label>
                <select id="tpl_header_source" name="header_source" class="form-input">
                    <option value="default" <?= ($s['header_source'] ?? 'default') === 'default' ? 'selected' : '' ?>>
                        Auto — global default header
                    </option>
                    <?php foreach ($headerTemplates ?? [] as $_ht): ?>
                    <option value="<?= e($_ht['slug']) ?>"
                        <?= ($s['header_source'] ?? '') === $_ht['slug'] ? 'selected' : '' ?>>
                        <?= e($_ht['name']) ?>
                    </option>
                    <?php endforeach; ?>
                    <option value="custom" <?= ($s['header_source'] ?? '') === 'custom' ? 'selected' : '' ?>>
                        Custom — header blocks on this template
                    </option>
                </select>
                <small class="form-help">
                    <?php
                    $__hs = $s['header_source'] ?? 'default';
                    if ($__hs === 'default') { ?>
                        <a href="<?= url('/admin/site-builder/global-header') ?>">Edit the global default header in Cruinn &rarr;</a>
                    <?php } elseif ($__hs !== 'custom') {
                        foreach ($headerTemplates ?? [] as $_ht) {
                            if ($_ht['slug'] === $__hs) { ?>
                        <a href="<?= url('/admin/templates/' . (int)$_ht['id'] . '/edit') ?>">Edit &ldquo;<?= e($_ht['name']) ?>&rdquo; in Cruinn &rarr;</a>
                            <?php break;
                            }
                        }
                    } ?>
                </small>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="hidden" name="show_breadcrumbs" value="0">
                    <input type="checkbox" name="show_breadcrumbs" value="1"
                           <?= ($s['show_breadcrumbs'] ?? false) ? 'checked' : '' ?>>
                    Breadcrumbs
                </label>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="hidden" name="show_title" value="0">
                    <input type="checkbox" name="show_title" value="1"
                           <?= ($s['show_title'] ?? true) ? 'checked' : '' ?>>
                    Page Title
                </label>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="hidden" name="show_footer" value="0">
                    <input type="checkbox" name="show_footer" value="1"
                           <?= ($s['show_footer'] ?? true) ? 'checked' : '' ?>>
                    Footer
                </label>
            </div>

            <h3 style="margin-top:1.5rem">Content Layout</h3>

            <div class="form-row">
                <div class="form-group">
                    <label for="tpl_content_width">Content Width</label>
                    <select id="tpl_content_width" name="content_width" class="form-input">
                        <option value="narrow"  <?= ($s['content_width'] ?? '') === 'narrow'  ? 'selected' : '' ?>>Narrow (640 px)</option>
                        <option value="default" <?= ($s['content_width'] ?? 'default') === 'default' ? 'selected' : '' ?>>Default (960 px)</option>
                        <option value="wide"    <?= ($s['content_width'] ?? '') === 'wide'    ? 'selected' : '' ?>>Wide (1200 px)</option>
                        <option value="full"    <?= ($s['content_width'] ?? '') === 'full'    ? 'selected' : '' ?>>Full Width</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="tpl_title_align">Title Alignment</label>
                    <select id="tpl_title_align" name="title_align" class="form-input">
                        <?php $align = $s['title_align'] ?? 'left'; ?>
                        <option value="left"   <?= $align === 'left'   ? 'selected' : '' ?>>Left</option>
                        <option value="center" <?= $align === 'center' ? 'selected' : '' ?>>Centre</option>
                        <option value="right"  <?= $align === 'right'  ? 'selected' : '' ?>>Right</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="tpl_sidebar_toggle"
                               <?= $hasSidebar ? 'checked' : '' ?>
                               onchange="(function(cb){
                                   document.getElementById('tpl_sidebar_pos').disabled = !cb.checked;
                                   var inp = document.getElementById('tplZones');
                                   var z = JSON.parse(inp.value || '[&quot;main&quot;]');
                                   if(cb.checked){ if(z.indexOf('sidebar')<0) z.push('sidebar'); }
                                   else { z = z.filter(function(v){return v!=='sidebar';}); }
                                   inp.value = JSON.stringify(z);
                               })(this)">
                        Sidebar
                    </label>
                </div>
                <div class="form-group">
                    <label for="tpl_sidebar_pos">Sidebar Position</label>
                    <select id="tpl_sidebar_pos" name="sidebar_position" class="form-input"
                            <?= $hasSidebar ? '' : 'disabled' ?>>
                        <option value="left"  <?= ($s['sidebar_position'] ?? 'right') === 'left'  ? 'selected' : '' ?>>Left</option>
                        <option value="right" <?= ($s['sidebar_position'] ?? 'right') === 'right' ? 'selected' : '' ?>>Right</option>
                    </select>
                </div>
            </div>

            <input type="hidden" name="zones" id="tplZones" value="<?= e($tpl['zones'] ?? '["main"]') ?>">
            <input type="hidden" name="css_class" value="<?= e($tpl['css_class'] ?? '') ?>">
            <input type="hidden" name="sort_order" value="<?= (int)($tpl['sort_order'] ?? 0) ?>">

            <div class="form-actions" style="margin-top:2rem">
                <button type="submit" class="btn btn-primary">Save Template</button>
                <a href="<?= url('/admin/templates') ?>" class="btn btn-outline">Cancel</a>
            </div>

        </div>

        <!-- Side panel -->
        <div style="width:260px;flex-shrink:0">

            <?php if (!empty($pages)): ?>
            <div class="sb-info-box">
                <h3>Pages using this template (<?= (int)$tpl['page_count'] ?>)</h3>
                <ul style="margin:0;padding:0;list-style:none">
                    <?php foreach ($pages as $pg): ?>
                    <li style="padding:0.3rem 0;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center">
                        <a href="<?= url('/admin/editor/' . (int)$pg['id'] . '/edit') ?>"><?= e($pg['title']) ?></a>
                        <span class="badge badge-<?= $pg['status'] === 'published' ? 'published' : 'draft' ?>"><?= e($pg['status']) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if (!$tpl['is_system'] && (int)$tpl['page_count'] === 0): ?>
            <div class="sb-info-box" style="border-color:#fca5a5;background:#fff5f5;margin-top:1rem">
                <h3 style="color:#dc2626">Danger Zone</h3>
                <p style="font-size:0.85rem;color:#6b7280;margin-bottom:0.75rem">Permanently delete this template.</p>
                <button type="button" class="btn btn-danger btn-small"
                        onclick="if(confirm('Permanently delete template \'<?= e(addslashes($tpl['name'])) ?>\'?')){var f=document.createElement('form');f.method='post';f.action='<?= url('/admin/templates/' . (int)$tpl['id'] . '/delete') ?>';var t=document.createElement('input');t.type='hidden';t.name='_csrf_token';t.value='<?= e(\Cruinn\CSRF::token()) ?>';f.appendChild(t);document.body.appendChild(f);f.submit();}">
                    Delete Template
                </button>
            </div>
            <?php endif; ?>

        </div>

    </div>

</form>

<?php include __DIR__ . '/_tabs_close.php'; ?>
