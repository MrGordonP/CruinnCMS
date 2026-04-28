<?php include __DIR__ . '/_tabs.php'; ?>
<?php \Cruinn\Template::requireCss('admin-acp.css'); ?>

<h2>Social Media</h2>

<form method="post" action="<?= url('/admin/settings/social') ?>">
    <?= csrf_field() ?>

    <fieldset class="acp-fieldset">
        <legend>Profile Links</legend>
        <p class="form-help">Public social media URLs displayed on the site.</p>

        <div class="form-row">
            <div class="form-group">
                <label for="social_facebook">Facebook URL</label>
                <input type="url" id="social_facebook" name="social_facebook" class="form-input"
                       value="<?= e($settings['social.facebook'] ?? '') ?>"
                       placeholder="https://facebook.com/yourpage">
            </div>
            <div class="form-group">
                <label for="social_twitter">Twitter / X URL</label>
                <input type="url" id="social_twitter" name="social_twitter" class="form-input"
                       value="<?= e($settings['social.twitter'] ?? '') ?>"
                       placeholder="https://x.com/yourhandle">
            </div>
        </div>

        <div class="form-group">
            <label for="social_instagram">Instagram URL</label>
            <input type="url" id="social_instagram" name="social_instagram" class="form-input"
                   value="<?= e($settings['social.instagram'] ?? '') ?>"
                   placeholder="https://instagram.com/yourpage">
        </div>
    </fieldset>

    <fieldset class="acp-fieldset">
        <legend>Auth Proxy</legend>
        <p class="form-help">Proxy service for social media authentication (e.g. posting on behalf of the site).</p>

        <div class="form-row">
            <div class="form-group">
                <label for="social_auth_proxy_url">Auth Proxy URL</label>
                <input type="url" id="social_auth_proxy_url" name="social_auth_proxy_url" class="form-input"
                       value="<?= e($settings['social.auth_proxy_url'] ?? '') ?>"
                       placeholder="https://auth.example.ie">
            </div>
            <div class="form-group">
                <label for="social_auth_proxy_secret">Auth Proxy Secret</label>
                <input type="password" id="social_auth_proxy_secret" name="social_auth_proxy_secret" class="form-input"
                       value=""
                       placeholder="<?= !empty($settings['social.auth_proxy_secret']) ? '••••••••' : '' ?>">
                <small class="form-help">Leave blank to keep current secret.</small>
            </div>
        </div>
    </fieldset>

    <fieldset class="acp-fieldset">
        <legend>Facebook App</legend>
        <div class="form-row">
            <div class="form-group">
                <label for="social_custom_facebook_app_id">App ID</label>
                <input type="text" id="social_custom_facebook_app_id" name="social_custom_facebook_app_id" class="form-input"
                       value="<?= e($settings['social.custom_facebook_app_id'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="social_custom_facebook_app_secret">App Secret</label>
                <input type="password" id="social_custom_facebook_app_secret" name="social_custom_facebook_app_secret" class="form-input"
                       value=""
                       placeholder="<?= !empty($settings['social.custom_facebook_app_secret']) ? '••••••••' : '' ?>">
                <small class="form-help">Leave blank to keep current secret.</small>
            </div>
        </div>
    </fieldset>

    <fieldset class="acp-fieldset">
        <legend>Twitter / X App</legend>
        <div class="form-row">
            <div class="form-group">
                <label for="social_custom_twitter_api_key">API Key</label>
                <input type="text" id="social_custom_twitter_api_key" name="social_custom_twitter_api_key" class="form-input"
                       value="<?= e($settings['social.custom_twitter_api_key'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="social_custom_twitter_api_secret">API Secret</label>
                <input type="password" id="social_custom_twitter_api_secret" name="social_custom_twitter_api_secret" class="form-input"
                       value=""
                       placeholder="<?= !empty($settings['social.custom_twitter_api_secret']) ? '••••••••' : '' ?>">
                <small class="form-help">Leave blank to keep current secret.</small>
            </div>
        </div>
    </fieldset>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Social Media Settings</button>
    </div>
</form>

<?php include __DIR__ . '/_tabs_end.php'; ?>
