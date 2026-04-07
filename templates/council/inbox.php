<div class="council-inbox">
    <div class="page-header">
        <h1>Council Inbox</h1>
        <?php if (!empty($roundcubeUrl)): ?>
            <a href="<?= e($roundcubeUrl) ?>" class="btn btn-primary" target="_blank" rel="noopener">
                Open in Roundcube ↗
            </a>
        <?php endif; ?>
    </div>

    <?php if ($error): ?>
    <div class="inbox-notice">
        <div class="notice-icon">📧</div>
        <h2>Mail Server Not Available</h2>
        <p><?= e($error) ?></p>
        <?php if (!empty($roundcubeUrl)): ?>
            <p>
                You can access the council email directly through Roundcube:
            </p>
            <a href="<?= e($roundcubeUrl) ?>" class="btn btn-primary btn-lg" target="_blank" rel="noopener">
                Open Roundcube Webmail ↗
            </a>
        <?php endif; ?>
        <div class="notice-details">
            <h3>Configuration</h3>
            <p>To enable the inbox viewer, configure the IMAP settings in <code>config.php</code>:</p>
            <ul>
                <li>IMAP host and port (default: localhost:993)</li>
                <li>Username (default: council@example.com)</li>
                <li>Password (must be set)</li>
                <li>PHP IMAP extension must be enabled</li>
            </ul>
        </div>
    </div>
    <?php elseif (empty($emails)): ?>
        <p class="empty-state">No emails in the inbox.</p>
    <?php else: ?>
        <table class="admin-table inbox-table">
            <thead>
                <tr>
                    <th class="inbox-status"></th>
                    <th>From</th>
                    <th>Subject</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($emails as $email): ?>
                <tr class="<?= $email['seen'] ? 'email-read' : 'email-unread' ?>">
                    <td class="inbox-status"><?= $email['seen'] ? '' : '●' ?></td>
                    <td><?= e($email['from']) ?></td>
                    <td><?= e($email['subject']) ?></td>
                    <td><time datetime="<?= e($email['date']) ?>"><?= e($email['date']) ?></time></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="text-muted">Showing the <?= count($emails) ?> most recent emails.
            <?php if (!empty($roundcubeUrl)): ?>
                <a href="<?= e($roundcubeUrl) ?>" target="_blank">Open Roundcube</a> for full access.
            <?php endif; ?>
        </p>
    <?php endif; ?>
</div>
