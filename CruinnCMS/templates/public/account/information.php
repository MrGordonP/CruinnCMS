<?php
/**
 * Account: Information partial
 * Variables: $user (array with created_at, last_login)
 */

$created = '&mdash;';
$lastLogin = '&mdash;';

if (!empty($user['created_at'])) {
    try {
        $created = htmlspecialchars((new DateTime($user['created_at']))->format('j F Y'), ENT_QUOTES, 'UTF-8');
    } catch (Throwable) {
        $created = htmlspecialchars($user['created_at'], ENT_QUOTES, 'UTF-8');
    }
}

if (!empty($user['last_login'])) {
    try {
        $lastLogin = htmlspecialchars((new DateTime($user['last_login']))->format('j F Y \a\t H:i'), ENT_QUOTES, 'UTF-8');
    } catch (Throwable) {
        $lastLogin = htmlspecialchars($user['last_login'], ENT_QUOTES, 'UTF-8');
    }
}
?>
<section class="profile-section">
    <h2>Account Information</h2>
    <dl class="info-list">
        <dt>Account Created</dt>
        <dd><?= $created ?></dd>
        <dt>Last Login</dt>
        <dd><?= $lastLogin ?></dd>
    </dl>
</section>
