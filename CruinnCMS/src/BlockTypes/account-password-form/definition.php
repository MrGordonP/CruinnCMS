<?php
/**
 * account-password-form block type.
 *
 * Renders a password change form for the logged-in user.
 */

use Cruinn\Auth;
use Cruinn\BlockTypes\BlockRegistry;
use Cruinn\CSRF;
use Cruinn\Database;

BlockRegistry::register([
    'slug' => 'account-password-form',
    'label' => 'Account Password Form',
    'tag' => 'div',
    'dynamic' => true,
    'container' => false,
    'isLayout' => false,
    'renderer' => function (array $config, Database $db): string {
        if (!Auth::check()) {
            return '<p class="account-block-empty">Password form is only available to logged-in users.</p>';
        }

        $user = $db->fetch('SELECT display_name, email FROM users WHERE id = ? LIMIT 1', [Auth::userId()]);
        if (!$user) {
            return '<p class="account-block-empty">User account not found.</p>';
        }

        $csrf = htmlspecialchars(CSRF::getToken(), ENT_QUOTES, 'UTF-8');
        $displayName = htmlspecialchars((string) ($user['display_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8');

        return '<section class="profile-section">'
             . '<h2>Change Password</h2>'
             . '<p class="form-help">Use this form to update your password.</p>'
             . '<form method="post" action="/profile" class="profile-form">'
             . '<input type="hidden" name="csrf_token" value="' . $csrf . '">'
             . '<input type="hidden" name="display_name" value="' . $displayName . '">'
             . '<input type="hidden" name="email" value="' . $email . '">'
             . '<div class="form-group">'
             . '<label for="account-password-current">Current Password</label>'
             . '<input type="password" name="current_password" id="account-password-current" class="form-input" autocomplete="current-password">'
             . '</div>'
             . '<div class="form-group">'
             . '<label for="account-password-new">New Password</label>'
             . '<input type="password" name="new_password" id="account-password-new" class="form-input" autocomplete="new-password" minlength="8">'
             . '</div>'
             . '<div class="form-group">'
             . '<label for="account-password-confirm">Confirm New Password</label>'
             . '<input type="password" name="confirm_password" id="account-password-confirm" class="form-input" autocomplete="new-password">'
             . '</div>'
             . '<div class="form-actions">'
             . '<button type="submit" class="btn btn-primary">Change Password</button>'
             . '</div>'
             . '</form>'
             . '</section>';
    },
]);
