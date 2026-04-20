<?php /** @var array $mailboxes */ ?>
<div class="mailbox-index">
    <h1 class="page-title">Mailbox</h1>

    <?php if (empty($mailboxes)): ?>
        <p class="empty-state">No mailboxes are available for your account. Contact an administrator to be assigned to an organisation position with a mailbox configured.</p>
    <?php else: ?>
        <div class="mailbox-list">
            <?php foreach ($mailboxes as $mb): ?>
                <a class="mailbox-card" href="/mail/<?= (int) $mb['id'] ?>/INBOX">
                    <span class="mailbox-icon">✉️</span>
                    <span class="mailbox-position"><?= $this->escape($mb['position']) ?></span>
                    <span class="mailbox-address"><?= $this->escape($mb['email'] ?? '') ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
