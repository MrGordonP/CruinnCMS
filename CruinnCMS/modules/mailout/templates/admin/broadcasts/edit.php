<div class="admin-page broadcasts-edit-page">
    <div class="admin-page-header">
        <h1><?= $broadcast ? 'Edit Mailout' : 'New Mailout' ?></h1>
        <a href="<?= url('/admin/mailout') ?>" class="btn btn-outline">← Back</a>
    </div>

    <form method="post" action="<?= url($broadcast ? '/admin/mailout/' . $broadcast['id'] : '/admin/mailout') ?>">
        <?= csrf_field() ?>

        <!-- ── Audience ─────────────────────────────────────── -->
        <fieldset class="acp-fieldset" style="margin-bottom:1.25rem;">
            <legend>Audience</legend>
            <?php
                $tConfig          = ($broadcast && !empty($broadcast['target_config']))
                                    ? (json_decode($broadcast['target_config'], true) ?: [])
                                    : [];
                $selectedStatuses = $tConfig['member_status'] ?? ['active', 'honorary'];
                $selectedYear     = $tConfig['membership_year'] ?? '';
                $currentTarget    = $broadcast['target_type'] ?? 'members';
            ?>

            <div class="form-group" style="margin-bottom:0.75rem;">
                <label>Send to</label>
                <div style="display:flex; flex-direction:column; gap:0.6rem; margin-top:0.25rem;">

                    <label style="display:inline-flex; align-items:center; gap:0.5rem; cursor:pointer;">
                        <input type="radio" name="target_type" value="members"
                               <?= $currentTarget === 'members' ? 'checked' : '' ?>
                               onchange="igaBcTarget('members')">
                        <strong>Members</strong>
                        <span class="text-muted" style="font-size:0.875rem;">(Cruinn membership list - no portal account needed)</span>
                    </label>

                    <label style="display:inline-flex; align-items:center; gap:0.5rem; cursor:pointer;">
                        <input type="radio" name="target_type" value="list"
                               <?= $currentTarget === 'list' ? 'checked' : '' ?>
                               onchange="igaBcTarget('list')">
                        <strong>Mailing List Subscribers</strong>
                        <span class="text-muted" style="font-size:0.875rem;">(opted-in portal subscribers only)</span>
                    </label>

                    <label style="display:inline-flex; align-items:center; gap:0.5rem; cursor:pointer;">
                        <input type="radio" name="target_type" value="portal_users"
                               <?= $currentTarget === 'portal_users' ? 'checked' : '' ?>
                               onchange="igaBcTarget('portal_users')">
                        <strong>Portal Users</strong>
                        <span class="text-muted" style="font-size:0.875rem;">
                            (<?= number_format($portal_user_count) ?> active account<?= $portal_user_count !== 1 ? 's' : '' ?> with email)
                        </span>
                    </label>

                </div>
            </div>

            <!-- Members panel -->
            <div id="panel-members" class="target-panel" style="display:<?= $currentTarget === 'members' ? 'block' : 'none' ?>; padding:0.75rem; background:var(--bg-subtle,#f8f9fa); border-radius:4px;">
                <div class="form-group" style="margin-bottom:0.75rem;">
                    <label style="font-weight:600; display:block; margin-bottom:0.35rem;">Filter by status</label>
                    <div style="display:flex; flex-wrap:wrap; gap:0.75rem;">
                        <?php foreach ($member_status_counts as $status => $count): ?>
                        <label style="display:inline-flex; align-items:center; gap:0.35rem; cursor:pointer;">
                            <input type="checkbox" name="member_status[]" value="<?= e($status) ?>"
                                   <?= in_array($status, $selectedStatuses, true) ? 'checked' : '' ?>>
                            <?= ucfirst(e($status)) ?>
                            <span class="text-muted" style="font-size:0.8rem;">(<?= number_format($count) ?>)</span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label for="membership_year" style="font-weight:600;">Membership year
                        <span class="text-muted" style="font-weight:normal;"> — optional filter</span>
                    </label>
                    <select name="membership_year" id="membership_year" class="form-input" style="max-width:12rem; margin-top:0.25rem;">
                        <option value="">— Any year —</option>
                        <?php foreach ($year_options as $y): ?>
                            <option value="<?= $y ?>" <?= (string)$y === (string)$selectedYear ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Mailing list panel -->
            <div id="panel-list" class="target-panel" style="display:<?= $currentTarget === 'list' ? 'block' : 'none' ?>; padding:0.75rem; background:var(--bg-subtle,#f8f9fa); border-radius:4px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label for="list_id">Mailing List</label>
                    <select name="list_id" id="list_id" class="form-input" style="margin-top:0.25rem;">
                        <option value="">— Select a list —</option>
                        <?php foreach ($lists as $list): ?>
                            <option value="<?= (int)$list['id'] ?>"
                                    <?= (string)($broadcast['list_id'] ?? '') === (string)$list['id'] ? ' selected' : '' ?>>
                                <?= e($list['name']) ?>
                                (<?= number_format((int)$list['subscriber_count']) ?> subscriber<?= $list['subscriber_count'] !== 1 ? 's' : '' ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p id="mailout-list-helper" class="text-muted" style="font-size:0.875rem; margin:0.45rem 0 0;">
                        Choose the specific mailing list for this send after selecting "Mailing List Subscribers" above.
                    </p>
                </div>
            </div>

            <!-- Portal users panel -->
            <div id="panel-portal_users" class="target-panel" style="display:<?= $currentTarget === 'portal_users' ? 'block' : 'none' ?>; padding:0.75rem; background:var(--bg-subtle,#f8f9fa); border-radius:4px;">
                <p class="text-muted" style="margin:0;">
                    Will send to all <strong><?= number_format($portal_user_count) ?></strong> active portal users
                    with an email address. Addresses in the unsubscribe list are automatically excluded.
                </p>
            </div>

        </fieldset>

        <script>
        function igaBcSyncListHelper(type) {
            var helper = document.getElementById('mailout-list-helper');
            if (!helper) return;
            helper.style.fontWeight = (type === 'list') ? '600' : '400';
            helper.style.color = (type === 'list') ? 'var(--color-text,#222)' : '';
        }

        function igaBcTarget(type) {
            ['members', 'list', 'portal_users'].forEach(function(t) {
                document.getElementById('panel-' + t).style.display = (t === type) ? 'block' : 'none';
            });
            igaBcSyncListHelper(type);
        }

        (function initMailoutAudienceHelper() {
            igaBcSyncListHelper('<?= e($currentTarget) ?>');
            var listSelect = document.getElementById('list_id');
            var helper = document.getElementById('mailout-list-helper');
            if (!listSelect || !helper) return;

            function syncSelectionHint() {
                if (listSelect.value) {
                    helper.textContent = 'Selected list will receive this mailout when queued or sent.';
                } else {
                    helper.textContent = 'Choose the specific mailing list for this send after selecting "Mailing List Subscribers" above.';
                }
            }

            listSelect.addEventListener('change', syncSelectionHint);
            syncSelectionHint();
        })();
        </script>

        <details class="acp-fieldset" style="margin-bottom:1.25rem;">
            <summary style="cursor:pointer; font-weight:600; padding:0.5rem 0;">Import from Blog Post</summary>
            <div style="padding:0.75rem 0 0.25rem;">
                <div style="display:flex; gap:0.75rem; align-items:flex-end;">
                    <div style="flex:1;">
                        <label for="import_article_id" style="font-size:0.875rem;">Select article</label>
                        <select id="import_article_id" class="form-input" style="margin-top:0.25rem;">
                            <option value="">— Choose a published article —</option>
                            <?php foreach ($articles as $a): ?>
                                <option value="<?= (int)$a['id'] ?>"><?= e($a['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" class="btn btn-outline" onclick="importArticle()">Import</button>
                </div>
                <p class="form-help" style="margin-top:0.5rem;">Copies the article title into Subject and its HTML content into the HTML Body. You can edit both after importing.</p>
            </div>
        </details>

        <details class="acp-fieldset" style="margin-bottom:1.25rem;">
            <summary style="cursor:pointer; font-weight:600; padding:0.5rem 0;">Import from Previous Mailout</summary>
            <div style="padding:0.75rem 0 0.25rem;">
                <div style="display:flex; gap:0.75rem; align-items:flex-end;">
                    <div style="flex:1;">
                        <label for="import_broadcast_id" style="font-size:0.875rem;">Select mailout</label>
                        <select id="import_broadcast_id" class="form-input" style="margin-top:0.25rem;">
                            <option value="">— Choose a previous mailout —</option>
                            <?php foreach ($previous_broadcasts as $pb): ?>
                                <option value="<?= (int)$pb['id'] ?>">
                                    <?= e($pb['subject']) ?> — <?= e(date('d M Y', strtotime($pb['created_at']))) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" class="btn btn-outline" onclick="importBroadcast()">Import</button>
                </div>
                <p class="form-help" style="margin-top:0.5rem;">Copies the subject and content from a previous mailout. You can edit everything after importing.</p>
            </div>
        </details>

        <div class="form-group">
            <label for="subject">Subject *</label>
            <input type="text" id="subject" name="subject" class="form-input" required
                   value="<?= e($broadcast['subject'] ?? '') ?>"
                   placeholder="Your email subject line">
            <?php if (!empty($subject_options ?? [])): ?>
            <div style="display:flex; gap:0.5rem; align-items:flex-end; margin-top:0.5rem; flex-wrap:wrap;">
                <div style="min-width:18rem; flex:1;">
                    <label for="subject_pick" style="font-size:0.85rem;">Pick from Subjects</label>
                    <select id="subject_pick" class="form-input" style="margin-top:0.2rem;">
                        <option value="">- Choose a subject title -</option>
                        <?php foreach (($subject_options ?? []) as $so): ?>
                            <option value="<?= e($so['title']) ?>"><?= e($so['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="button" class="btn btn-outline" onclick="applyPickedSubject()">Use Subject</button>
            </div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:0.25rem;">
                <label for="body_html" style="margin-bottom:0;">HTML Body</label>
                <button type="button" class="btn btn-small btn-outline" onclick="previewHtml()">Preview</button>
            </div>
            <p class="form-help">Use <code>{{name}}</code> and <code>{{email}}</code> for personalisation. An unsubscribe footer is appended automatically.</p>
            <textarea id="body_html" name="body_html" rows="16" class="form-input form-textarea monospace"><?= e($broadcast['body_html'] ?? '') ?></textarea>
        </div>

        <dialog id="html-preview-dialog" style="width:min(860px,95vw); max-height:90vh; padding:0; border:1px solid #ccc; border-radius:6px; overflow:hidden;">
            <div style="display:flex; justify-content:space-between; align-items:center; padding:0.75rem 1rem; border-bottom:1px solid #ddd; background:#f8f9fa;">
                <strong>Email Preview</strong>
                <button type="button" class="btn btn-small btn-outline" onclick="document.getElementById('html-preview-dialog').close()">Close</button>
            </div>
            <iframe id="html-preview-frame" style="width:100%; height:70vh; border:none; display:block;"></iframe>
        </dialog>

        <script>
        function previewHtml() {
            var html = document.getElementById('body_html').value;
            var frame = document.getElementById('html-preview-frame');
            var dialog = document.getElementById('html-preview-dialog');
            frame.srcdoc = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>body{font-family:sans-serif;padding:1rem 1.5rem;margin:0;line-height:1.6;}img{max-width:100%;}</style></head><body>' + html + '</body></html>';
            dialog.showModal();
        }
        </script>

        <script>
        function applyPickedSubject() {
            var pick = document.getElementById('subject_pick');
            var subject = document.getElementById('subject');
            if (!pick || !subject || !pick.value) return;
            subject.value = pick.value;
            subject.focus();
        }

        function importArticle() {
            const articleId = document.getElementById('import_article_id').value;
            if (!articleId) { alert('Please select an article first.'); return; }
            fetch('/admin/mailout/article-import?article_id=' + articleId)
                .then(r => r.json())
                .then(data => {
                    if (data.error) { alert('Error: ' + data.error); return; }
                    document.getElementById('subject').value = data.title;
                    document.getElementById('body_html').value = data.html;
                })
                .catch(() => alert('Import failed. Please try again.'));
        }

        function importBroadcast() {
            const broadcastId = document.getElementById('import_broadcast_id').value;
            if (!broadcastId) { alert('Please select a mailout first.'); return; }
            fetch('/admin/mailout/broadcast-import?broadcast_id=' + broadcastId)
                .then(r => r.json())
                .then(data => {
                    if (data.error) { alert('Error: ' + data.error); return; }
                    document.getElementById('subject').value = data.subject;
                    document.getElementById('body_html').value = data.body_html;
                    document.getElementById('body_text').value = data.body_text || '';
                })
                .catch(() => alert('Import failed. Please try again.'));
        }
        </script>

        <div class="form-group">
            <label for="body_text">Plain Text Body <span class="text-muted">(optional — auto-generated if blank)</span></label>
            <textarea id="body_text" name="body_text" rows="8" class="form-input form-textarea monospace"><?= e($broadcast['body_text'] ?? '') ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Draft</button>
            <a href="<?= url('/admin/mailout') ?>" class="btn btn-outline">Cancel</a>
            <span class="text-muted" style="font-size:0.8rem; margin-left:0.5rem;">Save before sending &mdash; audience and content must be saved first.</span>
        </div>
    </form>

    <?php if ($broadcast && $broadcast['status'] === 'draft'): ?>
        <hr class="section-divider">
        <div class="broadcast-queue-section">
            <h2>Queue &amp; Send</h2>
            <?php
            $qConfig = ($broadcast && !empty($broadcast['target_config']))
                ? (json_decode($broadcast['target_config'], true) ?: [])
                : [];
            if (($broadcast['target_type'] ?? '') === 'members'):
                $statuses = implode(', ', array_map('ucfirst', $qConfig['member_status'] ?? ['Active', 'Honorary']));
                $yearNote = !empty($qConfig['membership_year']) ? " from {$qConfig['membership_year']}" : '';
            ?>
                <p>This mailout will be sent to all <strong><?= e($statuses) ?></strong> members<?= e($yearNote) ?> with an email address.
                   Addresses in the unsubscribe list are automatically excluded.</p>
            <?php elseif (($broadcast['target_type'] ?? '') === 'portal_users'): ?>
                <p>This mailout will be sent to all <strong><?= number_format($portal_user_count) ?></strong> active portal users with an email address.</p>
            <?php else: ?>
                <p>This mailout will be sent to all active subscribers of the selected mailing list.</p>
            <?php endif; ?>
            <p class="text-muted" style="font-size:0.875rem;">
                Addresses in the unsubscribe list are automatically excluded.
            </p>
            <div style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end; margin-top:0.75rem;">
                <form method="post" action="<?= url('/admin/mailout/' . $broadcast['id'] . '/send-now') ?>"
                      onsubmit="return confirm('Send this mailout now to all recipients?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-success">Send Now</button>
                </form>
                <form method="post" action="<?= url('/admin/mailout/' . $broadcast['id'] . '/queue') ?>"
                      onsubmit="return confirm('Queue this mailout for sending?')"
                      style="display:flex; gap:0.5rem; align-items:flex-end; flex-wrap:wrap;">
                    <?= csrf_field() ?>
                    <div>
                        <label for="scheduled_at" style="font-size:0.8rem; display:block; margin-bottom:0.2rem;">Schedule for (optional)</label>
                        <input type="datetime-local" id="scheduled_at" name="scheduled_at"
                               style="padding:0.35rem 0.5rem; border:1px solid var(--color-border,#ccc); border-radius:4px;">
                    </div>
                    <button type="submit" class="btn btn-outline">Queue for Sending</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>
