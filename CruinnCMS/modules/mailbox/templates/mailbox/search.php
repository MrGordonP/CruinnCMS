<?php
/**
 * Search results.
 *
 * @var array  $mailbox
 * @var string $query
 * @var string $folder
 * @var array  $results
 */
$baseUrl = '/mail/' . (int) $mailbox['id'];
?>
<div class="mailbox-shell">
    <nav class="mailbox-sidebar">
        <div class="mailbox-account-header">
            <span class="mailbox-account-name"><?= e($mailbox['position']) ?></span>
            <span class="mailbox-account-email"><?= e($mailbox['email'] ?? '') ?></span>
        </div>
        <a class="mailbox-compose-btn" href="<?= $baseUrl ?>/compose">+ Compose</a>
        <div class="mailbox-sidebar-footer"><a href="<?= $baseUrl ?>/INBOX">← Inbox</a></div>
    </nav>

    <main class="mailbox-message-list">
        <div class="mailbox-folder-title">Search results for "<?= e($query) ?>" in <?= e($folder) ?></div>

        <form class="mailbox-search-form" method="get" action="<?= $baseUrl ?>/search">
            <input type="hidden" name="folder" value="<?= e($folder) ?>">
            <input class="mailbox-search-input" type="search" name="q"
                   value="<?= e($query) ?>" placeholder="Search…">
            <button type="submit">Search</button>
        </form>

        <?php if (empty($results)): ?>
            <p class="mailbox-empty">No messages matched "<?= e($query) ?>".</p>
        <?php else: ?>
            <table class="mailbox-table">
                <thead>
                    <tr>
                        <th class="col-from">From</th>
                        <th class="col-subject">Subject</th>
                        <th class="col-date">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $msg): ?>
                        <tr>
                            <td><?= e($msg['from_name'] ?: $msg['from_address']) ?></td>
                            <td>
                                <a href="<?= $baseUrl ?>/<?= urlencode($folder) ?>/<?= (int) $msg['imap_uid'] ?>">
                                    <?= e($msg['subject'] ?? '(no subject)') ?>
                                </a>
                            </td>
                            <td><?= e($msg['sent_at'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
</div>
