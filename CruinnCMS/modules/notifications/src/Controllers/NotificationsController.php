<?php

declare(strict_types=1);

namespace Cruinn\Module\Notifications\Controllers;

use Cruinn\Auth;
use Cruinn\CSRF;
use Cruinn\Controllers\BaseController;
use Cruinn\Module\Notifications\Services\NotificationService;

// Last edit: 2026-06-11 16:00 UTC.

class NotificationsController extends BaseController
{
    private NotificationService $svc;

    public function __construct()
    {
        parent::__construct();
        $this->svc = new NotificationService();
    }

    private function isAdminRoute(): bool
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        return str_starts_with($uri, '/admin/');
    }

    private function basePath(string $suffix = ''): string
    {
        $prefix = $this->isAdminRoute() ? '/admin' : '';
        return $prefix . $suffix;
    }

    private function renderNotifications(string $view, array $data): void
    {
        if ($this->isAdminRoute()) {
            $data['title'] = $data['title'] ?? 'Notifications';
            $this->renderAdmin($view, $data);
            return;
        }
        $this->render($view, $data);
    }

    // ── In-app notifications ──────────────────────────────────

    /**
     * GET /notifications
     */
    public function index(): void
    {
        Auth::requireLogin();
        $user   = Auth::user();
        $userId = (int) $user['id'];

        $filters = [
            'category' => trim((string) $this->query('category', '')),
            'unread'   => !empty($this->query('unread', '')),
        ];

        $notifications    = $this->svc->forUser($userId, $filters);
        $unreadCount      = $this->svc->unreadCount($userId);
        $categories       = $this->svc->categories($userId);
        $selectedCategory = $filters['category'];
        $showUnread       = $filters['unread'];
        $basePath         = $this->basePath('/notifications');
        $preferencesPath  = $this->basePath('/notifications/preferences');

        $this->renderNotifications('public/notifications/index', compact(
            'notifications', 'unreadCount', 'categories', 'selectedCategory', 'showUnread', 'basePath', 'preferencesPath'
        ));
    }

    /**
     * POST /notifications/{id}/read
     */
    public function markRead(int $id): void
    {
        Auth::requireLogin();
        CSRF::verify();
        $userId   = (int) Auth::user()['id'];
        $redirect = $this->input('redirect', $this->basePath('/notifications'));
        $this->svc->markRead($id, $userId);
        Auth::flash('success', 'Notification marked as read.');
        $this->redirect($redirect);
    }

    /**
     * POST /notifications/read-all
     */
    public function markAllRead(): void
    {
        Auth::requireLogin();
        CSRF::verify();
        $this->svc->markAllRead((int) Auth::user()['id']);
        Auth::flash('success', 'All notifications marked as read.');
        $this->redirect($this->basePath('/notifications'));
    }

    // ── Preferences ───────────────────────────────────────────

    /**
     * GET /notifications/preferences
     */
    public function preferences(): void
    {
        Auth::requireLogin();
        $userId      = (int) Auth::user()['id'];
        $categories  = $this->svc->categories($userId);
        $preferences = $this->svc->preferencesForUser($userId);
        $basePath    = $this->basePath('/notifications');
        $preferencesPath = $this->basePath('/notifications/preferences');

        $this->renderNotifications('public/notifications/preferences', compact('categories', 'preferences', 'basePath', 'preferencesPath'));
    }

    /**
     * POST /notifications/preferences
     */
    public function savePreferences(): void
    {
        Auth::requireLogin();
        CSRF::verify();
        $userId        = (int) Auth::user()['id'];
        $inApp         = (array) ($_POST['in_app']         ?? []);
        $emailFreq     = (array) ($_POST['email_frequency'] ?? []);
        $this->svc->savePreferences($userId, $inApp, $emailFreq);
        Auth::flash('success', 'Notification preferences saved.');
        $this->redirect($this->basePath('/notifications/preferences'));
    }

    // ── Mailing lists ─────────────────────────────────────────

    /**
     * GET /mailing-lists
     */
    public function mailingLists(): void
    {
        Auth::requireLogin();
        $userId = (int) Auth::user()['id'];
        $lists  = $this->svc->listsForUser($userId);
        $basePath = $this->isAdminRoute() ? '/admin/mailing-lists' : '/mailing-lists';
        $preferencesPath = $this->basePath('/notifications/preferences');
        $this->renderNotifications('public/notifications/mailing-lists', compact('lists', 'basePath', 'preferencesPath'));
    }

    /**
     * POST /mailing-lists/{id}/subscribe
     */
    public function subscribe(int $id): void
    {
        Auth::requireLogin();
        CSRF::verify();
        $user = Auth::user();
        $this->svc->subscribe($id, (int) $user['id'], (string) $user['email'], (string) ($user['display_name'] ?? ''));
        Auth::flash('success', 'Subscribed successfully.');
        $this->redirect($this->isAdminRoute() ? '/admin/mailing-lists' : '/mailing-lists');
    }

    /**
     * POST /mailing-lists/{id}/unsubscribe
     */
    public function unsubscribe(int $id): void
    {
        Auth::requireLogin();
        CSRF::verify();
        $this->svc->unsubscribeByUser($id, (int) Auth::user()['id']);
        Auth::flash('success', 'Unsubscribed.');
        $this->redirect($this->isAdminRoute() ? '/admin/mailing-lists' : '/mailing-lists');
    }

    /**
     * GET /admin/notifications/hub
     */
    public function hub(): void
    {
        Auth::requireAdmin();

        $rows = $this->svc->recentHubEvents(200);
        $summary = $this->svc->hubSummary();

        $this->renderAdmin('public/notifications/hub', [
            'title' => 'Notifications Hub',
            'rows' => $rows,
            'summary' => $summary,
        ]);
    }

    /**
     * GET /unsubscribe?token={token}
     * Token-based unsubscribe from email link (no login required).
     */
    public function unsubscribeToken(): void
    {
        $token   = trim((string) $this->query('token', ''));
        $success = false;
        $message = 'Invalid or expired unsubscribe link.';
        $listName = null;

        if ($token !== '') {
            $result = $this->svc->unsubscribeByToken($token);
            if ($result) {
                $success  = true;
                $message  = 'You have been unsubscribed.';
                $listName = $result['list_name'] ?? null;
            }
        }

        $this->render('public/notifications/unsubscribe', compact('success', 'message', 'listName'));
    }
}
