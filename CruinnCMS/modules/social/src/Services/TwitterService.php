<?php
/**
 * CruinnCMS â€” Twitter/X API v2 Service
 *
 * Handles reading tweets, mentions, DMs and publishing tweets
 * via the Twitter API v2.
 *
 * Requirements:
 *   - A Twitter Developer App with OAuth 2.0 User Context.
 *   - Access token with tweet.read, tweet.write, users.read, dm.read scopes.
 */

namespace Cruinn\Module\Social\Services;

use Cruinn\App;

class TwitterService extends AbstractSocialService
{
    private const API_BASE = 'https://api.twitter.com/2';

    public function getPlatformName(): string
    {
        return 'twitter';
    }

    private function authHeaders(): array
    {
        $token = $this->account['access_token'] ?? '';
        return [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ];
    }

    // â”€â”€ Posts (Tweets) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function fetchPosts(int $limit = 25): array
    {
        if (!$this->isConnected()) {
            return [];
        }

        $userId = $this->account['account_id'];
        $fields = 'created_at,public_metrics,text,entities';
        $url    = self::API_BASE . "/users/{$userId}/tweets?max_results={$limit}"
                . "&tweet.fields=" . urlencode($fields);

        $result = $this->httpRequest('GET', $url, $this->authHeaders());

        if (!$result['success'] || empty($result['body']['data'])) {
            return [];
        }

        $posts = [];
        foreach ($result['body']['data'] as $tweet) {
            $metrics = $tweet['public_metrics'] ?? [];
            $posts[] = [
                'platform'   => 'twitter',
                'id'         => $tweet['id'],
                'message'    => $tweet['text'] ?? '',
                'image'      => null,
                'link'       => 'https://twitter.com/i/status/' . $tweet['id'],
                'likes'      => $metrics['like_count'] ?? 0,
                'comments'   => $metrics['reply_count'] ?? 0,
                'shares'     => $metrics['retweet_count'] ?? 0,
                'created_at' => $tweet['created_at'] ?? '',
            ];
        }
        return $posts;
    }

    // â”€â”€ Inbox (mentions + DMs) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function fetchInbox(int $limit = 50): array
    {
        if (!$this->isConnected()) {
            return [];
        }

        $messages = [];
        $messages = array_merge($messages, $this->fetchMentions($limit));
        $messages = array_merge($messages, $this->fetchDMs($limit));

        $this->storeInboxMessages($messages);
        return $messages;
    }

    private function fetchMentions(int $limit): array
    {
        $userId = $this->account['account_id'];
        $fields = 'created_at,text,author_id,in_reply_to_user_id';
        $url    = self::API_BASE . "/users/{$userId}/mentions?max_results=" . min($limit, 100)
                . "&tweet.fields=" . urlencode($fields)
                . "&expansions=author_id&user.fields=name,profile_image_url";

        $result = $this->httpRequest('GET', $url, $this->authHeaders());

        if (!$result['success'] || empty($result['body']['data'])) {
            return [];
        }

        // Build a user lookup from includes
        $users = [];
        foreach ($result['body']['includes']['users'] ?? [] as $u) {
            $users[$u['id']] = $u;
        }

        $mentions = [];
        foreach ($result['body']['data'] as $tweet) {
            $authorId = $tweet['author_id'] ?? '';
            $user     = $users[$authorId] ?? [];

            $mentions[] = [
                'platform_msg_id'  => $tweet['id'],
                'platform_post_id' => null,
                'message_type'     => 'mention',
                'author_name'      => $user['name'] ?? 'Unknown',
                'author_id'        => $authorId,
                'author_avatar'    => $user['profile_image_url'] ?? null,
                'body'             => $tweet['text'] ?? '',
                'received_at'      => date('Y-m-d H:i:s', strtotime($tweet['created_at'])),
            ];
        }
        return $mentions;
    }

    private function fetchDMs(int $limit): array
    {
        $url = self::API_BASE . "/dm_events?max_results=" . min($limit, 100)
             . "&dm_event.fields=created_at,text,sender_id"
             . "&expansions=sender_id&user.fields=name,profile_image_url";

        $result = $this->httpRequest('GET', $url, $this->authHeaders());

        if (!$result['success'] || empty($result['body']['data'])) {
            return [];
        }

        $users = [];
        foreach ($result['body']['includes']['users'] ?? [] as $u) {
            $users[$u['id']] = $u;
        }

        $dms = [];
        foreach ($result['body']['data'] as $dm) {
            $senderId = $dm['sender_id'] ?? '';
            $user     = $users[$senderId] ?? [];

            $dms[] = [
                'platform_msg_id'  => $dm['id'],
                'platform_post_id' => null,
                'message_type'     => 'message',
                'author_name'      => $user['name'] ?? 'Unknown',
                'author_id'        => $senderId,
                'author_avatar'    => $user['profile_image_url'] ?? null,
                'body'             => $dm['text'] ?? '',
                'received_at'      => date('Y-m-d H:i:s', strtotime($dm['created_at'])),
            ];
        }
        return $dms;
    }

    // â”€â”€ Publish â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function publish(string $message, ?string $link = null, ?string $imageUrl = null): array
    {
        if (!$this->isConnected()) {
            return ['success' => false, 'error' => 'Twitter account not connected'];
        }

        $text = $link ? "{$message} {$link}" : $message;

        // Truncate to 280 characters
        if (mb_strlen($text) > 280) {
            $text = mb_substr($text, 0, 277) . '...';
        }

        $payload = json_encode(['text' => $text]);

        $result = $this->httpRequest('POST', self::API_BASE . '/tweets',
            $this->authHeaders(),
            $payload
        );

        if ($result['success'] && isset($result['body']['data']['id'])) {
            $tweetId = $result['body']['data']['id'];
            $this->recordPost($tweetId, $message, $link);
            return ['success' => true, 'post_id' => $tweetId];
        }

        return [
            'success' => false,
            'error'   => $result['body']['detail'] ?? $result['body']['title'] ?? 'Unknown Twitter API error',
        ];
    }

    // â”€â”€ Reply â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function reply(string $platformMsgId, string $text): array
    {
        if (!$this->isConnected()) {
            return ['success' => false, 'error' => 'Not connected'];
        }

        $payload = json_encode([
            'text'  => $text,
            'reply' => ['in_reply_to_tweet_id' => $platformMsgId],
        ]);

        $result = $this->httpRequest('POST', self::API_BASE . '/tweets',
            $this->authHeaders(),
            $payload
        );

        return [
            'success'  => $result['success'],
            'reply_id' => $result['body']['data']['id'] ?? null,
            'error'    => $result['body']['detail'] ?? null,
        ];
    }

    // â”€â”€ Metrics â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function getMetrics(): array
    {
        if (!$this->isConnected()) {
            return [];
        }

        $userId = $this->account['account_id'];
        $url    = self::API_BASE . "/users/{$userId}?user.fields=public_metrics,name,profile_image_url,description";

        $result = $this->httpRequest('GET', $url, $this->authHeaders());

        if (!$result['success'] || empty($result['body']['data'])) {
            return [];
        }

        $data    = $result['body']['data'];
        $metrics = $data['public_metrics'] ?? [];

        return [
            'followers' => $metrics['followers_count'] ?? 0,
            'following' => $metrics['following_count'] ?? 0,
            'tweets'    => $metrics['tweet_count'] ?? 0,
            'name'      => $data['name'] ?? '',
            'avatar'    => $data['profile_image_url'] ?? null,
        ];
    }
}
