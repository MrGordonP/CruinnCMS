<?php
/**
 * Admin — Mailbox settings (three-panel layout).
 *
 * Left:   list of all mailbox accounts
 * Middle: credentials form for the selected mailbox (fetched fragment)
 * Right:  access grants for the selected mailbox (fetched fragment)
 *
 * @var array  $mailboxes
 * @var string $csrf_token
 */
$GLOBALS['admin_flush_layout'] = true;
\Cruinn\Template::requireCss('admin-panel-layout.css');

$selectedId = (int) ($_GET['selected'] ?? 0);
?>

<div class="panel-layout" id="mailbox-panel">

    <!-- ── Left: mailbox list ─────────────────────────────────── -->
    <div class="pl-sidebar">
        <div class="pl-sidebar-header">
            <h3>Mailboxes</h3>
            <a href="/admin/mailbox/tags" class="btn btn-xs btn-secondary" title="Manage Tags">Tags</a>
        </div>
        <div class="pl-sidebar-scroll" id="mb-list">
            <?php if (empty($mailboxes)): ?>
            <p style="padding:0.75rem 0.9rem;font-size:0.82rem;color:#888">No mailboxes configured yet.</p>
            <?php else: ?>
            <?php foreach ($mailboxes as $mb): ?>
            <div class="pl-nav-item<?= (int)$mb['id'] === $selectedId ? ' active' : '' ?>"
                 data-id="<?= (int)$mb['id'] ?>"
                 onclick="selectMailbox(<?= (int)$mb['id'] ?>)">
                <span style="font-size:0.7rem;opacity:.5"><?= $mb['enabled'] ? '●' : '○' ?></span>
                <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($mb['label']) ?></span>
                <span class="pl-nav-count" style="font-size:0.75rem;opacity:.5"><?= (int)$mb['indexed_count'] ?></span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="pl-sidebar-footer">
            <button type="button" class="btn btn-sm btn-primary" style="width:100%"
                    onclick="selectNew()">+ Add Mailbox</button>
        </div>
    </div>

    <!-- ── Middle: credentials form ──────────────────────────── -->
    <div class="pl-main" id="mb-credentials-panel">
        <div class="pl-empty">
            <div class="pl-empty-icon">✉️</div>
            <p>Select a mailbox to view credentials,<br>or add a new one.</p>
        </div>
    </div>

    <!-- ── Right: access grants ──────────────────────────────── -->
    <div class="pl-detail" id="mb-access-panel">
        <div class="pl-detail-placeholder">
            <div class="pl-detail-placeholder-icon">🔑</div>
            <p>Select a mailbox to manage access.</p>
        </div>
    </div>

</div>

<script>
(function () {
    const selectedId = <?= (int)$selectedId ?>;

    function selectMailbox(id) {
        // Sidebar highlight
        document.querySelectorAll('#mb-list .pl-nav-item').forEach(el => {
            el.classList.toggle('active', parseInt(el.dataset.id) === id);
        });

        // Fetch credentials into middle panel
        const credPanel = document.getElementById('mb-credentials-panel');
        credPanel.innerHTML = '<div style="padding:1.5rem;color:#aaa;font-size:0.85rem">Loading…</div>';
        fetch('/admin/mailbox/' + id + '/credentials-panel')
            .then(r => r.text())
            .then(html => { credPanel.innerHTML = html; })
            .catch(() => { credPanel.innerHTML = '<p style="padding:1rem;color:#c00">Failed to load.</p>'; });

        // Fetch access list into right panel
        const accessPanel = document.getElementById('mb-access-panel');
        accessPanel.innerHTML = '<div style="padding:1.5rem;color:#aaa;font-size:0.85rem">Loading…</div>';
        fetch('/admin/mailbox/' + id + '/access')
            .then(r => r.text())
            .then(html => { accessPanel.innerHTML = html; })
            .catch(() => { accessPanel.innerHTML = '<p style="padding:1rem;color:#c00">Failed to load.</p>'; });
    }

    function selectNew() {
        document.querySelectorAll('#mb-list .pl-nav-item').forEach(el => el.classList.remove('active'));

        const credPanel = document.getElementById('mb-credentials-panel');
        credPanel.innerHTML = '<div style="padding:1.5rem;color:#aaa;font-size:0.85rem">Loading…</div>';
        fetch('/admin/mailbox/new')
            .then(r => r.text())
            .then(html => { credPanel.innerHTML = html; })
            .catch(() => { credPanel.innerHTML = '<p style="padding:1rem;color:#c00">Failed to load.</p>'; });

        document.getElementById('mb-access-panel').innerHTML =
            '<div class="pl-detail-placeholder"><div class="pl-detail-placeholder-icon">🔑</div><p>Save the mailbox first,<br>then configure access.</p></div>';
    }

    // Expose for onclick attributes on dynamically loaded fragments
    window.selectMailbox = selectMailbox;
    window.selectNew     = selectNew;

    // Auto-select on page load (either ?selected=N or first item)
    if (selectedId) {
        selectMailbox(selectedId);
    } else {
        const first = document.querySelector('#mb-list .pl-nav-item');
        if (first) selectMailbox(parseInt(first.dataset.id));
    }
}());
</script>

$mailboxesJson = json_encode(array_values($mailboxes), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
?>

<div class="panel-layout" id="mailbox-panel">

    <!-- ── Left: mailbox list ─────────────────────────────────── -->
    <div class="pl-sidebar">
        <div class="pl-sidebar-header">
            <h3>Mailboxes</h3>
            <a href="/admin/mailbox/tags" class="btn btn-xs btn-secondary" title="Manage Tags">Tags</a>
        </div>
        <div class="pl-sidebar-scroll" id="mb-list">
            <?php if (empty($mailboxes)): ?>
            <p style="padding:0.75rem 0.9rem;font-size:0.82rem;color:#888">No officer positions found.</p>
            <?php else: ?>
            <?php foreach ($mailboxes as $i => $mb): ?>
            <div class="pl-nav-item <?= $i === 0 ? 'active' : '' ?>"
                 data-mb-index="<?= $i ?>"
                 onclick="selectMailbox(<?= $i ?>)">
                <span><?= $mb['imap_enabled'] ? '✅' : '○' ?></span>
                <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($mb['position']) ?></span>
                <span class="pl-nav-count"><?= (int)$mb['indexed_count'] ?></span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="pl-sidebar-footer">
            <a href="/admin/organisation/officers" class="btn btn-sm btn-secondary" style="width:100%;text-align:center">
                Manage Officers
            </a>
        </div>
    </div>

    <!-- ── Middle: connection details ────────────────────────── -->
    <div class="pl-main">

