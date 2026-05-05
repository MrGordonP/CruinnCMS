<?php
/**
 * Theme Editor
 * Template variables: $theme, $vars (array of ['name','value','comment']), $filePath, $error
 */
$this->addCss('admin-site-builder.css');
?>
<div class="acp-page-header">
    <h1 class="acp-page-title">Theme Editor</h1>
    <p class="acp-page-subtitle">Editing: <code><?= e($filePath) ?></code></p>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
<div class="acp-alert acp-alert--success"><?= e($_SESSION['flash_success']) ?></div>
<?php unset($_SESSION['flash_success']); endif; ?>

<?php if (!empty($_SESSION['flash_error'])): ?>
<div class="acp-alert acp-alert--error"><?= e($_SESSION['flash_error']) ?></div>
<?php unset($_SESSION['flash_error']); endif; ?>

<?php if (!empty($error)): ?>
<div class="acp-alert acp-alert--error"><?= e($error) ?></div>
<?php else: ?>

<form method="post" action="<?= url('/admin/theme') ?>">
    <input type="hidden" name="csrf_token" value="<?= e(\Cruinn\CSRF::getToken()) ?>">

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
                <label class="theme-var-label" for="var_<?= e(ltrim($var['name'], '-')) ?>"><?= e($var['name']) ?></label>
                <?php
                // Detect colour values for a colour picker
                $isColour = preg_match('/^#[0-9a-f]{3,8}$/i', trim($var['value']))
                         || preg_match('/^rgba?\s*\(/', trim($var['value']));
                ?>
                <?php if ($isColour): ?>
                <div class="theme-colour-pair">
                    <input type="color"
                           value="<?= e(preg_match('/^#[0-9a-f]{3,8}$/i', trim($var['value'])) ? trim($var['value']) : '#000000') ?>"
                           oninput="document.getElementById('var_<?= e(ltrim($var['name'], '-')) ?>').value = this.value"
                           aria-label="Colour picker for <?= e($var['name']) ?>">
                    <input type="text"
                           id="var_<?= e(ltrim($var['name'], '-')) ?>"
                           name="vars[<?= e($var['name']) ?>]"
                           value="<?= e($var['value']) ?>"
                           class="theme-var-input">
                </div>
                <?php else: ?>
                <input type="text"
                       id="var_<?= e(ltrim($var['name'], '-')) ?>"
                       name="vars[<?= e($var['name']) ?>]"
                       value="<?= e($var['value']) ?>"
                       class="theme-var-input">
                <?php endif; ?>
            </div>
    <?php endforeach; ?>
    <?php if ($currentGroup !== null): ?>
        </div><!-- /.theme-group-body -->
    </div><!-- /.theme-group -->
    <?php endif; ?>

    <div class="acp-form-actions">
        <button type="submit" class="btn btn-primary">Save Theme</button>
    </div>
</form>

<style>
.theme-group { margin-bottom: 1.5rem; border: 1px solid var(--color-border, #e7e7e7); border-radius: 6px; overflow: hidden; }
.theme-group-title { background: var(--color-bg-light, #f1f1f1); padding: 0.5rem 1rem; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-text-light, #777); }
.theme-group-body { padding: 0.75rem 1rem; display: grid; gap: 0.6rem; }
.theme-var-row { display: grid; grid-template-columns: 220px 1fr; align-items: center; gap: 0.75rem; }
.theme-var-label { font-family: var(--font-mono, monospace); font-size: 0.82rem; color: var(--color-text, #333); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.theme-var-input { width: 100%; font-family: var(--font-mono, monospace); font-size: 0.85rem; padding: 0.3rem 0.5rem; border: 1px solid var(--color-border, #e7e7e7); border-radius: 4px; }
.theme-colour-pair { display: flex; align-items: center; gap: 0.5rem; }
.theme-colour-pair input[type="color"] { width: 2.4rem; height: 2rem; border: 1px solid var(--color-border, #e7e7e7); border-radius: 4px; padding: 0.1rem; cursor: pointer; flex-shrink: 0; }
.theme-colour-pair .theme-var-input { flex: 1; }
.acp-form-actions { margin-top: 1.5rem; }
</style>

<?php endif; ?>
