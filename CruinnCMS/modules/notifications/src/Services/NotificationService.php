<?php

declare(strict_types=1);

namespace Cruinn\Module\Notifications\Services;

use Cruinn\Database;

class NotificationService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── Notifications ─────────────────────────────────────────

    public function forUser(int $userId, array $filters = []): array
    {
        $where  = ['n.user_id = ?'];
        $params = [$userId];

        if (!empty($filters['category'])) {
            $where[]  = 'n.category = ?';
            $params[] = $filters['category'];
        }
        if (!empty($filters['unread'])) {
            $where[] = 'n.read_at IS NULL';
        }

        $sql = 'SELECT n.*, s.title AS subject_title
                FROM notifications n
                LEFT JOIN subjects s ON s.id = n.subject_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY n.created_at DESC
                LIMIT 200';

        return $this->db->fetchAll($sql, $params);
    }

    public function unreadCount(int $userId): int
    {
        return (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL',
            [$userId]
        );
    }

    public function categories(int $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT DISTINCT category FROM notifications WHERE user_id = ? ORDER BY category',
            [$userId]
        );
        return array_column($rows, 'category');
    }

    public function markRead(int $notificationId, int $userId): void
    {
        $this->db->execute(
            'UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ? AND read_at IS NULL',
            [$notificationId, $userId]
        );
    }

    public function markAllRead(int $userId): void
    {
        $this->db->execute(
            'UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL',
            [$userId]
        );
    }

    public function create(int $userId, string $category, string $title, ?string $body = null, ?string $url = null, ?int $subjectId = null): int
    {
        return (int) $this->db->insert('notifications', [
            'user_id'    => $userId,
            'category'   => $category,
            'title'      => $title,
            'body'       => $body,
            'url'        => $url,
            'subject_id' => $subjectId,
        ]);
    }

    // ── Preferences ───────────────────────────────────────────

    public function preferencesForUser(int $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT category, in_app, email_frequency FROM notification_preferences WHERE user_id = ?',
            [$userId]
        );
        $prefs = [];
        foreach ($rows as $row) {
            $prefs[$row['category']] = [
                'in_app'          => (bool) $row['in_app'],
                'email_frequency' => $row['email_frequency'],
            ];
        }
        return $prefs;
    }

    public function savePreferences(int $userId, array $inApp, array $emailFrequency): void
    {
        $allowedFreq = ['immediate', 'daily', 'weekly', 'off'];
        $categories  = array_unique(array_merge(array_keys($inApp), array_keys($emailFrequency)));

        foreach ($categories as $category) {
            $category = substr(preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $category)), 0, 60);
            if ($category === '') {
                continue;
            }
            $freq = $emailFrequency[$category] ?? 'daily';
            if (!in_array($freq, $allowedFreq, true)) {
                $freq = 'daily';
            }
            $this->db->execute(
                'INSERT INTO notification_preferences (user_id, category, in_app, email_frequency)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE in_app = VALUES(in_app), email_frequency = VALUES(email_frequency)',
                [$userId, $category, !empty($inApp[$category]) ? 1 : 0, $freq]
            );
        }
    }

    // ── Mailing lists ─────────────────────────────────────────

    public function publicLists(): array
    {
        return $this->db->fetchAll(
            'SELECT id, slug, name, description FROM mailing_lists WHERE is_public = 1 AND is_active = 1 ORDER BY name'
        );
    }

    public function listsForUser(int $userId): array
    {
        $lists = $this->publicLists();
        if (empty($lists)) {
            return [];
        }
        $ids   = array_column($lists, 'id');
        $ph    = implode(',', array_fill(0, count($ids), '?'));
        $subs  = $this->db->fetchAll(
            "SELECT list_id FROM mailing_list_subscriptions WHERE user_id = ? AND list_id IN ({$ph}) AND status = 'active'",
            array_merge([$userId], $ids)
        );
        $subscribed = array_flip(array_column($subs, 'list_id'));
        foreach ($lists as &$list) {
            $list['subscribed'] = isset($subscribed[$list['id']]);
        }
        return $lists;
    }

    public function subscribe(int $listId, int $userId, string $email, string $name = ''): void
    {
        $token    = bin2hex(random_bytes(32));
        $existing = $this->db->fetch(
            'SELECT id, status FROM mailing_list_subscriptions WHERE list_id = ? AND user_id = ?',
            [$listId, $userId]
        );
        if ($existing) {
            $this->db->execute(
                "UPDATE mailing_list_subscriptions SET status = 'active', subscribed_at = NOW(), unsubscribed_at = NULL WHERE id = ?",
                [(int) $existing['id']]
            );
        } else {
            $this->db->insert('mailing_list_subscriptions', [
                'list_id'           => $listId,
                'user_id'           => $userId,
                'email'             => $email,
                'name'              => $name ?: null,
                'unsubscribe_token' => $token,
                'status'            => 'active',
            ]);
        }
    }

    public function unsubscribeByUser(int $listId, int $userId): void
    {
        $this->db->execute(
            "UPDATE mailing_list_subscriptions SET status = 'unsubscribed', unsubscribed_at = NOW() WHERE list_id = ? AND user_id = ?",
            [$listId, $userId]
        );
    }

    public function unsubscribeByToken(string $token): ?array
    {
        $sub = $this->db->fetch(
            "SELECT mls.*, ml.name AS list_name
             FROM mailing_list_subscriptions mls
             JOIN mailing_lists ml ON ml.id = mls.list_id
             WHERE mls.unsubscribe_token = ? AND mls.status = 'active'",
            [$token]
        );
        if (!$sub) {
            return null;
        }
        $this->db->execute(
            "UPDATE mailing_list_subscriptions SET status = 'unsubscribed', unsubscribed_at = NOW() WHERE id = ?",
            [(int) $sub['id']]
        );
        return $sub;
    }
}
