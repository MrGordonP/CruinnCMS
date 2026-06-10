<section class="container notifications-page">
    <header class="notifications-header">
        <div>
            <h1>Notification Preferences</h1>
            <p class="results-count">Choose in-app and email behaviour by category.</p>
        </div>
        <a href="<?= url('/notifications') ?>" class="btn btn-outline btn-small">Back to notifications</a>
    </header>

    <form method="post" action="<?= url('/notifications/preferences') ?>" class="form-register notifications-preferences-form">
        <?= csrf_field() ?>

        <div class="notifications-pref-grid">
            <?php foreach ($categories as $category): ?>
                <?php $pref = $preferences[$category] ?? ['in_app' => true, 'email_frequency' => 'daily']; ?>
                <div class="notifications-pref-row">
                    <div class="notifications-pref-category">
                        <strong><?= e(ucfirst($category)) ?></strong>
                    </div>
                    <label>
                        <input type="checkbox" name="in_app[<?= e($category) ?>]" value="1"<?= !empty($pref['in_app']) ? ' checked' : '' ?>>
                        In-app
                    </label>
                    <label>
                        Email
                        <select name="email_frequency[<?= e($category) ?>]" class="form-input">
                            <?php foreach (['immediate' => 'Immediate', 'daily' => 'Daily', 'weekly' => 'Weekly', 'off' => 'Off'] as $value => $label): ?>
                                <option value="<?= e($value) ?>"<?= ($pref['email_frequency'] ?? 'daily') === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Preferences</button>
            <a href="<?= url('/notifications') ?>" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</section>
