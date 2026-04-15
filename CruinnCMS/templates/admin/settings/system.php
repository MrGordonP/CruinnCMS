<?php include __DIR__ . '/_tabs.php'; ?>
<?php \Cruinn\Template::requireCss('admin-acp.css'); ?>

<h2>System Information</h2>

<div class="acp-info-grid">
    <fieldset class="acp-fieldset">
        <legend>PHP</legend>
        <table class="acp-info-table">
            <tr><th>Version</th><td><?= e($system['php_version']) ?></td></tr>
            <tr><th>SAPI</th><td><?= e($system['php_sapi']) ?></td></tr>
            <tr><th>Memory Limit</th><td><?= e($system['memory_limit']) ?></td></tr>
            <tr><th>Max Execution Time</th><td><?= e($system['max_execution']) ?>s</td></tr>
            <tr><th>Upload Max Filesize</th><td><?= e($system['max_upload']) ?></td></tr>
            <tr><th>Post Max Size</th><td><?= e($system['max_post']) ?></td></tr>
            <tr><th>OPcache</th><td><?= function_exists('opcache_get_status') && @opcache_get_status() ? '✅ Enabled' : '❌ Disabled' ?></td></tr>
        </table>
    </fieldset>

    <fieldset class="acp-fieldset">
        <legend>Server</legend>
        <table class="acp-info-table">
            <tr><th>OS</th><td><?= e($system['os']) ?> (<?= php_uname('r') ?>)</td></tr>
            <tr><th>Architecture</th><td><?= php_uname('m') ?></td></tr>
            <tr><th>Server Software</th><td><?= e($system['server_software']) ?></td></tr>
            <tr><th>Document Root</th><td><?= e($system['document_root']) ?></td></tr>
            <tr><th>Hostname</th><td><?= e(gethostname()) ?></td></tr>
        </table>
    </fieldset>

    <fieldset class="acp-fieldset">
        <legend>Database</legend>
        <table class="acp-info-table">
            <tr><th>Database Name</th><td><?= e($system['db_name']) ?></td></tr>
            <tr><th>Total Size</th><td><?= e($system['db_size_mb']) ?> MB</td></tr>
            <tr><th>Tables</th><td><?= (int) $system['db_tables'] ?></td></tr>
        </table>
    </fieldset>

    <fieldset class="acp-fieldset">
        <legend>Uploads</legend>
        <table class="acp-info-table">
            <tr><th>Path</th><td><?= e($system['uploads_path']) ?></td></tr>
            <tr><th>Size</th><td><?= $system['uploads_size_mb'] ?> MB</td></tr>
            <tr><th>Writable</th><td><?= $system['uploads_writable'] ? '✅ Yes' : '❌ No' ?></td></tr>
        </table>
    </fieldset>

    <fieldset class="acp-fieldset">
        <legend>Loaded PHP Extensions</legend>
        <div class="acp-extensions"><?php
            $exts = $system['extensions'];
            sort($exts);
            foreach ($exts as $ext): ?>
                <span class="acp-ext-badge"><?= e($ext) ?></span>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <fieldset class="acp-fieldset">
        <legend>Writable Paths</legend>
        <table class="acp-info-table">
            <?php
            $paths = [
                'Uploads' => $system['uploads_path'],
                'Config' => dirname(__DIR__, 2) . '/config',
                'Instance' => dirname(__DIR__, 2) . '/instance',
            ];
            foreach ($paths as $label => $path): ?>
                <tr>
                    <th><?= e($label) ?></th>
                    <td>
                        <?php if ($path && is_dir($path)): ?>
                            <?= is_writable($path) ? '✅' : '❌' ?> <?= e($path) ?>
                        <?php else: ?>
                            ⚠️ Not found
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </fieldset>
</div>

<?php include __DIR__ . '/_tabs_end.php'; ?>
