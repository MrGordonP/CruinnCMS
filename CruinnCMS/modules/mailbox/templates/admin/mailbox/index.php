<?php
/**
 * Admin — Mailbox overview.
 *
 * @var array $mailboxes
 */
?>
<div class="acp-section">
    <div class="acp-header">
        <h1>Mailbox</h1>
        <a class="btn btn-primary" href="/mail">Open Mailbox</a>
        <a class="btn" href="/admin/mailbox/tags">Manage Tags</a>
    </div>

    <table class="acp-table">
        <thead>
            <tr>
                <th>Position</th>
                <th>Email</th>
                <th>IMAP host</th>
                <th>Indexed messages</th>
                <th>Enabled</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($mailboxes as $mb): ?>
                <tr>
                    <td><?= $this->escape($mb['position']) ?></td>
                    <td><?= $this->escape($mb['email'] ?? '—') ?></td>
                    <td><?= $this->escape($mb['imap_host'] ?? '—') ?></td>
                    <td><?= (int) $mb['indexed_count'] ?></td>
                    <td><?= $mb['imap_enabled'] ? '✅' : '—' ?></td>
                    <td>
                        <a class="btn btn-sm" href="/admin/organisation/officers">Edit credentials</a>
                        <?php if ($mb['imap_enabled']): ?>
                            <button class="btn btn-sm js-sync-btn"
                                    data-url="/admin/mailbox/<?= (int) $mb['id'] ?>/sync"
                                    data-csrf="<?= $this->escape($csrf_token ?? '') ?>">Sync now</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p class="acp-note">IMAP credentials are configured in <a href="/admin/organisation/officers">Organisation → Officers</a>.</p>
</div>

<script>
document.querySelectorAll('.js-sync-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        btn.disabled = true;
        btn.textContent = 'Syncing…';
        const body = new URLSearchParams({ csrf_token: btn.dataset.csrf });
        const res  = await fetch(btn.dataset.url, { method: 'POST', body });
        const json = await res.json();
        btn.textContent = res.ok ? ('Done (' + json.new_messages + ' new)') : 'Error';
        btn.disabled = false;
    });
});
</script>
