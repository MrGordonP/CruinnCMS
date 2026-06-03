<?php
/**
 * Profile Hub — logged-in user landing page.
 * Variables: $user (array with display_name)
 * This is a system page. Its content is instance-editable in the block editor.
 * The hub template is rendered only when the page has no seeded block content
 * (fallback). Ordinarily this file is not used — the page renders via blocks.
 */
$displayName = htmlspecialchars($user['display_name'] ?? 'My Account', ENT_QUOTES, 'UTF-8');
?>
<div class="profile-page">
    <div class="container">
        <h1><?= $displayName ?></h1>
        <nav class="profile-nav">
            <a href="/profile/account" class="btn btn-secondary">Account Information</a>
            <a href="/profile/password" class="btn btn-secondary">Change Password</a>
        </nav>
    </div>
</div>
