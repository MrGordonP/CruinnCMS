<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Cruinn CMS') ?></title>
    <link rel="icon" type="image/svg+xml" href="/brand/cruinn-favicon.svg">
    <link rel="stylesheet" href="/css/platform.css">
    <script src="/js/platform/boot.js"></script>
</head>
<body class="platform-body<?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/cms/editor') ? ' platform-editor-page' : '' ?>">
<div class="platform-wrap"><div class="platform-sidebar-backdrop" id="platform-sidebar-backdrop"></div><aside class="platform-sidebar" id="platform-sidebar"><div class="platform-sidebar-hero"><a href="/cms/dashboard"><img src="/brand/cruinn-favicon.svg" alt="Cruinn CMS">
</a>
<div class="platform-sidebar-wordmark">
    <span class="platform-sidebar-wm-name"><span>CRUINN</span><span class="platform-sidebar-wm-cms">CMS</span>
<span class="platform-sidebar-wm-fa" style="text-align: center;">
    <span style="width: 200px;">
        <span style="width: 100px;">
            <span style="font-style: normal; text-align: center; font-weight: 400; letter-spacing: 1em;">
                <span style="letter-spacing: 0.5em;">FULLY AXIOMATIC</span>
            </span>
        </span>
    </span>
</span>

</div>
</div>
<?php if (!empty($username)): ?>
<?php
            // Build sidebar nav data
            $_snRootDir    = dirname(__DIR__, 2);
            $_snActiveDir  = \Cruinn\App::instanceDir();
            $_snActiveName = $_snActiveDir ? basename($_snActiveDir) : null;
            $_snInstances  = [];
            foreach (glob($_snRootDir . '/instance/*/config.php') ?: [] as $_snCfg) {
                $_snFolder  = basename(dirname($_snCfg));
                $_snCfgData = require $_snCfg;
                $_snInstances[] = [
                    'folder_name' => $_snFolder,
                    'name'        => $_snCfgData['site']['name'] ?? $_snFolder,
                    'active'      => ($_snFolder === $_snActiveName),
                ];
            }
        ?>
<nav class="platform-sidebar-nav" id="platform-sidebar-nav">
    <a href="/cms/dashboard">
        <span>Dashboard</span>
</a>
<a href="/cms/settings"><span>Settings</span></a>
<a href="/cms/source"<?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/cms/source') ? ' class="active"' : '' ?>><span>Source Files</span></a>
<a href="/cms/migrations"<?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/cms/migrations') ? ' class="active"' : '' ?>><span>Migrations</span></a>
<span class="platform-nav-section-label">Editor</span>
<?php
                // Current URL is navigating to a specific instance if the query string contains instance=<folder>
                $_snCurrentUri      = $_SERVER['REQUEST_URI'] ?? '';
                $_snCurrentInstance = null;
                if (str_starts_with($_snCurrentUri, '/cms/editor')) {
                    parse_str(parse_url($_snCurrentUri, PHP_URL_QUERY) ?? '', $_snQs);
                    $_snCurrentInstance = $_snQs['instance'] ?? null;
                }
            ?>
<a href="/cms/editor?instance=__platform__"
               class="platform-nav-instance<?= ($_snCurrentInstance === '__platform__') ? ' active' : '' ?>"><span>CruinnCMS Platform</span></a>
<?php foreach ($_snInstances as $_snInst): ?>
<a href="/cms/editor?instance=<?= urlencode($_snInst['folder_name']) ?>"
               class="platform-nav-instance<?= ($_snCurrentInstance === $_snInst['folder_name']) ? ' active' : '' ?>"><?= e($_snInst['name']) ?>
</a>
<?php endforeach; ?>
</nav>
<?php endif; ?>
<?php
            // Show the active instance in the sidebar footer
            $activeInst = null;
            if (!empty($instances) && is_array($instances)) {
                foreach ($instances as $_inst) { if (!empty($_inst['online'])) { $activeInst = $_inst; break; } }
                if (!$activeInst) $activeInst = $instances[0] ?? null;
            } elseif (!empty($instance)) {
                $activeInst = $instance; // backward compat
            }
        ?>
<?php if (!empty($activeInst['name'])): ?>
<div class="platform-sidebar-footer"><span class="platform-sidebar-instance"><?= e($activeInst['name']) ?></span>
<?php if (!empty($activeInst['url'])): ?>
<a href="<?= e($activeInst['url']) ?>" target="_blank" rel="noopener" class="platform-sidebar-url"><?= e($activeInst['url']) ?></a>
<?php endif; ?>
</div>
<?php endif; ?>
</aside>
<div class="platform-right"><div class="platform-bar"><div class="platform-bar-inner"><?php if (!empty($username)): ?>
<div class="platform-bar-right"><button class="platform-sidebar-toggle" id="platform-sidebar-btn" aria-label="Toggle navigation" aria-expanded="true" title="Toggle sidebar">&#9776;</button><span class="platform-bar-user"><span>👤</span>

<?= e($username) ?></span>
<button class="platform-width-toggle" id="platform-width-btn" type="button" title="Toggle layout width"><span>⊞</span>
</button>
<a href="/cms/logout"><span>Logout</span></a>
</div>
<?php endif; ?>
</div>
</div>
<div class="platform-main"><div class="platform-content"><?php
                    // Instance-switch flash (separate from instance Auth::flash system)
                    $_pf = $_SESSION['_platform_flash'] ?? null;
                    if ($_pf) { unset($_SESSION['_platform_flash']); }
                ?>
<?php if ($_pf): ?>
<div class="flash flash-<?= e($_pf['type']) ?>"><?= e($_pf['message']) ?></div>
<?php endif; ?>
<?php if (!empty($flashes)): ?>
<div class="flash-messages"><?php foreach ($flashes as $flash): ?>
<div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?= $content ?>
</div>
</div>
</div>
</div>
<footer class="platform-footer"><span>Built with</span>
<a href="https://cruinncms.com" target="_blank" rel="noopener"><span>Cruinn CMS</span></a>
</footer>
<script src="/js/platform/shell.js"></script>
</body>
</html>
