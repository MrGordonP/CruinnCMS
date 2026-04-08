<div class="container">
    <div class="login-page">
        <h1>My Profile</h1>

        <?php if ($flash = \Cruinn\Auth::getFlash('success')): ?>
            <div class="alert alert-success"><?= e($flash) ?></div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="/users/profile" class="form-login">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="display_name">Display Name</label>
                <input type="text" id="display_name" name="display_name" required
                       value="<?= e($user['display_name']) ?>" class="form-input">
            </div>

            <div class="form-group">
                <label>Email</label>
                <p class="form-static"><?= e($user['email']) ?></p>
            </div>

            <hr>
            <p class="form-section-label">Change Password <span class="text-muted">(leave blank to keep current)</span></p>

            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password"
                       autocomplete="new-password" class="form-input">
            </div>

            <div class="form-group">
                <label for="password_confirm">Confirm New Password</label>
                <input type="password" id="password_confirm" name="password_confirm"
                       autocomplete="new-password" class="form-input">
            </div>

            <button type="submit" class="btn btn-primary btn-block">Save Changes</button>
        </form>
    </div>
</div>
