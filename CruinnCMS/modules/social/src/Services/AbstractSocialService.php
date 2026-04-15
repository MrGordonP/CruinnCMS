<?php
/**
 * CruinnCMS â€” Abstract Social Platform Service
 *
 * Common interface and helpers for all platform API wrappers.
 */

namespace Cruinn\Module\Social\Services;

use Cruinn\Database;
use Cruinn\App;

abstract class AbstractSocialService
{
    protected Database $db;
    protected ?array $account;

    public function __construct(?array $account = null)
    {
        $this->db = Database::getInstance();
        $this->account = $account;
    }

    abstract public function getPlatformName(): string;

    /**
     * Fetch the latest posts from the platform.
     * Returns a normalised array of post objects.
     */
    abstract public function fetchPosts(int $limit = 25): array;

    /**
     * Fetch comments and messages into the inbox.
     */
    abstract public function fetchInbox(int $limit = 50): array;

    /**
     * Publish a new post to the platform.
     */
    abstract public function publish(string $message, ?string $link = null, ?string $imageUrl = null): array;

    /**
     * Reply to a comment or message on the platform.
     */
    abstract public function reply(string $platformMsgId, string $text): array;

    /**
     * Get account metrics / stats.
     */
    abstract public function getMetrics(): array;

    /**
     * Check if the account connection is valid.
     */
    public function isConnected(): bool
    {
        return $this->account
            && !empty($this->account['access_token'])
            && $this->account['is_active'];
    }

    /**
     * Make an HTTP request using cURL.
     */
    protected function httpRequest(string $method, string $url, array $headers = [], $body = null): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_USERAGENT      => 'IGAPortal/1.0',
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? http_build_query($body) : $body);
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => $error, 'http_code' => 0, 'body' => null];
        }

        $decoded = json_decode($response, true);

        return [
            'success'   => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'body'      => $decoded ?? $response,
        ];
    }

    /**
     * Store messages into the social_inbox table, skipping duplicates.
     */
    protected function storeInboxMessages(array $messages): int
    {
        $stored = 0;
        foreach ($messages as $msg) {
            $exists = $this->db->fetchColumn(
                'SELECT COUNT(*) FROM social_inbox WHERE platform_msg_id = ? AND platform = ?',
                [$msg['platform_msg_id'], $this->getPlatformName()]
            );
            if ($exists) continue;

            $this->db->insert('social_inbox', [
                'social_account_id' => $this->account['id'],
                'platform'          => $this->getPlatformName(),
                'message_type'      => $msg['message_type'] ?? 'comment',
                'platform_msg_id'   => $msg['platform_msg_id'],
                'platform_post_id'  => $msg['platform_post_id'] ?? null,
                'author_name'       => $msg['author_name'] ?? 'Unknown',
                'author_id'         => $msg['author_id'] ?? null,
                'author_avatar'     => $msg['author_avatar'] ?? null,
                'body'              => $msg['body'] ?? '',
                'received_at'       => $msg['received_at'] ?? date('Y-m-d H:i:s'),
            ]);
            $stored++;
        }
        return $stored;
    }

    /**
     * Record a published post in social_posts.
     */
    protected function recordPost(string $platformPostId, string $message, ?string $link, string $contentType = null, ?int $contentId = null): int
    {
        return $this->db->insert('social_posts', [
            'social_account_id' => $this->account['id'],
            'platform'          => $this->getPlatformName(),
            'platform_post_id'  => $platformPostId,
            'content_type'      => $contentType,
            'content_id'        => $contentId,
            'message'           => $message,
            'link_url'          => $link,
            'status'            => 'published',
            'published_at'      => date('Y-m-d H:i:s'),
            'created_by'        => \Cruinn\Auth::userId(),
        ]);
    }
}
