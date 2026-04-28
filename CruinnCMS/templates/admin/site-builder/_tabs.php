<?php
/**
 * Site Builder — Tab Navigation
 *
 * Included by every Site Builder panel template. Renders the horizontal
 * tab bar with the current tab highlighted.
 */

$tabs = [
    'pages'         => ['label' => 'Pages',         'url' => '/admin/site-builder/pages', 'icon' => '📄'],
    'templates'     => ['label' => 'Templates',     'url' => '/admin/templates',          'icon' => '📐'],
    'menus'         => ['label' => 'Menus',         'url' => '/admin/menus',              'icon' => '☰'],
    'structure'     => ['label' => 'Structure',     'url' => '/admin/site-builder/structure', 'icon' => '🗺️'],
    'php-templates' => ['label' => 'PHP Templates', 'url' => '/admin/template-editor',    'icon' => '🧩'],
];

$currentTab = $tab ?? 'pages';
\Cruinn\Template::requireCss('admin-acp.css');
?>

<div class="acp-wrapper sb-wrapper">
    <div class="acp-tabs-header">
        <div class="acp-tabs">
            <?php foreach ($tabs as $key => $t): ?>
            <a href="<?= url($t['url']) ?>"
               class="acp-tab<?= $key === $currentTab ? ' acp-tab-active' : '' ?>">
                <span class="acp-tab-icon"><?= $t['icon'] ?></span>
                <span class="acp-tab-label"><?= e($t['label']) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="acp-panel">
