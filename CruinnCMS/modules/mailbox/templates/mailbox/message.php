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
\Cruinn\Template::requireCss('admin-panel-layout.css');
$h       = $body['headers'];
$baseUrl = '/mail/' . (int) $mailbox['id'];
$msgBase = $baseUrl . '/' . urlencode($folder) . '/' . $uid;
?>
<style>
.mb-compose { display: block; margin: 0.6rem 0.75rem 0.4rem; padding: 0.4rem 0.75rem; background: var(--color-primary, #1d9e75); color: #fff; border-radius: 4px; font-size: 0.83rem; font-weight: 600; text-align: center; text-decoration: none; }
.mb-compose:hover { background: var(--color-primary-dark, #166b52); color: #fff; text-decoration: none; }
.mb-account { padding: 0.65rem 0.9rem 0.5rem; border-bottom: 1px solid var(--color-border, #ccd9d3); }
.mb-account-name  { display: block; font-size: 0.84rem; font-weight: 700; color: var(--color-text, #0c1614); }
.mb-account-email { display: block; font-size: 0.75rem; color: #888; }
.mb-shell { display: flex; flex-direction: column; height: calc(100vh - 44px); overflow: hidden; }
.msg-header { padding: 0.9rem 1.25rem 0.75rem; border-bottom: 1px solid var(--color-border, #ccd9d3); background: #fff; flex-shrink: 0; }
.msg-subject { margin: 0 0 0.5rem; font-size: 1.1rem; font-weight: 700; }
.msg-meta { display: flex; flex-wrap: wrap; gap: 0.4rem 1.25rem; font-size: 0.82rem; color: #555; margin-bottom: 0.65rem; }
.msg-actions { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
.msg-actions form { display: contents; }
.msg-actions select { padding: 0.25rem 0.5rem; font-size: 0.82rem; border: 1px solid var(--color-border, #ccd9d3); border-radius: 4px; }
.msg-body { flex: 1; min-height: 0; overflow-y: auto; padding: 1rem 1.25rem; background: #fff; }
.message-html-frame { width: 100%; min-height: 400px; border: none; display: block; }
.message-text { white-space: pre-wrap; font-size: 0.88rem; font-family: monospace; }
.msg-attachments { padding: 0.75rem 1.25rem; border-top: 1px solid var(--color-border, #ccd9d3); background: var(--color-bg-light, #f2f5f3); font-size: 0.85rem; flex-shrink: 0; }
.msg-attachments h3 { margin: 0 0 0.4rem; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #888; }
.msg-attachments ul { margin: 0; padding: 0; list-style: none; display: flex; flex-wrap: wrap; gap: 0.4rem; }
.msg-attachments li { background: #fff; border: 1px solid var(--color-border, #ccd9d3); border-radius: 4px; padding: 0.25rem 0.6rem; font-size: 0.82rem; }
.attach-size { color: #aaa; margin-left: 0.3rem; }
.msg-tags { padding: 0.5rem 1.25rem; border-top: 1px solid var(--color-border, #ccd9d3); background: #fff; flex-shrink: 0; display: flex; flex-wrap: wrap; gap: 0.35rem; align-items: center; }
.tag-toggle { padding: 0.2rem 0.6rem; border-radius: 12px; border: 1px solid var(--tag-colour, #aaa); background: #fff; color: var(--tag-colour, #555); font-size: 0.78rem; cursor: pointer; transition: background 0.1s, color 0.1s; }
.tag-toggle.applied { background: var(--tag-colour, #aaa); color: #fff; }
</style>

<div class="sb-wrapper">
<div class="acp-panel">
<div class="panel-layout no-detail mb-shell">

    <!-- Folder sidebar -->
    <div class="pl-sidebar">
        <div class="mb-account">
            <span class="mb-account-name"><?= e($mailbox['position']) ?></span>
            <span class="mb-account-email"><?= e($mailbox['email'] ?? '') ?></span>
        </div>
        <a class="mb-compose" href="<?= $baseUrl ?>/compose">+ Compose</a>
        <div class="pl-sidebar-scroll">
            <?php foreach ($folders as $f): ?>
                <a class="pl-nav-item <?= $f === $folder ? 'active' : '' ?>"
                   href="<?= $baseUrl ?>/<?= urlencode($f) ?>"><?= e($f) ?></a>
            <?php endforeach; ?>
        </div>
        <div class="pl-sidebar-footer">
            <a href="<?= $baseUrl ?>/<?= urlencode($folder) ?>" style="font-size:0.8rem;color:#888;text-decoration:none;">← Back to <?= e($folder) ?></a>
        </div>
    </div>

    <!-- Message detail -->
    <div class="pl-main">

        <div class="msg-header">
            <h2 class="msg-subject"><?= e($h->subject ?? '(no subject)') ?></h2>
            <div class="msg-meta">
                <span><strong>From:</strong> <?= e($h->fromaddress ?? '') ?></span>
                <span><strong>To:</strong> <?= e($h->toaddress ?? '') ?></span>
                <?php if (!empty($h->ccaddress)): ?>
                    <span><strong>Cc:</strong> <?= e($h->ccaddress) ?></span>
                <?php endif; ?>
                <span><strong>Date:</strong> <?= e($h->date ?? '') ?></span>
            </div>

            <div class="msg-actions">
                <a class="btn btn-small btn-primary" href="<?= $msgBase ?>/reply">Reply</a>
                <a class="btn btn-small btn-outline" href="<?= $msgBase ?>/forward">Forward</a>
                <form method="post" action="<?= $msgBase ?>/delete"
                      onsubmit="return confirm('Move to Trash?')">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                    <button class="btn btn-small btn-danger" type="submit">Delete</button>
                </form>

                <!-- Move to folder -->
                <form method="post" action="<?= $msgBase ?>/move">
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
                <button class="btn btn-small btn-outline js-mark-unread"
                        data-url="<?= $msgBase ?>/unread"
                        data-csrf="<?= e($csrf_token) ?>">Mark unread</button>
            </div>

            <!-- Tags -->
            <?php if (!empty($all_tags)): ?>
                <div class="msg-tags">
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
        </div><!-- .msg-header -->

        <!-- Body -->
        <div class="msg-body">
            <?php if (!empty($body['html_body'])): ?>
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
            <div class="msg-attachments">
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

    </div><!-- .pl-main -->

</div><!-- .panel-layout -->
</div><!-- .acp-panel -->
</div><!-- .sb-wrapper -->

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
