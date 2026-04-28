<?php
/**
 * Three-panel mailbox layout: folder sidebar | message list | (message opens in same panel or detail page)
 *
 * @var array  $mailbox
 * @var string $folder
 * @var array  $folders
 * @var array  $messages
 * @var int    $page
 * @var int    $total
 * @var int    $per_page
 */
$baseUrl = '/mail/' . (int) $mailbox['id'];
$pages   = (int) ceil($total / $per_page);
?>
<div class="mailbox-shell">

    <!-- Sidebar: account + folder tree -->
    <nav class="mailbox-sidebar">
        <div class="mailbox-account-header">
            <span class="mailbox-account-name"><?= e($mailbox['position']) ?></span>
            <span class="mailbox-account-email"><?= e($mailbox['email'] ?? '') ?></span>
        </div>

        <a class="mailbox-compose-btn" href="<?= $baseUrl ?>/compose">+ Compose</a>

        <ul class="mailbox-folder-list">
            <?php foreach ($folders as $f): ?>
                <li class="mailbox-folder-item <?= $f === $folder ? 'active' : '' ?>">
                    <a href="<?= $baseUrl ?>/<?= urlencode($f) ?>">
                        <?= e($f) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="mailbox-sidebar-footer">
            <a href="/mail">← All Mailboxes</a>
        </div>
    </nav>

    <!-- Message list panel -->
    <main class="mailbox-message-list">
        <div class="mailbox-toolbar">
            <form class="mailbox-search-form" method="get" action="<?= $baseUrl ?>/search">
                <input type="hidden" name="folder" value="<?= e($folder) ?>">
                <input class="mailbox-search-input" type="search" name="q" placeholder="Search…">
                <button type="submit">Search</button>
            </form>
        </div>

        <div class="mailbox-folder-title"><?= e($folder) ?></div>

        <?php if (empty($messages)): ?>
            <p class="mailbox-empty">No messages in this folder.</p>
        <?php else: ?>
            <table class="mailbox-table">
                <thead>
                    <tr>
                        <th class="col-state"></th>
                        <th class="col-from">From</th>
                        <th class="col-subject">Subject</th>
                        <th class="col-date">Date</th>
                        <th class="col-attach"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $msg): ?>
                        <?php
                            $state   = $msg['read_state'] ?? 'unread';
                            $msgUrl  = $baseUrl . '/' . urlencode($folder) . '/' . (int) $msg['imap_uid'];
                        ?>
                        <tr class="mailbox-row state-<?= e($state) ?>">
                            <td class="col-state">
                                <?php if ($state === 'unread'): ?>
                                    <span class="badge badge-unread" title="Unread">●</span>
                                <?php elseif ($state === 'partial'): ?>
                                    <span class="badge badge-partial" title="Read by some">◑</span>
                                <?php else: ?>
                                    <span class="badge badge-read" title="Read by all">○</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-from">
                                <a href="<?= $msgUrl ?>"><?= e($msg['from_name'] ?: $msg['from_address']) ?></a>
                            </td>
                            <td class="col-subject">
                                <a href="<?= $msgUrl ?>"><?= e($msg['subject'] ?? '(no subject)') ?></a>
                            </td>
                            <td class="col-date"><?= e($msg['sent_at'] ?? '') ?></td>
                            <td class="col-attach"><?= $msg['has_attachments'] ? '📎' : '' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($pages > 1): ?>
                <div class="mailbox-pagination">
                    <?php for ($p = 1; $p <= $pages; $p++): ?>
                        <a class="page-link <?= $p === $page ? 'active' : '' ?>"
                           href="<?= $baseUrl ?>/<?= urlencode($folder) ?>?page=<?= $p ?>"><?= $p ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

</div>
