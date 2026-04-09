<?php
/**
 * IGA Portal â€” Instagram Graph API Service
 *
 * Handles reading media, comments and publishing content
 * via the Instagram Graph API (Business / Creator accounts only).
 *
 * Requirements:
 *   - A Facebook App linked to an Instagram Business Account.
 *   - Permissions: instagram_basic, instagram_content_publish,
 *     instagram_manage_comments, instagram_manage_messages, pages_show_list.
 *   - Access token stored in social_accounts.access_token.
 *   - Instagram Business Account ID stored in social_accounts.account_id.
 */

namespace IGA\Module\Social\Services;

use IGA\App;

class InstagramService extends AbstractSocialService
{
    private const API_BASE = 'https://graph.facebook.com/v19.0';

    public function getPlatformName(): string
    {
        return 'instagram';
    }

    private function getToken(): string
    {
        return $this->account['access_token'] ?? '';
    }

    // â”€â”€ Posts (Media) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function fetchPosts(int $limit = 25): array
    {
        if (!$this->isConnected()) {
            return [];
        }

        $igId   = $this->account['account_id'];
        $token  = $this->getToken();
        $fields = 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,like_count,comments_count';

        $url = self::API_BASE . "/{$igId}/media?fields=" . urlencode($fields)
             . "&limit={$limit}&access_token=" . urlencode($token);

        $result = $this->httpRequest('GET', $url);

        if (!$result['success'] || empty($result['body']['data'])) {
            return [];
        }

        $posts = [];
        foreach ($result['body']['data'] as $media) {
            $posts[] = [
                'platform'   => 'instagram',
                'id'         => $media['id'],
                'message'    => $media['caption'] ?? '',
                'image'      => $media['media_url'] ?? $media['thumbnail_url'] ?? null,
                'link'       => $media['permalink'] ?? '',
                'likes'      => $media['like_count'] ?? 0,
                'comments'   => $media['comments_count'] ?? 0,
                'shares'     => 0,
                'media_type' => $media['media_type'] ?? 'IMAGE',
                'created_at' => $media['timestamp'] ?? '',
            ];
        }
        return $posts;
    }

    // â”€â”€ Inbox (comments on media) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function fetchInbox(int $limit = 50): array
    {
        if (!$this->isConnected()) {
            return [];
        }

        $igId  = $this->account['account_id'];
        $token = $this->getToken();

        // Get recent media, then fetch comments on each
        $mediaUrl = self::API_BASE . "/{$igId}/media?fields=id&limit=10"
                  . "&access_token=" . urlencode($token);
        $mediaResult = $this->httpRequest('GET', $mediaUrl);

        if (!$mediaResult['success'] || empty($mediaResult['body']['data'])) {
            return [];
        }

        $comments = [];
        foreach ($mediaResult['body']['data'] as $media) {
            $commUrl = self::API_BASE . "/{$media['id']}/comments"
                     . "?fields=id,text,from,timestamp,username&limit={$limit}"
                     . "&access_token=" . urlencode($token);
            $commResult = $this->httpRequest('GET', $commUrl);

            if ($commResult['success'] && !empty($commResult['body']['data'])) {
                foreach ($commResult['body']['data'] as $c) {
                    $comments[] = [
                        'platform_msg_id'  => $c['id'],
                        'platform_post_id' => $media['id'],
                        'message_type'     => 'comment',
                        'author_name'      => $c['username'] ?? ($c['from']['username'] ?? 'Unknown'),
                        'author_id'        => $c['from']['id'] ?? null,
                        'body'             => $c['text'] ?? '',
                        'received_at'      => date('Y-m-d H:i:s', strtotime($c['timestamp'])),
                    ];
                }
            }
        }

        $this->storeInboxMessages($comments);
        return $comments;
    }

    // â”€â”€ Publish â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function publish(string $message, ?string $link = null, ?string $imageUrl = null): array
    {
        if (!$this->isConnected()) {
            return ['success' => false, 'error' => 'Instagram account not connected'];
        }

        if (!$imageUrl) {
            return ['success' => false, 'error' => 'Instagram requires an image URL to publish'];
        }

        $igId  = $this->account['account_id'];
        $token = $this->getToken();

        // Step 1: Create a media container
        $caption = $link ? "{$message}\n\n{$link}" : $message;

        $containerUrl = self::API_BASE . "/{$igId}/media";
        $containerResult = $this->httpRequest('POST', $containerUrl, [
            'Content-Type: application/x-www-form-urlencoded',
        ], [
            'image_url'    => $imageUrl,
            'caption'      => $caption,
            'access_token' => $token,
        ]);

        if (!$containerResult['success'] || empty($containerResult['body']['id'])) {
            return [
                'success' => false,
                'error'   => $containerResult['body']['error']['message'] ?? 'Failed to create media container',
            ];
        }

        $containerId = $containerResult['body']['id'];

        // Step 2: Publish the container
        $publishUrl = self::API_BASE . "/{$igId}/media_publish";
        $publishResult = $this->httpRequest('POST', $publishUrl, [
            'Content-Type: application/x-www-form-urlencoded',
        ], [
            'creation_id'  => $containerId,
            'access_token' => $token,
        ]);

        if ($publishResult['success'] && isset($publishResult['body']['id'])) {
            $mediaId = $publishResult['body']['id'];
            $this->recordPost($mediaId, $message, $link);
            return ['success' => true, 'post_id' => $mediaId];
        }

        return [
            'success' => false,
            'error'   => $publishResult['body']['error']['message'] ?? 'Failed to publish media',
        ];
    }

    // â”€â”€ Reply â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function reply(string $platformMsgId, string $text): array
    {
        if (!$this->isConnected()) {
            return ['success' => false, 'error' => 'Not connected'];
        }

        $token = $this->getToken();
        $url   = self::API_BASE . "/{$platformMsgId}/replies";

        $result = $this->httpRequest('POST', $url, [
            'Content-Type: application/x-www-form-urlencoded',
        ], [
            'message'      => $text,
            'access_token' => $token,
        ]);

        return [
            'success'  => $result['success'],
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

        $igId  = $this->account['account_id'];
        $token = $this->getToken();
        $fields = 'followers_count,media_count,name,profile_picture_url,biography';

        $url = self::API_BASE . "/{$igId}?fields=" . urlencode($fields)
             . "&access_token=" . urlencode($token);

        $result = $this->httpRequest('GET', $url);

        if (!$result['success']) {
            return [];
        }

        return [
            'followers'  => $result['body']['followers_count'] ?? 0,
            'posts'      => $result['body']['media_count'] ?? 0,
            'name'       => $result['body']['name'] ?? '',
            'avatar'     => $result['body']['profile_picture_url'] ?? null,
            'bio'        => $result['body']['biography'] ?? '',
        ];
    }
}
