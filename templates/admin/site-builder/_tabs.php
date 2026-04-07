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
$acpLayout = $_SESSION['acp_layout'] ?? '1';
?>

<div class="acp-wrapper sb-wrapper<?= $acpLayout === '2' ? ' acp-two-col' : '' ?>">
    <div class="acp-header">
        <div class="acp-layout-toggle" title="Toggle column layout">
            <button type="button" class="acp-layout-btn<?= $acpLayout === '1' ? ' active' : '' ?>" data-layout="1" aria-label="Single column">
                <svg width="18" height="18" viewBox="0 0 18 18"><rect x="2" y="2" width="14" height="14" rx="2" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>
            </button>
            <button type="button" class="acp-layout-btn<?= $acpLayout === '2' ? ' active' : '' ?>" data-layout="2" aria-label="Two columns">
                <svg width="18" height="18" viewBox="0 0 18 18"><rect x="2" y="2" width="5.5" height="14" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.5"/><rect x="10.5" y="2" width="5.5" height="14" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>
            </button>
        </div>
    </div>

    <div class="acp-tabs">
        <?php foreach ($tabs as $key => $t): ?>
        <a href="<?= url($t['url']) ?>"
           class="acp-tab<?= $key === $currentTab ? ' acp-tab-active' : '' ?>">
            <span class="acp-tab-icon"><?= $t['icon'] ?></span>
            <span class="acp-tab-label"><?= e($t['label']) ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="acp-panel">
