<?php
/**
 * Template Preview — renders the template as it would appear on the live frontend.
 * Uses the public layout.php with template header/footer blocks injected via globals.
 */
\Cruinn\Template::requireCss('admin-site-builder.css'); \Cruinn\Template::requireCss('admin-template-editor.css');

$tplSettings = $tpl['settings'] ?? [];
$tplClass = $tpl['css_class'] ?? 'layout-default';
$zones = $tpl['zones'] ?? ['main'];
$hasSidebar = in_array('sidebar', $zones, true);
$showTitle = $tplSettings['show_title'] ?? true;
$titleAlign = $tplSettings['title_align'] ?? 'left';
$contentWidth = $tplSettings['content_width'] ?? 'default';
$showBreadcrumbs = $tplSettings['show_breadcrumbs'] ?? false;

$widthClass = '';
if ($contentWidth === 'narrow') $widthClass = ' content-narrow';
elseif ($contentWidth === 'wide') $widthClass = ' content-wide';
elseif ($contentWidth === 'full') $widthClass = ' content-full';

$bodyBlocks = $bodyBlocks ?? [];
?>

<!-- Preview banner -->
<div style="background:#1a1a2e;color:#fff;padding:8px 20px;text-align:center;font-size:14px;">
    Template Preview: <strong><?= e($tpl['name']) ?></strong>
    &nbsp;&mdash;&nbsp;
    <a href="#" data-action="window-close" style="color:#60a5fa;">&larr; Close Preview</a>
</div>

<div class="page-content <?= e($tplClass) ?><?= $widthClass ?>">
    <div class="container">
        <?php if ($showBreadcrumbs): ?>
        <nav class="breadcrumbs" aria-label="Breadcrumb">
            <a href="<?= url('/') ?>">Home</a> &rsaquo;
            <span>Sample Page</span>
        </nav>
        <?php endif; ?>

        <?php if ($showTitle): ?>
        <h1<?= $titleAlign !== 'left' ? ' style="text-align:' . e($titleAlign) . '"' : '' ?>>Sample Page Title</h1>
        <?php endif; ?>

        <?php if (!empty($bodyBlocks)): ?>
            <?php foreach ($bodyBlocks as $block): ?>
                <?php include __DIR__ . '/../../components/block.php'; ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($hasSidebar): ?>
        <div class="page-grid">
            <div class="page-main">
        <?php endif; ?>

        <!-- Sample page content to show how the template wraps actual content -->
        <div class="template-preview-sample" style="border:2px dashed #cbd5e1;border-radius:8px;padding:32px;margin:24px 0;color:#64748b;text-align:center;">
            <p style="font-size:1.1rem;margin:0 0 8px;"><strong>Page Content Area</strong></p>
            <p style="margin:0;">This is where the page's own content blocks would appear.<br>The header, footer, and body blocks above come from the template.</p>
        </div>

        <?php if ($hasSidebar): ?>
            </div>
            <aside class="page-sidebar">
                <div class="template-preview-sample" style="border:2px dashed #cbd5e1;border-radius:8px;padding:24px;color:#64748b;text-align:center;">
                    <p style="margin:0;"><strong>Sidebar Area</strong></p>
                </div>
            </aside>
        </div>
        <?php endif; ?>
    </div>
</div>
