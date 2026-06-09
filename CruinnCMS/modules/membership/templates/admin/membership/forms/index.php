<?php
\Cruinn\Template::requireCss('admin-acp.css');
\Cruinn\Template::requireCss('admin-panel-layout.css');
$GLOBALS['admin_flush_layout'] = true;

$forms = $forms ?? [];
$selectedForm = $selectedForm ?? null;
$selectedFormId = (int) ($selectedFormId ?? 0);
$submissions = $submissions ?? [];
$selectedSubmission = $selectedSubmission ?? null;
$selectedSubmissionId = (int) ($selectedSubmissionId ?? 0);
$statusFilter = (string) ($statusFilter ?? '');
$search = (string) ($search ?? '');
?>

<div class="panel-layout" id="membership-forms-layout">
    <div class="pl-panel pl-panel-left">
        <div class="pl-panel-header">
            <span class="pl-panel-title">Associated Forms</span>
        </div>
        <div class="pl-panel-body" style="padding:0;">
            <?php if (empty($forms)): ?>
            <div style="padding:0.75rem;color:#64748b;font-size:0.85rem;">No forms are associated with membership subjects yet.</div>
            <?php else: ?>
            <?php foreach ($forms as $form): ?>
            <a class="pl-nav-item<?= (int) $form['id'] === $selectedFormId ? ' active' : '' ?>" href="<?= url('/admin/membership/forms?form=' . (int) $form['id']) ?>">
                <span><?= e($form['title']) ?></span>
                <span class="pl-nav-count"><?= (int) ($form['submission_count'] ?? 0) ?></span>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="pl-main">
        <div class="pl-main-toolbar">
            <span class="pl-main-title"><?= $selectedForm ? e($selectedForm['title']) : 'Forms and Responses' ?></span>
            <div class="pl-main-toolbar-actions">
                <a href="<?= url('/admin/membership') ?>" class="btn btn-outline btn-small">Hub</a>
                <?php if ($selectedForm): ?>
                <a href="<?= url('/admin/forms/' . (int) $selectedForm['id'] . '/edit') ?>" class="btn btn-outline btn-small">Edit Form</a>
                <a href="<?= url('/admin/forms/' . (int) $selectedForm['id'] . '/submissions') ?>" class="btn btn-outline btn-small">Full Submissions</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="pl-main-search">
            <form method="get" action="<?= url('/admin/membership/forms') ?>" style="display:flex;gap:0.5rem;flex:1;">
                <input type="hidden" name="form" value="<?= (int) $selectedFormId ?>">
                <select class="form-input" name="status" style="max-width:140px;">
                    <option value="">All status</option>
                    <option value="pending"<?= $statusFilter === 'pending' ? ' selected' : '' ?>>Pending</option>
                    <option value="approved"<?= $statusFilter === 'approved' ? ' selected' : '' ?>>Approved</option>
                    <option value="rejected"<?= $statusFilter === 'rejected' ? ' selected' : '' ?>>Rejected</option>
                    <option value="processed"<?= $statusFilter === 'processed' ? ' selected' : '' ?>>Processed</option>
                </select>
                <input class="pl-search-input" type="text" name="search" value="<?= e($search) ?>" placeholder="Search response payload...">
                <button class="btn btn-small" type="submit">Filter</button>
            </form>
        </div>

        <div class="pl-main-scroll">
            <?php if (!$selectedForm): ?>
            <p class="pl-empty">Select a form to list responses.</p>
            <?php elseif (empty($submissions)): ?>
            <p class="pl-empty">No responses found for this form/filter.</p>
            <?php else: ?>
            <table class="pl-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Submitted</th>
                        <th>User</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($submissions as $submission): ?>
                    <?php
                        $userLabel = (string) ($submission['user_name'] ?? '');
                        if ($userLabel === '') {
                            $userLabel = (string) ($submission['user_email'] ?? 'Guest');
                        }
                    ?>
                    <tr<?= (int) $submission['id'] === $selectedSubmissionId ? ' class="selected"' : '' ?> onclick="window.location='<?= url('/admin/membership/forms?form=' . (int) $selectedFormId . '&submission=' . (int) $submission['id'] . ($statusFilter !== '' ? '&status=' . urlencode($statusFilter) : '') . ($search !== '' ? '&search=' . urlencode($search) : '')) ?>'">
                        <td><?= (int) $submission['id'] ?></td>
                        <td><?= e(format_date((string) $submission['submitted_at'], 'j M Y H:i')) ?></td>
                        <td><?= e($userLabel) ?></td>
                        <td><?= e(ucfirst((string) $submission['status'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="pl-panel pl-panel-right">
        <div class="pl-panel-header">
            <span class="pl-panel-title">Response Detail</span>
        </div>
        <div class="pl-panel-body" style="padding:0.75rem;">
            <?php if (!$selectedSubmission): ?>
            <p class="text-muted" style="font-size:0.85rem;">Select a response to inspect details.</p>
            <?php else: ?>
            <table class="pl-meta">
                <tr><th>ID</th><td>#<?= (int) $selectedSubmission['id'] ?></td></tr>
                <tr><th>Submitted</th><td><?= e(format_date((string) $selectedSubmission['submitted_at'], 'j M Y H:i')) ?></td></tr>
                <tr><th>Status</th><td><?= e(ucfirst((string) $selectedSubmission['status'])) ?></td></tr>
                <tr><th>User</th><td><?= e((string) ($selectedSubmission['user_name'] ?? $selectedSubmission['user_email'] ?? 'Guest')) ?></td></tr>
            </table>

            <div class="pl-detail-section-title">Submission Data</div>
            <?php if (empty($selectedSubmission['data'])): ?>
            <p class="text-muted" style="font-size:0.82rem;">No response payload stored.</p>
            <?php else: ?>
            <table class="pl-meta" style="margin-bottom:0;">
                <?php foreach ($selectedSubmission['data'] as $field => $value): ?>
                <?php
                    if (is_array($value)) {
                        $value = implode(', ', $value);
                    } elseif (is_bool($value)) {
                        $value = $value ? 'Yes' : 'No';
                    } elseif ($value === null) {
                        $value = '';
                    }
                ?>
                <tr>
                    <th><?= e((string) $field) ?></th>
                    <td><?= e((string) $value) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
