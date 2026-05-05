<?php
/**
 * Theme Editor
 * Template variables: $theme, $vars (array of ['name','value','comment']), $filePath, $error
 */
$this->addCss('admin-site-builder.css');
?>

<?php if (!empty($_SESSION['flash_success'])): ?>
<div class="acp-alert acp-alert--success"><?= e($_SESSION['flash_success']) ?></div>
<?php unset($_SESSION['flash_success']); endif; ?>

<?php if (!empty($_SESSION['flash_error'])): ?>
<div class="acp-alert acp-alert--error"><?= e($_SESSION['flash_error']) ?></div>
<?php unset($_SESSION['flash_error']); endif; ?>

<?php if (!empty($error)): ?>
<div class="acp-page-header">
    <h1 class="acp-page-title">Theme Editor</h1>
</div>
<div class="acp-alert acp-alert--error"><?= e($error) ?></div>
<?php else: ?>

<div class="theme-editor-wrap">

    <!-- ── Controls panel ──────────────────────────────────────── -->
    <div class="theme-editor-panel">
        <div class="theme-editor-panel-header">
            <span class="theme-editor-panel-title">Theme Editor</span>
            <span class="theme-editor-panel-file"><?= e($filePath) ?></span>
        </div>

        <form method="post" action="<?= url('/admin/theme') ?>" id="theme-form">
            <input type="hidden" name="csrf_token" value="<?= e(\Cruinn\CSRF::getToken()) ?>">

            <div class="theme-editor-groups">
            <?php
            $currentGroup = null;
            foreach ($vars as $var):
                if (!empty($var['comment']) && $var['comment'] !== $currentGroup):
                    if ($currentGroup !== null): ?>
                </div><!-- /.theme-group-body -->
            </div><!-- /.theme-group -->
                    <?php endif;
                        $currentGroup = $var['comment'];
                    ?>
            <div class="theme-group">
                <div class="theme-group-title"><?= e($currentGroup) ?></div>
                <div class="theme-group-body">
                <?php endif; ?>
                    <div class="theme-var-row">
                        <label class="theme-var-label" for="var_<?= e(ltrim($var['name'], '-')) ?>"
                               title="<?= e($var['name']) ?>"><?= e($var['name']) ?></label>
                        <?php
                        $isColour = preg_match('/^#[0-9a-f]{3,8}$/i', trim($var['value']))
                                 || preg_match('/^rgba?\s*\(/', trim($var['value']));
                        $safeId   = e(ltrim($var['name'], '-'));
                        ?>
                        <?php if ($isColour): ?>
                        <div class="theme-colour-pair">
                            <input type="color"
                                   value="<?= e(preg_match('/^#[0-9a-f]{3,8}$/i', trim($var['value'])) ? trim($var['value']) : '#000000') ?>"
                                   data-syncs="var_<?= $safeId ?>"
                                   aria-label="Colour picker for <?= e($var['name']) ?>">
                            <input type="text"
                                   id="var_<?= $safeId ?>"
                                   name="vars[<?= e($var['name']) ?>]"
                                   value="<?= e($var['value']) ?>"
                                   data-var="<?= e($var['name']) ?>"
                                   class="theme-var-input">
                        </div>
                        <?php else: ?>
                        <input type="text"
                               id="var_<?= $safeId ?>"
                               name="vars[<?= e($var['name']) ?>]"
                               value="<?= e($var['value']) ?>"
                               data-var="<?= e($var['name']) ?>"
                               class="theme-var-input">
                        <?php endif; ?>
                    </div>
            <?php endforeach; ?>
            <?php if ($currentGroup !== null): ?>
                </div><!-- /.theme-group-body -->
            </div><!-- /.theme-group -->
            <?php endif; ?>
            </div><!-- /.theme-editor-groups -->

            <div class="theme-editor-actions">
                <button type="submit" class="btn btn-primary">Save Theme</button>
            </div>
        </form>
    </div><!-- /.theme-editor-panel -->

    <!-- ── Live preview ────────────────────────────────────────── -->
    <div class="theme-editor-preview" aria-label="Live preview">
        <style id="theme-preview-vars"><?php
            foreach ($vars as $v) { echo e($v['name']) . ':' . e($v['value']) . ';'; }
        ?></style>
        <div class="theme-preview-canvas">

            <section class="theme-preview-section">
                <h3 class="theme-preview-section-label">Colours</h3>
                <div class="theme-preview-swatches">
                    <?php
                    $swatches = [
                        '--color-primary'    => 'Primary',
                        '--color-primary-dark'  => 'Primary Dark',
                        '--color-primary-light' => 'Primary Light',
                        '--color-secondary'  => 'Secondary',
                        '--color-accent'     => 'Accent',
                        '--color-danger'     => 'Danger',
                        '--color-success'    => 'Success',
                        '--color-warning'    => 'Warning',
                        '--color-text'       => 'Text',
                        '--color-text-light' => 'Text Light',
                        '--color-bg'         => 'Background',
                        '--color-bg-light'   => 'BG Light',
                        '--color-bg-dark'    => 'BG Dark',
                        '--color-border'     => 'Border',
                    ];
                    foreach ($swatches as $prop => $label):
                    ?>
                    <div class="theme-swatch">
                        <div class="theme-swatch-colour" style="background:var(<?= e($prop) ?>);border:1px solid var(--color-border)"></div>
                        <span class="theme-swatch-label"><?= e($label) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="theme-preview-section">
                <h3 class="theme-preview-section-label">Typography</h3>
                <div class="theme-preview-typography">
                    <h1 style="font-family:var(--font-heading);color:var(--color-text)">Heading 1 — The quick brown fox</h1>
                    <h2 style="font-family:var(--font-heading);color:var(--color-text)">Heading 2 — The quick brown fox</h2>
                    <h3 style="font-family:var(--font-heading);color:var(--color-text)">Heading 3 — The quick brown fox</h3>
                    <p style="font-family:var(--font-body);color:var(--color-text)">Body text — Lorem ipsum dolor sit amet, consectetur adipiscing elit. Praesent at <a href="#" style="color:var(--color-primary)">a link in body copy</a> and the sentence continues.</p>
                    <p style="font-family:var(--font-body);color:var(--color-text-light);font-size:0.9em">Secondary / light text — Supporting copy in a lighter tone sits below the primary paragraph, used for captions and metadata.</p>
                    <code style="font-family:var(--font-mono);font-size:0.85em">monospace — const theme = 'default';</code>
                </div>
            </section>

            <section class="theme-preview-section">
                <h3 class="theme-preview-section-label">Buttons &amp; Actions</h3>
                <div class="theme-preview-buttons">
                    <button class="btn btn-primary" type="button">Primary Button</button>
                    <button class="btn btn-secondary" type="button">Secondary Button</button>
                    <button class="btn btn-danger" type="button">Danger</button>
                    <button class="btn" style="background:var(--color-success);color:#fff;border:none;padding:.5rem 1rem;border-radius:4px;cursor:pointer" type="button">Success</button>
                </div>
            </section>

            <section class="theme-preview-section">
                <h3 class="theme-preview-section-label">Cards</h3>
                <div class="theme-preview-cards">
                    <div style="border:var(--card-border);border-radius:var(--card-radius);box-shadow:var(--card-shadow);padding:var(--space-lg);background:var(--color-bg);max-width:300px">
                        <h4 style="font-family:var(--font-heading);color:var(--color-text);margin:0 0 var(--space-sm)">Card Title</h4>
                        <p style="font-family:var(--font-body);color:var(--color-text-light);margin:0 0 var(--space-md);font-size:0.9em">Card body copy showing how content looks inside a bordered, shadowed card element.</p>
                        <a href="#" style="color:var(--color-primary);font-family:var(--font-body);font-size:0.9em">Read more →</a>
                    </div>
                </div>
            </section>

            <section class="theme-preview-section">
                <h3 class="theme-preview-section-label">Spacing &amp; Layout</h3>
                <div class="theme-preview-spacing">
                    <?php foreach (['--space-xs','--space-sm','--space-md','--space-lg','--space-xl','--space-2xl'] as $sp): ?>
                    <div class="theme-spacing-row">
                        <span class="theme-spacing-name"><?= e($sp) ?></span>
                        <div class="theme-spacing-bar" style="width:var(<?= e($sp) ?>);height:1rem;background:var(--color-primary);border-radius:2px;display:inline-block"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>

        </div><!-- /.theme-preview-canvas -->
    </div><!-- /.theme-editor-preview -->

</div><!-- /.theme-editor-wrap -->

<style>
/* ── Layout ───────────────────────────────── */
.theme-editor-wrap { display: grid; grid-template-columns: 340px 1fr; gap: 0; height: calc(100vh - 56px); overflow: hidden; margin: -1.5rem; }

/* ── Controls panel ───────────────────────── */
.theme-editor-panel { display: flex; flex-direction: column; border-right: 1px solid var(--color-border, #e7e7e7); overflow: hidden; }
.theme-editor-panel-header { padding: 0.75rem 1rem; background: var(--color-bg-dark, #e7e7e7); border-bottom: 1px solid var(--color-border, #e7e7e7); flex-shrink: 0; }
.theme-editor-panel-title { font-weight: 700; font-size: 0.95rem; display: block; }
.theme-editor-panel-file { font-family: var(--font-mono, monospace); font-size: 0.75rem; color: var(--color-text-light, #777); display: block; margin-top: 0.1rem; }
.theme-editor-groups { flex: 1; overflow-y: auto; padding: 0.75rem; }
.theme-editor-actions { padding: 0.75rem 1rem; border-top: 1px solid var(--color-border, #e7e7e7); flex-shrink: 0; }
.theme-group { margin-bottom: 0.75rem; border: 1px solid var(--color-border, #e7e7e7); border-radius: 6px; overflow: hidden; }
.theme-group-title { background: var(--color-bg-light, #f1f1f1); padding: 0.4rem 0.75rem; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-text-light, #777); }
.theme-group-body { padding: 0.5rem 0.75rem; display: grid; gap: 0.45rem; }
.theme-var-row { display: grid; grid-template-columns: 140px 1fr; align-items: center; gap: 0.5rem; }
.theme-var-label { font-family: var(--font-mono, monospace); font-size: 0.75rem; color: var(--color-text, #333); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.theme-var-input { width: 100%; font-family: var(--font-mono, monospace); font-size: 0.8rem; padding: 0.25rem 0.4rem; border: 1px solid var(--color-border, #e7e7e7); border-radius: 4px; }
.theme-colour-pair { display: flex; align-items: center; gap: 0.4rem; }
.theme-colour-pair input[type="color"] { width: 2.2rem; height: 1.8rem; border: 1px solid var(--color-border, #e7e7e7); border-radius: 4px; padding: 0.1rem; cursor: pointer; flex-shrink: 0; }
.theme-colour-pair .theme-var-input { flex: 1; }

/* ── Preview pane ─────────────────────────── */
.theme-editor-preview { overflow-y: auto; background: var(--color-bg-light, #f1f1f1); }
.theme-preview-canvas { padding: 2rem; }
.theme-preview-section { background: var(--color-bg, #fff); border: 1px solid var(--color-border, #e7e7e7); border-radius: 8px; padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; }
.theme-preview-section-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-text-light, #777); font-weight: 600; margin: 0 0 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--color-border, #e7e7e7); }
.theme-preview-swatches { display: flex; flex-wrap: wrap; gap: 0.75rem; }
.theme-swatch { display: flex; flex-direction: column; align-items: center; gap: 0.3rem; }
.theme-swatch-colour { width: 3rem; height: 3rem; border-radius: 6px; }
.theme-swatch-label { font-size: 0.7rem; color: var(--color-text-light, #777); text-align: center; }
.theme-preview-typography h1 { font-size: 1.8rem; margin: 0 0 0.5rem; }
.theme-preview-typography h2 { font-size: 1.4rem; margin: 0 0 0.5rem; }
.theme-preview-typography h3 { font-size: 1.1rem; margin: 0 0 0.5rem; }
.theme-preview-typography p { margin: 0.5rem 0; line-height: 1.6; }
.theme-preview-typography code { display: block; margin-top: 0.75rem; padding: 0.5rem 0.75rem; background: var(--color-bg-light, #f1f1f1); border-radius: 4px; }
.theme-preview-buttons { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
.theme-preview-cards { display: flex; gap: 1rem; flex-wrap: wrap; }
.theme-spacing-row { display: flex; align-items: center; gap: 1rem; margin-bottom: 0.4rem; }
.theme-spacing-name { font-family: var(--font-mono, monospace); font-size: 0.8rem; color: var(--color-text-light, #777); width: 100px; flex-shrink: 0; }
</style>

<script>
(function () {
    var styleEl = document.getElementById('theme-preview-vars');

    function rebuildVars() {
        var inputs = document.querySelectorAll('[data-var]');
        var css = ':root{';
        inputs.forEach(function (inp) {
            css += inp.dataset.var + ':' + inp.value + ';';
        });
        css += '}';
        styleEl.textContent = css;
    }

    // Text inputs update preview on input
    document.querySelectorAll('.theme-var-input').forEach(function (inp) {
        inp.addEventListener('input', rebuildVars);
    });

    // Colour pickers sync their paired text input then update preview
    document.querySelectorAll('input[type="color"][data-syncs]').forEach(function (picker) {
        picker.addEventListener('input', function () {
            var target = document.getElementById(picker.dataset.syncs);
            if (target) { target.value = picker.value; }
            rebuildVars();
        });
    });
}());
</script>

<?php endif; ?>
