<div class="container">
    <div class="policy-page">
        <h1>Privacy Policy</h1>
        <p class="text-muted">Last updated: <?= date('F Y') ?></p>

        <h2>Who We Are</h2>
        <p><?= e($org_name) ?> operates this website. When we refer to "we", "us" or "our" in this policy, we mean <?= e($org_name) ?>.</p>
        <?php if ($contact): ?>
        <p>You can contact us at <a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a>.</p>
        <?php endif; ?>
        <?php if ($dpo_email): ?>
        <p>Our Data Protection Officer can be reached at <a href="mailto:<?= e($dpo_email) ?>"><?= e($dpo_email) ?></a>.</p>
        <?php endif; ?>

        <h2>What Data We Collect</h2>
        <p>We collect and process the following personal data:</p>
        <ul>
            <li><strong>Account information</strong> — your name, email address, and a secure hash of your password when you register. We never store your actual password in plain text.</li>
            <li><strong>Membership details</strong> — address (optional), phone number (optional), institute/organisation (optional), and membership status if you become a member. Subscription payments and membership level are recorded for financial administration.</li>
            <li><strong>Activity data</strong> — event registrations, forum posts, and notification preferences. This data is self-managed via your member dashboard.</li>
            <li><strong>Technical data</strong> — your IP address is recorded in activity logs for security auditing (e.g. detecting unauthorised access). This data is associated with your account while active and deleted when your account is closed. Your browser&rsquo;s user-agent string is recorded only when you give or withdraw cookie consent, as proof of that action.</li>
        </ul>

        <h2>Why We Process Your Data</h2>
        <p>We process your data on the following legal bases under GDPR:</p>
        <ul>
            <li><strong>Contractual necessity</strong> — to provide and manage your membership and the services you sign up for.</li>
            <li><strong>Legitimate interest</strong> — to maintain site security, prevent fraud, and improve our services.</li>
            <li><strong>Consent</strong> — where you have opted in, such as appearing in the public member directory.</li>
        </ul>

        <h2>Cookies</h2>
        <p>We use only <strong>strictly necessary cookies</strong> to keep you logged in and protect form submissions. We do not use tracking, analytics, or advertising cookies. See our <a href="/cookies">Cookie Policy</a> for full details.</p>

        <h2>Third-Party Services</h2>
        <p>If you choose to log in via Google, Facebook, or X, your profile information (name, email, profile picture) is shared with us by that provider. We only use this to create or link your account. We do not share your data with these providers beyond the authentication flow.</p>

        <h2>How Long We Keep Your Data</h2>
        <ul>
            <li><strong>Account data</strong> — retained while your account is active. If you delete your account, your data is removed from the live system immediately but held securely for 30 days in case you change your mind or there is a dispute. After 30 days it is permanently deleted.</li>
            <li><strong>Forum posts</strong> — if you delete your account, your posts are anonymised (authorship removed) but the content is retained for community continuity.</li>
            <li><strong>Event registrations</strong> — if you delete your account, registrations are anonymised (your name and personal details removed) but kept for event reporting.</li>
        </ul>

        <h2>Your Rights</h2>
        <p>Under GDPR, you have the right to:</p>
        <ul>
            <li><strong>Access</strong> — download a copy of all personal data we hold about you.</li>
            <li><strong>Rectification</strong> — correct inaccurate data via your profile page, or by contacting us.</li>
            <li><strong>Erasure</strong> — delete your account and all personal data.</li>
            <li><strong>Portability</strong> — export your data in a machine-readable format (JSON).</li>
            <li><strong>Objection</strong> — contact us to object to specific processing activities.</li>
        </ul>
        <p>You can exercise your right to access and erasure directly from your <a href="/users/profile">profile page</a>. For other requests, contact us at <a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a>.</p>

        <h2>Data Security</h2>
        <p>We protect your data with:</p>
        <ul>
            <li>Passwords hashed with bcrypt (never stored in plain text)</li>
            <li>CSRF protection on all forms</li>
            <li>Session cookies with HttpOnly and SameSite flags</li>
            <li>HTTPS encryption in production</li>
        </ul>

        <h2>Complaints</h2>
        <p>If you believe your data rights have been violated, you have the right to lodge a complaint with the <a href="https://www.dataprotection.ie" target="_blank" rel="noopener">Data Protection Commission</a> (Ireland) or your local supervisory authority.</p>
    </div>
</div>
