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
<div id="source-wrap"
    data-csrf-token="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
    data-preview-url="<?= htmlspecialchars(($activeFile !== null && $fileContent !== null && in_array(strtolower(pathinfo($activeFile, PATHINFO_EXTENSION)), ['php', 'html'], true)) ? '/cms/source/preview?file=' . rawurlencode($activeFile) : '', ENT_QUOTES, 'UTF-8') ?>">

    <!-- ── Left: File tree ────────────────────────────────────── -->
    <div class="source-panel source-panel-left" id="source-panel-left">
        <div class="source-panel-header">
            <span class="source-panel-title">Files</span>
            <button type="button" class="source-panel-toggle" id="source-panel-left-toggle" title="Collapse tree">◀</button>
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
    <div class="source-code-pane" id="source-code-pane">
        <?php if (!empty($savedFlash)): ?>
        <div class="source-flash source-flash-<?= htmlspecialchars($savedFlash['type'], ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($savedFlash['message'], ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>

        <?php if ($activeFile !== null && $fileContent !== null):
            $activeExt      = strtolower(pathinfo($activeFile, PATHINFO_EXTENSION));
            $canPreview     = in_array($activeExt, ['php', 'html'], true);
        ?>

        <form method="post" action="/cms/source/save" class="source-code-form" id="source-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="file" value="<?= htmlspecialchars($activeFile, ENT_QUOTES, 'UTF-8') ?>">
            <div class="source-code-toolbar">
                <button type="button" id="source-panel-centre-toggle" class="source-panel-toggle" title="Collapse editor">&#x25BC;</button>
                <span class="source-code-path"><?= htmlspecialchars($activeFile, ENT_QUOTES, 'UTF-8') ?></span>
                <div class="source-toolbar-actions">
                    <?php if ($canPreview): ?>
                    <div class="source-view-toggle" role="group" aria-label="View mode">
                        <button type="button" id="btn-view-code" data-source-view="code" class="btn btn-small btn-secondary active">Code</button>
                        <button type="button" id="btn-view-split" data-source-view="split" class="btn btn-small btn-secondary">Split</button>
                        <button type="button" id="btn-view-preview" data-source-view="preview" class="btn btn-small btn-secondary">Preview</button>
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
            <button type="button" class="source-panel-toggle" id="source-panel-right-toggle" title="Collapse properties">▶</button>
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
                    <button type="submit" id="props-file-pull-btn" data-file-name="<?= htmlspecialchars(basename($activeFile), ENT_QUOTES, 'UTF-8') ?>"
                            style="width:100%;padding:.45rem .75rem;background:var(--plat-accent);color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:.8rem;"
                            >
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
<script src="/js/admin/panel-collapse.js"></script>
<script src="/js/platform/source-editor.js"></script>

<?php $content = ob_get_clean(); ?>
<?php require __DIR__ . '/layout.php'; ?>
