<?php /** @var array $summary */ /** @var array $rows */ ?>
<section class="pl-panel">
    <div class="pl-panel-header">
        <h1>Notifications Hub</h1>
        <p class="text-muted">Cross-module notification event log and delivery status.</p>
    </div>

    <div class="dash-quick-grid" style="margin-bottom:1rem;">
        <div class="dash-quick-link"><strong class="dash-stat-num"><?= (int) ($summary['total'] ?? 0) ?></strong><span>Total</span></div>
        <div class="dash-quick-link"><strong class="dash-stat-num"><?= (int) ($summary['delivered'] ?? 0) ?></strong><span>Delivered</span></div>
        <div class="dash-quick-link"><strong class="dash-stat-num"><?= (int) ($summary['queued'] ?? 0) ?></strong><span>Queued</span></div>
        <div class="dash-quick-link"><strong class="dash-stat-num"><?= (int) ($summary['skipped'] ?? 0) ?></strong><span>Skipped</span></div>
        <div class="dash-quick-link"><strong class="dash-stat-num"><?= (int) ($summary['failed'] ?? 0) ?></strong><span>Failed</span></div>
    </div>

    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Created</th>
                    <th>Module/Event</th>
                    <th>Category</th>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Recipients</th>
                    <th>Delivered</th>
                    <th>Error</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="9" class="text-muted">No hub events logged yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= (int) ($row['id'] ?? 0) ?></td>
                            <td><?= e(format_date((string) ($row['created_at'] ?? ''), 'j M Y H:i')) ?></td>
                            <td><?= e((string) ($row['source_module'] ?? '')) ?> / <?= e((string) ($row['source_event'] ?? '')) ?></td>
                            <td><?= e((string) ($row['category'] ?? '')) ?></td>
                            <td><?= e((string) ($row['title'] ?? '')) ?></td>
                            <td><?= e((string) ($row['status'] ?? '')) ?></td>
                            <td><?= (int) ($row['recipient_count'] ?? 0) ?></td>
                            <td><?= (int) ($row['delivered_count'] ?? 0) ?></td>
                            <td><?= e((string) ($row['error_message'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
