<?php

declare(strict_types=1);

namespace Cruinn\Module\Notifications\Controllers;

use Cruinn\Auth;
use Cruinn\Module\Notifications\Services\NotificationService;

// Last edit: 2026-06-11 16:00 UTC.

class NotificationsContentController
{
    public static function contentProviderUserInbox(array $settings = [], array $context = []): array
    {
        $svc = new NotificationService();
        $userId = self::resolveUserId($context);
        if ($userId <= 0) {
            return ['notifications' => [], 'unreadCount' => 0, 'basePath' => '/notifications'];
        }

        $limit = max(1, min((int) ($settings['per_page'] ?? 8), 25));
        $rows = $svc->forUser($userId, ['unread' => !empty($settings['unread_only'])]);
        return [
            'notifications' => array_slice($rows, 0, $limit),
            'unreadCount' => $svc->unreadCount($userId),
            'basePath' => '/notifications',
        ];
    }

    public static function contentProviderRecentList(array $settings = [], array $context = []): array
    {
        $svc = new NotificationService();
        $userId = self::resolveUserId($context);
        if ($userId <= 0) {
            return ['notifications' => [], 'title' => 'Recent Notifications', 'basePath' => '/notifications'];
        }

        $filters = [];
        $category = trim((string) ($settings['category'] ?? ''));
        if ($category !== '') {
            $filters['category'] = $category;
        }

        $limit = max(1, min((int) ($settings['per_page'] ?? 5), 20));
        return [
            'title' => (string) ($settings['title'] ?? 'Recent Notifications'),
            'notifications' => array_slice($svc->forUser($userId, $filters), 0, $limit),
            'basePath' => '/notifications',
        ];
    }

    public static function contentProviderUnreadBadge(array $settings = [], array $context = []): array
    {
        $svc = new NotificationService();
        $userId = self::resolveUserId($context);
        $count = $userId > 0 ? $svc->unreadCount($userId) : 0;

        return [
            'count' => $count,
            'label' => (string) ($settings['label'] ?? 'Unread Notifications'),
            'basePath' => '/notifications',
        ];
    }

    private static function resolveUserId(array $context): int
    {
        $ctxUser = (int) ($context['user_id'] ?? 0);
        if ($ctxUser > 0) {
            return $ctxUser;
        }
        return (int) (Auth::userId() ?? 0);
    }
}
