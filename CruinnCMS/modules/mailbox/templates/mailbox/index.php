<?php
/** @var array $mailboxes */
\Cruinn\Template::requireCss('admin-panel-layout.css');
?>
<div style="max-width:600px;margin:2.5rem auto;padding:0 1rem;">
    <div class="pl-sidebar-header" style="padding:0.9rem 1rem;background:#fff;border:1px solid var(--color-border,#ccd9d3);border-bottom:none;border-radius:6px 6px 0 0;">
        <h3 style="font-size:1rem;opacity:1;text-transform:none;letter-spacing:normal">Mailbox</h3>
    </div>

    <?php if (empty($mailboxes)): ?>
        <div class="pl-empty" style="background:#fff;border:1px solid var(--color-border,#ccd9d3);border-radius:0 0 6px 6px">
            No mailboxes are available for your account. Contact an administrator to be assigned to an organisation position with a mailbox configured.
        </div>
    <?php else: ?>
        <table class="pl-table" style="background:#fff;border:1px solid var(--color-border,#ccd9d3);border-radius:0 0 6px 6px;overflow:hidden">
            <thead>
                <tr>
                    <th style="width:28px"></th>
                    <th>Position</th>
                    <th>Address</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mailboxes as $mb): ?>
                    <tr onclick="location.href='/mail/<?= (int) $mb['id'] ?>/INBOX'" style="cursor:pointer">
                        <td style="text-align:center;font-size:1rem">✉️</td>
                        <td style="font-weight:600"><?= e($mb['position']) ?></td>
                        <td style="color:#888;font-size:0.85rem"><?= e($mb['email'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
