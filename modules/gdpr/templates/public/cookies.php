<div class="container">
    <div class="policy-page">
        <h1>Cookie Policy</h1>
        <p class="text-muted">Last updated: <?= date('F Y') ?></p>

        <h2>What Are Cookies?</h2>
        <p>Cookies are small text files stored on your device by your web browser. They are widely used to make websites work and to provide information to site owners.</p>

        <h2>Cookies We Use</h2>
        <p>We only use <strong>strictly necessary cookies</strong> that are essential for the website to function. We do not use any cookies for tracking, analytics, advertising, or profiling.</p>

        <table class="table">
            <thead>
                <tr>
                    <th>Cookie Name</th>
                    <th>Purpose</th>
                    <th>Duration</th>
                    <th>Type</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code><?= e($session_name ?? 'iga_sess') ?></code></td>
                    <td>Keeps you logged in and maintains your session</td>
                    <td>1 hour (or until you close the browser)</td>
                    <td>Strictly necessary</td>
                </tr>
            </tbody>
        </table>

        <h2>Local Storage</h2>
        <p>We also use your browser's local storage (not a cookie) to remember that you have acknowledged this cookie notice, so it is not shown again on each visit.</p>

        <table class="table">
            <thead>
                <tr>
                    <th>Key</th>
                    <th>Purpose</th>
                    <th>Duration</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>cookie_consent</code></td>
                    <td>Records that you have acknowledged the cookie banner</td>
                    <td>Persistent (until you clear browser data)</td>
                </tr>
            </tbody>
        </table>

        <h2>Third-Party Cookies</h2>
        <p>We do not set any third-party cookies. If you use social login (Google, Facebook, X), those providers may set their own cookies on their own domains during the login process — we have no control over those cookies. Please refer to their respective privacy policies.</p>

        <h2>How to Manage Cookies</h2>
        <p>You can control and delete cookies through your browser settings. Note that disabling the session cookie will prevent you from logging in.</p>
        <ul>
            <li><a href="https://support.google.com/chrome/answer/95647" target="_blank" rel="noopener">Chrome</a></li>
            <li><a href="https://support.mozilla.org/en-US/kb/cookies-information-websites-store-on-your-computer" target="_blank" rel="noopener">Firefox</a></li>
            <li><a href="https://support.apple.com/en-ie/guide/safari/sfri11471/mac" target="_blank" rel="noopener">Safari</a></li>
            <li><a href="https://support.microsoft.com/en-us/microsoft-edge/manage-cookies-in-microsoft-edge-view-allow-block-delete-and-use-168dab11-0753-043d-7c16-ede5947fc64d" target="_blank" rel="noopener">Edge</a></li>
        </ul>

        <h2>Contact</h2>
        <p>If you have questions about our use of cookies, please see our <a href="/privacy">Privacy Policy</a><?php $gdpr_email = \IGA\App::config('gdpr.contact_email', ''); if ($gdpr_email): ?> or contact us at <a href="mailto:<?= e($gdpr_email) ?>"><?= e($gdpr_email) ?></a><?php endif; ?>.</p>
    </div>
</div>
