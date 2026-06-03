<?php
/**
 * Account: Details form partial
 * Variables: $user (array with display_name, email), $errors (optional array)
 */
?>
<section class="profile-section">
    <h2>Account Details</h2>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="/profile/details" class="profile-form">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="display_name">Display Name</label>
            <input type="text" name="display_name" id="display_name" class="form-input"
                   value="<?= htmlspecialchars($user['display_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" name="email" id="email" class="form-input"
                   value="<?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Account Details</button>
        </div>
    </form>
</section>
