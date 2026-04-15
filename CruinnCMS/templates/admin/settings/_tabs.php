<?php
/**
 * ACP — Settings Tab Navigation
 *
 * Included by every settings panel template. Renders the horizontal
 * tab bar with the current tab highlighted.
 */

$tabs = [
    'site'     => ['label' => 'Site',           'url' => '/admin/settings/site',     'icon' => '🏠'],
    'email'    => ['label' => 'Email',          'url' => '/admin/settings/email',    'icon' => '✉️'],
    'auth'     => ['label' => 'Authentication', 'url' => '/admin/settings/auth',     'icon' => '🔑'],
    'security' => ['label' => 'Security',       'url' => '/admin/settings/security', 'icon' => '🛡️'],
    'gdpr'     => ['label' => 'GDPR & Privacy', 'url' => '/admin/settings/gdpr',     'icon' => '📋'],
    'social'   => ['label' => 'Social Media',   'url' => '/admin/settings/social',   'icon' => '📱'],
    'payments' => ['label' => 'Payments',       'url' => '/admin/settings/payments', 'icon' => '💳'],
    'oauth'    => ['label' => 'OAuth',          'url' => '/admin/settings/oauth',    'icon' => '🔗'],
    'system'   => ['label' => 'System Info',    'url' => '/admin/settings/system',   'icon' => 'ℹ️'],
    'database' => ['label' => 'Database',       'url' => '/admin/settings/database', 'icon' => '🗄️'],
    'modules'     => ['label' => 'Modules',         'url' => '/admin/settings/modules',  'icon' => '🧩'],
    'maintenance' => ['label' => 'Maintenance',     'url' => '/admin/maintenance/links',  'icon' => '🔧'],
];

$currentTab = $tab ?? 'site';
$acpLayout = $_SESSION['acp_layout'] ?? '1';
?>

<div class="acp-wrapper<?= $acpLayout === '2' ? ' acp-two-col' : '' ?>">
    <div class="acp-header">
        <h1>Administration Control Panel</h1>
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
