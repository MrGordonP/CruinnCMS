<?php
/**
 * Message preview fragment — standalone (no layout).
 * Loaded into the pl-detail panel in messages.php via fetch().
 *
 * @var array  $mailbox
 * @var string $folder
 * @var int    $uid
 * @var array  $body      ['headers'=>object, 'text_body'=>string, 'html_body'=>string, 'attachments'=>array]
 * @var string $csrf_token
 * @var string $base_url
 */
$h       = $body['headers'];
$msgBase = $base_url . '/' . urlencode($folder) . '/' . $uid;
?>
<style>
.pvw-header    { padding: 0.85rem 1rem 0.7rem; border-bottom: 1px solid var(--color-border,#ccd9d3); background: var(--color-bg-light,#f2f5f3); flex-shrink: 0; }
.pvw-subject   { margin: 0 0 0.45rem; font-size: 0.95rem; font-weight: 700; line-height: 1.3; }
.pvw-meta      { font-size: 0.78rem; color: #777; display: flex; flex-direction: column; gap: 0.15rem; }
.pvw-actions   { display: flex; gap: 0.4rem; flex-wrap: wrap; padding: 0.55rem 1rem; border-bottom: 1px solid var(--color-border,#ccd9d3); background: #fff; flex-shrink: 0; }
.pvw-actions form { display: contents; }
.pvw-actions select { padding: 0.2rem 0.4rem; font-size: 0.78rem; border: 1px solid var(--color-border,#ccd9d3); border-radius: 4px; }
.pvw-body      { flex: 1; min-height: 0; overflow-y: auto; padding: 0.85rem 1rem; background: #fff; }
.pvw-frame     { width: 100%; min-height: 300px; border: none; display: block; }
.pvw-text      { white-space: pre-wrap; font-size: 0.84rem; font-family: monospace; margin: 0; }
.pvw-attach    { padding: 0.5rem 1rem; border-top: 1px solid var(--color-border,#ccd9d3); background: var(--color-bg-light,#f2f5f3); font-size: 0.8rem; flex-shrink: 0; display: flex; flex-wrap: wrap; gap: 0.35rem; align-items: center; }
.pvw-attach-label { font-weight: 600; color: #888; margin-right: 0.25rem; }
.pvw-chip      { background: #fff; border: 1px solid var(--color-border,#ccd9d3); border-radius: 4px; padding: 0.15rem 0.5rem; }
</style>

<div style="display:flex;flex-direction:column;height:100%;overflow:hidden;">

    <div class="pvw-header">
        <h3 class="pvw-subject"><?= e($h->subject ?? '(no subject)') ?></h3>
        <div class="pvw-meta">
            <span><strong>From:</strong> <?= e($h->fromaddress ?? '') ?></span>
            <span><strong>To:</strong> <?= e($h->toaddress ?? '') ?></span>
            <?php if (!empty($h->ccaddress)): ?>
                <span><strong>Cc:</strong> <?= e($h->ccaddress) ?></span>
            <?php endif; ?>
            <span><strong>Date:</strong> <?= e($h->date ?? '') ?></span>
        </div>
    </div>

    <div class="pvw-actions">
        <a class="btn btn-small btn-primary" href="<?= $msgBase ?>/reply">Reply</a>
        <a class="btn btn-small btn-outline" href="<?= $msgBase ?>/forward">Forward</a>
        <a class="btn btn-small" href="<?= $msgBase ?>">Open full view</a>
        <form method="post" action="<?= $msgBase ?>/delete"
              onsubmit="return confirm('Move to Trash?')">
            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
            <button class="btn btn-small btn-danger" type="submit">Delete</button>
        </form>
    </div>

    <div class="pvw-body">
        <?php if (!empty($body['html_body'])): ?>
            <iframe class="pvw-frame"
                    sandbox="allow-same-origin"
                    srcdoc="<?= htmlspecialchars($body['html_body'], ENT_QUOTES | ENT_HTML5) ?>"
                    title="Message body"
                    onload="this.style.minHeight = this.contentDocument.body.scrollHeight + 'px'"></iframe>
        <?php else: ?>
            <pre class="pvw-text"><?= e($body['text_body'] ?? '') ?></pre>
        <?php endif; ?>
    </div>

    <?php if (!empty($body['attachments'])): ?>
        <div class="pvw-attach">
            <span class="pvw-attach-label">📎 Attachments:</span>
            <?php foreach ($body['attachments'] as $att): ?>
                <span class="pvw-chip"><?= e($att['filename'] ?: 'attachment') ?> <span style="color:#aaa">(<?= number_format($att['size'] / 1024, 1) ?> KB)</span></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>
