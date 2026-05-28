<?php if (!empty($latestSub)): ?>
<div class="detail-card">
    <h2>Membership</h2>
    <dl class="detail-list">
        <dt>Period</dt><dd><?= e((string) ($latestSub['period'] ?? '—')) ?></dd>
        <dt>Status</dt><dd><span class="badge badge-<?= ($latestSub['status'] ?? '') === 'paid' ? 'success' : 'warning' ?>"><?= e(ucfirst((string) ($latestSub['status'] ?? ''))) ?></span></dd>
        <?php if (!empty($latestSub['payment_date'])): ?><dt>Paid</dt><dd><?= e((string) date('j M Y', strtotime((string) $latestSub['payment_date']))) ?></dd><?php endif; ?>
    </dl>
</div>
<?php endif; ?>
