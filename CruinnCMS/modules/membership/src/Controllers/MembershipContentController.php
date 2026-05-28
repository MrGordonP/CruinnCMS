<?php

declare(strict_types=1);

namespace Cruinn\Module\Membership\Controllers;

use Cruinn\Auth;
use Cruinn\Database;

final class MembershipContentController
{
    private static function context(): array
    {
        $db = Database::getInstance();
        $userId = (int) (Auth::userId() ?? 0);

        $ctx = [
            'user' => null,
            'current_user' => null,
            'member' => null,
            'address' => [],
            'adminStats' => null,
            'notifications' => [],
            'unreadCount' => 0,
            'upcomingEvents' => [],
            'latestSub' => null,
            'isAdmin' => false,
            'isCouncil' => false,
        ];

        if ($userId <= 0) {
            return $ctx;
        }

        $ctx['isAdmin'] = Auth::isAdmin();
        $ctx['isCouncil'] = !$ctx['isAdmin'] && Auth::roleLevel() >= 50;

        try {
            $ctx['user'] = $db->fetch('SELECT id, display_name, email FROM users WHERE id = ? LIMIT 1', [$userId]) ?: null;
        } catch (\Throwable) {
            $ctx['user'] = null;
        }

        if ($ctx['user']) {
            $ctx['current_user'] = [
                'id' => (int) ($ctx['user']['id'] ?? $userId),
                'name' => (string) ($ctx['user']['display_name'] ?? ''),
                'display_name' => (string) ($ctx['user']['display_name'] ?? ''),
                'email' => (string) ($ctx['user']['email'] ?? ''),
            ];
        }

        try {
            $ctx['member'] = $db->fetch('SELECT * FROM members WHERE user_id = ? LIMIT 1', [$userId]) ?: null;
        } catch (\Throwable) {
            $ctx['member'] = null;
        }

        if ($ctx['member']) {
            try {
                $ctx['address'] = $db->fetch('SELECT * FROM member_addresses WHERE member_id = ? LIMIT 1', [(int) $ctx['member']['id']]) ?: [];
            } catch (\Throwable) {
                $ctx['address'] = [];
            }

            try {
                $ctx['latestSub'] = $db->fetch(
                    'SELECT s.*, p.name AS plan_name
                     FROM membership_subscriptions s
                     LEFT JOIN membership_plans p ON p.id = s.plan_id
                     WHERE s.member_id = ?
                     ORDER BY s.period_end DESC, s.id DESC
                     LIMIT 1',
                    [(int) $ctx['member']['id']]
                ) ?: null;
            } catch (\Throwable) {
                $ctx['latestSub'] = null;
            }
        }

        try {
            $ctx['notifications'] = $db->fetchAll(
                'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5',
                [$userId]
            );
            $ctx['unreadCount'] = (int) $db->fetchColumn(
                'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL',
                [$userId]
            );
        } catch (\Throwable) {
            $ctx['notifications'] = [];
            $ctx['unreadCount'] = 0;
        }

        try {
            $ctx['upcomingEvents'] = $db->fetchAll(
                "SELECT id, title, slug, date_start, location
                 FROM events
                 WHERE date_start >= CURDATE() AND status = 'published'
                 ORDER BY date_start ASC
                 LIMIT 5"
            );
        } catch (\Throwable) {
            $ctx['upcomingEvents'] = [];
        }

        if ($ctx['isAdmin']) {
            try {
                $ctx['adminStats'] = [
                    'pages' => (int) $db->fetchColumn('SELECT COUNT(*) FROM pages_index'),
                    'members' => (int) $db->fetchColumn('SELECT COUNT(*) FROM members'),
                    'users' => (int) $db->fetchColumn('SELECT COUNT(*) FROM users'),
                ];
            } catch (\Throwable) {
                $ctx['adminStats'] = null;
            }
        }

        return $ctx;
    }

    public static function contentProviderMemberDashboardHeader(array $settings = [], array $context = []): array
    {
        return self::context();
    }

    public static function contentProviderMemberDetailsForm(array $settings = [], array $context = []): array
    {
        return self::context();
    }

    public static function contentProviderMemberNotifications(array $settings = [], array $context = []): array
    {
        return self::context();
    }

    public static function contentProviderMemberUpcomingEvents(array $settings = [], array $context = []): array
    {
        return self::context();
    }

    public static function contentProviderMemberMembershipSummary(array $settings = [], array $context = []): array
    {
        return self::context();
    }

    public static function contentProviderMemberAdminStats(array $settings = [], array $context = []): array
    {
        return self::context();
    }
}
