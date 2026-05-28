<?php
/**
 * account-details-form block type.
 *
 * Renders a profile details form (display name + email) for the logged-in user.
 */

use Cruinn\Auth;
use Cruinn\BlockTypes\BlockRegistry;
use Cruinn\CSRF;
use Cruinn\Database;

BlockRegistry::register([
    'slug' => 'account-details-form',
    'label' => 'Account Details Form',
    'tag' => 'div',
    'dynamic' => true,
    'container' => false,
    'isLayout' => false,
    'renderer' => function (array $config, Database $db): string {
        if (!Auth::check()) {
            return '<p class="account-block-empty">Account details form is only available to logged-in users.</p>';
        }

        $user = $db->fetch('SELECT display_name, email FROM users WHERE id = ? LIMIT 1', [Auth::userId()]);
        if (!$user) {
            return '<p class="account-block-empty">User account not found.</p>';
        }

        $csrf = htmlspecialchars(CSRF::getToken(), ENT_QUOTES, 'UTF-8');
        $displayName = htmlspecialchars((string) ($user['display_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8');

        return '<section class="profile-section">'
             . '<h2>Account Details</h2>'
             . '<form method="post" action="/profile" class="profile-form">'
             . '<input type="hidden" name="csrf_token" value="' . $csrf . '">'
             . '<div class="form-group">'
             . '<label for="account-details-display-name">Display Name</label>'
             . '<input type="text" name="display_name" id="account-details-display-name" class="form-input" value="' . $displayName . '" required>'
             . '</div>'
             . '<div class="form-group">'
             . '<label for="account-details-email">Email Address</label>'
             . '<input type="email" name="email" id="account-details-email" class="form-input" value="' . $email . '" required>'
             . '</div>'
             . '<div class="form-actions">'
             . '<button type="submit" class="btn btn-primary">Save Account Details</button>'
             . '</div>'
             . '</form>'
             . '</section>';
    },
]);
