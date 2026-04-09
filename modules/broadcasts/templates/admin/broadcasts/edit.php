<div class="admin-page broadcasts-edit-page">
    <div class="admin-page-header">
        <h1><?= $broadcast ? 'Edit Broadcast' : 'New Broadcast' ?></h1>
        <a href="<?= url('/admin/broadcasts') ?>" class="btn btn-outline">← Back</a>
    </div>

    <form method="post" action="<?= url($broadcast ? '/admin/broadcasts/' . $broadcast['id'] : '/admin/broadcasts') ?>">
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
                        <span class="text-muted" style="font-size:0.875rem;">(IGA membership list — no portal account needed)</span>
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
        function igaBcTarget(type) {
            ['members', 'list', 'portal_users'].forEach(function(t) {
                document.getElementById('panel-' + t).style.display = (t === type) ? 'block' : 'none';
            });
        }
        </script>

        <div class="form-group">
            <label for="subject">Subject *</label>
            <input type="text" id="subject" name="subject" class="form-input" required
                   value="<?= e($broadcast['subject'] ?? '') ?>"
                   placeholder="Your email subject line">
        </div>

        <div class="form-group">
            <label for="body_html">HTML Body</label>
            <p class="form-help">Use <code>{{name}}</code> and <code>{{email}}</code> for personalisation. An unsubscribe footer is appended automatically.</p>
            <textarea id="body_html" name="body_html" rows="16" class="form-input form-textarea monospace"><?= e($broadcast['body_html'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="body_text">Plain Text Body <span class="text-muted">(optional — auto-generated if blank)</span></label>
            <textarea id="body_text" name="body_text" rows="8" class="form-input form-textarea monospace"><?= e($broadcast['body_text'] ?? '') ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Draft</button>
            <a href="<?= url('/admin/broadcasts') ?>" class="btn btn-outline">Cancel</a>
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
                <p>This broadcast will be sent to all <strong><?= e($statuses) ?></strong> members<?= e($yearNote) ?> with an email address.
                   Addresses in the unsubscribe list are automatically excluded.</p>
            <?php elseif (($broadcast['target_type'] ?? '') === 'portal_users'): ?>
                <p>This broadcast will be sent to all <strong><?= number_format($portal_user_count) ?></strong> active portal users with an email address.</p>
            <?php else: ?>
                <p>This broadcast will be sent to all active subscribers of the selected mailing list.</p>
            <?php endif; ?>
            <p class="text-muted" style="font-size:0.875rem;">
                Emails are dispatched by the queue processor. Use the
                <a href="<?= url('/admin/settings/database') ?>">Database page</a>
                to run it manually if cron is not yet configured.
            </p>
            <form method="post" action="<?= url('/admin/broadcasts/' . $broadcast['id'] . '/queue') ?>"
                  onsubmit="return confirm('Queue this broadcast for sending?')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-success">Queue for Sending</button>
            </form>
        </div>
    <?php endif; ?>
</div>
