<?php
/**
 * IGA Portal â€” Facebook Graph API Service
 *
 * Handles reading posts, comments, messages and publishing to a Facebook Page
 * via the Graph API v19.0.
 *
 * Requirements:
 *   - A Facebook App with pages_manage_posts, pages_read_engagement,
 *     pages_messaging, pages_read_user_content permissions.
 *   - A Page Access Token stored in social_accounts.page_token.
 */

namespace IGA\Module\Social\Services;

use IGA\App;

class FacebookService extends AbstractSocialService
{
    private const API_BASE = 'https://graph.facebook.com/v19.0';

    public function getPlatformName(): string
    {
        return 'facebook';
    }

    /**
     * Get the Page token (preferred) or fall back to user access token.
     */
    private function getToken(): string
    {
        return $this->account['page_token']
            ?: $this->account['access_token']
            ?: '';
    }

    // â”€â”€ Posts â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function fetchPosts(int $limit = 25): array
    {
        if (!$this->isConnected()) {
            return [];
        }

        $pageId = $this->account['account_id'];
        $token  = $this->getToken();
        $fields = 'id,message,created_time,permalink_url,full_picture,shares,likes.summary(true),comments.summary(true)';

        $url = self::API_BASE . "/{$pageId}/posts?fields=" . urlencode($fields)
             . "&limit={$limit}&access_token=" . urlencode($token);

        $result = $this->httpRequest('GET', $url);

        if (!$result['success'] || !isset($result['body']['data'])) {
            return [];
        }

        $posts = [];
        foreach ($result['body']['data'] as $post) {
            $posts[] = [
                'platform'    => 'facebook',
                'id'          => $post['id'],
                'message'     => $post['message'] ?? '',
                'image'       => $post['full_picture'] ?? null,
                'link'        => $post['permalink_url'] ?? '',
                'likes'       => $post['likes']['summary']['total_count'] ?? 0,
                'comments'    => $post['comments']['summary']['total_count'] ?? 0,
                'shares'      => $post['shares']['count'] ?? 0,
                'created_at'  => $post['created_time'] ?? '',
            ];
        }
        return $posts;
    }

    // â”€â”€ Inbox (comments + page messages) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function fetchInbox(int $limit = 50): array
    {
        if (!$this->isConnected()) {
            return [];
        }

        $messages = [];
        $messages = array_merge($messages, $this->fetchComments($limit));
        $messages = array_merge($messages, $this->fetchConversations($limit));

        $this->storeInboxMessages($messages);
        return $messages;
    }

    private function fetchComments(int $limit): array
    {
        $pageId = $this->account['account_id'];
        $token  = $this->getToken();
        $fields = 'id,message,from,created_time,post_id';

        // Fetch recent page posts, then their comments
        $postsUrl = self::API_BASE . "/{$pageId}/posts?fields=id&limit=10&access_token=" . urlencode($token);
        $postsResult = $this->httpRequest('GET', $postsUrl);

        if (!$postsResult['success'] || empty($postsResult['body']['data'])) {
            return [];
        }

        $comments = [];
        foreach ($postsResult['body']['data'] as $post) {
            $commentsUrl = self::API_BASE . "/{$post['id']}/comments?fields=" . urlencode($fields)
                         . "&limit={$limit}&access_token=" . urlencode($token);
            $commResult = $this->httpRequest('GET', $commentsUrl);

            if ($commResult['success'] && !empty($commResult['body']['data'])) {
                foreach ($commResult['body']['data'] as $c) {
                    $comments[] = [
                        'platform_msg_id'  => $c['id'],
                        'platform_post_id' => $post['id'],
                        'message_type'     => 'comment',
                        'author_name'      => $c['from']['name'] ?? 'Unknown',
                        'author_id'        => $c['from']['id'] ?? null,
                        'body'             => $c['message'] ?? '',
                        'received_at'      => date('Y-m-d H:i:s', strtotime($c['created_time'])),
                    ];
                }
            }
        }
        return $comments;
    }

    private function fetchConversations(int $limit): array
    {
        $pageId = $this->account['account_id'];
        $token  = $this->getToken();

        $url = self::API_BASE . "/{$pageId}/conversations?fields=id,participants,messages{message,from,created_time}"
             . "&limit={$limit}&access_token=" . urlencode($token);

        $result = $this->httpRequest('GET', $url);

        if (!$result['success'] || empty($result['body']['data'])) {
            return [];
        }

        $messages = [];
        foreach ($result['body']['data'] as $convo) {
            if (empty($convo['messages']['data'])) continue;
            foreach ($convo['messages']['data'] as $m) {
                $messages[] = [
                    'platform_msg_id'  => $m['id'] ?? $convo['id'],
                    'platform_post_id' => null,
                    'message_type'     => 'message',
                    'author_name'      => $m['from']['name'] ?? 'Unknown',
                    'author_id'        => $m['from']['id'] ?? null,
                    'body'             => $m['message'] ?? '',
                    'received_at'      => date('Y-m-d H:i:s', strtotime($m['created_time'])),
                ];
            }
        }
        return $messages;
    }

    // â”€â”€ Publish â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function publish(string $message, ?string $link = null, ?string $imageUrl = null): array
    {
        if (!$this->isConnected()) {
            return ['success' => false, 'error' => 'Facebook account not connected'];
        }

        $pageId = $this->account['account_id'];
        $token  = $this->getToken();

        $params = ['message' => $message, 'access_token' => $token];
        if ($link) {
            $params['link'] = $link;
        }

        // If image is provided, post to /photos instead
        $endpoint = $imageUrl ? "/{$pageId}/photos" : "/{$pageId}/feed";
        if ($imageUrl) {
            $params['url'] = $imageUrl;
            $params['caption'] = $message;
            unset($params['message']);
        }

        $url = self::API_BASE . $endpoint;
        $result = $this->httpRequest('POST', $url, [
            'Content-Type: application/x-www-form-urlencoded',
        ], $params);

        if ($result['success'] && isset($result['body']['id'])) {
            $this->recordPost($result['body']['id'], $message, $link);
            return ['success' => true, 'post_id' => $result['body']['id']];
        }

        return [
            'success' => false,
            'error'   => $result['body']['error']['message'] ?? 'Unknown Facebook API error',
        ];
    }

    // â”€â”€ Reply â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function reply(string $platformMsgId, string $text): array
    {
        if (!$this->isConnected()) {
            return ['success' => false, 'error' => 'Not connected'];
        }

        $token = $this->getToken();
        $url   = self::API_BASE . "/{$platformMsgId}/comments";

        $result = $this->httpRequest('POST', $url, [
            'Content-Type: application/x-www-form-urlencoded',
        ], ['message' => $text, 'access_token' => $token]);

        return [
            'success' => $result['success'],
            'reply_id' => $result['body']['id'] ?? null,
            'error'    => $result['body']['error']['message'] ?? null,
        ];
    }

    // â”€â”€ Metrics â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function getMetrics(): array
    {
        if (!$this->isConnected()) {
            return [];
        }

        $pageId = $this->account['account_id'];
        $token  = $this->getToken();
        $fields = 'fan_count,followers_count,name,about,picture';

        $url = self::API_BASE . "/{$pageId}?fields=" . urlencode($fields)
             . "&access_token=" . urlencode($token);

        $result = $this->httpRequest('GET', $url);

        if (!$result['success']) {
            return [];
        }

        return [
            'followers'  => $result['body']['followers_count'] ?? 0,
            'fans'       => $result['body']['fan_count'] ?? 0,
            'name'       => $result['body']['name'] ?? '',
            'avatar'     => $result['body']['picture']['data']['url'] ?? null,
        ];
    }
}
