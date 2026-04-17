<?php
/**
 * Cruinn CMS — Full-screen Page Editor
 *
 * Three-panel layout: toolbar | left panel (palette + block tree) | canvas | right panel (properties)
 * Requires admin/layout wrapper (set by CruinnController::edit via renderAdmin).
 */
\Cruinn\Template::requireCss('editor.css');

// Context-aware page URL helper.
// $editorPageBase: null = admin context, string = platform context url prefix e.g. '/cms/editor?instance=testsite'
$_editorPageHref = function (int $id) use ($editorPageBase): string {
    if ($editorPageBase !== null) {
        return $editorPageBase . '&page=' . $id;
    }
    return '/admin/editor/' . $id . '/edit';
};
$_editorBackHref  = $editorPageBase ?? '/admin/site-builder/pages';
$_editorPagesHref = $editorPageBase ?? '/admin/pages';
?>
<?php if (!empty($headerZoneCss) || !empty($footerZoneCss) || !empty($templateCanvasCss)): ?>
<style id="editor-zone-context-styles">
<?= $headerZoneCss ?? '' ?>
<?= $footerZoneCss ?? '' ?>
<?= $templateCanvasCss ?? '' ?>
</style>
<?php endif; ?>
<div id="editor-wrap"
     data-page-id="<?= $page ? (int) $page['id'] : '' ?>"
     data-csrf="<?= htmlspecialchars(\Cruinn\CSRF::getToken(), ENT_QUOTES, 'UTF-8') ?>"
     data-api-base="<?= htmlspecialchars($apiBase ?? '/admin/editor', ENT_QUOTES, 'UTF-8') ?>"
     data-has-draft="<?= $hasDraft ? '1' : '0' ?>"
     data-template-zones="<?= htmlspecialchars(json_encode($templateZones ?? []), ENT_QUOTES, 'UTF-8') ?>"
     data-start-in-code-view="<?= !empty($startInCodeView) ? '1' : '0' ?>"
     data-html-content="<?= htmlspecialchars($htmlContent ?? '', ENT_QUOTES, 'UTF-8') ?>">


    <!-- ── Toolbar ──────────────────────────────────────────────── -->
    <div id="editor-toolbar">
        <?php if (!empty($isTemplatePage)): ?>
            <a href="<?= $templateId ? '/admin/templates/' . (int)$templateId . '/edit' : '/admin/templates' ?>" class="editor-back-btn">&larr; Templates</a>
        <?php else: ?>
            <a href="<?= e($_editorBackHref) ?>" class="editor-back-btn">&larr; All Pages</a>
        <?php endif; ?>
        <?php if (!empty($isTemplatePage)): ?>
            <span class="editor-zone-badge">Template Layout: <?= e($templateSlugName ?? '') ?></span>
        <?php elseif (!empty($isZonePage)): ?>
            <span class="editor-zone-badge"><?= e(ucfirst($zoneName ?? '')) ?> Zone</span>
        <?php endif; ?>
        <?php if ($page): ?>
        <?php
            // Show full file path when editing a source file, otherwise just the title
            $renderFile = $page['render_file'] ?? '';
            $displayPath = '';
            if ($renderFile) {
                $displayPath = ltrim(str_replace('@cms/', '', $renderFile), '/');
            }
        ?>
        <span class="editor-page-title" title="<?= e($displayPath ?: $page['title']) ?>">
            <?= $displayPath ? e($displayPath) : htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8') ?>
        </span>
        <?php if ($hasDraft): ?>
            <span class="editor-draft-badge" title="Unsaved draft in progress">⚠ Draft</span>
        <?php endif; ?>
        <?php else: ?>
        <span class="editor-page-title" style="opacity:0.45">No page selected</span>
        <?php endif; ?>
        <?php if (empty($isZonePage)): ?>
            <?php if (!empty($headerPages) && count($headerPages) > 1): ?>
            <div style="position:relative;display:inline-block" class="zone-picker">
                <button type="button" class="btn btn-small editor-zone-link" onclick="this.nextElementSibling.classList.toggle('open')">Edit Header ▾</button>
                <div class="zone-picker-menu">
                    <?php foreach ($headerPages as $_hp): ?>
                    <a href="<?= e($_editorPageHref((int)$_hp['id'])) ?>"><?= e($_hp['template_name'] ?? $_hp['title']) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php elseif (!empty($headerPageId)): ?>
            <a href="<?= e($_editorPageHref((int)$headerPageId)) ?>" class="btn btn-small editor-zone-link">Edit Header</a>
            <?php endif; ?>
            <?php if (!empty($footerPages) && count($footerPages) > 1): ?>
            <div style="position:relative;display:inline-block" class="zone-picker">
                <button type="button" class="btn btn-small editor-zone-link" onclick="this.nextElementSibling.classList.toggle('open')">Edit Footer ▾</button>
                <div class="zone-picker-menu">
                    <?php foreach ($footerPages as $_fp): ?>
                    <a href="<?= e($_editorPageHref((int)$_fp['id'])) ?>"><?= e($_fp['template_name'] ?? $_fp['title']) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php elseif (!empty($footerPageId)): ?>
            <a href="<?= e($_editorPageHref((int)$footerPageId)) ?>" class="btn btn-small editor-zone-link">Edit Footer</a>
            <?php endif; ?>
        <?php endif; ?>
        <div id="cruinn-canvas-size-control">
            <label for="cruinn-canvas-height-input">H:</label>
            <input type="number" id="cruinn-canvas-height-input" placeholder="auto" min="40" step="1" title="Canvas height in px — leave blank for auto">
            <span>px</span>
            <button id="cruinn-canvas-height-clear" title="Reset to auto">&times;</button>
        </div>
        <div class="editor-toolbar-actions">
            <?php if ($page): ?>
            <button id="editor-code-toggle-btn" class="btn btn-small btn-outline" title="Switch between block editor and code view">
                &lt;/&gt; Code
            </button>
            <button id="editor-undo-btn" class="btn btn-small btn-outline"
                    title="Undo (Ctrl+Z)"
                    <?= (!$hasDraft || (isset($state) && (int)($state['current_edit_seq'] ?? 0) <= 0)) ? 'disabled' : '' ?>>
                Undo
            </button>
            <button id="editor-redo-btn" class="btn btn-small btn-outline"
                    title="Redo (Ctrl+Shift+Z)"
                    <?= (!$hasDraft || (isset($state) && (int)($state['current_edit_seq'] ?? 0) >= (int)($state['max_edit_seq'] ?? 0))) ? 'disabled' : '' ?>>
                Redo
            </button>
            <button id="editor-discard-btn" class="btn btn-small btn-outline"
                    <?= !$hasDraft ? 'disabled' : '' ?>>
                Discard Draft
            </button>
            <button id="editor-publish-btn" class="btn btn-small btn-primary">
                Publish
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Document panel (file-mode pages only) ─────────────────── -->
    <?php if (!empty($isFileMode) && !empty($page)): ?>
    <?php
        $docHtmlAttrs = isset($docHtmlBlock) ? (json_decode($docHtmlBlock['block_config'] ?? '{}', true) ?: []) : [];
        $docHeadHtml  = $docHeadBlock['inner_html'] ?? '';
        $docBodyAttrs = isset($docBodyBlock) ? (json_decode($docBodyBlock['block_config'] ?? '{}', true) ?: []) : [];
    ?>
    <div id="editor-doc-panel">
        <div class="editor-doc-section">
            <button type="button" class="editor-doc-toggle" aria-expanded="false"
                    data-target="doc-html-body">&lt;html&gt; Attributes</button>
            <div id="doc-html-body" class="editor-doc-body" hidden>
                <div class="editor-doc-row">
                    <label>lang</label>
                    <input type="text" data-doc-html-attr="lang"
                           value="<?= e($docHtmlAttrs['lang'] ?? '') ?>">
                </div>
                <div class="editor-doc-row">
                    <label>dir</label>
                    <input type="text" data-doc-html-attr="dir"
                           value="<?= e($docHtmlAttrs['dir'] ?? '') ?>">
                </div>
            </div>
        </div>
        <div class="editor-doc-section">
            <button type="button" class="editor-doc-toggle" aria-expanded="false"
                    data-target="doc-head-body">&lt;head&gt; Content</button>
            <div id="doc-head-body" class="editor-doc-body" hidden>
                <textarea data-doc-head-html rows="8"><?= e($docHeadHtml) ?></textarea>
            </div>
        </div>
        <div class="editor-doc-section">
            <button type="button" class="editor-doc-toggle" aria-expanded="false"
                    data-target="doc-body-body">&lt;body&gt; Attributes</button>
            <div id="doc-body-body" class="editor-doc-body" hidden>
                <div class="editor-doc-row">
                    <label>class</label>
                    <input type="text" data-doc-body-attr="class"
                           value="<?= e($docBodyAttrs['class'] ?? '') ?>">
                </div>
                <div class="editor-doc-row">
                    <label>id</label>
                    <input type="text" data-doc-body-attr="id"
                           value="<?= e($docBodyAttrs['id'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Editor body ──────────────────────────────────────────── -->
    <div id="editor-body">

        <!-- Left panel: site nav + block palette + block tree -->
        <div id="editor-left">

            <!-- Site Builder navigation -->
            <div class="editor-panel-section editor-site-nav">
                <h3 class="editor-panel-heading editor-site-nav-toggle" onclick="this.parentElement.classList.toggle('collapsed')">
                    Site Builder <span class="editor-site-nav-chevron">▾</span>
                </h3>
                <div class="editor-site-nav-body">
                    <?php if (!empty($isPlatformMode) && !empty($platformPages)): ?>
                    <div class="editor-site-nav-group">
                        <span class="editor-site-nav-label" onclick="this.parentElement.classList.toggle('collapsed')">Platform Pages <span class="editor-group-chevron">▾</span></span>
                        <div class="editor-site-nav-list">
                            <?php foreach ($platformPages as $_pt): ?>
                            <?php $_ptFileRel = 'templates/platform/' . $_pt['slug'] . '.php'; ?>
                            <a href="<?= e('/cms/editor?instance=__platform__&file=' . rawurlencode($_ptFileRel)) ?>"
                               class="editor-site-nav-link<?= $page && ($page['render_file'] ?? '') === '@cms/' . $_ptFileRel ? ' active' : '' ?>">
                                <?= e($_pt['name']) ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="editor-site-nav-group collapsed">
                        <span class="editor-site-nav-label" onclick="this.parentElement.classList.toggle('collapsed')">Pages <span class="editor-group-chevron">▾</span></span>
                        <div class="editor-site-nav-list">
                            <?php foreach ($sitePages as $_sp): ?>
                            <a href="<?= e($_editorPageHref((int)$_sp['id'])) ?>"
                               class="editor-site-nav-link<?= $page && (int)$_sp['id'] === (int)$page['id'] ? ' active' : '' ?>"
                               title="/<?= e($_sp['slug']) ?>">
                                <?= e($_sp['title']) ?>
                            </a>
                            <?php endforeach; ?>
                            <?php if (!empty($isPlatformMode)): ?>
                            <?php else: ?>
                            <a href="<?= e($_editorPagesHref) ?>" class="editor-site-nav-link editor-site-nav-manage">Manage pages →</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($isPlatformMode) && !empty($navSourceGroups)): ?>
                    <?php
                        // Determine the active render_file for highlighting
                        $_activeRenderFile = $page['render_file'] ?? '';
                    ?>
                    <?php foreach ($navSourceGroups as $_sgName => $_sgFiles): ?>
                    <div class="editor-site-nav-group collapsed">
                        <span class="editor-site-nav-label" onclick="this.parentElement.classList.toggle('collapsed')"><?= e($_sgName) ?> <span class="editor-group-chevron">▾</span></span>
                        <div class="editor-site-nav-list">
                            <?php foreach ($_sgFiles as $_sgRel => $_sgLabel): ?>
                            <a href="<?= e('/cms/editor?instance=__platform__&file=' . rawurlencode($_sgRel)) ?>"
                               class="editor-site-nav-link<?= $_activeRenderFile === '@cms/' . $_sgRel ? ' active' : '' ?>">
                                <?= e($_sgLabel) ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if (!empty($headerPages)): ?>
                    <div class="editor-site-nav-group collapsed">
                        <span class="editor-site-nav-label" onclick="this.parentElement.classList.toggle('collapsed')">Headers <span class="editor-group-chevron">▾</span></span>
                        <div class="editor-site-nav-list">
                            <?php foreach ($headerPages as $_hp): ?>
                            <a href="<?= e($_editorPageHref((int)$_hp['id'])) ?>"
                               class="editor-site-nav-link<?= $page && (int)$_hp['id'] === (int)$page['id'] ? ' active' : '' ?>">
                                <?= e($_hp['template_name'] ?? $_hp['title']) ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($footerPages)): ?>
                    <div class="editor-site-nav-group collapsed">
                        <span class="editor-site-nav-label" onclick="this.parentElement.classList.toggle('collapsed')">Footers <span class="editor-group-chevron">▾</span></span>
                        <div class="editor-site-nav-list">
                            <?php foreach ($footerPages as $_fp): ?>
                            <a href="<?= e($_editorPageHref((int)$_fp['id'])) ?>"
                               class="editor-site-nav-link<?= $page && (int)$_fp['id'] === (int)$page['id'] ? ' active' : '' ?>">
                                <?= e($_fp['template_name'] ?? $_fp['title']) ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($navTemplates)): ?>
                    <div class="editor-site-nav-group collapsed">
                        <span class="editor-site-nav-label" onclick="this.parentElement.classList.toggle('collapsed')">Templates <span class="editor-group-chevron">▾</span></span>
                        <div class="editor-site-nav-list">
                            <?php foreach ($navTemplates as $_tpl): ?>
                            <?php $_tplPageId = $_tpl['editor_page_id'] ?? null; ?>
                            <?php if ($_tplPageId): ?>
                            <a href="<?= e($_editorPageHref((int)$_tplPageId)) ?>"
                               class="editor-site-nav-link<?= $page && (int)$_tplPageId === (int)$page['id'] ? ' active' : '' ?>">
                                <?= e($_tpl['name']) ?>
                            </a>
                            <?php else: ?>
                            <a href="<?= url('/admin/templates/' . (int)$_tpl['id'] . '/edit') ?>" class="editor-site-nav-link">
                                <?= e($_tpl['name']) ?> <em style="opacity:0.6">(no canvas)</em>
                            </a>
                            <?php endif; ?>
                            <?php endforeach; ?>
                            <a href="<?= url('/admin/templates') ?>" class="editor-site-nav-link editor-site-nav-manage">Manage templates →</a>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($navMenus)): ?>
                    <div class="editor-site-nav-group collapsed">
                        <span class="editor-site-nav-label" onclick="this.parentElement.classList.toggle('collapsed')">Menus <span class="editor-group-chevron">▾</span></span>
                        <div class="editor-site-nav-list">
                            <?php foreach ($navMenus as $_nm): ?>
                            <a href="<?= url('/admin/menus/' . (int)$_nm['id'] . '/block-editor') ?>"
                               class="editor-site-nav-link<?= $page && !empty($_nm['block_page_id']) && (int)$_nm['block_page_id'] === (int)$page['id'] ? ' active' : '' ?>">
                                <?= e($_nm['name']) ?>
                            </a>
                            <?php endforeach; ?>
                            <a href="<?= url('/admin/menus') ?>" class="editor-site-nav-link editor-site-nav-manage">Manage menus →</a>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (empty($isPlatformMode)): ?>
                    <div class="editor-site-nav-group editor-site-nav-shortcuts">
                        <a href="<?= url('/admin/menus') ?>" class="editor-site-nav-link">Menus</a>
                        <a href="<?= url('/admin/site-builder/structure') ?>" class="editor-site-nav-link">Structure</a>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($navCssFiles)): ?>
                    <div class="editor-site-nav-group collapsed">
                        <span class="editor-site-nav-label" onclick="this.parentElement.classList.toggle('collapsed')">🎨 CSS Files <span class="editor-group-chevron">▾</span></span>
                        <div class="editor-site-nav-list">
                            <?php foreach ($navCssFiles as $_css): ?>
                            <?php $_cssFileRel = 'public/css/' . $_css; ?>
                            <a href="<?= e(!empty($isPlatformMode)
                                ? '/cms/editor?instance=__platform__&file=' . rawurlencode($_cssFileRel)
                                : '/cms/editor?instance=' . rawurlencode($_SESSION['_platform_editor_instance'] ?? '') . '&file=' . rawurlencode($_cssFileRel)) ?>"
                               class="editor-site-nav-link<?= $page && str_ends_with($page['render_file'] ?? '', $_cssFileRel) ? ' active' : '' ?>"><?= e($_css) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($navPhpGroups)): ?>
                    <?php $_grpLabels = ['root' => 'Root', 'public' => 'Public', 'components' => 'Components', 'council' => 'Council', 'errors' => 'Errors']; ?>
                    <div class="editor-site-nav-group collapsed">
                        <span class="editor-site-nav-label" onclick="this.parentElement.classList.toggle('collapsed')">🧩 PHP Templates <span class="editor-group-chevron">▾</span></span>
                        <div class="editor-site-nav-list">
                            <?php foreach ($navPhpGroups as $_grpKey => $_grpFiles): ?>
                            <span class="editor-site-nav-subheading"><?= e($_grpLabels[$_grpKey] ?? ucfirst($_grpKey)) ?></span>
                            <?php foreach ($_grpFiles as $_rel): ?>
                            <a href="<?= url('/admin/template-editor/edit?f=' . rawurlencode($_rel) . ($page ? '&return=' . rawurlencode('/admin/editor/' . (int)$page['id'] . '/edit') : '')) ?>" class="editor-site-nav-link"><?= e(basename($_rel)) ?></a>
                            <?php endforeach; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    </div>
            </div>

            <div class="editor-panel-section">
                <h3 class="editor-panel-heading editor-panel-toggle" onclick="this.parentElement.classList.toggle('collapsed')">Add Block <span class="editor-panel-chevron">▾</span></h3>
                <div class="editor-palette">
                    <button class="palette-btn" data-add-block="text">Text</button>
                    <button class="palette-btn" data-add-block="heading">Heading</button>
                    <button class="palette-btn" data-add-block="image">Image</button>
                    <button class="palette-btn" data-add-block="section">Section</button>
                    <button class="palette-btn" data-add-block="columns">Columns</button>
                    <button class="palette-btn" data-add-block="gallery">Gallery</button>
                    <button class="palette-btn" data-add-block="html">HTML</button>
                    <button class="palette-btn palette-btn--site" data-add-block="site-header">Site Header</button>
                    <button class="palette-btn palette-btn--site" data-add-block="nav-menu">Nav Menu</button>
                    <button class="palette-btn palette-btn--site" data-add-block="site-logo">Site Logo</button>
                    <button class="palette-btn palette-btn--site" data-add-block="site-title">Site Title</button>
                    <button class="palette-btn palette-btn--site" data-add-block="event-list">Event List</button>
                    <button class="palette-btn palette-btn--site" data-add-block="php-include">PHP Include</button>
                    <button class="palette-btn palette-btn--zone" data-add-block="zone">Zone</button>
                </div>
            </div>
            <div class="editor-panel-section" id="editor-tree-section">
                <h3 class="editor-panel-heading editor-panel-toggle" onclick="this.parentElement.classList.toggle('collapsed')">Block Tree <span class="editor-panel-chevron">▾</span></h3>
                <div id="editor-block-tree" class="editor-block-tree"></div>
            </div>
        </div>

        <!-- Canvas -->
        <div id="editor-canvas-wrap">

            <?php if ((empty($isZonePage) || !empty($isTemplatePage)) && isset($headerPageId)): ?>
            <?php if (!empty($headerPages) && count($headerPages) > 1): ?>
            <div class="editor-zone-preview editor-zone--header zone-picker">
                <?php if (!empty($headerZoneHtml)): ?>
                <div class="editor-zone-inner"><?= $headerZoneHtml ?></div>
                <?php else: ?>
                <div class="editor-zone-inner editor-zone-empty">Header zone — not yet published.</div>
                <?php endif; ?>
                <button type="button" class="editor-zone-edit-link" onclick="this.nextElementSibling.classList.toggle('open')">Choose header to edit ▾</button>
                <div class="zone-picker-menu">
                    <?php foreach ($headerPages as $_hp): ?>
                    <a href="<?= e($_editorPageHref((int)$_hp['id'])) ?>"><?= e($_hp['template_name'] ?? $_hp['title']) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <a href="<?= e($_editorPageHref((int)$headerPageId)) ?>" class="editor-zone-preview editor-zone--header">
                <?php if (!empty($headerZoneHtml)): ?>
                <div class="editor-zone-inner"><?= $headerZoneHtml ?></div>
                <?php else: ?>
                <div class="editor-zone-inner editor-zone-empty">Header zone — not yet published.</div>
                <?php endif; ?>
                <span class="editor-zone-edit-link">Click to edit header</span>
            </a>
            <?php endif; ?>
            <?php endif; ?>

            <div id="editor-canvas" class="editor-mode<?= !empty($isZonePage) ? ' zone-canvas' : '' ?>">
                <?php if (!$page): ?>
                <div class="editor-empty-canvas editor-no-page">Select a page from the left panel to begin editing.</div>
                <?php else: ?>
                <?= $cruinnHtml ?>
                <?php if (empty(trim($cruinnHtml))): ?>
                <div class="editor-empty-canvas">Click a block type on the left to begin.</div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            <div id="cruinn-canvas-resize-handle" title="Drag to resize canvas height"></div>

            <?php if (!empty($templateCanvasHtml)): ?>
            <div class="editor-zone-preview editor-template-canvas-preview">
                <div class="editor-zone-inner">
                    <?= $templateCanvasHtml ?>
                </div>
                <a href="<?= $templateCanvasPageId ? e($_editorPageHref((int)$templateCanvasPageId)) : '#' ?>"
                   class="editor-zone-edit-link">Edit Template Layout</a>
            </div>
            <?php endif; ?>

            <?php if ((empty($isZonePage) || !empty($isTemplatePage)) && isset($footerPageId)): ?>
            <a href="<?= e($_editorPageHref((int)$footerPageId)) ?>" class="editor-zone-preview editor-zone--footer">
                <?php if (!empty($footerZoneHtml)): ?>
                <div class="editor-zone-inner"><?= $footerZoneHtml ?></div>
                <?php else: ?>
                <div class="editor-zone-inner editor-zone-empty">Footer zone — not yet published.</div>
                <?php endif; ?>
                <span class="editor-zone-edit-link">Click to edit footer</span>
            </a>
            <?php endif; ?>

        </div>

        <!-- Right panel: properties accordion -->
        <div id="editor-props">
            <div class="editor-props-empty">Select a block to edit its properties.</div>

            <!-- Zone Settings group (zone blocks only) -->
            <div class="editor-accordion" data-group="zone" style="display:none">
                <button class="editor-accordion-toggle">Zone Settings</button>
                <div class="editor-accordion-body">
                    <div class="editor-prop-row">
                        <label>Zone Name</label>
                        <input type="text" class="editor-prop-input" id="prop-zone-name"
                               data-config="zone_name" placeholder="main">
                        <span class="editor-label-hint">e.g. main, sidebar, footer</span>
                    </div>
                    <div class="editor-prop-row">
                        <label>Display Label</label>
                        <input type="text" class="editor-prop-input"
                               data-config="zone_label" placeholder="Main Content">
                    </div>
                </div>
            </div>

            <!-- Identity group -->
            <div class="editor-accordion" data-group="identity">
                <button class="editor-accordion-toggle">Identity</button>
                <div class="editor-accordion-body">
                    <div class="editor-prop-row">
                        <label>Block ID</label>
                        <input type="text" id="prop-block-id" class="editor-prop-input" readonly>
                    </div>
                    <div class="editor-prop-row">
                        <label>Type</label>
                        <input type="text" id="prop-block-type" class="editor-prop-input" readonly>
                    </div>
                    <div class="editor-prop-row">
                        <label>CSS Class</label>
                        <input type="text" id="prop-class" class="editor-prop-input" data-prop-class>
                    </div>
                    <div class="editor-prop-row">
                        <label>
                            <input type="checkbox" id="prop-collapsed">
                            Collapsed
                        </label>
                        <span class="editor-label-hint">Add "collapsed" class (define behaviour in site CSS).</span>
                    </div>
                    <div class="editor-prop-row" id="prop-zone-assign-row" style="display:none">
                        <label>Template Zone</label>
                        <select class="editor-prop-input" id="prop-zone-assign">
                            <option value="main">main</option>
                        </select>
                        <span class="editor-label-hint">Which zone this block belongs to in the template.</span>
                    </div>
                    <div class="editor-prop-row editor-prop-danger">
                        <button id="prop-delete-btn" class="btn btn-small btn-danger">Delete Block</button>
                    </div>
                </div>
            </div>

            <!-- PHP Code group (php-code blocks only) -->
            <div class="editor-accordion collapsed" data-group="php-code" style="display:none">
                <button class="editor-accordion-toggle">PHP Source</button>
                <div class="editor-accordion-body">
                    <div class="editor-prop-row">
                        <textarea id="prop-php-code" class="editor-prop-input editor-prop-code"
                                  rows="12" spellcheck="false" autocomplete="off"
                                  placeholder="<?php echo 'Hello'; ?>"></textarea>
                    </div>
                </div>
            </div>

            <!-- Typography group -->
            <div class="editor-accordion collapsed" data-group="typography">
                <button class="editor-accordion-toggle">Typography</button>
                <div class="editor-accordion-body">
                    <div class="editor-prop-row">
                        <label>Colour</label>
                        <div class="cruinn-color-pair">
                            <input type="color" class="cruinn-color-swatch" data-color-swatch="color">
                            <input type="text" class="cruinn-prop-input cruinn-color-text" data-prop="color" placeholder="none" maxlength="9">
                            <button type="button" class="cruinn-color-clear" data-clear-prop="color" title="Clear">&#x2715;</button>
                        </div>
                    </div>
                    <div class="editor-prop-row">
                        <label>Font Size</label>
                        <div class="editor-prop-with-unit">
                            <input type="number" class="editor-prop-input" data-prop-num="fontSize" min="0" step="1">
                            <select class="editor-unit-select" data-unit-for="fontSize">
                                <option value="px">px</option>
                                <option value="rem">rem</option>
                                <option value="em">em</option>
                                <option value="%">%</option>
                            </select>
                        </div>
                    </div>
                    <div class="editor-prop-row">
                        <label>Font Weight</label>
                        <select class="editor-prop-input" data-prop="fontWeight">
                            <option value="">—</option>
                            <option value="300">Light (300)</option>
                            <option value="400">Normal (400)</option>
                            <option value="600">Semibold (600)</option>
                            <option value="700">Bold (700)</option>
                            <option value="900">Black (900)</option>
                        </select>
                    </div>
                    <div class="editor-prop-row">
                        <label>Text Align</label>
                        <select class="editor-prop-input" data-prop="textAlign">
                            <option value="">—</option>
                            <option value="left">Left</option>
                            <option value="center">Center</option>
                            <option value="right">Right</option>
                            <option value="justify">Justify</option>
                        </select>
                    </div>
                    <div class="editor-prop-row">
                        <label>Line Height</label>
                        <input type="number" class="editor-prop-input" data-prop="lineHeight" min="0" step="0.1">
                    </div>
                    <div class="editor-prop-row">
                        <label>Font Style</label>
                        <select class="editor-prop-input" data-prop="fontStyle">
                            <option value="">&mdash;</option>
                            <option value="normal">Normal</option>
                            <option value="italic">Italic</option>
                            <option value="oblique">Oblique</option>
                        </select>
                    </div>
                    <div class="editor-prop-row">
                        <label>Decoration</label>
                        <select class="editor-prop-input" data-prop="textDecoration">
                            <option value="">&mdash;</option>
                            <option value="none">None</option>
                            <option value="underline">Underline</option>
                            <option value="line-through">Strikethrough</option>
                            <option value="overline">Overline</option>
                        </select>
                    </div>
                    <div class="editor-prop-row">
                        <label>Transform</label>
                        <select class="editor-prop-input" data-prop="textTransform">
                            <option value="">&mdash;</option>
                            <option value="none">None</option>
                            <option value="uppercase">UPPERCASE</option>
                            <option value="lowercase">lowercase</option>
                            <option value="capitalize">Capitalize</option>
                        </select>
                    </div>
                    <div class="editor-prop-row">
                        <label>Letter Spacing</label>
                        <div class="editor-prop-with-unit">
                            <input type="number" class="editor-prop-input" data-prop-num="letterSpacing" step="0.5">
                            <select class="editor-unit-select" data-unit-for="letterSpacing">
                                <option value="px">px</option>
                                <option value="em">em</option>
                                <option value="rem">rem</option>
                            </select>
                        </div>
                    </div>
                    <div class="editor-prop-row">
                        <label>Text Shadow</label>
                        <div class="editor-shadow-grid">
                            <input type="number" class="editor-prop-input" id="prop-text-shadow-x" placeholder="X px" step="1">
                            <input type="number" class="editor-prop-input" id="prop-text-shadow-y" placeholder="Y px" step="1">
                            <input type="number" class="editor-prop-input" id="prop-text-shadow-blur" placeholder="Blur" min="0" step="1">
                            <input type="color" class="editor-prop-input editor-color-sm" id="prop-text-shadow-color" value="#000000">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Spacing group -->
            <div class="editor-accordion collapsed" data-group="spacing">
                <button class="editor-accordion-toggle">Spacing</button>
                <div class="editor-accordion-body">
                    <?php foreach (['padding' => 'Padding', 'margin' => 'Margin'] as $prop => $label): ?>
                    <div class="editor-prop-row">
                        <label><?= $label ?></label>
                        <div class="editor-trbl-grid">
                            <?php foreach (['Top', 'Right', 'Bottom', 'Left'] as $side): ?>
                            <div class="editor-prop-with-unit">
                                <input type="number" class="editor-prop-input"
                                       data-prop-num="<?= $prop . $side ?>"
                                       placeholder="<?= $side[0] ?>" min="0" step="1">
                                <select class="editor-unit-select" data-unit-for="<?= $prop . $side ?>">
                                    <option value="px">px</option>
                                    <option value="rem">rem</option>
                                    <option value="%">%</option>
                                    <?php if ($prop === 'margin'): ?><option value="auto">auto</option><?php endif; ?>
                                </select>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Image group (image / site-logo only) -->
            <div class="editor-accordion collapsed" data-group="image" style="display:none">
                <button class="editor-accordion-toggle">Image</button>
                <div class="editor-accordion-body">
                    <div class="editor-prop-row">
                        <label>Source</label>
                        <div class="editor-prop-browse-row">
                            <input type="text" class="editor-prop-input" id="prop-img-src" placeholder="/images/logo.png">
                            <button type="button" class="btn btn-small btn-outline" id="prop-img-src-browse-btn">Browse</button>
                        </div>
                    </div>
                    <div class="editor-prop-row">
                        <label>Alt text</label>
                        <input type="text" class="editor-prop-input" id="prop-img-alt">
                    </div>
                    <div class="editor-prop-row">
                        <label>Width</label>
                        <div class="editor-prop-with-unit">
                            <input type="number" class="editor-prop-input" id="prop-img-width-num" min="0" step="1">
                            <select class="editor-unit-select" id="prop-img-width-unit">
                                <option value="px">px</option>
                                <option value="%">%</option>
                                <option value="rem">rem</option>
                            </select>
                        </div>
                    </div>
                    <div class="editor-prop-row">
                        <label>Height</label>
                        <div class="editor-prop-with-unit">
                            <input type="number" class="editor-prop-input" id="prop-img-height-num" min="0" step="1" placeholder="auto">
                            <select class="editor-unit-select" id="prop-img-height-unit">
                                <option value="px">px</option>
                                <option value="%">%</option>
                                <option value="rem">rem</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Site Title group (site-title blocks only) -->
            <div class="editor-accordion collapsed" data-group="site-title" style="display:none">
                <button class="editor-accordion-toggle">Site Title</button>
                <div class="editor-accordion-body">
                    <div class="editor-prop-row">
                        <label>Title text</label>
                        <input type="text" class="editor-prop-input" id="prop-site-title-text" placeholder="Site Name">
                    </div>
                    <div class="editor-prop-row">
                        <label>Tagline</label>
                        <input type="text" class="editor-prop-input" id="prop-site-tagline-text" placeholder="Your organisation's tagline">
                    </div>
                </div>
            </div>

            <!-- Background group -->
            <div class="editor-accordion collapsed" data-group="background">
                <button class="editor-accordion-toggle">Background</button>
                <div class="editor-accordion-body">
                    <div class="editor-prop-row">
                        <label>Colour</label>
                        <div class="cruinn-color-pair">
                            <input type="color" class="cruinn-color-swatch" data-color-swatch="backgroundColor">
                            <input type="text" class="cruinn-prop-input cruinn-color-text" data-prop="backgroundColor" placeholder="none" maxlength="9">
                            <button type="button" class="cruinn-color-clear" data-clear-prop="backgroundColor" title="Clear">&#x2715;</button>
                        </div>
                    </div>
                    <div class="editor-prop-row">
                        <label>Image URL</label>
                        <div class="editor-prop-browse-row">
                            <input type="text" class="editor-prop-input" data-prop="backgroundImage" id="prop-bg-image-url">
                            <button type="button" class="btn btn-small btn-outline" id="prop-bg-browse-btn">Browse</button>
                        </div>
                    </div>
                    <div class="editor-prop-row">
                        <label>Repeat</label>
                        <select class="editor-prop-input" data-prop="backgroundRepeat" id="prop-bg-repeat">
                            <option value="">—</option>
                            <option value="no-repeat">No repeat</option>
                            <option value="repeat">Tile</option>
                            <option value="repeat-x">Tile X</option>
                            <option value="repeat-y">Tile Y</option>
                        </select>
                    </div>
                    <div class="editor-prop-row">
                        <label>Size</label>
                        <select class="editor-prop-input" data-prop="backgroundSize" id="prop-bg-size">
                            <option value="">—</option>
                            <option value="cover">Cover</option>
                            <option value="contain">Contain</option>
                            <option value="auto">Auto</option>
                        </select>
                    </div>
                    <div class="editor-prop-row">
                        <label>Position</label>
                        <select class="editor-prop-input" data-prop="backgroundPosition">
                            <option value="">—</option>
                            <option value="center center">Centre</option>
                            <option value="top center">Top</option>
                            <option value="bottom center">Bottom</option>
                            <option value="left center">Left</option>
                            <option value="right center">Right</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Border group -->
            <div class="editor-accordion collapsed" data-group="border">
                <button class="editor-accordion-toggle">Border</button>
                <div class="editor-accordion-body">
                    <div class="editor-prop-row">
                        <label>Width</label>
                        <div class="editor-prop-with-unit">
                            <input type="number" class="editor-prop-input" data-prop-num="borderWidth" min="0" step="1">
                            <select class="editor-unit-select" data-unit-for="borderWidth">
                                <option value="px">px</option>
                                <option value="rem">rem</option>
                            </select>
                        </div>
                    </div>
                    <div class="editor-prop-row">
                        <label>Style</label>
                        <select class="editor-prop-input" data-prop="borderStyle">
                            <option value="">—</option>
                            <option value="solid">Solid</option>
                            <option value="dashed">Dashed</option>
                            <option value="dotted">Dotted</option>
                            <option value="none">None</option>
                        </select>
                    </div>
                    <div class="editor-prop-row">
                        <label>Colour</label>
                        <div class="cruinn-color-pair">
                            <input type="color" class="cruinn-color-swatch" data-color-swatch="borderColor">
                            <input type="text" class="cruinn-prop-input cruinn-color-text" data-prop="borderColor" placeholder="none" maxlength="9">
                        </div>
                    </div>
                    <div class="cruinn-prop-row">
                        <label>Radius (all)</label>
                        <div class="cruinn-prop-with-unit">
                            <input type="number" class="cruinn-prop-input" data-prop-num="borderRadius" min="0" step="1">
                            <select class="cruinn-unit-select" data-unit-for="borderRadius">
                                <option value="px">px</option>
                                <option value="rem">rem</option>
                                <option value="%">%</option>
                            </select>
                        </div>
                    </div>
                    <div class="cruinn-prop-row">
                        <label>Radius (corners)</label>
                        <div class="cruinn-trbl-grid">
                            <div class="cruinn-prop-with-unit">
                                <input type="number" class="cruinn-prop-input" data-prop-num="borderTopLeftRadius" placeholder="TL" min="0" step="1">
                                <select class="cruinn-unit-select" data-unit-for="borderTopLeftRadius">
                                    <option value="px">px</option>
                                    <option value="rem">rem</option>
                                    <option value="%">%</option>
                                </select>
                            </div>
                            <div class="cruinn-prop-with-unit">
                                <input type="number" class="cruinn-prop-input" data-prop-num="borderTopRightRadius" placeholder="TR" min="0" step="1">
                                <select class="cruinn-unit-select" data-unit-for="borderTopRightRadius">
                                    <option value="px">px</option>
                                    <option value="rem">rem</option>
                                    <option value="%">%</option>
                                </select>
                            </div>
                            <div class="cruinn-prop-with-unit">
                                <input type="number" class="cruinn-prop-input" data-prop-num="borderBottomLeftRadius" placeholder="BL" min="0" step="1">
                                <select class="cruinn-unit-select" data-unit-for="borderBottomLeftRadius">
                                    <option value="px">px</option>
                                    <option value="rem">rem</option>
                                    <option value="%">%</option>
                                </select>
                            </div>
                            <div class="cruinn-prop-with-unit">
                                <input type="number" class="cruinn-prop-input" data-prop-num="borderBottomRightRadius" placeholder="BR" min="0" step="1">
                                <select class="cruinn-unit-select" data-unit-for="borderBottomRightRadius">
                                    <option value="px">px</option>
                                    <option value="rem">rem</option>
                                    <option value="%">%</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Effects group -->
            <div class="editor-accordion collapsed" data-group="effects">
                <button class="editor-accordion-toggle">Effects</button>
                <div class="editor-accordion-body">
                    <div class="editor-prop-row">
                        <label>Opacity <span class="editor-label-hint" id="prop-opacity-label">100%</span></label>
                        <input type="range" class="editor-prop-input editor-range" data-prop="opacity" min="0" max="1" step="0.01" value="1">
                    </div>
                    <div class="editor-prop-row">
                        <label>Box Shadow</label>
                        <div class="editor-shadow-grid">
                            <input type="number" class="editor-prop-input" id="prop-box-shadow-x" placeholder="X px" step="1">
                            <input type="number" class="editor-prop-input" id="prop-box-shadow-y" placeholder="Y px" step="1">
                            <input type="number" class="editor-prop-input" id="prop-box-shadow-blur" placeholder="Blur" min="0" step="1">
                            <input type="color" class="editor-prop-input editor-color-sm" id="prop-box-shadow-color" value="#000000">
                        </div>
                        <div class="editor-shadow-spread-row">
                            <input type="number" class="editor-prop-input" id="prop-box-shadow-spread" placeholder="Spread px" step="1">
                            <label class="editor-inline-label"><input type="checkbox" id="prop-box-shadow-inset"> Inset</label>
                        </div>
                    </div>
                    <div class="editor-prop-row">
                        <label>Overflow</label>
                        <select class="editor-prop-input" data-prop="overflow">
                            <option value="">&mdash;</option>
                            <option value="visible">Visible</option>
                            <option value="hidden">Hidden</option>
                            <option value="scroll">Scroll</option>
                            <option value="auto">Auto</option>
                        </select>
                    </div>
                    <div class="editor-prop-row">
                        <label>Z-Index</label>
                        <input type="number" class="editor-prop-input" data-prop="zIndex" step="1">
                    </div>
                    <div class="editor-prop-row">
                        <label>Filter
                            <span class="editor-label-hint">e.g. drop-shadow(2px 2px 4px #000)</span>
                        </label>
                        <input type="text" class="editor-prop-input" data-prop="filter"
                               placeholder="drop-shadow(0 0 8px #000)">
                    </div>
                </div>
            </div>

            <!-- Size & Position group (all blocks) -->
            <div class="editor-accordion collapsed" data-group="size-position">
                <button class="editor-accordion-toggle">Size &amp; Position</button>
                <div class="editor-accordion-body">
                    <div class="editor-prop-row">
                        <label>Width</label>
                        <div class="editor-prop-with-unit">
                            <input type="number" class="editor-prop-input" data-prop-num="width" min="0" step="1">
                            <select class="editor-unit-select" data-unit-for="width">
                                <option value="px">px</option>
                                <option value="%">%</option>
                                <option value="rem">rem</option>
                                <option value="vw">vw</option>
                                <option value="auto">auto</option>
                            </select>
                        </div>
                    </div>
                    <div class="editor-prop-row">
                        <label>Height</label>
                        <div class="editor-prop-with-unit">
                            <input type="number" class="editor-prop-input" data-prop-num="height" min="0" step="1">
                            <select class="editor-unit-select" data-unit-for="height">
                                <option value="px">px</option>
                                <option value="%">%</option>
                                <option value="rem">rem</option>
                                <option value="vh">vh</option>
                                <option value="auto">auto</option>
                            </select>
                        </div>
                    </div>
                    <div class="editor-prop-row">
                        <label>Min Height</label>
                        <div class="editor-prop-with-unit">
                            <input type="number" class="editor-prop-input" data-prop-num="minHeight" min="0" step="1">
                            <select class="editor-unit-select" data-unit-for="minHeight">
                                <option value="px">px</option>
                                <option value="%">%</option>
                                <option value="rem">rem</option>
                                <option value="vh">vh</option>
                                <option value="auto">auto</option>
                            </select>
                        </div>
                    </div>
                    <div class="editor-prop-row">
                        <label>Display</label>
                        <select class="editor-prop-input" data-prop="display">
                            <option value="">—</option>
                            <option value="block">Block (full-width)</option>
                            <option value="inline-block">Inline block</option>
                            <option value="inline">Inline</option>
                            <option value="flex">Flex</option>
                            <option value="grid">Grid</option>
                            <option value="none">Hidden</option>
                        </select>
                    </div>
                    <div class="editor-prop-row">
                        <label>Float</label>
                        <select class="editor-prop-input" data-prop="cssFloat">
                            <option value="">—</option>
                            <option value="none">None</option>
                            <option value="left">Left</option>
                            <option value="right">Right</option>
                        </select>
                    </div>
                    <div class="editor-prop-row">
                        <label>Position</label>
                        <select class="editor-prop-input" data-prop="position">
                            <option value="">—</option>
                            <option value="static">Static</option>
                            <option value="relative">Relative</option>
                            <option value="absolute">Absolute</option>
                            <option value="fixed">Fixed</option>
                        </select>
                    </div>
                    <div class="editor-prop-row">
                        <label>Offset (T / R / B / L)</label>
                        <div class="editor-trbl-grid">
                            <?php foreach (['top', 'right', 'bottom', 'left'] as $side): ?>
                            <div class="editor-prop-with-unit">
                                <input type="number" class="editor-prop-input"
                                       data-prop-num="<?= $side ?>"
                                       placeholder="<?= strtoupper($side[0]) ?>" step="1">
                                <select class="editor-unit-select" data-unit-for="<?= $side ?>">
                                    <option value="px">px</option>
                                    <option value="%">%</option>
                                    <option value="rem">rem</option>
                                </select>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Layout group (section/columns only) -->
            <div class="editor-accordion collapsed" data-group="layout" style="display:none">
                <button class="editor-accordion-toggle">Layout</button>
                <div class="editor-accordion-body">
                    <div class="editor-prop-row" id="prop-col-count-row">
                        <label>Column Count</label>
                        <input type="number" class="editor-prop-input" id="prop-col-count" data-config="columns" min="1" max="12" placeholder="2">
                    </div>
                    <div class="editor-prop-row">
                        <label>Grid Columns</label>
                        <input type="text" class="editor-prop-input" data-prop="gridTemplateColumns" placeholder="1fr 1fr">
                    </div>
                    <div class="editor-prop-row">
                        <label>Gap</label>
                        <input type="text" class="editor-prop-input" data-prop="gap" placeholder="1rem">
                    </div>
                    <div class="editor-prop-row">
                        <label>Flex Direction</label>
                        <select class="editor-prop-input" data-prop="flexDirection">
                            <option value="">—</option>
                            <option value="row">Row</option>
                            <option value="column">Column</option>
                            <option value="row-reverse">Row Reverse</option>
                            <option value="column-reverse">Column Reverse</option>
                        </select>
                    </div>
                    <div class="editor-prop-row">
                        <label>Flex Wrap</label>
                        <select class="editor-prop-input" data-prop="flexWrap">
                            <option value="">—</option>
                            <option value="nowrap">No wrap</option>
                            <option value="wrap">Wrap</option>
                        </select>
                    </div>
                    <div class="editor-prop-row">
                        <label>Align Items</label>
                        <select class="editor-prop-input" data-prop="alignItems">
                            <option value="">—</option>
                            <option value="flex-start">Start</option>
                            <option value="center">Center</option>
                            <option value="flex-end">End</option>
                            <option value="stretch">Stretch</option>
                        </select>
                    </div>
                    <div class="editor-prop-row">
                        <label>Justify Content</label>
                        <select class="editor-prop-input" data-prop="justifyContent">
                            <option value="">—</option>
                            <option value="flex-start">Start</option>
                            <option value="center">Center</option>
                            <option value="flex-end">End</option>
                            <option value="space-between">Space Between</option>
                            <option value="space-around">Space Around</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Content group (dynamic blocks only) -->
            <div class="editor-accordion collapsed" data-group="content" style="display:none">
                <button class="editor-accordion-toggle">Content</button>
                <div class="editor-accordion-body">
                    <!-- event-list config -->
                    <div class="editor-content-group" data-content-type="event-list">
                        <div class="editor-prop-row">
                            <label>Count</label>
                            <input type="number" class="editor-prop-input" data-config="count" min="1" max="50">
                        </div>
                        <div class="editor-prop-row">
                            <label>Filter</label>
                            <select class="editor-prop-input" data-config="filter">
                                <option value="upcoming">Upcoming</option>
                                <option value="past">Past</option>
                            </select>
                        </div>
                    </div>
                    <!-- nav-menu config -->
                    <div class="editor-content-group" data-content-type="nav-menu" style="display:none">
                        <div class="editor-prop-row">
                            <label>Menu</label>
                            <select class="editor-prop-input" data-config="menu_id">
                                <option value="">— Select —</option>
                                <?php foreach ($menus as $menu): ?>
                                <option value="<?= (int) $menu['id'] ?>"><?= htmlspecialchars($menu['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <!-- php-include config -->
                    <div class="editor-content-group" data-content-type="php-include" style="display:none">
                        <div class="editor-prop-row">
                            <label>Template</label>
                            <select class="editor-prop-input php-include-tpl-picker">
                                <option value="">— Select template —</option>
                                <?php
                                $piGrpLabels = ['root' => 'Root', 'public' => 'Public', 'components' => 'Components', 'council' => 'Council', 'errors' => 'Errors'];
                                foreach ($navPhpGroups as $_piGrp => $_piFiles):
                                ?>
                                <optgroup label="<?= htmlspecialchars($piGrpLabels[$_piGrp] ?? ucfirst($_piGrp), ENT_QUOTES, 'UTF-8') ?>">
                                    <?php foreach ($_piFiles as $_piRel): ?>
                                    <option value="<?= htmlspecialchars($_piRel, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(basename($_piRel), ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="php-include-vars" style="margin-top: 0.5rem">
                            <p class="php-include-hint" style="font-size:0.75rem;color:#9ca3af;margin:0;padding:0.25rem 0">Select a template to see its variables.</p>
                        </div>
                        <div class="editor-prop-row" style="margin-top:0.75rem">
                            <button type="button" id="prop-php-edit-source-btn" class="btn btn-small btn-outline" style="width:100%">&lt;/&gt; Edit Template Source</button>
                        </div>
                    </div>
                </div>
            </div>
        </div><!-- /#editor-props -->
    </div><!-- /#editor-body -->

    <!-- Mini-toolbar (floating, for contenteditable selection) -->
    <div id="editor-mini-toolbar" style="display:none">
        <button type="button" data-cmd="bold"><strong>B</strong></button>
        <button type="button" data-cmd="italic"><em>I</em></button>
        <button type="button" data-cmd="underline"><u>U</u></button>
        <button type="button" data-cmd="createLink" data-cmd-prompt="Enter URL">Link</button>
        <button type="button" data-cmd="unlink">Unlink</button>
    </div>

</div><!-- /#editor-wrap -->

<style id="editor-live-styles"><?= $cruinnCss ?></style>



<?php
// Load block type registry + all registered type definitions
$btDir = CRUINN_PUBLIC . '/js/admin/block-types/';
$btFiles = is_dir($btDir) ? (glob($btDir . '*.js') ?: []) : [];
foreach ($btFiles as $btFile):
    $btMtime = filemtime($btFile);
    $btName  = basename($btFile);
?>
<script src="<?= url('/js/admin/block-types/' . $btName) ?>?v=<?= $btMtime ?>"></script>
<?php endforeach; ?>
<script src="/js/editor.js?v=<?= file_exists(CRUINN_PUBLIC . '/js/editor.js') ? filemtime(CRUINN_PUBLIC . '/js/editor.js') : 0 ?>"></script>
