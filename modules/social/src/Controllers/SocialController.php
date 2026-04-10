<?php
/**
 * CruinnCMS â€” Social Media Controller
 *
 * Manages the Social Media Command Centre:
 *   - Dashboard: unified view of all platform activity
 *   - Inbox: shared comments and messages across platforms
 *   - Distribute: publish content to social channels + mailing lists
 *   - Accounts: manage connected platform credentials
 */

namespace Cruinn\Module\Social\Controllers;

use Cruinn\Auth;
use Cruinn\App;
use Cruinn\Controllers\BaseController;
use Cruinn\Module\Social\Services\FacebookService;
use Cruinn\Module\Social\Services\TwitterService;
use Cruinn\Module\Social\Services\InstagramService;
use Cruinn\Module\Social\Services\AbstractSocialService;

class SocialController extends BaseController
{
    // â”€â”€ Dashboard â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Social Media Command Centre â€” overview of all platforms.
     */
    public function dashboard(): void
    {
        $accounts = $this->db->fetchAll('SELECT * FROM social_accounts ORDER BY platform');

        // Build per-platform data
        $platforms = [];
        foreach ($accounts as $acct) {
            $service = $this->getService($acct);
            $platforms[$acct['platform']] = [
                'account'   => $acct,
                'connected' => $service->isConnected(),
                'metrics'   => $service->isConnected() ? $service->getMetrics() : [],
            ];
        }

        // Recent posts from our DB
        $recentPosts = $this->db->fetchAll(
            'SELECT sp.*, sa.account_name, sa.platform
             FROM social_posts sp
             JOIN social_accounts sa ON sp.social_account_id = sa.id
             ORDER BY sp.created_at DESC
             LIMIT 20'
        );

        // Inbox stats
        $unreadCount = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM social_inbox WHERE is_read = 0'
        );

        $inboxRecent = $this->db->fetchAll(
            'SELECT si.*, sa.account_name
             FROM social_inbox si
             JOIN social_accounts sa ON si.social_account_id = sa.id
             ORDER BY si.received_at DESC
             LIMIT 10'
        );

        $this->renderAdmin('admin/social/dashboard', [
            'title'       => 'Social Media',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => '/admin'],
                ['label' => 'Social Media'],
            ],
            'accounts'    => $accounts,
            'platforms'   => $platforms,
            'recentPosts' => $recentPosts,
            'unreadCount' => $unreadCount,
            'inboxRecent' => $inboxRecent,
        ]);
    }

    // â”€â”€ Feed: read platform posts â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * View the feed from a specific platform.
     */
    public function feed(string $platform): void
    {
        $account = $this->getAccount($platform);
        if (!$account) {
            Auth::flash('warning', ucfirst($platform) . ' account is not connected.');
            $this->redirect('/admin/social');
        }

        $service = $this->getService($account);
        $posts   = $service->isConnected() ? $service->fetchPosts(25) : [];
        $metrics = $service->isConnected() ? $service->getMetrics() : [];

        $this->renderAdmin('admin/social/feed', [
            'title'       => ucfirst($platform) . ' Feed',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => '/admin'],
                ['label' => 'Social Media', 'url' => '/admin/social'],
                ['label' => ucfirst($platform) . ' Feed'],
            ],
            'platform' => $platform,
            'account'  => $account,
            'posts'    => $posts,
            'metrics'  => $metrics,
        ]);
    }

    // â”€â”€ Inbox â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Unified inbox â€” all comments and messages across platforms.
     */
    public function inbox(): void
    {
        $platform    = $this->query('platform', '');
        $messageType = $this->query('type', '');
        $readFilter  = $this->query('read', '');

        $where  = [];
        $params = [];

        if ($platform) {
            $where[]  = 'si.platform = ?';
            $params[] = $platform;
        }
        if ($messageType) {
            $where[]  = 'si.message_type = ?';
            $params[] = $messageType;
        }
        if ($readFilter === 'unread') {
            $where[] = 'si.is_read = 0';
        } elseif ($readFilter === 'read') {
            $where[] = 'si.is_read = 1';
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $messages = $this->db->fetchAll(
            "SELECT si.*, sa.account_name
             FROM social_inbox si
             JOIN social_accounts sa ON si.social_account_id = sa.id
             {$whereClause}
             ORDER BY si.received_at DESC
             LIMIT 100",
            $params
        );

        $unreadCount = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM social_inbox WHERE is_read = 0'
        );

        $this->renderAdmin('admin/social/inbox', [
            'title'       => 'Social Inbox',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => '/admin'],
                ['label' => 'Social Media', 'url' => '/admin/social'],
                ['label' => 'Inbox'],
            ],
            'messages'     => $messages,
            'unreadCount'  => $unreadCount,
            'filterPlatform' => $platform,
            'filterType'     => $messageType,
            'filterRead'     => $readFilter,
        ]);
    }

    /**
     * Sync / refresh inbox from all connected platforms.
     */
    public function syncInbox(): void
    {
        $accounts = $this->db->fetchAll('SELECT * FROM social_accounts WHERE is_active = 1');
        $total = 0;

        foreach ($accounts as $acct) {
            $service = $this->getService($acct);
            if ($service->isConnected()) {
                $fetched = $service->fetchInbox(50);
                $total  += count($fetched);
            }
        }

        Auth::flash('success', "Inbox synced â€” {$total} new messages fetched.");
        $this->redirect('/admin/social/inbox');
    }

    /**
     * Mark an inbox message as read (AJAX).
     */
    public function markRead(string $id): void
    {
        $this->db->update('social_inbox', ['is_read' => 1], 'id = ?', [(int)$id]);
        $this->json(['success' => true]);
    }

    /**
     * Mark an inbox message as starred (AJAX).
     */
    public function toggleStar(string $id): void
    {
        $msg = $this->db->fetch('SELECT is_starred FROM social_inbox WHERE id = ?', [(int)$id]);
        if ($msg) {
            $newVal = $msg['is_starred'] ? 0 : 1;
            $this->db->update('social_inbox', ['is_starred' => $newVal], 'id = ?', [(int)$id]);
            $this->json(['success' => true, 'starred' => (bool)$newVal]);
        }
        $this->json(['success' => false], 404);
    }

    /**
     * Reply to an inbox message.
     */
    public function replyToMessage(string $id): void
    {
        $msg = $this->db->fetch(
            'SELECT si.*, sa.platform FROM social_inbox si
             JOIN social_accounts sa ON si.social_account_id = sa.id
             WHERE si.id = ?',
            [(int)$id]
        );

        if (!$msg) {
            Auth::flash('danger', 'Message not found.');
            $this->redirect('/admin/social/inbox');
        }

        $text = trim($this->input('reply_text', ''));
        if (!$text) {
            Auth::flash('warning', 'Reply text cannot be empty.');
            $this->redirect('/admin/social/inbox');
        }

        $account = $this->getAccount($msg['platform']);
        $service = $this->getService($account);
        $result  = $service->reply($msg['platform_msg_id'], $text);

        if ($result['success']) {
            $this->db->update('social_inbox', [
                'replied'    => 1,
                'reply_text' => $text,
            ], 'id = ?', [(int)$id]);
            Auth::flash('success', 'Reply sent successfully.');
        } else {
            Auth::flash('danger', 'Reply failed: ' . ($result['error'] ?? 'Unknown error'));
        }

        $this->redirect('/admin/social/inbox');
    }

    // â”€â”€ Content Distribution â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Show the distribute content form.
     */
    public function distribute(): void
    {
        $contentType = $this->query('type', 'article');
        $contentId   = $this->query('id');

        // Get publishable content
        $articles = $this->db->fetchAll(
            "SELECT id, title, slug, featured_image FROM articles WHERE status = 'published' ORDER BY published_at DESC LIMIT 50"
        );
        $events = $this->db->fetchAll(
            "SELECT id, title, slug, image FROM events WHERE status = 'published' ORDER BY start_date DESC LIMIT 50"
        );

        // Get connected accounts
        $accounts = $this->db->fetchAll('SELECT * FROM social_accounts WHERE is_active = 1 ORDER BY platform');

        // Get mailing lists
        $mailingLists = $this->db->fetchAll('SELECT * FROM mailing_lists WHERE is_active = 1 ORDER BY name');

        // Pre-select content if provided
        $selectedContent = null;
        if ($contentId && $contentType) {
            $table = $contentType === 'article' ? 'articles' : 'events';
            $selectedContent = $this->db->fetch("SELECT * FROM {$table} WHERE id = ?", [(int)$contentId]);
        }

        // Distribution history
        $history = $this->db->fetchAll(
            'SELECT * FROM content_distributions ORDER BY created_at DESC LIMIT 20'
        );

        $this->renderAdmin('admin/social/distribute', [
            'title'       => 'Distribute Content',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => '/admin'],
                ['label' => 'Social Media', 'url' => '/admin/social'],
                ['label' => 'Distribute'],
            ],
            'articles'        => $articles,
            'events'          => $events,
            'accounts'        => $accounts,
            'mailingLists'    => $mailingLists,
            'selectedContent' => $selectedContent,
            'selectedType'    => $contentType,
            'history'         => $history,
        ]);
    }

    /**
     * Process content distribution to selected channels.
     */
    public function distributePost(): void
    {
        $contentType = $this->input('content_type', '');
        $contentId   = (int) $this->input('content_id', 0);
        $message     = trim($this->input('message', ''));
        $imageUrl    = trim($this->input('image_url', ''));
        $channels    = $_POST['channels'] ?? [];
        $lists       = $_POST['mailing_lists'] ?? [];

        if (!$contentType || !$contentId || !$message) {
            Auth::flash('warning', 'Please select content and write a message.');
            $this->redirect('/admin/social/distribute');
        }

        // Get content details for the link
        $table = $contentType === 'article' ? 'articles' : 'events';
        $content = $this->db->fetch("SELECT * FROM {$table} WHERE id = ?", [$contentId]);
        if (!$content) {
            Auth::flash('danger', 'Content not found.');
            $this->redirect('/admin/social/distribute');
        }

        $siteUrl = rtrim(App::config('site.url', ''), '/');
        $slug    = $content['slug'] ?? '';
        $link    = $siteUrl . '/' . ($contentType === 'article' ? "blog/{$slug}" : "events/{$slug}");

        $successCount = 0;
        $errors = [];

        // Post to selected social accounts
        if (is_array($channels)) {
            foreach ($channels as $accountId) {
                $account = $this->db->fetch('SELECT * FROM social_accounts WHERE id = ?', [(int)$accountId]);
                if (!$account) continue;

                $service = $this->getService($account);
                $result  = $service->publish($message, $link, $imageUrl ?: null);

                $status = $result['success'] ? 'sent' : 'failed';

                $this->db->insert('content_distributions', [
                    'content_type'  => $contentType,
                    'content_id'    => $contentId,
                    'channel_type'  => 'social',
                    'channel_id'    => $account['id'],
                    'channel_name'  => ucfirst($account['platform']) . ': ' . $account['account_name'],
                    'status'        => $status,
                    'sent_at'       => $result['success'] ? date('Y-m-d H:i:s') : null,
                    'error_message' => $result['error'] ?? null,
                    'created_by'    => Auth::userId(),
                ]);

                if ($result['success']) {
                    $successCount++;
                } else {
                    $errors[] = ucfirst($account['platform']) . ': ' . ($result['error'] ?? 'Failed');
                }
            }
        }

        // Queue mailing list sends
        if (is_array($lists)) {
            foreach ($lists as $listId) {
                $list = $this->db->fetch('SELECT * FROM mailing_lists WHERE id = ?', [(int)$listId]);
                if (!$list) continue;

                $this->db->insert('content_distributions', [
                    'content_type'  => $contentType,
                    'content_id'    => $contentId,
                    'channel_type'  => 'email',
                    'channel_id'    => $list['id'],
                    'channel_name'  => 'Email: ' . $list['name'],
                    'status'        => 'pending',
                    'created_by'    => Auth::userId(),
                ]);
                $successCount++;
            }
        }

        if ($errors) {
            Auth::flash('warning', "Distributed to {$successCount} channels. Errors: " . implode('; ', $errors));
        } else {
            Auth::flash('success', "Content distributed to {$successCount} channels successfully.");
        }

        $this->logActivity('distribute', $contentType, $contentId,
            "Distributed to {$successCount} channels");
        $this->redirect('/admin/social/distribute');
    }

    // â”€â”€ Quick Post â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Quick-post a custom message to selected platforms (AJAX or form).
     */
    public function quickPost(): void
    {
        $message  = trim($this->input('message', ''));
        $link     = trim($this->input('link', ''));
        $imageUrl = trim($this->input('image_url', ''));
        $channels = $_POST['channels'] ?? [];

        if (!$message) {
            Auth::flash('warning', 'Message cannot be empty.');
            $this->redirect('/admin/social');
        }

        $successCount = 0;
        $errors = [];

        if (is_array($channels)) {
            foreach ($channels as $accountId) {
                $account = $this->db->fetch('SELECT * FROM social_accounts WHERE id = ?', [(int)$accountId]);
                if (!$account) continue;

                $service = $this->getService($account);
                $result  = $service->publish($message, $link ?: null, $imageUrl ?: null);

                if ($result['success']) {
                    $successCount++;
                } else {
                    $errors[] = ucfirst($account['platform']) . ': ' . ($result['error'] ?? 'Failed');
                }
            }
        }

        if ($errors) {
            Auth::flash('warning', "Posted to {$successCount} platforms. Errors: " . implode('; ', $errors));
        } else {
            Auth::flash('success', "Posted to {$successCount} platforms successfully.");
        }

        $this->redirect('/admin/social');
    }

    // â”€â”€ Account Management â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * List / manage connected social accounts.
     */
    public function accounts(): void
    {
        $accounts = $this->db->fetchAll('SELECT * FROM social_accounts ORDER BY platform');

        // Ensure all three platforms have a row
        $existing = array_column($accounts, 'platform');
        foreach (['facebook', 'twitter', 'instagram'] as $platform) {
            if (!in_array($platform, $existing)) {
                $accounts[] = [
                    'id'           => null,
                    'platform'     => $platform,
                    'account_name' => '',
                    'account_id'   => '',
                    'access_token' => '',
                    'page_token'   => '',
                    'is_active'    => 0,
                    'connected_at' => null,
                ];
            }
        }

        // Sort by platform name
        usort($accounts, fn($a, $b) => strcmp($a['platform'], $b['platform']));

        $this->renderAdmin('admin/social/accounts', [
            'title'       => 'Social Accounts',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => '/admin'],
                ['label' => 'Social Media', 'url' => '/admin/social'],
                ['label' => 'Accounts'],
            ],
            'accounts' => $accounts,
        ]);
    }

    /**
     * Save / update a social account connection.
     */
    public function saveAccount(): void
    {
        $platform     = $this->input('platform', '');
        $accountName  = $this->input('account_name', '');
        $accountId    = $this->input('account_id', '');
        $accessToken  = $this->input('access_token', '');
        $pageToken    = $this->input('page_token', '');
        $isActive     = $this->input('is_active') ? 1 : 0;

        if (!in_array($platform, ['facebook', 'twitter', 'instagram'])) {
            Auth::flash('danger', 'Invalid platform.');
            $this->redirect('/admin/social/accounts');
        }

        $existing = $this->db->fetch(
            'SELECT id FROM social_accounts WHERE platform = ?',
            [$platform]
        );

        $data = [
            'platform'     => $platform,
            'account_name' => $accountName,
            'account_id'   => $accountId,
            'access_token' => $accessToken,
            'page_token'   => $pageToken,
            'is_active'    => $isActive,
            'connected_at' => $accessToken ? date('Y-m-d H:i:s') : null,
        ];

        if ($existing) {
            $this->db->update('social_accounts', $data, 'id = ?', [$existing['id']]);
        } else {
            $this->db->insert('social_accounts', $data);
        }

        $this->logActivity('update', 'social_account', null, "Updated {$platform} connection");
        Auth::flash('success', ucfirst($platform) . ' account settings saved.');
        $this->redirect('/admin/social/accounts');
    }

    /**
     * Disconnect / remove a social account.
     */
    public function disconnectAccount(string $id): void
    {
        $account = $this->db->fetch('SELECT * FROM social_accounts WHERE id = ?', [(int)$id]);
        if ($account) {
            $this->db->update('social_accounts', [
                'access_token' => null,
                'refresh_token' => null,
                'page_token'   => null,
                'is_active'    => 0,
                'connected_at' => null,
            ], 'id = ?', [(int)$id]);

            $this->logActivity('disconnect', 'social_account', (int)$id,
                "Disconnected {$account['platform']}");
            Auth::flash('success', ucfirst($account['platform']) . ' account disconnected.');
        }
        $this->redirect('/admin/social/accounts');
    }

    // â”€â”€ Mailing List Management â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function mailingLists(): void
    {
        $lists = $this->db->fetchAll('SELECT * FROM mailing_lists ORDER BY name');

        $this->renderAdmin('admin/social/mailing-lists', [
            'title'       => 'Mailing Lists',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => '/admin'],
                ['label' => 'Social Media', 'url' => '/admin/social'],
                ['label' => 'Mailing Lists'],
            ],
            'lists' => $lists,
        ]);
    }

    public function saveMailingList(): void
    {
        $id          = (int) $this->input('id', 0);
        $name        = $this->input('name', '');
        $description = $this->input('description', '');
        $isActive    = $this->input('is_active') ? 1 : 0;

        if (!$name) {
            Auth::flash('warning', 'List name is required.');
            $this->redirect('/admin/social/mailing-lists');
        }

        $data = [
            'name'        => $name,
            'description' => $description,
            'is_active'   => $isActive,
        ];

        if ($id) {
            $this->db->update('mailing_lists', $data, 'id = ?', [$id]);
        } else {
            $this->db->insert('mailing_lists', $data);
        }

        Auth::flash('success', 'Mailing list saved.');
        $this->redirect('/admin/social/mailing-lists');
    }

    public function deleteMailingList(string $id): void
    {
        $this->db->delete('mailing_lists', 'id = ?', [(int)$id]);
        Auth::flash('success', 'Mailing list deleted.');
        $this->redirect('/admin/social/mailing-lists');
    }

    // â”€â”€ Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Get OAuth app credentials for a platform.
     * Custom (per-site) credentials take priority over shared CMS defaults.
     */
    private function getOAuthCredentials(string $platform): array
    {
        switch ($platform) {
            case 'facebook':
                return [
                    'app_id'     => App::config('social.custom_facebook_app_id', '')
                                 ?: App::config('social.facebook_app_id', ''),
                    'app_secret' => App::config('social.custom_facebook_app_secret', '')
                                 ?: App::config('social.facebook_app_secret', ''),
                ];
            case 'instagram':
                return [
                    'app_id'     => App::config('social.custom_instagram_app_id', '')
                                 ?: App::config('social.instagram_app_id', '')
                                 ?: App::config('social.custom_facebook_app_id', '')
                                 ?: App::config('social.facebook_app_id', ''),
                    'app_secret' => App::config('social.custom_instagram_app_secret', '')
                                 ?: App::config('social.instagram_app_secret', '')
                                 ?: App::config('social.custom_facebook_app_secret', '')
                                 ?: App::config('social.facebook_app_secret', ''),
                ];
            case 'twitter':
                return [
                    'app_id'     => App::config('social.custom_twitter_api_key', '')
                                 ?: App::config('social.twitter_api_key', ''),
                    'app_secret' => App::config('social.custom_twitter_api_secret', '')
                                 ?: App::config('social.twitter_api_secret', ''),
                ];
            default:
                return ['app_id' => '', 'app_secret' => ''];
        }
    }

    // â”€â”€ OAuth Connect & Callback â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Check if this install should use the auth proxy or direct OAuth.
     * Direct OAuth is used when custom per-site credentials are configured.
     */
    private function shouldUseProxy(string $platform): bool
    {
        $proxyUrl = App::config('social.auth_proxy_url', '');
        if (!$proxyUrl) {
            return false;
        }

        // If custom credentials are set, use direct OAuth instead of proxy
        $creds = $this->getOAuthCredentials($platform);
        return empty($creds['app_id']);
    }

    /**
     * Redirect the admin to the platform's OAuth authorization page.
     * Routes through the auth proxy if configured, or directly if custom credentials are set.
     */
    public function oauthConnect(string $platform): void
    {
        if (!in_array($platform, ['facebook', 'twitter', 'instagram'])) {
            Auth::flash('danger', 'Invalid platform.');
            $this->redirect('/admin/social/accounts');
        }

        // Generate a random state parameter and store it in session to prevent CSRF
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state_' . $platform] = $state;

        // â”€â”€ Proxy mode: redirect to central auth proxy â”€â”€
        if ($this->shouldUseProxy($platform)) {
            $proxyUrl = rtrim(App::config('social.auth_proxy_url', ''), '/');
            $siteUrl  = rtrim(App::config('site.url', ''), '/');

            $authUrl = $proxyUrl . '/connect/' . $platform . '?' . http_build_query([
                'origin' => $siteUrl,
                'state'  => $state,
            ]);

            header('Location: ' . $authUrl);
            exit;
        }

        // â”€â”€ Direct mode: redirect straight to the platform â”€â”€
        $siteUrl     = rtrim(App::config('site.url', ''), '/');
        $redirectUri = $siteUrl . '/admin/social/callback/' . $platform;

        switch ($platform) {
            case 'facebook':
                $creds = $this->getOAuthCredentials('facebook');
                if (!$creds['app_id']) {
                    Auth::flash('danger', 'Facebook App ID is not configured.');
                    $this->redirect('/admin/social/accounts');
                }
                $scopes = 'pages_manage_posts,pages_read_engagement,pages_messaging,pages_read_user_content,pages_show_list';
                $authUrl = 'https://www.facebook.com/v19.0/dialog/oauth?'
                    . http_build_query([
                        'client_id'    => $creds['app_id'],
                        'redirect_uri' => $redirectUri,
                        'scope'        => $scopes,
                        'state'        => $state,
                        'response_type' => 'code',
                    ]);
                break;

            case 'instagram':
                $creds = $this->getOAuthCredentials('instagram');
                if (!$creds['app_id']) {
                    Auth::flash('danger', 'Instagram/Facebook App ID is not configured.');
                    $this->redirect('/admin/social/accounts');
                }
                $scopes = 'instagram_basic,instagram_content_publish,instagram_manage_comments,instagram_manage_messages,pages_show_list,pages_read_engagement';
                $authUrl = 'https://www.facebook.com/v19.0/dialog/oauth?'
                    . http_build_query([
                        'client_id'    => $creds['app_id'],
                        'redirect_uri' => $redirectUri,
                        'scope'        => $scopes,
                        'state'        => $state,
                        'response_type' => 'code',
                    ]);
                break;

            case 'twitter':
                $creds = $this->getOAuthCredentials('twitter');
                if (!$creds['app_id']) {
                    Auth::flash('danger', 'Twitter API Key is not configured.');
                    $this->redirect('/admin/social/accounts');
                }
                // Twitter OAuth 2.0 with PKCE
                $codeVerifier  = bin2hex(random_bytes(32));
                $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
                $_SESSION['oauth_code_verifier_twitter'] = $codeVerifier;

                $scopes  = 'tweet.read tweet.write users.read dm.read offline.access';
                $authUrl = 'https://twitter.com/i/oauth2/authorize?'
                    . http_build_query([
                        'response_type'         => 'code',
                        'client_id'             => $creds['app_id'],
                        'redirect_uri'          => $redirectUri,
                        'scope'                 => $scopes,
                        'state'                 => $state,
                        'code_challenge'        => $codeChallenge,
                        'code_challenge_method' => 'S256',
                    ]);
                break;
        }

        header('Location: ' . $authUrl);
        exit;
    }

    /**
     * Handle the OAuth callback â€” either from the auth proxy or directly from the platform.
     */
    public function oauthCallback(string $platform): void
    {
        if (!in_array($platform, ['facebook', 'twitter', 'instagram'])) {
            Auth::flash('danger', 'Invalid platform.');
            $this->redirect('/admin/social/accounts');
        }

        // Verify state to prevent CSRF
        $state = $this->query('state', '');
        $expectedState = $_SESSION['oauth_state_' . $platform] ?? '';
        unset($_SESSION['oauth_state_' . $platform]);

        if (!$state || !$expectedState || !hash_equals($expectedState, $state)) {
            Auth::flash('danger', 'Invalid OAuth state. Please try connecting again.');
            $this->redirect('/admin/social/accounts');
        }

        // Check for proxy errors
        $proxyError = $this->query('proxy_error', '');
        if ($proxyError) {
            Auth::flash('danger', 'Authorization failed: ' . $proxyError);
            $this->redirect('/admin/social/accounts');
        }

        // â”€â”€ Proxy mode: decrypt the token payload from the proxy â”€â”€
        $proxyPayload = $this->query('proxy_payload', '');
        if ($proxyPayload) {
            $this->handleProxyCallback($platform, $proxyPayload);
            return;
        }

        // â”€â”€ Direct mode: exchange the code ourselves â”€â”€
        // Check for errors from the platform
        $error = $this->query('error', '');
        if ($error) {
            $errorDesc = $this->query('error_description', $error);
            Auth::flash('danger', 'Authorization denied: ' . $errorDesc);
            $this->redirect('/admin/social/accounts');
        }

        $code = $this->query('code', '');
        if (!$code) {
            Auth::flash('danger', 'No authorization code received.');
            $this->redirect('/admin/social/accounts');
        }

        $siteUrl     = rtrim(App::config('site.url', ''), '/');
        $redirectUri = $siteUrl . '/admin/social/callback/' . $platform;

        switch ($platform) {
            case 'facebook':
                $this->handleFacebookCallback($code, $redirectUri);
                break;
            case 'instagram':
                $this->handleInstagramCallback($code, $redirectUri);
                break;
            case 'twitter':
                $this->handleTwitterCallback($code, $redirectUri);
                break;
        }
    }

    /**
     * Handle a callback from the auth proxy â€” decrypt and save the token payload.
     */
    private function handleProxyCallback(string $platform, string $encryptedPayload): void
    {
        $secret = App::config('social.auth_proxy_secret', '');
        if (!$secret) {
            Auth::flash('danger', 'Auth proxy secret is not configured.');
            $this->redirect('/admin/social/accounts');
        }

        $tokenData = $this->decryptProxyPayload($encryptedPayload, $secret);
        if (!$tokenData) {
            Auth::flash('danger', 'Failed to decrypt auth proxy response. Check the proxy secret configuration.');
            $this->redirect('/admin/social/accounts');
        }

        // Build the account data from the proxy response
        $accountData = [
            'access_token'  => $tokenData['access_token'] ?? '',
            'token_expires' => isset($tokenData['token_expires'])
                ? date('Y-m-d H:i:s', $tokenData['token_expires'])
                : null,
        ];

        if (!empty($tokenData['refresh_token'])) {
            $accountData['refresh_token'] = $tokenData['refresh_token'];
        }

        switch ($platform) {
            case 'facebook':
                if (!empty($tokenData['page_id'])) {
                    $accountData['account_name'] = $tokenData['page_name'] ?? '';
                    $accountData['account_id']   = $tokenData['page_id'];
                    $accountData['page_token']   = $tokenData['page_token'] ?? '';
                }
                break;

            case 'instagram':
                if (!empty($tokenData['account_id'])) {
                    $accountData['account_name'] = $tokenData['account_name'] ?? '';
                    $accountData['account_id']   = $tokenData['account_id'];
                }
                break;

            case 'twitter':
                if (!empty($tokenData['account_id'])) {
                    $accountData['account_name'] = $tokenData['account_name'] ?? '';
                    $accountData['account_id']   = $tokenData['account_id'];
                }
                break;
        }

        $this->saveSocialAccount($platform, $accountData);

        $displayName = $accountData['account_name'] ?? '';
        $this->logActivity('connect', 'social_account', null, "Connected {$platform} via auth proxy");
        Auth::flash('success', ucfirst($platform) . ' connected successfully!' . ($displayName ? ' Account: ' . $displayName : ''));
        $this->redirect('/admin/social/accounts');
    }

    /**
     * Decrypt a payload from the auth proxy using AES-256-GCM.
     */
    private function decryptProxyPayload(string $encrypted, string $secret): ?array
    {
        // URL-safe base64 decode
        $packed = base64_decode(strtr($encrypted, '-_', '+/'));
        if (!$packed || strlen($packed) < 28) { // 12 (IV) + 16 (tag) minimum
            return null;
        }

        $key  = hash('sha256', $secret, true);
        $iv   = substr($packed, 0, 12);
        $tag  = substr($packed, 12, 16);
        $ciphertext = substr($packed, 28);

        $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plaintext === false) {
            return null;
        }

        return json_decode($plaintext, true);
    }

    /**
     * Exchange Facebook auth code for tokens and save the account.
     */
    private function handleFacebookCallback(string $code, string $redirectUri): void
    {
        $creds     = $this->getOAuthCredentials('facebook');
        $appId     = $creds['app_id'];
        $appSecret = $creds['app_secret'];

        // Exchange code for short-lived user access token
        $tokenUrl = 'https://graph.facebook.com/v19.0/oauth/access_token?' . http_build_query([
            'client_id'     => $appId,
            'client_secret' => $appSecret,
            'redirect_uri'  => $redirectUri,
            'code'          => $code,
        ]);

        $result = $this->httpGet($tokenUrl);
        if (!$result || empty($result['access_token'])) {
            Auth::flash('danger', 'Failed to get Facebook access token: ' . ($result['error']['message'] ?? 'Unknown error'));
            $this->redirect('/admin/social/accounts');
        }

        $shortToken = $result['access_token'];

        // Exchange for long-lived token (~60 days)
        $longTokenUrl = 'https://graph.facebook.com/v19.0/oauth/access_token?' . http_build_query([
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => $appId,
            'client_secret'     => $appSecret,
            'fb_exchange_token' => $shortToken,
        ]);

        $longResult = $this->httpGet($longTokenUrl);
        $userToken  = $longResult['access_token'] ?? $shortToken;
        $expiresIn  = $longResult['expires_in'] ?? 5184000; // default 60 days

        // Get list of pages the user manages
        $pagesUrl = 'https://graph.facebook.com/v19.0/me/accounts?access_token=' . urlencode($userToken);
        $pagesResult = $this->httpGet($pagesUrl);

        if (empty($pagesResult['data'])) {
            Auth::flash('warning', 'Facebook connected, but no Pages found. Make sure you manage at least one Facebook Page.');
            $this->saveSocialAccount('facebook', [
                'access_token' => $userToken,
                'token_expires' => date('Y-m-d H:i:s', time() + $expiresIn),
            ]);
            $this->redirect('/admin/social/accounts');
        }

        // Use the first page (or the user can change later)
        $page = $pagesResult['data'][0];

        $this->saveSocialAccount('facebook', [
            'account_name'  => $page['name'] ?? '',
            'account_id'    => $page['id'] ?? '',
            'access_token'  => $userToken,
            'page_token'    => $page['access_token'] ?? '',
            'token_expires' => date('Y-m-d H:i:s', time() + $expiresIn),
        ]);

        $this->logActivity('connect', 'social_account', null, 'Connected Facebook via OAuth');
        Auth::flash('success', 'Facebook connected successfully! Page: ' . ($page['name'] ?? 'Unknown'));
        $this->redirect('/admin/social/accounts');
    }

    /**
     * Exchange Instagram/Facebook auth code for tokens and find the IG Business Account.
     */
    private function handleInstagramCallback(string $code, string $redirectUri): void
    {
        $creds     = $this->getOAuthCredentials('instagram');
        $appId     = $creds['app_id'];
        $appSecret = $creds['app_secret'];

        // Exchange code for access token
        $tokenUrl = 'https://graph.facebook.com/v19.0/oauth/access_token?' . http_build_query([
            'client_id'     => $appId,
            'client_secret' => $appSecret,
            'redirect_uri'  => $redirectUri,
            'code'          => $code,
        ]);

        $result = $this->httpGet($tokenUrl);
        if (!$result || empty($result['access_token'])) {
            Auth::flash('danger', 'Failed to get Instagram access token: ' . ($result['error']['message'] ?? 'Unknown error'));
            $this->redirect('/admin/social/accounts');
        }

        $userToken = $result['access_token'];

        // Exchange for long-lived token
        $longTokenUrl = 'https://graph.facebook.com/v19.0/oauth/access_token?' . http_build_query([
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => $appId,
            'client_secret'     => $appSecret,
            'fb_exchange_token' => $userToken,
        ]);
        $longResult = $this->httpGet($longTokenUrl);
        $accessToken = $longResult['access_token'] ?? $userToken;
        $expiresIn   = $longResult['expires_in'] ?? 5184000;

        // Find the user's Pages, then their linked Instagram Business Account
        $pagesUrl = 'https://graph.facebook.com/v19.0/me/accounts?fields=id,name,instagram_business_account&access_token=' . urlencode($accessToken);
        $pagesResult = $this->httpGet($pagesUrl);

        $igAccountId   = null;
        $igAccountName = '';

        foreach ($pagesResult['data'] ?? [] as $page) {
            if (!empty($page['instagram_business_account']['id'])) {
                $igAccountId = $page['instagram_business_account']['id'];
                // Fetch IG account name
                $igInfoUrl = 'https://graph.facebook.com/v19.0/' . $igAccountId . '?fields=username,name&access_token=' . urlencode($accessToken);
                $igInfo = $this->httpGet($igInfoUrl);
                $igAccountName = $igInfo['username'] ?? $igInfo['name'] ?? $page['name'] ?? '';
                break;
            }
        }

        if (!$igAccountId) {
            Auth::flash('warning', 'Connected to Facebook, but no Instagram Business Account was found. Make sure your Instagram is a Business/Creator account linked to a Facebook Page.');
            $this->saveSocialAccount('instagram', [
                'access_token'  => $accessToken,
                'token_expires' => date('Y-m-d H:i:s', time() + $expiresIn),
            ]);
            $this->redirect('/admin/social/accounts');
        }

        $this->saveSocialAccount('instagram', [
            'account_name'  => $igAccountName,
            'account_id'    => $igAccountId,
            'access_token'  => $accessToken,
            'token_expires' => date('Y-m-d H:i:s', time() + $expiresIn),
        ]);

        $this->logActivity('connect', 'social_account', null, 'Connected Instagram via OAuth');
        Auth::flash('success', 'Instagram connected successfully! Account: @' . $igAccountName);
        $this->redirect('/admin/social/accounts');
    }

    /**
     * Exchange Twitter auth code for tokens using PKCE.
     */
    private function handleTwitterCallback(string $code, string $redirectUri): void
    {
        $creds        = $this->getOAuthCredentials('twitter');
        $clientId     = $creds['app_id'];
        $clientSecret = $creds['app_secret'];
        $codeVerifier = $_SESSION['oauth_code_verifier_twitter'] ?? '';
        unset($_SESSION['oauth_code_verifier_twitter']);

        if (!$codeVerifier) {
            Auth::flash('danger', 'Missing OAuth code verifier. Please try connecting again.');
            $this->redirect('/admin/social/accounts');
        }

        // Exchange authorization code for access token
        $tokenUrl = 'https://api.twitter.com/2/oauth2/token';
        $postData = http_build_query([
            'code'          => $code,
            'grant_type'    => 'authorization_code',
            'client_id'     => $clientId,
            'redirect_uri'  => $redirectUri,
            'code_verifier' => $codeVerifier,
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $tokenUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_USERAGENT      => 'IGAPortal/1.0',
            CURLOPT_TIMEOUT        => 30,
        ]);

        // If client secret is set, use HTTP Basic Auth
        if ($clientSecret) {
            curl_setopt($ch, CURLOPT_USERPWD, $clientId . ':' . $clientSecret);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $tokenData = json_decode($response, true);

        if ($httpCode < 200 || $httpCode >= 300 || empty($tokenData['access_token'])) {
            $error = $tokenData['error_description'] ?? $tokenData['error'] ?? 'Unknown error';
            Auth::flash('danger', 'Failed to get Twitter access token: ' . $error);
            $this->redirect('/admin/social/accounts');
        }

        $accessToken  = $tokenData['access_token'];
        $refreshToken = $tokenData['refresh_token'] ?? null;
        $expiresIn    = $tokenData['expires_in'] ?? 7200;

        // Get the authenticated user's info
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://api.twitter.com/2/users/me?user.fields=name,username,profile_image_url',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_USERAGENT      => 'IGAPortal/1.0',
            CURLOPT_TIMEOUT        => 30,
        ]);
        $userResponse = curl_exec($ch);
        curl_close($ch);

        $userData = json_decode($userResponse, true);
        $userName = $userData['data']['name'] ?? '';
        $userId   = $userData['data']['id'] ?? '';

        $this->saveSocialAccount('twitter', [
            'account_name'  => $userName ?: ($userData['data']['username'] ?? ''),
            'account_id'    => $userId,
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'token_expires' => date('Y-m-d H:i:s', time() + $expiresIn),
        ]);

        $this->logActivity('connect', 'social_account', null, 'Connected Twitter via OAuth');
        Auth::flash('success', 'Twitter/X connected successfully! User: @' . ($userData['data']['username'] ?? $userName));
        $this->redirect('/admin/social/accounts');
    }

    /**
     * Save or update a social account record after OAuth.
     */
    private function saveSocialAccount(string $platform, array $data): void
    {
        $data['platform']     = $platform;
        $data['is_active']    = 1;
        $data['connected_at'] = date('Y-m-d H:i:s');

        $existing = $this->db->fetch('SELECT id FROM social_accounts WHERE platform = ?', [$platform]);

        if ($existing) {
            $this->db->update('social_accounts', $data, 'id = ?', [$existing['id']]);
        } else {
            $this->db->insert('social_accounts', $data);
        }
    }

    /**
     * Simple GET request helper for OAuth token exchanges.
     */
    private function httpGet(string $url): ?array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERAGENT      => 'IGAPortal/1.0',
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    private function getAccount(string $platform): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM social_accounts WHERE platform = ? AND is_active = 1',
            [$platform]
        );
    }

    private function getService(array $account): AbstractSocialService
    {
        return match ($account['platform']) {
            'facebook'  => new FacebookService($account),
            'twitter'   => new TwitterService($account),
            'instagram' => new InstagramService($account),
            default     => throw new \InvalidArgumentException("Unknown platform: {$account['platform']}"),
        };
    }
}
