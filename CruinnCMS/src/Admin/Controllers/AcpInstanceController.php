<?php
/**
 * CruinnCMS — ACP Instance Controller
 *
 * Instance-specific integration settings: GDPR/privacy policy config,
 * social media credentials, payment provider keys, and OAuth app secrets.
 * These panels hold config that varies per deployment.
 */

namespace Cruinn\Admin\Controllers;

use Cruinn\App;
use Cruinn\Auth;
use Cruinn\Controllers\BaseController;
use Cruinn\Services\SettingsService;

class AcpInstanceController extends BaseController
{
    private SettingsService $settings;

    public function __construct()
    {
        parent::__construct();
        $this->settings = new SettingsService();
    }

    // ── GDPR / Privacy ───────────────────────────────────────────

    public function gdpr(): void
    {
        $data = [
            'title'    => 'GDPR & Privacy',
            'tab'      => 'gdpr',
            'settings' => $this->getSettingsForPanel([
                'gdpr.enabled', 'gdpr.org_name', 'gdpr.contact_email', 'gdpr.dpo_email',
            ]),
        ];
        $this->renderAcp('admin/settings/gdpr', $data);
    }

    public function saveGdpr(): void
    {
        $this->settings->setMany([
            'gdpr.enabled'       => $this->input('gdpr_enabled', '0'),
            'gdpr.org_name'      => $this->input('gdpr_org_name', ''),
            'gdpr.contact_email' => $this->input('gdpr_contact_email', ''),
            'gdpr.dpo_email'     => $this->input('gdpr_dpo_email', ''),
        ], 'gdpr');

        Auth::flash('success', 'GDPR settings updated.');
        $this->redirect('/admin/settings/gdpr');
    }

    // ── Social Media ─────────────────────────────────────────────

    public function social(): void
    {
        $data = [
            'title'    => 'Social Media',
            'tab'      => 'social',
            'settings' => $this->getSettingsForPanel([
                'social.facebook', 'social.twitter', 'social.instagram',
                'social.auth_proxy_url', 'social.auth_proxy_secret',
                'social.custom_facebook_app_id', 'social.custom_facebook_app_secret',
                'social.custom_twitter_api_key', 'social.custom_twitter_api_secret',
                'social.custom_instagram_app_id', 'social.custom_instagram_app_secret',
            ]),
        ];
        $this->renderAcp('admin/settings/social', $data);
    }

    public function saveSocial(): void
    {
        $this->settings->setMany([
            'social.facebook'                    => $this->input('social_facebook', ''),
            'social.twitter'                     => $this->input('social_twitter', ''),
            'social.instagram'                   => $this->input('social_instagram', ''),
            'social.auth_proxy_url'              => $this->input('social_auth_proxy_url', ''),
            'social.auth_proxy_secret'           => $this->input('social_auth_proxy_secret', ''),
            'social.custom_facebook_app_id'      => $this->input('social_custom_facebook_app_id', ''),
            'social.custom_facebook_app_secret'  => $this->input('social_custom_facebook_app_secret', ''),
            'social.custom_twitter_api_key'      => $this->input('social_custom_twitter_api_key', ''),
            'social.custom_twitter_api_secret'   => $this->input('social_custom_twitter_api_secret', ''),
            'social.custom_instagram_app_id'     => $this->input('social_custom_instagram_app_id', ''),
            'social.custom_instagram_app_secret' => $this->input('social_custom_instagram_app_secret', ''),
        ], 'social');

        Auth::flash('success', 'Social media settings updated.');
        $this->redirect('/admin/settings/social');
    }

    // ── Payments ─────────────────────────────────────────────────

    public function payments(): void
    {
        $data = [
            'title'    => 'Payments',
            'tab'      => 'payments',
            'settings' => $this->getSettingsForPanel([
                'paypal.client_id', 'paypal.sandbox',
                'stripe.public_key', 'stripe.sandbox',
            ]),
            'paypal_secret_set' => App::config('paypal.client_secret', '') !== '',
            'stripe_secret_set' => App::config('stripe.secret_key', '') !== '',
        ];
        $this->renderAcp('admin/settings/payments', $data);
    }

    public function savePayments(): void
    {
        $this->settings->setMany([
            'paypal.client_id'  => $this->input('paypal_client_id', ''),
            'paypal.sandbox'    => $this->input('paypal_sandbox', '0'),
            'stripe.public_key' => $this->input('stripe_public_key', ''),
            'stripe.sandbox'    => $this->input('stripe_sandbox', '0'),
        ], 'payments');

        Auth::flash('success', 'Payment settings updated.');
        $this->redirect('/admin/settings/payments');
    }

    // ── OAuth Providers ──────────────────────────────────────────

    public function oauth(): void
    {
        $data = [
            'title'    => 'OAuth Providers',
            'tab'      => 'oauth',
            'settings' => $this->getSettingsForPanel([
                'oauth.google.client_id',    'oauth.google.client_secret',
                'oauth.facebook.client_id',  'oauth.facebook.client_secret',
                'oauth.twitter.client_id',   'oauth.twitter.client_secret',
                'oauth.github.client_id',    'oauth.github.client_secret',
                'oauth.microsoft.client_id', 'oauth.microsoft.client_secret',
                'oauth.linkedin.client_id',  'oauth.linkedin.client_secret',
            ]),
        ];
        $this->renderAcp('admin/settings/oauth', $data);
    }

    public function saveOauth(): void
    {
        $this->settings->setMany([
            'oauth.google.client_id'    => $this->input('oauth_google_client_id', ''),
            'oauth.facebook.client_id'  => $this->input('oauth_facebook_client_id', ''),
            'oauth.twitter.client_id'   => $this->input('oauth_twitter_client_id', ''),
            'oauth.github.client_id'    => $this->input('oauth_github_client_id', ''),
            'oauth.microsoft.client_id' => $this->input('oauth_microsoft_client_id', ''),
            'oauth.linkedin.client_id'  => $this->input('oauth_linkedin_client_id', ''),
        ], 'oauth');

        foreach (['google', 'facebook', 'twitter', 'github', 'microsoft', 'linkedin'] as $provider) {
            $secret = $this->input("oauth_{$provider}_client_secret", '');
            if ($secret !== '') {
                $this->settings->set("oauth.{$provider}.client_secret", $secret, 'oauth');
            }
        }

        Auth::flash('success', 'OAuth provider settings updated.');
        $this->redirect('/admin/settings/oauth');
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function getSettingsForPanel(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->settings->get($key, '');
        }
        return $result;
    }
}
