<?php
/**
 * Admin — Mailbox overview (three-panel layout).
 *
 * Left:   list of all officer positions
 * Middle: IMAP / SMTP connection details for selected mailbox
 * Right:  officer identity and status
 *
 * @var array  $mailboxes
 * @var string $csrf_token
 */
\Cruinn\Template::requireCss('admin-panel-layout.css');

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
        <div class="pl-main-toolbar">
            <span class="pl-main-title" id="mb-title">Select a mailbox</span>
            <div class="pl-main-toolbar-actions">
                <a href="#" id="mb-credentials-btn" class="btn btn-sm btn-secondary" style="display:none">✏️ Credentials</a>
                <button type="button" id="mb-sync-btn" class="btn btn-sm btn-primary" style="display:none"
                        onclick="syncMailbox()">⟳ Sync Now</button>
            </div>
        </div>

        <div style="padding:1.25rem;overflow-y:auto;flex:1" id="mb-connection">
            <p style="color:#aaa;font-size:0.85rem">Select a mailbox from the left panel.</p>
        </div>
    </div>

    <!-- ── Right: officer details ────────────────────────────── -->
    <div class="pl-detail" style="background:#fff;border-left:1px solid var(--color-border,#ccd9d3);overflow-y:auto;padding:1rem">
        <div id="mb-officer">
            <p style="color:#aaa;font-size:0.85rem">No mailbox selected.</p>
        </div>
    </div>

</div>

<script>
const MAILBOXES  = <?= $mailboxesJson ?>;
const CSRF_TOKEN = <?= json_encode($csrf_token) ?>;
let   activeMb   = null;

function selectMailbox(idx) {
    const mb = MAILBOXES[idx];
    if (!mb) return;
    activeMb = mb;

    // Sidebar highlight
    document.querySelectorAll('#mb-list .pl-nav-item').forEach((el, i) => {
        el.classList.toggle('active', i === idx);
    });

    // Toolbar
    document.getElementById('mb-title').textContent = mb.position;
    const credBtn = document.getElementById('mb-credentials-btn');
    credBtn.href  = '/admin/mailbox/officer/' + mb.id + '/credentials';
    credBtn.style.display = '';
    const syncBtn = document.getElementById('mb-sync-btn');
    syncBtn.style.display = mb.imap_enabled ? '' : 'none';

    // Middle — connection details
    const enc = v => v || '<span style="color:#aaa">—</span>';
    document.getElementById('mb-connection').innerHTML = `
        <h4 style="margin:0 0 0.75rem;font-size:0.85rem;text-transform:uppercase;letter-spacing:.06em;opacity:.5">IMAP (Incoming)</h4>
        <table style="width:100%;border-collapse:collapse;margin-bottom:1.5rem;font-size:0.85rem">
            <tr><th style="text-align:left;padding:0.3rem 0.5rem 0.3rem 0;opacity:.6;white-space:nowrap">Host</th>
                <td style="padding:0.3rem 0">${enc(mb.imap_host)}</td></tr>
            <tr><th style="text-align:left;padding:0.3rem 0.5rem 0.3rem 0;opacity:.6;white-space:nowrap">Port</th>
                <td style="padding:0.3rem 0">${mb.imap_port || '—'}</td></tr>
            <tr><th style="text-align:left;padding:0.3rem 0.5rem 0.3rem 0;opacity:.6;white-space:nowrap">Encryption</th>
                <td style="padding:0.3rem 0">${enc(mb.imap_encryption)}</td></tr>
            <tr><th style="text-align:left;padding:0.3rem 0.5rem 0.3rem 0;opacity:.6;white-space:nowrap">Username</th>
                <td style="padding:0.3rem 0">${enc(mb.imap_user)}</td></tr>
            <tr><th style="text-align:left;padding:0.3rem 0.5rem 0.3rem 0;opacity:.6;white-space:nowrap">Password</th>
                <td style="padding:0.3rem 0;color:#aaa">••••••••</td></tr>
            <tr><th style="text-align:left;padding:0.3rem 0.5rem 0.3rem 0;opacity:.6;white-space:nowrap">Messages</th>
                <td style="padding:0.3rem 0">${mb.indexed_count}</td></tr>
        </table>
        <h4 style="margin:0 0 0.75rem;font-size:0.85rem;text-transform:uppercase;letter-spacing:.06em;opacity:.5">SMTP (Outgoing)</h4>
        <table style="width:100%;border-collapse:collapse;font-size:0.85rem">
            <tr><th style="text-align:left;padding:0.3rem 0.5rem 0.3rem 0;opacity:.6;white-space:nowrap">Host</th>
                <td style="padding:0.3rem 0">${enc(mb.smtp_host)}</td></tr>
            <tr><th style="text-align:left;padding:0.3rem 0.5rem 0.3rem 0;opacity:.6;white-space:nowrap">Port</th>
                <td style="padding:0.3rem 0">${mb.smtp_port || '—'}</td></tr>
            <tr><th style="text-align:left;padding:0.3rem 0.5rem 0.3rem 0;opacity:.6;white-space:nowrap">Encryption</th>
                <td style="padding:0.3rem 0">${enc(mb.smtp_encryption)}</td></tr>
            <tr><th style="text-align:left;padding:0.3rem 0.5rem 0.3rem 0;opacity:.6;white-space:nowrap">Username</th>
                <td style="padding:0.3rem 0">${enc(mb.smtp_user)}</td></tr>
            <tr><th style="text-align:left;padding:0.3rem 0.5rem 0.3rem 0;opacity:.6;white-space:nowrap">Password</th>
                <td style="padding:0.3rem 0;color:#aaa">••••••••</td></tr>
        </table>`;

    // Right — officer identity
    document.getElementById('mb-officer').innerHTML = `
        <h4 style="margin:0 0 1rem;font-size:0.85rem;text-transform:uppercase;letter-spacing:.06em;opacity:.5">Officer</h4>
        <p style="margin:0 0 0.5rem;font-weight:700;font-size:1rem">${escHtml(mb.position)}</p>
        ${mb.email ? `<p style="margin:0 0 0.4rem;font-size:0.84rem">📧 ${escHtml(mb.email)}</p>` : ''}
        ${mb.user_display_name
            ? `<p style="margin:0 0 0.4rem;font-size:0.84rem">👤 ${escHtml(mb.user_display_name)}</p>`
            : '<p style="margin:0 0 0.4rem;font-size:0.84rem;color:#aaa">No linked user account</p>'}
        <p style="margin:0.75rem 0 0.4rem;font-size:0.84rem">
            <span style="display:inline-block;padding:0.15rem 0.5rem;border-radius:3px;font-size:0.75rem;background:${mb.imap_enabled ? '#d4edda' : '#f2f2f2'};color:${mb.imap_enabled ? '#155724' : '#666'}">
                ${mb.imap_enabled ? 'Mailbox enabled' : 'Mailbox disabled'}
            </span>
        </p>
        <p style="margin:0.4rem 0;font-size:0.84rem">
            <span style="display:inline-block;padding:0.15rem 0.5rem;border-radius:3px;font-size:0.75rem;background:${mb.active ? '#d4edda' : '#f2f2f2'};color:${mb.active ? '#155724' : '#666'}">
                ${mb.active ? 'Position active' : 'Position inactive'}
            </span>
        </p>
        <div style="margin-top:1.25rem;display:flex;flex-direction:column;gap:0.5rem">
            <a href="/admin/mailbox/officer/${mb.id}/credentials" class="btn btn-sm btn-primary">✏️ Edit Credentials</a>
            <a href="/admin/organisation/officers" class="btn btn-sm btn-secondary">Officers List</a>
        </div>`;
}

function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

async function syncMailbox() {
    if (!activeMb) return;
    const btn = document.getElementById('mb-sync-btn');
    btn.disabled    = true;
    btn.textContent = '⟳ Syncing…';
    try {
        const body = new URLSearchParams({ csrf_token: CSRF_TOKEN });
        const res  = await fetch('/admin/mailbox/' + activeMb.id + '/sync', { method: 'POST', body });
        const json = await res.json();
        btn.textContent = res.ok ? ('✓ Done (' + json.new_messages + ' new)') : '✗ Error';
    } catch (e) {
        btn.textContent = '✗ Failed';
    }
    btn.disabled = false;
    setTimeout(() => { btn.textContent = '⟳ Sync Now'; }, 3000);
}

// Select first mailbox on load
if (MAILBOXES.length > 0) selectMailbox(0);
</script>
