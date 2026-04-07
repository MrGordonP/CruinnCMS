<!DOCTYPE html>
<html lang="en">
<head>















    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Cruinn CMS') ?></title>
    <link rel="icon" type="image/svg+xml" href="/brand/cruinn-favicon.svg">
    <link rel="stylesheet" href="/css/platform.css">















</head>
<body class="platform-body">
<script>if(localStorage.getItem('platform-layout-wide')==='1')document.documentElement.classList.add('platform-layout-wide');</script>
<div class="platform-wrap"><aside class="platform-sidebar"><div class="platform-sidebar-hero"><a href="/cms/dashboard"><img src="/brand/cruinn-favicon.svg" alt="Cruinn CMS">
</a>
<div class="platform-sidebar-wordmark"><span class="platform-sidebar-wm-name"><span><span><span><span><span><span><span><span><span><span><span><span><span><span><span><span>CRUINN</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
<span class="platform-sidebar-wm-cms"><span><span><span><span><span><span><span><span><span><span><span><span><span><span><span>CMS</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
<span class="platform-sidebar-wm-fa" style="text-align: center;"><span><span><span><span style="width: 200px;"><span><span><span style="width: 100px;"><span><span><span><span><span><span><span style="font-style: normal; text-align: center; font-weight: 400; letter-spacing: 1em;"><span style="letter-spacing: 0.5em;">FULLY AXIOMATIC</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</div>
</div>
<?php if (!empty($username)): ?>
<?php
            // ╬ô├╢├ç╬ô├╢├ç Build sidebar nav data ╬ô├╢├ç╬ô├╢├ç╬ô├╢├ç╬ô├╢├ç╬ô├╢├ç╬ô├╢├ç╬ô├╢├ç╬ô├╢├ç╬ô├╢├ç╬ô├╢├ç╬ô├╢├ç╬ô├╢├ç╬ô├╢├ç╬ô├╢├ç╬ô├╢├ç╬ô├╢├ç╬ô├╢├ç╬ô├╢├ç╬ô├╢├ç╬ô├╢├ç╬ô├╢├ç╬ô├╢├ç╬ô├╢├ç╬ô├╢├ç╬ô├╢├ç╬ô├╢├ç╬ô├╢├ç╬ô├╢├ç╬ô├╢├ç
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
<nav class="platform-sidebar-nav" id="platform-sidebar-nav"><a href="/cms/dashboard"><span><span><span><span><span><span><span><span><span><span><span><span><span><span><span>Dashboard</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</a>
<a href="/cms/settings"><span><span><span><span><span><span><span><span><span><span><span><span><span><span><span>Settings</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</a>
<a href="/cms/source"<?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/cms/source') ? ' class="active"' : '' ?>><span><span><span><span><span><span><span><span><span><span><span><span><span><span><span>Source Files</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</a>
<span class="platform-nav-section-label"><span><span><span><span><span><span><span><span><span><span><span><span><span><span><span>Editor</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
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
               class="platform-nav-instance<?= ($_snCurrentInstance === '__platform__') ? ' active' : '' ?>"><span><span><span><span><span><span><span><span><span><span><span><span><span><span><span>CruinnCMS Platform</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</a>
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
                foreach ($instances as $_inst) { if ($_inst['active']) { $activeInst = $_inst; break; } }
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
<div class="platform-bar-right"><span class="platform-bar-user"><span><span><span><span><span><span><span><span><span><span><span><span><span><span><span>👤</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
<?= e($username) ?></span>
<button class="platform-width-toggle" id="platform-width-btn" title="Toggle layout width"
                            onclick="var w=document.documentElement.classList.toggle('platform-layout-wide');localStorage.setItem('platform-layout-wide',w?'1':'0');this.textContent=w?'\u22A1':'\u229E';"><span><span><span><span><span><span><span><span><span><span><span><span><span><span><span>⊞</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</button>
<script>document.getElementById('platform-width-btn').textContent=document.documentElement.classList.contains('platform-layout-wide')?'\u22A1':'\u229E';</script>
<a href="/cms/logout"><span><span><span><span><span><span><span><span><span><span><span><span><span><span><span>Logout</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</a>
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
<footer class="platform-footer"><span><span><span><span><span><span><span><span><span><span><span><span><span><span><span><span>Built with</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
<a href="https://cruinncms.com" target="_blank" rel="noopener"><span><span><span><span><span><span><span><span><span><span><span><span><span><span><span>Cruinn CMS</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</span>
</a>
</span>
</footer>

</body>
</html>