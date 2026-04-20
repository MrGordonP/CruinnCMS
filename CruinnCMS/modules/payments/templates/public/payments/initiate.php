<?php $this->setTitle($title ?? 'Complete Payment') ?>

<div class="content-wrap">
    <h1>Complete Your Payment</h1>

    <div class="notice notice-info">
        <p><strong>Online payment is not yet available.</strong></p>
        <p>Please contact us to arrange payment. Quote your submission reference below.</p>
    </div>

    <?php if (!empty($source_id)): ?>
    <p class="text-muted">Reference: <strong><?= e($source ?? 'submission') ?>_<?= (int) $source_id ?></strong></p>
    <?php endif; ?>
</div>
