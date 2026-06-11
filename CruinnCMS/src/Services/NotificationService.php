<?php

declare(strict_types=1);

namespace Cruinn\Services;

use Cruinn\Module\Notifications\Services\NotificationService as ModuleNotificationService;

// Last edit: 2026-06-11 16:00 UTC.

/**
 * Core bridge for notifications so modules can depend on Cruinn\Services API
 * while the notifications module remains the canonical implementation.
 */
class NotificationService
{
    private ModuleNotificationService $svc;

    public function __construct()
    {
        $this->svc = new ModuleNotificationService();
    }

    public function notifyUser(int $userId, string $category, string $title, ?string $body = null, ?string $url = null, ?int $subjectId = null): int
    {
        return $this->svc->notifyUser($userId, $category, $title, $body, $url, $subjectId);
    }

    public function publish(array $event): int
    {
        return $this->svc->publishHubEvent($event);
    }
}
