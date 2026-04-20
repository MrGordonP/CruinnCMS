<?php
/**
 * Single message view.
 *
 * HTML body is rendered in a sandboxed iframe to prevent CSS/JS bleed.
 * Plain text is shown as fallback.
 *
 * @var array  $mailbox
 * @var string $folder
 * @var int    $uid
 * @var array  $body        ['headers'=>object, 'text_body'=>string, 'html_body'=>string, 'attachments'=>array]
 * @var array  $tags        Tags already applied to this message
 * @var array  $all_tags    All available tags
 * @var array  $folders     All folders for move-to selector
 * @var string $csrf_token
 */
$h       = $body['headers'];
$baseUrl = '/mail/' . (int) $mailbox['id'];
$msgBase = $baseUrl . '/' . urlencode($folder) . '/' . $uid;
?>
<div class="mailbox-shell">

    <!-- Sidebar -->
    <nav class="mailbox-sidebar">
        <div class="mailbox-account-header">
            <span class="mailbox-account-name"><?= e($mailbox['position']) ?></span>
            <span class="mailbox-account-email"><?= e($mailbox['email'] ?? '') ?></span>
        </div>
        <a class="mailbox-compose-btn" href="<?= $baseUrl ?>/compose">+ Compose</a>
        <ul class="mailbox-folder-list">
            <?php foreach ($folders as $f): ?>
                <li class="mailbox-folder-item <?= $f === $folder ? 'active' : '' ?>">
                    <a href="<?= $baseUrl ?>/<?= urlencode($f) ?>"><?= e($f) ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="mailbox-sidebar-footer"><a href="<?= $baseUrl ?>/<?= urlencode($folder) ?>">← Back to <?= e($folder) ?></a></div>
    </nav>

    <!-- Message detail -->
    <main class="mailbox-message-detail">

        <div class="message-header">
            <h2 class="message-subject"><?= e($h->subject ?? '(no subject)') ?></h2>
            <div class="message-meta">
                <span class="meta-from"><strong>From:</strong> <?= e($h->fromaddress ?? '') ?></span>
                <span class="meta-to"><strong>To:</strong> <?= e($h->toaddress ?? '') ?></span>
                <?php if (!empty($h->ccaddress)): ?>
                    <span class="meta-cc"><strong>Cc:</strong> <?= e($h->ccaddress) ?></span>
                <?php endif; ?>
                <span class="meta-date"><strong>Date:</strong> <?= e($h->date ?? '') ?></span>
            </div>

            <div class="message-actions">
                <a class="btn btn-sm" href="<?= $msgBase ?>/reply">Reply</a>
                <a class="btn btn-sm" href="<?= $msgBase ?>/forward">Forward</a>
                <form method="post" action="<?= $msgBase ?>/delete" class="inline-form"
                      onsubmit="return confirm('Move to Trash?')">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                    <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                </form>

                <!-- Move to folder -->
                <form method="post" action="<?= $msgBase ?>/move" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                    <select name="folder" onchange="this.form.submit()">
                        <option value="">Move to…</option>
                        <?php foreach ($folders as $f): ?>
                            <?php if ($f !== $folder): ?>
                                <option value="<?= e($f) ?>"><?= e($f) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </form>

                <!-- Mark unread -->
                <button class="btn btn-sm js-mark-unread"
                        data-url="<?= $msgBase ?>/unread"
                        data-csrf="<?= e($csrf_token) ?>">Mark unread</button>
            </div>

            <!-- Tags -->
            <?php if (!empty($all_tags)): ?>
                <div class="message-tags">
                    <?php foreach ($all_tags as $tag): ?>
                        <?php $applied = in_array($tag['id'], array_column($tags, 'id'), false); ?>
                        <button class="tag-toggle <?= $applied ? 'applied' : '' ?>"
                                style="--tag-colour: <?= e($tag['colour']) ?>"
                                data-tag-id="<?= (int) $tag['id'] ?>"
                                data-applied="<?= $applied ? '1' : '0' ?>"
                                data-add-url="<?= $msgBase ?>/tag"
                                data-remove-url="<?= $msgBase ?>/untag"
                                data-csrf="<?= e($csrf_token) ?>">
                            <?= e($tag['label']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Body -->
        <div class="message-body">
            <?php if (!empty($body['html_body'])): ?>
                <!-- Sandboxed iframe prevents injected scripts/styles from leaking -->
                <iframe class="message-html-frame"
                        sandbox="allow-same-origin"
                        srcdoc="<?= htmlspecialchars($body['html_body'], ENT_QUOTES | ENT_HTML5) ?>"
                        title="Message body"></iframe>
            <?php else: ?>
                <pre class="message-text"><?= e($body['text_body'] ?? '') ?></pre>
            <?php endif; ?>
        </div>

        <!-- Attachments -->
        <?php if (!empty($body['attachments'])): ?>
            <div class="message-attachments">
                <h3>Attachments</h3>
                <ul>
                    <?php foreach ($body['attachments'] as $att): ?>
                        <li>
                            📎 <?= e($att['filename'] ?: 'attachment') ?>
                            <span class="attach-size">(<?= number_format($att['size'] / 1024, 1) ?> KB)</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

    </main>
</div>

<script>
// Tag toggle
document.querySelectorAll('.tag-toggle').forEach(btn => {
    btn.addEventListener('click', async () => {
        const applied = btn.dataset.applied === '1';
        const url     = applied ? btn.dataset.removeUrl : btn.dataset.addUrl;
        const body    = new URLSearchParams({ csrf_token: btn.dataset.csrf, tag_id: btn.dataset.tagId });
        const res     = await fetch(url, { method: 'POST', body });
        if (res.ok) {
            btn.dataset.applied = applied ? '0' : '1';
            btn.classList.toggle('applied', !applied);
        }
    });
});

// Mark unread
document.querySelector('.js-mark-unread')?.addEventListener('click', async function() {
    const body = new URLSearchParams({ csrf_token: this.dataset.csrf });
    const res  = await fetch(this.dataset.url, { method: 'POST', body });
    if (res.ok) window.history.back();
});
</script>
