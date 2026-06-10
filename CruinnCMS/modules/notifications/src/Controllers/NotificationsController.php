<?php

declare(strict_types=1);

namespace Cruinn\Module\Notifications\Controllers;

use Cruinn\Auth;
use Cruinn\CSRF;
use Cruinn\Controllers\BaseController;
use Cruinn\Module\Notifications\Services\NotificationService;

class NotificationsController extends BaseController
{
    private NotificationService $svc;

    public function __construct()
    {
        parent::__construct();
        $this->svc = new NotificationService();
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

        $this->render('public/notifications/index', compact(
            'notifications', 'unreadCount', 'categories', 'selectedCategory', 'showUnread'
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
        $redirect = $this->input('redirect', '/notifications');
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
        $this->redirect('/notifications');
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

        $this->render('public/notifications/preferences', compact('categories', 'preferences'));
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
        $this->redirect('/notifications/preferences');
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
        $this->render('public/notifications/mailing-lists', compact('lists'));
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
        $this->redirect('/mailing-lists');
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
        $this->redirect('/mailing-lists');
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
