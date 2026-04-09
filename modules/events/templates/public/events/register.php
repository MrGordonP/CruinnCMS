<div class="container">
    <div class="register-page">
        <h1>Register for <?= e($event['title']) ?></h1>

        <div class="event-summary-bar">
            <span class="event-type-badge"><?= e(ucfirst($event['event_type'])) ?></span>
            <time datetime="<?= e($event['date_start']) ?>"><?= format_date($event['date_start'], 'l, j F Y') ?></time>
            <?php if (!empty($event['location'])): ?>
                <span class="event-location">&bull; <?= e($event['location']) ?></span>
            <?php endif; ?>
            <?php if ($event['price'] > 0): ?>
                <span class="event-price">&bull; &euro;<?= number_format($event['price'], 2) ?></span>
            <?php endif; ?>
            <?php if ($spotsRemaining !== null): ?>
                <span class="spots-badge"><?= (int) $spotsRemaining ?> spot<?= $spotsRemaining !== 1 ? 's' : '' ?> left</span>
            <?php endif; ?>
        </div>

        <form method="post" action="/events/<?= e($event['slug']) ?>/register" class="register-form">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="name">Full Name <span class="required">*</span></label>
                <input type="text" id="name" name="name" value="<?= e($prefill['name'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" value="<?= e($prefill['email'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="dietary_notes">Dietary Requirements</label>
                <input type="text" id="dietary_notes" name="dietary_notes" placeholder="e.g. vegetarian, allergies…">
            </div>

            <div class="form-group">
                <label for="access_notes">Accessibility Needs</label>
                <input type="text" id="access_notes" name="access_notes" placeholder="e.g. wheelchair access, hearing loop…">
            </div>

            <?php if ($event['price'] > 0): ?>
            <div class="payment-notice">
                <strong>Payment Required:</strong> &euro;<?= number_format($event['price'], 2) ?>
                <p>Payment instructions will be included in your confirmation email.</p>
            </div>
            <?php endif; ?>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Confirm Registration</button>
                <a href="/events/<?= e($event['slug']) ?>" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>
