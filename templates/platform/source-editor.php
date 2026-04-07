<?php
/**
 * Platform Source Editor — /cms/source
 *
 * Two-pane layout: file tree (left) + code editor textarea (right).
 * Reads and writes actual CruinnCMS source files directly on disk.
 * No instance database involved.
 *
 * Variables: $groups (array), $activeFile (?string), $activeGroup (?string),
 *            $fileContent (?string), $fileError (?string),
 *            $savedFlash (?array), $csrfToken (string)
 */
?>
<?php ob_start(); ?>
<div id="source-wrap">

    <!-- ── File tree ──────────────────────────────────────────── -->
    <div class="source-tree">
        <?php foreach ($groups as $groupName => $files): ?>
        <details<?= $groupName === ($activeGroup ?? null) ? ' open' : '' ?>>
            <summary><?= htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8') ?></summary>
            <?php foreach ($files as $relPath => $displayName): ?>
            <a href="/cms/source?file=<?= rawurlencode($relPath) ?>"
               class="source-tree-file<?= $relPath === ($activeFile ?? null) ? ' active' : '' ?>"
               title="<?= htmlspecialchars($relPath, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>
            </a>
            <?php endforeach; ?>
        </details>
        <?php endforeach; ?>
    </div>

    <!-- ── Code pane ──────────────────────────────────────────── -->
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

    </div>
</div>
<?php $content = ob_get_clean(); ?>
<?php require __DIR__ . '/layout.php'; ?>
