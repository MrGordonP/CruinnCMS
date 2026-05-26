<?php
/** @var array $mailbox */
/** @var array $folders */

\Cruinn\Template::requireCss('admin-panel-layout.css');
$GLOBALS['admin_flush_layout'] = true;
$baseUrl = '/mail/' . (int) $mailbox['id'];
?>
<div style="max-width:720px;margin:2rem auto;padding:0 1rem;">
    <div class="pl-sidebar-header" style="padding:0.9rem 1rem;background:#fff;border:1px solid var(--color-border,#ccd9d3);border-bottom:none;border-radius:6px 6px 0 0;display:flex;justify-content:space-between;align-items:center;gap:1rem;">
        <div>
            <h3 style="font-size:1rem;opacity:1;text-transform:none;letter-spacing:normal;margin:0"><?= e($mailbox['position'] ?? 'Mailbox') ?></h3>
            <div style="font-size:0.8rem;color:#888"><?= e($mailbox['email'] ?? '') ?></div>
        </div>
        <a class="btn btn-small btn-primary" href="<?= $baseUrl ?>/INBOX">Open Inbox</a>
    </div>

    <div style="background:#fff;border:1px solid var(--color-border,#ccd9d3);border-radius:0 0 6px 6px;padding:0.75rem 0;">
        <?php if (empty($folders)): ?>
            <div class="pl-empty" style="margin:0 0.75rem;">No folders are available for this mailbox.</div>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:0.25rem;">
                <?php foreach ($folders as $folder): ?>
                    <a href="<?= $baseUrl ?>/<?= urlencode($folder) ?>" class="pl-nav-item" style="margin:0 0.75rem;display:flex;justify-content:space-between;align-items:center;">
                        <span><?= e($folder) ?></span>
                        <span style="color:#888">→</span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
