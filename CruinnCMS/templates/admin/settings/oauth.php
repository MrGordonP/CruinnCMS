<?php include __DIR__ . '/_tabs.php'; ?>
<?php \Cruinn\Template::requireCss('admin-acp.css'); ?>

<h2>OAuth Providers</h2>

<form method="post" action="<?= url('/admin/settings/oauth') ?>">
    <?= csrf_field() ?>

    <fieldset class="acp-fieldset">
        <legend>Google</legend>
        <div class="form-row">
            <div class="form-group">
                <label for="oauth_google_client_id">Client ID</label>
                <input type="text" id="oauth_google_client_id" name="oauth_google_client_id" class="form-input"
                       value="<?= e($settings['oauth.google.client_id'] ?? '') ?>"
                       placeholder="xxxx.apps.googleusercontent.com">
            </div>
            <div class="form-group">
                <label for="oauth_google_client_secret">Client Secret</label>
                <input type="password" id="oauth_google_client_secret" name="oauth_google_client_secret" class="form-input"
                       value=""
                       placeholder="<?= !empty($settings['oauth.google.client_secret']) ? '••••••••' : '' ?>">
                <small class="form-help">Leave blank to keep current secret.</small>
            </div>
        </div>
    </fieldset>

    <fieldset class="acp-fieldset">
        <legend>Facebook</legend>
        <div class="form-row">
            <div class="form-group">
                <label for="oauth_facebook_client_id">App ID</label>
                <input type="text" id="oauth_facebook_client_id" name="oauth_facebook_client_id" class="form-input"
                       value="<?= e($settings['oauth.facebook.client_id'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="oauth_facebook_client_secret">App Secret</label>
                <input type="password" id="oauth_facebook_client_secret" name="oauth_facebook_client_secret" class="form-input"
                       value=""
                       placeholder="<?= !empty($settings['oauth.facebook.client_secret']) ? '••••••••' : '' ?>">
                <small class="form-help">Leave blank to keep current secret.</small>
            </div>
        </div>
    </fieldset>

    <fieldset class="acp-fieldset">
        <legend>Twitter / X</legend>
        <div class="form-row">
            <div class="form-group">
                <label for="oauth_twitter_client_id">Client ID</label>
                <input type="text" id="oauth_twitter_client_id" name="oauth_twitter_client_id" class="form-input"
                       value="<?= e($settings['oauth.twitter.client_id'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="oauth_twitter_client_secret">Client Secret</label>
                <input type="password" id="oauth_twitter_client_secret" name="oauth_twitter_client_secret" class="form-input"
                       value=""
                       placeholder="<?= !empty($settings['oauth.twitter.client_secret']) ? '••••••••' : '' ?>">
                <small class="form-help">Leave blank to keep current secret.</small>
            </div>
        </div>
    </fieldset>

    <fieldset class="acp-fieldset">
        <legend>GitHub</legend>
        <div class="form-row">
            <div class="form-group">
                <label for="oauth_github_client_id">Client ID</label>
                <input type="text" id="oauth_github_client_id" name="oauth_github_client_id" class="form-input"
                       value="<?= e($settings['oauth.github.client_id'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="oauth_github_client_secret">Client Secret</label>
                <input type="password" id="oauth_github_client_secret" name="oauth_github_client_secret" class="form-input"
                       value=""
                       placeholder="<?= !empty($settings['oauth.github.client_secret']) ? '••••••••' : '' ?>">
                <small class="form-help">Leave blank to keep current secret.</small>
            </div>
        </div>
    </fieldset>

    <fieldset class="acp-fieldset">
        <legend>Microsoft</legend>
        <div class="form-row">
            <div class="form-group">
                <label for="oauth_microsoft_client_id">Application (Client) ID</label>
                <input type="text" id="oauth_microsoft_client_id" name="oauth_microsoft_client_id" class="form-input"
                       value="<?= e($settings['oauth.microsoft.client_id'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="oauth_microsoft_client_secret">Client Secret</label>
                <input type="password" id="oauth_microsoft_client_secret" name="oauth_microsoft_client_secret" class="form-input"
                       value=""
                       placeholder="<?= !empty($settings['oauth.microsoft.client_secret']) ? '••••••••' : '' ?>">
                <small class="form-help">Leave blank to keep current secret.</small>
            </div>
        </div>
    </fieldset>

    <fieldset class="acp-fieldset">
        <legend>LinkedIn</legend>
        <div class="form-row">
            <div class="form-group">
                <label for="oauth_linkedin_client_id">Client ID</label>
                <input type="text" id="oauth_linkedin_client_id" name="oauth_linkedin_client_id" class="form-input"
                       value="<?= e($settings['oauth.linkedin.client_id'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="oauth_linkedin_client_secret">Client Secret</label>
                <input type="password" id="oauth_linkedin_client_secret" name="oauth_linkedin_client_secret" class="form-input"
                       value=""
                       placeholder="<?= !empty($settings['oauth.linkedin.client_secret']) ? '••••••••' : '' ?>">
                <small class="form-help">Leave blank to keep current secret.</small>
            </div>
        </div>
    </fieldset>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save OAuth Settings</button>
    </div>
</form>

<?php include __DIR__ . '/_tabs_end.php'; ?>
