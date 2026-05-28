<?php
/**
 * account-information block type.
 *
 * Renders basic account metadata for the logged-in user.
 */

use Cruinn\Auth;
use Cruinn\BlockTypes\BlockRegistry;
use Cruinn\Database;

BlockRegistry::register([
    'slug' => 'account-information',
    'label' => 'Account Information',
    'tag' => 'div',
    'dynamic' => true,
    'container' => false,
    'isLayout' => false,
    'renderer' => function (array $config, Database $db): string {
        if (!Auth::check()) {
            return '<p class="account-block-empty">Account information is only available to logged-in users.</p>';
        }

        $user = $db->fetch('SELECT created_at, last_login FROM users WHERE id = ? LIMIT 1', [Auth::userId()]);
        if (!$user) {
            return '<p class="account-block-empty">User account not found.</p>';
        }

        $created = !empty($user['created_at'])
            ? htmlspecialchars(date('j F Y', strtotime((string) $user['created_at'])), ENT_QUOTES, 'UTF-8')
            : '&mdash;';
        $lastLogin = !empty($user['last_login'])
            ? htmlspecialchars(date('j F Y \a\t H:i', strtotime((string) $user['last_login'])), ENT_QUOTES, 'UTF-8')
            : '&mdash;';

        return '<section class="profile-section">'
             . '<h2>Account Information</h2>'
             . '<dl class="info-list">'
             . '<dt>Account Created</dt><dd>' . $created . '</dd>'
             . '<dt>Last Login</dt><dd>' . $lastLogin . '</dd>'
             . '</dl>'
             . '</section>';
    },
]);
