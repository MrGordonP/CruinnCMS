<?php
/**
 * Platform Source Editor — /cms/source
 *
 * Three-pane layout: file tree (left, collapsible) + code editor (centre) + properties (right, collapsible).
 * Reads and writes actual CruinnCMS source files directly on disk.
 * No instance database involved.
 *
 * Variables: $sourceFileTree (array), $groups (array), $activeFile (?string), $activeGroup (?string),
 *            $fileContent (?string), $fileError (?string),
 *            $savedFlash (?array), $csrfToken (string)
 */

// Build file stat info for the properties panel
// NB: __DIR__ here is CruinnCMS/templates/platform — 2 levels up reaches CruinnCMS root.
$_fileStat = null;
if ($activeFile !== null && $fileContent !== null) {
    $rcRoot  = dirname(__DIR__, 2);
    $absPath = realpath(str_starts_with($activeFile, 'public/')
        ? CRUINN_PUBLIC . '/' . substr($activeFile, 7)
        : $rcRoot . '/' . $activeFile);
    if ($absPath && is_file($absPath)) {
        $st = stat($absPath);
        $_fileStat = [
            'size'     => $st['size'],
            'mtime'    => $st['mtime'],
            'writable' => is_writable($absPath),
            'ext'      => strtolower(pathinfo($activeFile, PATHINFO_EXTENSION)),
        ];
    }
}
?>
<?php ob_start(); ?>
<div id="source-wrap">

    <!-- ── Left: File tree ────────────────────────────────────── -->
    <div class="source-panel source-panel-left" id="source-panel-left">
        <div class="source-panel-header">
            <span class="source-panel-title">Files</span>
            <button type="button" class="source-panel-toggle" title="Collapse tree"
                    onclick="sourceTogglePanel('left')">◀</button>
        </div>
        <div class="source-panel-body source-tree">
        <?php
        $_stActive = $activeFile ?? '';
        $_stRender = function(array $entries) use (&$_stRender, $_stActive): void {
            foreach ($entries as $_e) {
                if ($_e['type'] === 'dir') {
                    $_open = $_stActive !== '' && str_starts_with($_stActive, $_e['rel'] . '/');
                    $_drel = htmlspecialchars($_e['rel'], ENT_QUOTES, 'UTF-8');
                    echo '<details' . ($_open ? ' open' : '') . '>';
                    echo '<summary data-dir="' . $_drel . '">' . htmlspecialchars($_e['name'], ENT_QUOTES, 'UTF-8') . '</summary>';
                    $_stRender($_e['children']);
                    echo '</details>';
                } else {
                    $_act = $_stActive === $_e['rel'];
                    $_rel = htmlspecialchars($_e['rel'], ENT_QUOTES, 'UTF-8');
                    echo '<a href="/cms/source?file=' . rawurlencode($_e['rel']) . '"'
                       . ' class="source-tree-file' . ($_act ? ' active' : '') . '"'
                       . ' title="' . $_rel . '">'
                       . htmlspecialchars($_e['name'], ENT_QUOTES, 'UTF-8')
                       . '</a>';
                }
            }
        };
        $_stRender($sourceFileTree);
        ?>
        </div>
    </div>

    <!-- ── Centre: Code pane ──────────────────────────────────── -->
    <div class="source-code-pane">
        <?php if (!empty($savedFlash)): ?>
        <div class="source-flash source-flash-<?= htmlspecialchars($savedFlash['type'], ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($savedFlash['message'], ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>

        <?php if ($activeFile !== null && $fileContent !== null):
            $activeExt      = strtolower(pathinfo($activeFile, PATHINFO_EXTENSION));
            $canPreview     = in_array($activeExt, ['php', 'html'], true);
            $previewUrl     = '/cms/source/preview?file=' . rawurlencode($activeFile);
        ?>

        <form method="post" action="/cms/source/save" class="source-code-form" id="source-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="file" value="<?= htmlspecialchars($activeFile, ENT_QUOTES, 'UTF-8') ?>">
            <div class="source-code-toolbar">
                <span class="source-code-path"><?= htmlspecialchars($activeFile, ENT_QUOTES, 'UTF-8') ?></span>
                <div class="source-toolbar-actions">
                    <?php if ($canPreview): ?>
                    <div class="source-view-toggle" role="group" aria-label="View mode">
                        <button type="button" id="btn-view-code"  class="btn btn-small btn-secondary active" onclick="sourceSetView('code')">Code</button>
                        <button type="button" id="btn-view-split" class="btn btn-small btn-secondary"        onclick="sourceSetView('split')">Split</button>
                        <button type="button" id="btn-view-preview" class="btn btn-small btn-secondary"     onclick="sourceSetView('preview')">Preview</button>
                    </div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-small btn-primary">Save</button>
                </div>
            </div>

            <div id="source-editor-body" class="source-editor-body source-view-code">
                <textarea
                    id="source-textarea"
                    name="content"
                    class="source-code-textarea"
                    spellcheck="false"
                    autocomplete="off"
                    onkeydown="if(event.key==='Tab'){event.preventDefault();var s=this.selectionStart,e=this.selectionEnd;this.value=this.value.substring(0,s)+'\t'+this.value.substring(e);this.selectionStart=this.selectionEnd=s+1;}"
                ><?= htmlspecialchars($fileContent, ENT_QUOTES, 'UTF-8') ?></textarea>
                <?php if ($canPreview): ?>
                <iframe
                    id="source-preview-frame"
                    class="source-preview-frame"
                    src="about:blank"
                    sandbox="allow-same-origin"
                    title="File preview"
                ></iframe>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($canPreview): ?>
        <script>
        (function () {
            var previewUrl  = <?= json_encode($previewUrl) ?>;
            var frame       = document.getElementById('source-preview-frame');
            var body        = document.getElementById('source-editor-body');
            var btnCode     = document.getElementById('btn-view-code');
            var btnSplit    = document.getElementById('btn-view-split');
            var btnPreview  = document.getElementById('btn-view-preview');
            var currentView = 'code';
            var loaded      = false;

            function loadPreview() {
                if (!loaded) {
                    frame.src = previewUrl;
                    loaded = true;
                }
            }

            window.sourceSetView = function (view) {
                currentView = view;
                body.className = 'source-editor-body source-view-' + view;
                btnCode.classList.toggle('active',    view === 'code');
                btnSplit.classList.toggle('active',   view === 'split');
                btnPreview.classList.toggle('active', view === 'preview');
                if (view === 'split' || view === 'preview') {
                    loadPreview();
                }
            };

            // Reload preview after a successful save (page reload already happens,
            // but if we ever do async saves this ensures the frame refreshes).
            document.getElementById('source-form').addEventListener('submit', function () {
                loaded = false;
            });
        }());
        </script>
        <?php endif; ?>

        <?php elseif ($fileError !== null): ?>

        <div class="source-empty">
            <span style="color:var(--plat-err)"><?= htmlspecialchars($fileError, ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <?php else: ?>

        <div class="source-empty">
            <span>Select a file from the tree to edit it.</span>
        </div>

        <?php endif; ?>

    </div><!-- /.source-code-pane -->

    <!-- ── Right: Properties panel ───────────────────────────── -->
    <div class="source-panel source-panel-right" id="source-panel-right">
        <div class="source-panel-header">
            <button type="button" class="source-panel-toggle" title="Collapse properties"
                    onclick="sourceTogglePanel('right')">▶</button>
            <span class="source-panel-title">Properties</span>
        </div>
        <div class="source-panel-body source-props">

            <!-- File properties (PHP-rendered, shown when a file is active) -->
            <div id="props-file" <?= $_fileStat === null ? 'style="display:none"' : '' ?>>
            <?php if ($_fileStat !== null): ?>
            <dl class="source-props-list">
                <dt>Path</dt>
                <dd class="source-props-path"><?= htmlspecialchars($activeFile, ENT_QUOTES, 'UTF-8') ?></dd>

                <dt>Type</dt>
                <dd><?= htmlspecialchars(strtoupper($_fileStat['ext']), ENT_QUOTES, 'UTF-8') ?></dd>

                <dt>Size</dt>
                <dd><?= number_format($_fileStat['size']) ?> bytes</dd>

                <dt>Modified</dt>
                <dd><?= date('Y-m-d H:i', $_fileStat['mtime']) ?></dd>

                <dt>Writable</dt>
                <dd>
                    <?php if ($_fileStat['writable']): ?>
                    <span class="source-props-badge source-props-ok">Yes</span>
                    <?php else: ?>
                    <span class="source-props-badge source-props-warn">Read-only</span>
                    <?php endif; ?>
                </dd>
            </dl>
            <?php $protected = ['config/', 'instance/', 'public/uploads/', 'public/storage/'];
                  $_isProtected = false;
                  foreach ($protected as $_pg) { if (str_starts_with($activeFile, $_pg)) { $_isProtected = true; break; } }
            ?>
            <?php if (!$_isProtected): ?>
            <div style="margin-top:1rem;">
                <form id="props-pull-form" method="post" action="/cms/source/pull">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="file" value="<?= htmlspecialchars($activeFile, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit"
                            style="width:100%;padding:.45rem .75rem;background:var(--plat-accent);color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:.8rem;"
                            onclick="return confirm('Pull &quot;<?= htmlspecialchars(basename($activeFile), ENT_QUOTES, 'UTF-8') ?>&quot; from GitHub and overwrite the local copy?')">
                        ↓ Pull from Repo
                    </button>
                </form>
            </div>
            <?php else: ?>
            <p style="font-size:.75rem;color:var(--plat-text-muted);margin-top:.75rem;">Protected path — cannot pull from repo.</p>
            <?php endif; ?>
            <?php endif; ?>
            </div>

            <!-- Folder info (JS-populated, shown when a tree folder is clicked) -->
            <div id="props-dir" style="display:none">
                <dl class="source-props-list">
                    <dt>Folder</dt>
                    <dd id="props-dir-name" class="source-props-path"></dd>
                </dl>
                <div style="margin-top:1rem;">
                    <button id="props-dir-pull-btn" type="button"
                            style="width:100%;padding:.45rem .75rem;background:var(--plat-accent);color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:.8rem;">
                        ↓ Pull Folder from Repo
                    </button>
                </div>
                <div id="props-dir-results" style="margin-top:.75rem;font-size:.75rem;display:none;max-height:260px;overflow-y:auto;"></div>
            </div>

            <!-- Fallback -->
            <div id="props-empty" <?= $_fileStat !== null ? 'style="display:none"' : '' ?>>
                <p class="source-props-empty">Select a file or folder.</p>
            </div>

        </div>
    </div>

</div><!-- /#source-wrap -->

<script>
(function () {
    var STORE_KEY_LEFT  = 'cms_source_panel_left';
    var STORE_KEY_RIGHT = 'cms_source_panel_right';

    function applyState(side, collapsed) {
        var panel = document.getElementById('source-panel-' + side);
        if (!panel) { return; }
        var btn = panel.querySelector('.source-panel-toggle');
        if (collapsed) {
            panel.classList.add('collapsed');
            btn.textContent = side === 'left' ? '▶' : '◀';
            btn.title = 'Expand ' + (side === 'left' ? 'tree' : 'properties');
        } else {
            panel.classList.remove('collapsed');
            btn.textContent = side === 'left' ? '◀' : '▶';
            btn.title = 'Collapse ' + (side === 'left' ? 'tree' : 'properties');
        }
    }

    window.sourceTogglePanel = function (side) {
        var panel     = document.getElementById('source-panel-' + side);
        var collapsed = !panel.classList.contains('collapsed');
        applyState(side, collapsed);
        try { localStorage.setItem(side === 'left' ? STORE_KEY_LEFT : STORE_KEY_RIGHT, collapsed ? '1' : '0'); } catch(e) {}
    };

    // Restore saved state
    try {
        applyState('left',  false);
        applyState('right', false);
    } catch(e) {}

    // ── Folder click → show in properties panel ─────────────────
    var _currentDir = null;
    document.querySelector('.source-tree').addEventListener('click', function(e) {
        var summary = e.target.closest('summary[data-dir]');
        if (!summary) return;
        _currentDir = summary.dataset.dir;
        document.getElementById('props-file').style.display  = 'none';
        document.getElementById('props-empty').style.display = 'none';
        var pd = document.getElementById('props-dir');
        document.getElementById('props-dir-name').textContent = _currentDir + '/';
        document.getElementById('props-dir-results').style.display = 'none';
        document.getElementById('props-dir-results').innerHTML = '';
        document.getElementById('props-dir-pull-btn').disabled = false;
        document.getElementById('props-dir-pull-btn').textContent = '\u2193 Pull Folder from Repo';
        pd.style.display = '';
        var rp = document.getElementById('source-panel-right');
        if (rp && rp.classList.contains('collapsed')) { window.sourceTogglePanel('right'); }
    });

    // ── Folder pull ───────────────────────────────────────────────
    document.getElementById('props-dir-pull-btn').addEventListener('click', function() {
        if (!_currentDir) return;
        if (!confirm('Pull all files under "' + _currentDir + '/" from GitHub and overwrite local copies?')) return;

        var btn     = this;
        var results = document.getElementById('props-dir-results');
        btn.disabled    = true;
        btn.textContent = 'Pulling\u2026';
        results.style.display = '';
        results.innerHTML = '<span style="color:var(--plat-text-muted)">Contacting GitHub\u2026</span>';

        var fd = new FormData();
        fd.append('csrf_token', <?= json_encode($csrfToken) ?>);
        fd.append('dir', _currentDir);

        fetch('/cms/source/pull-dir', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                btn.disabled    = false;
                btn.textContent = '\u2193 Pull Folder from Repo';
                if (!data.ok && !data.files) {
                    results.innerHTML = '<span style="color:#dc2626">\u274c ' + (data.error || 'Unknown error') + '</span>';
                    return;
                }
                if (data.error && (!data.files || data.files.length === 0)) {
                    results.innerHTML = '<span style="color:#d97706">\u26a0 ' + data.error + '</span>';
                    return;
                }
                var html = '';
                var ok = 0, failed = 0, skipped = 0;
                (data.files || []).forEach(function(f) {
                    if (f.status === 'ok')          { ok++;      html += '<div style="color:#16a34a">\u2713 ' + f.path + '</div>'; }
                    else if (f.status === 'skipped') { skipped++; html += '<div style="color:var(--plat-text-muted)">\u2014 ' + f.path + (f.error ? ' (' + f.error + ')' : '') + '</div>'; }
                    else                             { failed++;   html += '<div style="color:#dc2626">\u274c ' + f.path + (f.error ? ': ' + f.error : '') + '</div>'; }
                });
                var summary = '<div style="font-weight:600;margin-bottom:.4rem;border-bottom:1px solid var(--plat-border);padding-bottom:.3rem;">'
                    + ok + ' updated, ' + failed + ' failed, ' + skipped + ' skipped</div>';
                results.innerHTML = summary + html;
            })
            .catch(function(err) {
                btn.disabled    = false;
                btn.textContent = '\u2193 Pull Folder from Repo';
                results.innerHTML = '<span style="color:#dc2626">\u274c Network error: ' + err.message + '</span>';
            });
    });
}());
</script>

<?php $content = ob_get_clean(); ?>
<?php require __DIR__ . '/layout.php'; ?>
