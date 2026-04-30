<?php
/**
 * Three-panel mailbox layout: folder sidebar | message list
 *
 * @var array  $mailbox
 * @var string $folder
 * @var array  $folders
 * @var array  $messages
 * @var int    $page
 * @var int    $total
 * @var int    $per_page
 * @var string|null $imap_error
 */
\Cruinn\Template::requireCss('admin-panel-layout.css');
$baseUrl = '/mail/' . (int) $mailbox['id'];
$pages   = (int) ceil($total / $per_page);
?>
<style>
/* Mailbox-specific additions — engine classes handle layout/table/nav */
.mb-shell { display: flex; flex-direction: column; height: calc(100vh - 44px); overflow: hidden; }
.mb-error  { background: #fef2f2; border-bottom: 1px solid #fca5a5; color: #991b1b; padding: 0.55rem 1rem; font-size: 0.85rem; flex-shrink: 0; }
.mb-compose { display: block; margin: 0.6rem 0.75rem 0.4rem; padding: 0.4rem 0.75rem; background: var(--color-primary, #1d9e75); color: #fff; border-radius: 4px; font-size: 0.83rem; font-weight: 600; text-align: center; text-decoration: none; }
.mb-compose:hover { background: var(--color-primary-dark, #166b52); color: #fff; text-decoration: none; }
.mb-account { padding: 0.65rem 0.9rem 0.5rem; border-bottom: 1px solid var(--color-border, #ccd9d3); }
.mb-account-name  { display: block; font-size: 0.84rem; font-weight: 700; color: var(--color-text, #0c1614); }
.mb-account-email { display: block; font-size: 0.75rem; color: #888; }
.pl-table tr.state-unread td { font-weight: 600; }
.dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; }
.dot-unread  { background: var(--color-primary, #1d9e75); }
.dot-partial { background: #f59e0b; }
.dot-read    { background: #d1d5db; }
.mb-pagination { display: flex; gap: 0.3rem; padding: 0.6rem 1rem; flex-shrink: 0; border-top: 1px solid var(--color-border, #ccd9d3); }
.mb-page-link { display: inline-block; padding: 0.2rem 0.55rem; font-size: 0.8rem; border: 1px solid var(--color-border, #ccd9d3); border-radius: 3px; color: var(--color-text, #0c1614); text-decoration: none; }
.mb-page-link.active, .mb-page-link:hover { background: var(--color-primary, #1d9e75); color: #fff; border-color: var(--color-primary, #1d9e75); }
</style>

<div class="sb-wrapper">
<div class="acp-panel">

<?php if ($imap_error ?? null): ?>
    <div class="mb-error"><strong>IMAP connection failed:</strong> <?= e($imap_error) ?></div>
<?php endif; ?>

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
            <a href="/mail" style="font-size:0.8rem;color:#888;text-decoration:none;">← All Mailboxes</a>
        </div>
    </div>

    <!-- Message list -->
    <div class="pl-main">
        <div class="pl-main-search">
            <form method="get" action="<?= $baseUrl ?>/search" style="display:flex;gap:0.5rem;flex:1">
                <input type="hidden" name="folder" value="<?= e($folder) ?>">
                <input class="pl-search-input" type="search" name="q" placeholder="Search messages…">
                <button class="btn btn-small btn-primary" type="submit">Search</button>
            </form>
        </div>
        <div class="pl-main-toolbar">
            <span class="pl-main-title"><?= e($folder) ?></span>
            <span style="font-size:0.8rem;color:#999"><?= $total ?> message<?= $total !== 1 ? 's' : '' ?></span>
        </div>

        <div style="flex:1;min-height:0;overflow-y:auto;">
            <?php if (empty($messages)): ?>
                <div class="pl-empty">No messages in this folder.</div>
            <?php else: ?>
                <table class="pl-table">
                    <thead>
                        <tr>
                            <th style="width:28px"></th>
                            <th>From</th>
                            <th>Subject</th>
                            <th style="width:130px">Date</th>
                            <th style="width:24px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($messages as $msg): ?>
                            <?php
                                $state  = $msg['read_state'] ?? 'unread';
                                $msgUrl = $baseUrl . '/' . urlencode($folder) . '/' . (int) $msg['imap_uid'];
                            ?>
                            <tr class="state-<?= e($state) ?>" onclick="location.href='<?= $msgUrl ?>'" style="cursor:pointer">
                                <td style="text-align:center">
                                    <?php if ($state === 'unread'): ?>
                                        <span class="dot dot-unread" title="Unread"></span>
                                    <?php elseif ($state === 'partial'): ?>
                                        <span class="dot dot-partial" title="Partially read"></span>
                                    <?php else: ?>
                                        <span class="dot dot-read" title="Read"></span>
                                    <?php endif; ?>
                                </td>
                                <td><a href="<?= $msgUrl ?>" style="text-decoration:none;color:inherit"><?= e($msg['from_name'] ?: $msg['from_address']) ?></a></td>
                                <td><a href="<?= $msgUrl ?>" style="text-decoration:none;color:inherit"><?= e($msg['subject'] ?? '(no subject)') ?></a></td>
                                <td style="white-space:nowrap;font-size:0.8rem;color:#888"><?= e($msg['sent_at'] ?? '') ?></td>
                                <td><?= $msg['has_attachments'] ? '📎' : '' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <?php if ($pages > 1): ?>
            <div class="mb-pagination">
                <?php for ($p = 1; $p <= $pages; $p++): ?>
                    <a class="mb-page-link <?= $p === $page ? 'active' : '' ?>"
                       href="<?= $baseUrl ?>/<?= urlencode($folder) ?>?page=<?= $p ?>"><?= $p ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>

</div><!-- .panel-layout -->
</div><!-- .acp-panel -->
</div><!-- .sb-wrapper -->
