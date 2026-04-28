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

            <div class="register-details-block">
                <h3>Your Details</h3>
                <p class="register-details-note">Your registration details are taken from your account for consent and liability purposes. If these are incorrect, please <a href="/profile">update your profile</a> before registering.</p>

                <input type="hidden" name="name" value="<?= e($prefill['name'] ?? '') ?>">
                <input type="hidden" name="email" value="<?= e($prefill['email'] ?? '') ?>">

                <dl class="register-details-list">
                    <dt>Name</dt>
                    <dd><?= e($prefill['name'] ?? '—') ?></dd>
                    <dt>Email</dt>
                    <dd><?= e($prefill['email'] ?? '—') ?></dd>
                </dl>
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" placeholder="Optional contact number">
            </div>

            <div class="form-group">
                <label for="attendees">Number of Attendees</label>
                <input type="number" id="attendees" name="attendees" value="1" min="1" max="<?= $event['capacity'] > 0 ? (int)$spotsRemaining : 20 ?>">
            </div>

            <div class="form-group">
                <label for="notes">Requirements / Notes</label>
                <textarea id="notes" name="notes" rows="3" placeholder="Dietary requirements, accessibility needs, or any other information…"></textarea>
            </div>

            <div class="register-payment-status">
                <strong>Payment:</strong>
                <?php if ($event['price'] > 0): ?>
                    <span class="badge badge-warning">Payment required &mdash; &euro;<?= number_format($event['price'], 2) ?></span>
                    <p class="register-payment-note">Payment instructions will be sent in your confirmation email.</p>
                <?php else: ?>
                    <span class="badge badge-success">N/A &mdash; Free event</span>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Confirm Registration</button>
                <a href="/events/<?= e($event['slug']) ?>" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>
