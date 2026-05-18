<?php
/**
 * Mailbox Notifications Widget Template
 *
 * Variables available:
 * - $mailboxes (array)   — List of accessible mailboxes with unread_count
 * - $total_unread (int)  — Total unread messages across all mailboxes
 */
?>
<div class="widget-mailbox-notifications">
    <h3 class="widget-title">
        📬 Mailbox Notifications
        <?php if ($total_unread > 0): ?>
            <span class="badge badge-primary"><?= (int) $total_unread ?></span>
        <?php endif; ?>
    </h3>

    <?php if (empty($mailboxes)): ?>
        <p class="widget-empty">No mailboxes accessible.</p>
    <?php else: ?>
        <ul class="widget-mailbox-list">
            <?php foreach ($mailboxes as $mailbox): ?>
                <li class="widget-mailbox-item">
                    <a href="/mail/<?= (int) $mailbox['id'] ?>" class="widget-mailbox-link">
                        <span class="mailbox-label"><?= htmlspecialchars($mailbox['label'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if ($mailbox['unread_count'] > 0): ?>
                            <span class="badge badge-unread"><?= (int) $mailbox['unread_count'] ?></span>
                        <?php endif; ?>
                    </a>
                    <?php if (!empty($mailbox['email'])): ?>
                        <small class="mailbox-email"><?= htmlspecialchars($mailbox['email'], ENT_QUOTES, 'UTF-8') ?></small>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="widget-footer">
            <a href="/mail" class="widget-link-all">View All Mailboxes &rarr;</a>
        </div>
    <?php endif; ?>
</div>

<style>
.widget-mailbox-notifications {
    background: var(--bg-surface, #fff);
    border: 1px solid var(--border-color, #ddd);
    border-radius: var(--radius-md, 8px);
    padding: var(--space-md, 1rem);
}

.widget-mailbox-notifications .widget-title {
    margin: 0 0 var(--space-md, 1rem);
    font-size: 1.125rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.widget-mailbox-notifications .badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: var(--radius-sm, 4px);
    background: var(--color-primary, #1d9e75);
    color: #fff;
}

.widget-mailbox-notifications .badge-unread {
    background: var(--color-accent, #ff5722);
}

.widget-mailbox-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.widget-mailbox-item {
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--border-color-subtle, #f0f0f0);
}

.widget-mailbox-item:last-child {
    border-bottom: none;
}

.widget-mailbox-link {
    display: flex;
    align-items: center;
    justify-content: space-between;
    text-decoration: none;
    color: var(--color-text, #333);
    font-weight: 500;
}

.widget-mailbox-link:hover {
    color: var(--color-primary, #1d9e75);
}

.mailbox-email {
    display: block;
    margin-top: 0.25rem;
    font-size: 0.875rem;
    color: var(--color-text-muted, #666);
}

.widget-footer {
    margin-top: var(--space-md, 1rem);
    padding-top: var(--space-sm, 0.5rem);
    border-top: 1px solid var(--border-color-subtle, #f0f0f0);
}

.widget-link-all {
    font-size: 0.875rem;
    color: var(--color-primary, #1d9e75);
    text-decoration: none;
    font-weight: 500;
}

.widget-link-all:hover {
    text-decoration: underline;
}

.widget-empty {
    color: var(--color-text-muted, #666);
    font-style: italic;
    margin: 0;
}
</style>
