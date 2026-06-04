<?php
/**
 * CruinnCMS — Event Controller
 *
 * Public event listing, detail pages, and event registration.
 * Admin event CRUD, attendee management, and export.
 */

namespace Cruinn\Module\Events\Controllers;

use Cruinn\Auth;
use Cruinn\App;
use Cruinn\CSRF;
use Cruinn\Database;
use Cruinn\Mailer;
use Cruinn\Controllers\BaseController;
use Cruinn\Services\SubjectThreadProvisionService;

class EventController extends BaseController
{
    // ══════════════════════════════════════════════════════════════
    // PUBLIC — Event Listing
    // ══════════════════════════════════════════════════════════════

    /**
     * Public list handler for resolver-driven event paths.
     */
    public function index(): void
    {
        $basePath = self::publicBasePath($this->db);
        $data = self::buildEventListViewData($this->db, $basePath, ['per_page' => 10]);

        $this->render('public/events/index', $data);
    }

    /**
     * Public detail handler for resolver-driven event paths.
     */
    public function show(string $slug): void
    {
        $event = self::findPublishedEventBySlug($this->db, $slug);

        if (!$event) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Event Not Found']);
            return;
        }

        $basePath = self::publicBasePath($this->db);
        $this->render('public/events/show', self::buildEventDetailViewData($event, $basePath));
    }

    public function dashboard(): void
    {
        Auth::requireAdmin();

        $settings = self::readEventSettings($this->db);
        $profileCount = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM event_profiles');
        $recentEvents = $this->db->fetchAll(
            'SELECT id, title, status, date_start, updated_at
             FROM events
             ORDER BY updated_at DESC
             LIMIT 8'
        );

        $draftCount = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM events WHERE status = ?', ['draft']);
        $publishedCount = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM events WHERE status = ?', ['published']);
        $listPage = !empty($settings['list_page_id'])
            ? $this->db->fetch('SELECT id, title, slug FROM pages_index WHERE id = ? LIMIT 1', [(int) $settings['list_page_id']])
            : null;

        $this->renderAdmin('admin/events/dashboard', [
            'title' => 'Events',
            'settings' => $settings,
            'recentEvents' => $recentEvents,
            'draftCount' => $draftCount,
            'publishedCount' => $publishedCount,
            'profileCount' => $profileCount,
            'listPage' => $listPage,
            'breadcrumbs' => [['Admin', '/admin'], ['Events']],
        ]);
    }

    public function settings(): void
    {
        Auth::requireAdmin();

        $settings = self::readEventSettings($this->db);
        $pages = $this->db->fetchAll(
            "SELECT id, title, slug FROM pages_index WHERE canvas_type = 'content' ORDER BY title ASC"
        );

        $this->renderAdmin('admin/events/settings', [
            'title' => 'Events Settings',
            'settings' => $settings,
            'pages' => $pages,
            'breadcrumbs' => [['Admin', '/admin'], ['Events', '/admin/events'], ['Settings']],
        ]);
    }

    public function saveSettings(): void
    {
        Auth::requireAdmin();
        CSRF::verify();

        $listPageId = max(0, (int) $this->input('list_page_id', 0));
        $detailPageId = max(0, (int) $this->input('detail_page_id', 0));
        $defaultEventsPerPage = self::normalisePerPage($this->input('default_events_per_page', 10));
        $defaultFilter = self::normaliseEventFilter($this->input('default_filter', 'upcoming'));
        $showReturnToList = $this->input('show_return_to_list', '0') === '1' ? '1' : '0';
        $showEventNavigation = $this->input('show_event_navigation', '0') === '1' ? '1' : '0';

        $this->upsertEventSetting('events.list_page_id', $listPageId > 0 ? (string) $listPageId : null);
        $this->upsertEventSetting('events.detail_page_id', $detailPageId > 0 ? (string) $detailPageId : null);
        $this->upsertEventSetting('events.default_events_per_page', (string) $defaultEventsPerPage);
        $this->upsertEventSetting('events.default_filter', $defaultFilter);
        $this->upsertEventSetting('events.show_return_to_list', $showReturnToList);
        $this->upsertEventSetting('events.show_event_navigation', $showEventNavigation);

        Auth::flash('success', 'Events settings saved.');
        $this->redirect('/admin/events/settings');
    }

    public function profiles(): void
    {
        Auth::requireAdmin();

        $profiles = self::readEventProfiles($this->db);

        $this->renderAdmin('admin/events/profiles/index', [
            'title' => 'Events Profiles',
            'profiles' => $profiles,
            'breadcrumbs' => [['Admin', '/admin'], ['Events', '/admin/events'], ['Profiles']],
        ]);
    }

    public function profileNew(): void
    {
        Auth::requireAdmin();

        $this->renderAdmin('admin/events/profiles/edit', [
            'title' => 'New Events Profile',
            'profile' => null,
            'errors' => [],
            'breadcrumbs' => [['Admin', '/admin'], ['Events', '/admin/events'], ['Profiles', '/admin/events/profiles'], ['New Profile']],
        ]);
    }

    public function profileCreate(): void
    {
        Auth::requireAdmin();
        CSRF::verify();

        [$profile, $errors] = $this->normaliseEventProfileInput();
        if ($profile['slug'] !== '' && (int) $this->db->fetchColumn('SELECT COUNT(*) FROM event_profiles WHERE slug = ?', [$profile['slug']]) > 0) {
            $errors['slug'] = 'An events profile with this slug already exists.';
        }

        if ($errors) {
            $this->renderAdmin('admin/events/profiles/edit', [
                'title' => 'New Events Profile',
                'profile' => $profile,
                'errors' => $errors,
                'breadcrumbs' => [['Admin', '/admin'], ['Events', '/admin/events'], ['Profiles', '/admin/events/profiles'], ['New Profile']],
            ]);
            return;
        }

        $this->db->insert('event_profiles', [
            'name' => $profile['name'],
            'slug' => $profile['slug'],
            'description' => $profile['description'],
            'display_mode' => $profile['display_mode'],
            'events_per_page' => $profile['events_per_page'],
            'default_filter' => $profile['default_filter'],
            'show_return_to_list' => $profile['show_return_to_list'] ? 1 : 0,
            'show_event_navigation' => $profile['show_event_navigation'] ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Auth::flash('success', 'Events profile created.');
        $this->redirect('/admin/events/profiles');
    }

    public function profileEdit(string $id): void
    {
        Auth::requireAdmin();

        $profile = self::readEventProfile($this->db, (int) $id);
        if (!$profile) {
            Auth::flash('error', 'Events profile not found.');
            $this->redirect('/admin/events/profiles');
        }

        $this->renderAdmin('admin/events/profiles/edit', [
            'title' => 'Edit Events Profile',
            'profile' => $profile,
            'errors' => [],
            'breadcrumbs' => [['Admin', '/admin'], ['Events', '/admin/events'], ['Profiles', '/admin/events/profiles'], [$profile['name']]],
        ]);
    }

    public function profileUpdate(string $id): void
    {
        Auth::requireAdmin();
        CSRF::verify();

        $profileId = (int) $id;
        $existingProfile = self::readEventProfile($this->db, $profileId);
        if (!$existingProfile) {
            Auth::flash('error', 'Events profile not found.');
            $this->redirect('/admin/events/profiles');
        }

        [$profile, $errors] = $this->normaliseEventProfileInput($existingProfile);
        if ($profile['slug'] !== '' && (int) $this->db->fetchColumn('SELECT COUNT(*) FROM event_profiles WHERE slug = ? AND id != ?', [$profile['slug'], $profileId]) > 0) {
            $errors['slug'] = 'An events profile with this slug already exists.';
        }

        if ($errors) {
            $profile['id'] = $profileId;
            $this->renderAdmin('admin/events/profiles/edit', [
                'title' => 'Edit Events Profile',
                'profile' => $profile,
                'errors' => $errors,
                'breadcrumbs' => [['Admin', '/admin'], ['Events', '/admin/events'], ['Profiles', '/admin/events/profiles'], [$existingProfile['name']]],
            ]);
            return;
        }

        $this->db->update('event_profiles', [
            'name' => $profile['name'],
            'slug' => $profile['slug'],
            'description' => $profile['description'],
            'display_mode' => $profile['display_mode'],
            'events_per_page' => $profile['events_per_page'],
            'default_filter' => $profile['default_filter'],
            'show_return_to_list' => $profile['show_return_to_list'] ? 1 : 0,
            'show_event_navigation' => $profile['show_event_navigation'] ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$profileId]);

        Auth::flash('success', 'Events profile updated.');
        $this->redirect('/admin/events/profiles');
    }

    public function profileDelete(string $id): void
    {
        Auth::requireAdmin();
        CSRF::verify();

        $profile = self::readEventProfile($this->db, (int) $id);
        if (!$profile) {
            Auth::flash('error', 'Events profile not found.');
            $this->redirect('/admin/events/profiles');
        }

        $this->db->delete('event_profiles', 'id = ?', [(int) $id]);

        Auth::flash('success', 'Events profile deleted.');
        $this->redirect('/admin/events/profiles');
    }

    // ══════════════════════════════════════════════════════════════
    // PUBLIC — Event Registration
    // ══════════════════════════════════════════════════════════════

    /**
     * Legacy registration form handler retained for compatibility.
     */
    public function showRegisterForm(string $slug): void
    {
        $event = $this->db->fetch(
            'SELECT * FROM events WHERE slug = ? AND status = ? LIMIT 1',
            [$slug, 'published']
        );

        if (!$event) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Event Not Found']);
            return;
        }

        $registrationCount = $this->db->fetchColumn(
            'SELECT COUNT(*) FROM event_registrations WHERE event_id = ? AND status = ?',
            [$event['id'], 'confirmed']
        );

        $spotsRemaining = null;
        if ($event['capacity'] > 0) {
            $spotsRemaining = max(0, $event['capacity'] - $registrationCount);
        }

            if (!$this->canRegister($event, $spotsRemaining)) {
                Auth::flash('error', 'Registration is not available for this event.');
                $this->redirect($this->publicEventPath($slug));
        }

        // Pre-fill fields for logged-in members
        $prefill = [];
        if (Auth::check()) {
            // Check if already registered
            $existing = $this->db->fetch(
                'SELECT id FROM event_registrations WHERE event_id = ? AND user_id = ? AND status = ?',
                [$event['id'], Auth::userId(), 'confirmed']
            );
            if ($existing) {
                Auth::flash('info', 'You are already registered for this event.');
                    $this->redirect($this->publicEventPath($slug));
            }

            $user = $this->db->fetch(
                'SELECT u.*, m.forenames, m.surnames FROM users u LEFT JOIN members m ON m.user_id = u.id WHERE u.id = ?',
                [Auth::userId()]
            );
            if ($user) {
                $name = trim(($user['forenames'] ?? '') . ' ' . ($user['surnames'] ?? ''));
                $prefill = [
                    'name'    => $name ?: ($user['display_name'] ?? ''),
                    'email'   => $user['email'] ?? '',
                    'user_id' => Auth::userId(),
                ];
            }
        }

        $this->render('public/events/register', [
            'title'          => 'Register — ' . $event['title'],
            'event'          => self::attachPublicUrl($event, self::publicBasePath($this->db)),
            'event_base_path' => self::publicBasePath($this->db),
            'register_url'   => '',
            'spotsRemaining' => $spotsRemaining,
            'prefill'        => $prefill,
        ]);
    }

    /**
     * Legacy registration submit handler retained for compatibility.
     */
    public function register(string $slug): void
    {
        $event = $this->db->fetch(
            'SELECT * FROM events WHERE slug = ? AND status = ? LIMIT 1',
            [$slug, 'published']
        );

        if (!$event) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Event Not Found']);
            return;
        }

        $registrationCount = $this->db->fetchColumn(
            'SELECT COUNT(*) FROM event_registrations WHERE event_id = ? AND status = ?',
            [$event['id'], 'confirmed']
        );

        $spotsRemaining = null;
        if ($event['capacity'] > 0) {
            $spotsRemaining = max(0, $event['capacity'] - $registrationCount);
        }

            if (!$this->canRegister($event, $spotsRemaining)) {
                Auth::flash('error', 'Registration is not available for this event.');
                $this->redirect($this->publicEventPath($slug));
        }

        // Validate required fields
        $name  = $this->input('name');
        $email = $this->input('email');

        if (empty($name) || empty($email)) {
            Auth::flash('error', 'Name and email are required.');
                $this->redirect($this->publicRegisterPath($slug));
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Auth::flash('error', 'Please enter a valid email address.');
                $this->redirect($this->publicRegisterPath($slug));
        }

        // Determine user_id
        $memberId = null;
        if (Auth::check()) {
            $memberId = Auth::userId();
            $member = $this->db->fetch('SELECT id FROM members WHERE user_id = ?', [$memberId]);
            if ($member) {
                // Check for duplicate registration
                $existing = $this->db->fetch(
                    'SELECT id FROM event_registrations WHERE event_id = ? AND user_id = ? AND status = ?',
                    [$event['id'], $memberId, 'confirmed']
                );
                if ($existing) {
                    Auth::flash('info', 'You are already registered for this event.');
                        $this->redirect($this->publicEventPath($slug));
                }
            }
        } else {
            // Check for duplicate guest email registration
            $existing = $this->db->fetch(
                'SELECT id FROM event_registrations WHERE event_id = ? AND email = ? AND user_id IS NULL AND status = ?',
                [$event['id'], strtolower($email), 'confirmed']
            );
            if ($existing) {
                Auth::flash('info', 'This email is already registered for this event.');
                    $this->redirect($this->publicEventPath($slug));
            }
        }

        // Check capacity (race-condition safe re-check)
        if ($event['capacity'] > 0) {
            $currentCount = $this->db->fetchColumn(
                'SELECT COUNT(*) FROM event_registrations WHERE event_id = ? AND status = ?',
                [$event['id'], 'confirmed']
            );
            if ($currentCount >= $event['capacity']) {
                Auth::flash('error', 'Sorry, this event has reached full capacity.');
                    $this->redirect($this->publicEventPath($slug));
            }
        }

        // Determine payment status
        $paymentStatus = ($event['price'] > 0) ? 'pending' : 'n/a';

        // Generate confirmation token
        $token = bin2hex(random_bytes(32));

        // Create registration
        $regId = $this->db->insert('event_registrations', [
            'event_id'           => $event['id'],
            'user_id'            => $memberId,
            'name'               => $name,
            'email'              => strtolower($email),
            'phone'              => $this->input('phone') ?: null,
            'attendees'          => (int)($this->input('attendees') ?: 1),
            'notes'              => $this->input('notes') ?: null,
            'amount_paid'        => 0.00,
            'status'             => 'confirmed',
            'confirmation_token' => $token,
        ]);

        // Log activity
        $this->logActivity(
            'register',
            'event',
            (int) $event['id'],
            "Registration #{$regId} by {$name} ({$email})"
        );

        // Send confirmation email
        $this->sendConfirmationEmail($event, $name, $email, $token, $paymentStatus);

        if ($paymentStatus === 'pending') {
            Auth::flash('success', "You're registered for {$event['title']}! Payment of €" . number_format($event['price'], 2) . " is required — we'll send details separately.");
        } else {
            Auth::flash('success', "You're registered for {$event['title']}! A confirmation email has been sent.");
        }

            $this->redirect($this->publicEventPath($slug));
    }

    /**
     * Legacy self-cancel handler retained for compatibility.
     */
    public function cancelRegistration(string $slug, string $token): void
    {
        $event = $this->db->fetch('SELECT * FROM events WHERE slug = ? LIMIT 1', [$slug]);
        if (!$event) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Event Not Found']);
            return;
        }

        $reg = $this->db->fetch(
            'SELECT * FROM event_registrations WHERE event_id = ? AND confirmation_token = ? AND status = ? LIMIT 1',
            [$event['id'], $token, 'confirmed']
        );

        if (!$reg) {
            Auth::flash('error', 'Registration not found or already cancelled.');
                $this->redirect($this->publicEventPath($slug));
        }

        $this->db->update('event_registrations', [
            'status'        => 'cancelled',
            'cancelled_at'  => date('Y-m-d H:i:s'),
            'cancel_reason' => 'Self-cancelled via link',
        ], 'id = ?', [$reg['id']]);

        $registrantName = $reg['name'] ?? 'Member #' . $reg['user_id'];
        $this->logActivity('cancel', 'event', (int) $event['id'], "Registration #{$reg['id']} cancelled by {$registrantName}");

        Auth::flash('success', 'Your registration has been cancelled.');
            $this->redirect($this->publicEventPath($slug));
    }

    // ══════════════════════════════════════════════════════════════
    // ADMIN — Event List
    // ══════════════════════════════════════════════════════════════

    /**
     * GET /admin/events — Admin event listing.
     */
    public function adminList(): void
    {
        Auth::requireAdmin();

        $search  = trim($this->query('q', ''));
        $status  = $this->query('status', '');
        $type    = $this->query('type', '');
        $page    = max(1, (int) $this->query('page', 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        $where  = [];
        $params = [];

        if ($search !== '') {
            $where[] = '(e.title LIKE ? OR e.location LIKE ?)';
            $like = "%{$search}%";
            $params = array_merge($params, [$like, $like]);
        }

        if ($status !== '') {
            $where[] = 'e.status = ?';
            $params[] = $status;
        }

        if ($type !== '') {
            $where[] = 'e.event_type = ?';
            $params[] = $type;
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $totalCount = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM events e {$whereSQL}",
            $params
        );
        $totalPages = (int) ceil($totalCount / $perPage);

        $events = $this->db->fetchAll(
            "SELECT e.*, u.display_name as created_by_name,
                    (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id = e.id AND r.status = 'confirmed') as reg_count
             FROM events e
             LEFT JOIN users u ON e.created_by = u.id
             {$whereSQL}
             ORDER BY e.date_start DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        $this->renderAdmin('admin/events/index', [
            'title'       => 'Events',
            'events'      => $events,
            'eventBasePath' => $this->adminEventBasePath(),
            'search'      => $search,
            'status'      => $status,
            'type'        => $type,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'totalCount'  => $totalCount,
            'breadcrumbs' => [['Admin', '/admin'], ['Events', '/admin/events'], ['Event List']],
        ]);
    }

    /**
     * GET /admin/events/{id} — Event detail with registration summary.
     */
    public function adminShow(string $id): void
    {
        Auth::requireAdmin();

        $event = $this->db->fetch(
            'SELECT e.*, u.display_name as created_by_name
             FROM events e
             LEFT JOIN users u ON e.created_by = u.id
             WHERE e.id = ?',
            [$id]
        );

        if (!$event) {
            Auth::flash('error', 'Event not found.');
            $this->redirect('/admin/events');
        }

        $registrations = $this->db->fetchAll(
            'SELECT r.*
             FROM event_registrations r
             WHERE r.event_id = ?
             ORDER BY r.created_at DESC',
            [$id]
        );

        $confirmedCount = 0;
        $cancelledCount = 0;
        $pendingPayment = 0;
        $totalRevenue   = 0;

        foreach ($registrations as $reg) {
            if ($reg['status'] === 'confirmed') {
                $confirmedCount++;
                $totalRevenue += (float) $reg['amount_paid'];
                if (false) { // payment_status not in schema
                    $pendingPayment++;
                }
            } elseif ($reg['status'] === 'cancelled') {
                $cancelledCount++;
            }
        }

        $this->renderAdmin('admin/events/show', [
            'title'          => $event['title'],
            'event'          => $event,
            'eventBasePath'  => $this->adminEventBasePath(),
            'registrations'  => $registrations,
            'confirmedCount' => $confirmedCount,
            'cancelledCount' => $cancelledCount,
            'pendingPayment' => $pendingPayment,
            'totalRevenue'   => $totalRevenue,
            'breadcrumbs'    => [['Admin', '/admin'], ['Events', '/admin/events'], [$event['title']]],
        ]);
    }

    /**
     * GET /admin/events/new — Show new event form.
     */
    public function adminNew(): void
    {
        Auth::requireAdmin();

        $articles = $this->fetchPublishedArticles();
        $subjects = $this->fetchSubjects();
        $prefillSubjectId = (int) $this->input('subject_id', 0);
        $prefillSubjectIds = $prefillSubjectId > 0 ? [$prefillSubjectId] : [];
        $this->renderAdmin('admin/events/edit', [
            'title'       => 'New Event',
            'event'       => null,
            'articles'    => $articles,
            'subjects'    => $subjects,
            'eventSubjectIds' => $prefillSubjectIds,
            'eventBasePath' => $this->adminEventBasePath(),
            'breadcrumbs' => [['Admin', '/admin'], ['Events', '/admin/events'], ['New Event']],
        ]);
    }

    /**
     * POST /admin/events — Create event.
     */
    public function adminCreate(): void
    {
        Auth::requireAdmin();
        CSRF::verify();

        $errors = $this->validateRequired([
            'title'      => 'Title',
            'event_type' => 'Event Type',
            'date_start' => 'Start Date',
        ]);

        if (!empty($errors)) {
            Auth::flash('error', implode(' ', $errors));
            $this->redirect('/admin/events/new');
        }

        $slug = $this->generateSlug($this->input('title'));

        $dateStartRaw = $this->input('date_start');
        $dateEndRaw   = $this->input('date_end') ?: null;
        $subjectIds = array_values(array_filter(array_map('intval', (array) ($_POST['subject_ids'] ?? []))));

        $id = $this->db->insert('events', [
            'title'             => $this->input('title'),
            'slug'              => $slug,
            'event_type'        => $this->input('event_type'),
            'description'       => $this->input('description') ?: null,
            'date_start'        => $dateStartRaw,
            'date_end'          => $dateEndRaw,
            'start_date'        => $dateStartRaw ? date('Y-m-d', strtotime($dateStartRaw)) : date('Y-m-d'),
            'start_time'        => $dateStartRaw ? date('H:i:s', strtotime($dateStartRaw)) : null,
            'end_date'          => $dateEndRaw   ? date('Y-m-d', strtotime($dateEndRaw))   : null,
            'end_time'          => $dateEndRaw   ? date('H:i:s', strtotime($dateEndRaw))   : null,
            'location'          => $this->input('location') ?: null,
            'location_lat'      => $this->input('location_lat') ?: null,
            'location_lng'      => $this->input('location_lng') ?: null,
            'capacity'          => (int) ($this->input('capacity') ?: 0) ?: null,
            'price'             => (float) ($this->input('price') ?: 0.00),
            'currency'          => $this->input('currency', 'EUR'),
            'reg_deadline'      => $this->input('reg_deadline') ?: null,
            'registration_open' => $this->input('registration_open') ? 1 : 0,
            'external_form_url' => $this->input('external_form_url') ?: null,
            'related_article_id' => $this->input('related_article_id') ? (int) $this->input('related_article_id') : null,
            'status'            => $this->input('status', 'draft'),
            'created_by'        => Auth::userId(),
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        if ($this->input('status', 'draft') === 'published') {
            $this->provisionForumThreadForEvent(
                (int) $id,
                (int) ($subjectIds[0] ?? 0),
                (string) $this->input('title'),
                $slug,
                (string) ($this->input('description') ?: ''),
                (int) Auth::userId()
            );
        }

        // Sync subject associations
        foreach ($subjectIds as $sid) {
            try {
                $this->db->query(
                    'INSERT IGNORE INTO subject_content (subject_id, item_type, item_id) VALUES (?, ?, ?)',
                    [$sid, 'event', (int) $id]
                );
            } catch (\Throwable) {}
        }

        $this->logActivity('create', 'event', (int) $id, $this->input('title'));
        Auth::flash('success', 'Event created.');
        $this->redirect("/admin/events/{$id}");
    }

    /**
     * GET /admin/events/{id}/edit — Show edit event form.
     */
    public function adminEdit(string $id): void
    {
        Auth::requireAdmin();

        $event = $this->db->fetch('SELECT * FROM events WHERE id = ?', [$id]);
        if (!$event) {
            Auth::flash('error', 'Event not found.');
            $this->redirect('/admin/events');
        }

        $articles = $this->fetchPublishedArticles();
        $subjects = $this->fetchSubjects();
        $eventSubjectIds = array_column(
            $this->db->fetchAll('SELECT subject_id FROM subject_content WHERE item_type = ? AND item_id = ?', ['event', (int) $id]),
            'subject_id'
        );
        $this->renderAdmin('admin/events/edit', [
            'title'          => 'Edit: ' . $event['title'],
            'event'          => $event,
            'articles'       => $articles,
            'subjects'       => $subjects,
            'eventSubjectIds'=> $eventSubjectIds,
            'eventBasePath'  => $this->adminEventBasePath(),
            'breadcrumbs'    => [['Admin', '/admin'], ['Events', '/admin/events'], [$event['title']]],
        ]);
    }

    /**
     * POST /admin/events/{id} — Update event.
     */
    public function adminUpdate(string $id): void
    {
        Auth::requireAdmin();
        CSRF::verify();

        $event = $this->db->fetch('SELECT * FROM events WHERE id = ?', [$id]);
        if (!$event) {
            Auth::flash('error', 'Event not found.');
            $this->redirect('/admin/events');
        }

        $errors = $this->validateRequired([
            'title'      => 'Title',
            'event_type' => 'Event Type',
            'date_start' => 'Start Date',
        ]);

        if (!empty($errors)) {
            Auth::flash('error', implode(' ', $errors));
            $this->redirect("/admin/events/{$id}/edit");
        }

        $slug = $event['slug'];
        if ($this->input('title') !== $event['title']) {
            $slug = $this->generateSlug($this->input('title'), (int) $id);
        }

        $dateStartRaw = $this->input('date_start');
        $dateEndRaw   = $this->input('date_end') ?: null;
        $subjectIds = array_values(array_filter(array_map('intval', (array) ($_POST['subject_ids'] ?? []))));

        $this->db->update('events', [
            'title'             => $this->input('title'),
            'slug'              => $slug,
            'event_type'        => $this->input('event_type'),
            'description'       => $this->input('description') ?: null,
            'date_start'        => $dateStartRaw,
            'date_end'          => $dateEndRaw,
            'start_date'        => $dateStartRaw ? date('Y-m-d', strtotime($dateStartRaw)) : null,
            'start_time'        => $dateStartRaw ? date('H:i:s', strtotime($dateStartRaw)) : null,
            'end_date'          => $dateEndRaw   ? date('Y-m-d', strtotime($dateEndRaw))   : null,
            'end_time'          => $dateEndRaw   ? date('H:i:s', strtotime($dateEndRaw))   : null,
            'location'          => $this->input('location') ?: null,
            'location_lat'      => $this->input('location_lat') ?: null,
            'location_lng'      => $this->input('location_lng') ?: null,
            'capacity'          => (int) ($this->input('capacity') ?: 0) ?: null,
            'price'             => (float) ($this->input('price') ?: 0.00),
            'currency'          => $this->input('currency', 'EUR'),
            'reg_deadline'      => $this->input('reg_deadline') ?: null,
            'registration_open' => $this->input('registration_open') ? 1 : 0,
            'external_form_url' => $this->input('external_form_url') ?: null,
            'related_article_id' => $this->input('related_article_id') ? (int) $this->input('related_article_id') : null,
            'status'            => $this->input('status', 'draft'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        if ($this->input('status', 'draft') === 'published') {
            $this->provisionForumThreadForEvent(
                (int) $id,
                (int) ($subjectIds[0] ?? 0),
                (string) $this->input('title'),
                $slug,
                (string) ($this->input('description') ?: ''),
                (int) ($event['created_by'] ?? Auth::userId())
            );
        }

        // Sync subject associations — replace all existing for this event
        $this->db->query('DELETE FROM subject_content WHERE item_type = ? AND item_id = ?', ['event', (int) $id]);
        foreach ($subjectIds as $sid) {
            try {
                $this->db->query(
                    'INSERT IGNORE INTO subject_content (subject_id, item_type, item_id) VALUES (?, ?, ?)',
                    [$sid, 'event', (int) $id]
                );
            } catch (\Throwable) {}
        }

        $this->logActivity('update', 'event', (int) $id, $this->input('title'));
        Auth::flash('success', 'Event updated.');
        $this->redirect("/admin/events/{$id}");
    }

    /**
     * POST /admin/events/{id}/delete — Delete event and all registrations.
     */
    public function adminDelete(string $id): void
    {
        Auth::requireAdmin();
        CSRF::verify();

        $event = $this->db->fetch('SELECT * FROM events WHERE id = ?', [$id]);
        if (!$event) {
            Auth::flash('error', 'Event not found.');
            $this->redirect('/admin/events');
        }

        $regCount = $this->db->fetchColumn(
            'SELECT COUNT(*) FROM event_registrations WHERE event_id = ? AND status = ?',
            [$id, 'confirmed']
        );

        if ($regCount > 0) {
            Auth::flash('error', "Cannot delete event with {$regCount} active registration(s). Cancel or remove registrations first.");
            $this->redirect("/admin/events/{$id}");
        }

        $title = $event['title'];
        $this->db->transaction(function () use ($id, $title) {
            $this->db->delete('event_registrations', 'event_id = ?', [$id]);
            $this->db->delete('events', 'id = ?', [$id]);
            $this->logActivity('delete', 'event', (int) $id, $title);
        });

        Auth::flash('success', "Event '{$title}' deleted.");
        $this->redirect('/admin/events');
    }

    /**
     * POST /admin/events/{id}/registrations/{regId}/cancel — Admin cancel a registration.
     */
    public function adminCancelRegistration(string $id, string $regId): void
    {
        Auth::requireAdmin();
        CSRF::verify();

        $event = $this->db->fetch('SELECT * FROM events WHERE id = ?', [$id]);
        if (!$event) {
            Auth::flash('error', 'Event not found.');
            $this->redirect('/admin/events');
        }

        $reg = $this->db->fetch(
            'SELECT * FROM event_registrations WHERE id = ? AND event_id = ?',
            [$regId, $id]
        );

        if (!$reg) {
            Auth::flash('error', 'Registration not found.');
            $this->redirect("/admin/events/{$id}");
        }

        $this->db->update('event_registrations', [
            'status'        => 'cancelled',
            'cancelled_at'  => date('Y-m-d H:i:s'),
            'cancel_reason' => 'Cancelled by admin',
        ], 'id = ?', [$regId]);

        $registrantName = $reg['name'] ?? "Member #{$reg['user_id']}";
        $this->logActivity('cancel', 'event', (int) $id, "Admin cancelled registration #{$regId} ({$registrantName})");

        Auth::flash('success', 'Registration cancelled.');
        $this->redirect("/admin/events/{$id}");
    }

    /**
     * POST /admin/events/{id}/registrations/{regId}/payment — Mark registration as paid.
     */
    public function adminMarkPaid(string $id, string $regId): void
    {
        Auth::requireAdmin();
        CSRF::verify();

        $event = $this->db->fetch('SELECT * FROM events WHERE id = ?', [$id]);
        if (!$event) {
            Auth::flash('error', 'Event not found.');
            $this->redirect('/admin/events');
        }

        $reg = $this->db->fetch(
            'SELECT * FROM event_registrations WHERE id = ? AND event_id = ?',
            [$regId, $id]
        );

        if (!$reg) {
            Auth::flash('error', 'Registration not found.');
            $this->redirect("/admin/events/{$id}");
        }

        $this->db->update('event_registrations', [
            // payment_status/method/ref not in current schema
            'amount_paid'    => (float) $event['price'],
        ], 'id = ?', [$regId]);

        $registrantName = $reg['name'] ?? "Member #{$reg['user_id']}";
        $this->logActivity('payment', 'event', (int) $id, "Marked registration #{$regId} as paid ({$registrantName})");

        Auth::flash('success', 'Payment recorded.');
        $this->redirect("/admin/events/{$id}");
    }

    /**
     * GET /admin/events/{id}/export — Export registrations as CSV.
     */
    public function adminExportRegistrations(string $id): void
    {
        Auth::requireAdmin();

        $event = $this->db->fetch('SELECT * FROM events WHERE id = ?', [$id]);
        if (!$event) {
            Auth::flash('error', 'Event not found.');
            $this->redirect('/admin/events');
        }

        $registrations = $this->db->fetchAll(
            'SELECT r.*, m.forenames, m.surnames, m.email as member_email
             FROM event_registrations r
             LEFT JOIN members m ON m.user_id = r.user_id
             WHERE r.event_id = ?
             ORDER BY r.created_at ASC',
            [$id]
        );

        $filename = 'registrations-' . $event['slug'] . '-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");

        $out = fopen('php://output', 'w');
        // BOM for Excel UTF-8
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, [
            'Reg #', 'Name', 'Email', 'Type', 'Notes', 'Amount Paid', 'Status', 'Registered At', 'Cancelled At',
        ]);

        foreach ($registrations as $reg) {
            $name  = $reg['user_id']
                ? trim(($reg['forenames'] ?? '') . ' ' . ($reg['surnames'] ?? ''))
                : ($reg['name'] ?? '');
            $email = $reg['user_id']
                ? ($reg['member_email'] ?? '')
                : ($reg['email'] ?? '');
            $type  = $reg['user_id'] ? 'Member' : 'Guest';

            fputcsv($out, [
                $reg['id'],
                $name,
                $email,
                $type,
                $reg['notes'] ?? '',
                number_format((float) $reg['amount_paid'], 2),
                ucfirst($reg['status']),
                $reg['created_at'],
                $reg['cancelled_at'] ?? '',
            ]);
        }

        fclose($out);
        exit;
    }

    public static function contentProviderEventsList(array $settings = [], array $context = []): array
    {
        $settings = self::applyEventProfileSettings($settings);
        $basePath = self::normalisePublicBasePath((string) ($settings['base_path'] ?? ($context['event_base_path'] ?? '')));

        if (!empty($context['events']) && is_array($context['events'])) {
            $events = self::attachPublicUrls($context['events'], $basePath);
            $events = self::hydrateEventPreviewData($events);

            return [
                'events' => $events,
                'page' => (int) ($context['page'] ?? 1),
                'totalPages' => (int) ($context['totalPages'] ?? 1),
                'filter' => self::normaliseEventFilter($context['filter'] ?? ($settings['filter'] ?? 'upcoming')),
                'event_base_path' => $basePath,
            ];
        }

        return self::buildEventListViewData(Database::getInstance(), $basePath, $settings);
    }

    public static function contentProviderEventsContent(array $settings = [], array $context = []): array
    {
        $settings = self::applyEventProfileSettings($settings);
        $mode = strtolower(trim((string) ($settings['mode'] ?? 'both')));
        if (!in_array($mode, ['list', 'detail', 'both'], true)) {
            $mode = 'both';
        }

        $basePath = self::normalisePublicBasePath((string) ($settings['base_path'] ?? ($context['event_base_path'] ?? '')));
        $hasEvent = !empty($context['event']) && is_array($context['event']);
        $showList = $mode === 'list' || ($mode === 'both' && !$hasEvent);
        $showDetail = $mode === 'detail' || ($mode === 'both' && $hasEvent);

        $listData = [];
        $detailData = [];

        if ($showList) {
            $listData = self::contentProviderEventsList(array_merge($settings, ['base_path' => $basePath]), $context);
        }

        if ($showDetail && $hasEvent) {
            $detailData = self::contentProviderEventDetail(array_merge($settings, ['base_path' => $basePath]), $context);
        }

        return array_merge($listData, $detailData, [
            'show_list' => $showList,
            'show_detail' => $showDetail && $hasEvent,
            'event_base_path' => $basePath,
        ]);
    }

    public static function contentProviderEventDetail(array $settings = [], array $context = []): array
    {
        $event = $context['event'] ?? null;
        if (!is_array($event) || empty($event['id'])) {
            return [];
        }

        $settings = self::applyEventProfileSettings($settings);
        $basePath = self::normalisePublicBasePath((string) ($settings['base_path'] ?? ($context['event_base_path'] ?? '')));

        return self::buildEventDetailViewData($event, $basePath, $settings);
    }

    public static function resolvePublicPath(string $path, array $settings = [], string $moduleSlug = 'events'): ?array
    {
        $db = Database::getInstance();
        $eventSettings = self::readEventSettings($db);
        $listPageId = (int) ($eventSettings['list_page_id'] ?? 0);
        if ($listPageId <= 0) {
            return null;
        }

        $listPage = $db->fetch('SELECT id, slug, status FROM pages_index WHERE id = ? LIMIT 1', [$listPageId]);
        if (!$listPage || ($listPage['status'] ?? '') !== 'published') {
            return null;
        }

        $baseSlug = trim((string) ($listPage['slug'] ?? ''), '/');
        if ($baseSlug === '') {
            return null;
        }

        $normalisedPath = trim($path, '/');
        $basePath = self::normalisePublicBasePath($baseSlug);

        if ($normalisedPath === $baseSlug) {
            return [
                'page_id' => $listPageId,
                'data' => self::buildEventListViewData($db, $basePath, $settings),
            ];
        }

        $prefix = $baseSlug . '/';
        if (!str_starts_with($normalisedPath, $prefix)) {
            return null;
        }

        $eventSlug = substr($normalisedPath, strlen($prefix));
        if ($eventSlug === '' || str_contains($eventSlug, '/')) {
            return null;
        }

        $event = self::findPublishedEventBySlug($db, $eventSlug);
        if (!$event) {
            return null;
        }

        $detailPageId = (int) ($eventSettings['detail_page_id'] ?? 0);

        return [
            'page_id' => $detailPageId > 0 ? $detailPageId : $listPageId,
            'data' => self::buildEventDetailViewData($event, $basePath, $settings),
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════

    private function adminEventBasePath(): string
    {
        return self::publicBasePath($this->db);
    }

    public static function publicBasePath(?Database $db = null): string
    {
        $db ??= Database::getInstance();
        $settings = self::readEventSettings($db);
        $listPageId = (int) ($settings['list_page_id'] ?? 0);
        if ($listPageId <= 0) {
            return '';
        }

        $slug = (string) ($db->fetchColumn('SELECT slug FROM pages_index WHERE id = ? LIMIT 1', [$listPageId]) ?: '');
        return self::normalisePublicBasePath($slug);
    }

    private function fetchPublishedArticles(): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT id, title FROM articles WHERE status = 'published' ORDER BY published_at DESC LIMIT 200"
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function fetchSubjects(): array
    {
        try {
            return $this->db->fetchAll(
                'SELECT id, title FROM subjects ORDER BY title ASC'
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function canRegister(array $event, ?int $spotsRemaining): bool
    {
        if ($event['status'] !== 'published') {
            return false;
        }
        if (isset($event['registration_open']) && !$event['registration_open']) {
            return false;
        }
        if (!empty($event['reg_deadline']) && strtotime($event['reg_deadline']) < time()) {
            return false;
        }
        if ($spotsRemaining !== null && $spotsRemaining <= 0) {
            return false;
        }
        if (strtotime($event['date_start']) < time()) {
            return false;
        }
        return true;
    }

    private function generateSlug(string $title, ?int $excludeId = null): string
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        $baseSlug = $slug;
        $counter = 1;
        while (true) {
            $params = [$slug];
            $whereExtra = '';
            if ($excludeId !== null) {
                $whereExtra = ' AND id != ?';
                $params[] = $excludeId;
            }
            $existing = $this->db->fetch("SELECT id FROM events WHERE slug = ?{$whereExtra}", $params);
            if (!$existing) {
                break;
            }
            $slug = $baseSlug . '-' . (++$counter);
        }

        return $slug;
    }

    private function sendConfirmationEmail(
        array $event,
        string $name,
        string $email,
        string $token,
        string $paymentStatus
    ): void {
        $siteUrl   = rtrim(App::config('site.url', 'http://localhost:8080'), '/');
        $eventPath = $this->publicEventPath((string) ($event['slug'] ?? ''));
        $eventUrl  = $eventPath !== '/' ? $siteUrl . $eventPath : $siteUrl;

        $dateStr = date('l, j F Y', strtotime($event['date_start']));
        if (!empty($event['date_end']) && $event['date_end'] !== $event['date_start']) {
            $dateStr .= ' – ' . date('l, j F Y', strtotime($event['date_end']));
        }

        $paymentNote = '';
        if ($paymentStatus === 'pending') {
            $paymentNote = "<p><strong>Payment required:</strong> €" . number_format($event['price'], 2) . ". We will contact you with payment instructions.</p>";
        }

        $html = "
            <h2>Registration Confirmed</h2>
            <p>Hi {$name},</p>
            <p>You're registered for <strong>{$event['title']}</strong>.</p>
            <table style='border-collapse:collapse;margin:16px 0'>
                <tr><td style='padding:4px 12px 4px 0;font-weight:bold'>Date</td><td>{$dateStr}</td></tr>
                <tr><td style='padding:4px 12px 4px 0;font-weight:bold'>Location</td><td>" . ($event['location'] ?? 'TBC') . "</td></tr>
                <tr><td style='padding:4px 12px 4px 0;font-weight:bold'>Type</td><td>" . ucfirst($event['event_type']) . "</td></tr>
            </table>
            {$paymentNote}
            <p><a href='{$eventUrl}'>View event details</a></p>
        ";

        $text = "Registration Confirmed\n\nHi {$name},\n\nYou're registered for {$event['title']}.\n\nDate: {$dateStr}\nLocation: " . ($event['location'] ?? 'TBC') . "\n\nView event: {$eventUrl}";

        Mailer::send($email, "Registration Confirmed — {$event['title']}", $html, $text);
    }

    private static function buildEventListViewData(Database $db, string $basePath, array $settings = []): array
    {
        $settings = self::applyEventProfileSettings($settings, $db);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $eventSettings = self::readEventSettings($db);
        $perPage = self::normalisePerPage($settings['per_page'] ?? ($eventSettings['default_events_per_page'] ?? 10));
        $filter = self::normaliseEventFilter($_GET['show'] ?? ($settings['filter'] ?? ($eventSettings['default_filter'] ?? 'upcoming')));
        $offset = ($page - 1) * $perPage;

        if ($filter === 'past') {
            $whereSql = 'date_start < NOW()';
            $orderSql = 'date_start DESC, id DESC';
        } else {
            $whereSql = 'date_start >= NOW()';
            $orderSql = 'date_start ASC, id ASC';
        }

        $events = $db->fetchAll(
            "SELECT * FROM events WHERE status = ? AND {$whereSql} ORDER BY {$orderSql} LIMIT ? OFFSET ?",
            ['published', $perPage, $offset]
        );
        $events = self::attachPublicUrls(self::hydrateEventPreviewData($events), $basePath);

        $totalCount = (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM events WHERE status = ? AND {$whereSql}",
            ['published']
        );

        return [
            'title' => 'Events',
            'events' => $events,
            'page' => $page,
            'per_page' => $perPage,
            'filter' => $filter,
            'totalPages' => max(1, (int) ceil($totalCount / $perPage)),
            'canonical_url' => $basePath,
            'event_base_path' => $basePath,
        ];
    }

    private static function buildEventDetailViewData(array $event, string $basePath, array $settings = []): array
    {
        $db = Database::getInstance();
        $settings = self::applyEventProfileSettings($settings, $db);
        $eventSettings = self::readEventSettings($db);
        $publicEvent = self::attachPublicUrl($event, $basePath);
        $registrationCount = (int) $db->fetchColumn(
            'SELECT COUNT(*) FROM event_registrations WHERE event_id = ? AND status = ?',
            [(int) $event['id'], 'confirmed']
        );

        $spotsRemaining = null;
        if ((int) ($event['capacity'] ?? 0) > 0) {
            $spotsRemaining = max(0, (int) $event['capacity'] - $registrationCount);
        }

        $userRegistered = false;
        if (Auth::check()) {
            $existing = $db->fetch(
                'SELECT id FROM event_registrations WHERE event_id = ? AND user_id = ? AND status = ? LIMIT 1',
                [(int) $event['id'], Auth::userId(), 'confirmed']
            );
            $userRegistered = (bool) $existing;
        }

        $relatedArticle = null;
        if (!empty($event['related_article_id'])) {
            $relatedArticle = $db->fetch(
                "SELECT id, title, slug FROM articles WHERE id = ? AND status = 'published' LIMIT 1",
                [(int) $event['related_article_id']]
            ) ?: null;
        }

        $siteUrl = rtrim(App::config('site.url', ''), '/');
        $defaultImg = $siteUrl . App::config('social.default_image', '');
        $eventDesc = truncate(strip_tags((string) ($event['description'] ?? '')), 200);
        $navigation = self::buildEventNavigation($db, $event, $basePath, self::normalisePerPage($settings['per_page'] ?? ($eventSettings['default_events_per_page'] ?? 10)));
        $showReturnToList = array_key_exists('show_return_to_list', $settings)
            ? (bool) $settings['show_return_to_list']
            : (bool) ($eventSettings['show_return_to_list'] ?? true);
        $showEventNavigation = array_key_exists('show_event_navigation', $settings)
            ? (bool) $settings['show_event_navigation']
            : (bool) ($eventSettings['show_event_navigation'] ?? true);

        return array_merge([
            'title' => $event['title'],
            'event' => $publicEvent,
            'event_base_path' => $basePath,
            'registrationCount' => $registrationCount,
            'spotsRemaining' => $spotsRemaining,
            'userRegistered' => $userRegistered,
            'canRegister' => (new self())->canRegister($event, $spotsRemaining),
            'register_url' => '',
            'relatedArticle' => $relatedArticle,
            'show_return_to_list' => $showReturnToList,
            'show_event_navigation' => $showEventNavigation,
            'canonical_url' => (string) ($publicEvent['public_url'] ?? $basePath),
            'meta_description' => $eventDesc,
            'og_title' => $event['title'],
            'og_type' => 'event',
            'og_url' => $siteUrl . ($publicEvent['public_url'] ?? $basePath),
            'og_description' => $eventDesc,
            'og_image' => $defaultImg,
        ], $navigation);
    }

    private static function findPublishedEventBySlug(Database $db, string $slug): ?array
    {
        $event = $db->fetch(
            'SELECT * FROM events WHERE slug = ? AND status = ? LIMIT 1',
            [$slug, 'published']
        );

        return is_array($event) ? $event : null;
    }

    private static function attachPublicUrls(array $events, string $basePath): array
    {
        foreach ($events as &$event) {
            $event = self::attachPublicUrl($event, $basePath);
        }
        unset($event);

        return $events;
    }

    private static function attachPublicUrl(array $event, string $basePath): array
    {
        if ($basePath === '') {
            return $event;
        }

        $event['public_url'] = rtrim($basePath, '/') . '/' . ltrim((string) ($event['slug'] ?? ''), '/');
        return $event;
    }

    private static function normalisePublicBasePath(string $path): string
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            return '';
        }

        return '/' . trim($trimmed, '/');
    }

    private function publicEventPath(string $slug): string
    {
        $basePath = self::publicBasePath($this->db);
        if ($basePath === '') {
            return '/';
        }

        return rtrim($basePath, '/') . '/' . ltrim($slug, '/');
    }

    private function publicRegisterPath(string $slug): string
    {
        return $this->publicEventPath($slug);
    }

    private static function normalisePerPage(mixed $value): int
    {
        return max(1, min(100, (int) $value ?: 10));
    }

    private function provisionForumThreadForEvent(
        int $eventId,
        int $subjectId,
        string $title,
        string $slug,
        string $description,
        int $authorId
    ): void {
        if ($eventId <= 0 || $subjectId <= 0) {
            return;
        }

        try {
            (new SubjectThreadProvisionService($this->db))->ensurePublishedContentThread(
                'event',
                $eventId,
                $subjectId,
                $title,
                $slug,
                $description,
                $authorId
            );
        } catch (\Throwable $e) {
            error_log('Event forum-thread provisioning failed for event #' . $eventId . ': ' . $e->getMessage());
        }
    }

    private static function normaliseEventFilter(mixed $value): string
    {
        return strtolower(trim((string) $value)) === 'past' ? 'past' : 'upcoming';
    }

    private static function readEventSettings(?Database $db = null): array
    {
        $db ??= Database::getInstance();

        $defaults = [
            'list_page_id' => 0,
            'detail_page_id' => 0,
            'default_events_per_page' => 10,
            'default_filter' => 'upcoming',
            'show_return_to_list' => true,
            'show_event_navigation' => true,
        ];

        $rows = $db->fetchAll(
            "SELECT `key`, `value` FROM settings WHERE `group` = 'events' AND `key` IN (?, ?, ?, ?, ?, ?)",
            [
                'events.list_page_id',
                'events.detail_page_id',
                'events.default_events_per_page',
                'events.default_filter',
                'events.show_return_to_list',
                'events.show_event_navigation',
            ]
        );

        $raw = [];
        foreach ($rows as $row) {
            $raw[(string) ($row['key'] ?? '')] = $row['value'] ?? null;
        }

        if ($raw === []) {
            $legacyJson = $db->fetchColumn('SELECT settings FROM module_config WHERE slug = ? LIMIT 1', ['events']);
            $legacy = is_string($legacyJson) ? (json_decode($legacyJson, true) ?: []) : [];
            if (is_array($legacy) && !empty($legacy)) {
                $legacyMap = [
                    'events.list_page_id' => isset($legacy['event_list_page_id']) ? (string) $legacy['event_list_page_id'] : null,
                    'events.detail_page_id' => isset($legacy['event_detail_page_id']) ? (string) $legacy['event_detail_page_id'] : null,
                ];

                foreach ($legacyMap as $legacyKey => $legacyValue) {
                    if ($legacyValue === null || $legacyValue === '' || $legacyValue === '0') {
                        continue;
                    }
                    $db->execute(
                        "INSERT INTO settings (`key`, `value`, `group`) VALUES (?, ?, 'events')"
                        . " ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `group` = VALUES(`group`)",
                        [$legacyKey, $legacyValue]
                    );
                    $raw[$legacyKey] = $legacyValue;
                }
            }
        }

        $settings = $defaults;
        $settings['list_page_id'] = max(0, (int) ($raw['events.list_page_id'] ?? 0));
        $settings['detail_page_id'] = max(0, (int) ($raw['events.detail_page_id'] ?? 0));
        $settings['default_events_per_page'] = self::normalisePerPage($raw['events.default_events_per_page'] ?? 10);
        $settings['default_filter'] = self::normaliseEventFilter($raw['events.default_filter'] ?? 'upcoming');
        $settings['show_return_to_list'] = ($raw['events.show_return_to_list'] ?? '1') === '1';
        $settings['show_event_navigation'] = ($raw['events.show_event_navigation'] ?? '1') === '1';

        return $settings;
    }

    private static function readEventProfiles(Database $db): array
    {
        $rows = $db->fetchAll(
            'SELECT id, name, slug, description, display_mode, events_per_page, default_filter, show_return_to_list, show_event_navigation, updated_at
             FROM event_profiles
             ORDER BY name ASC'
        );

        return array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'display_mode' => (string) ($row['display_mode'] ?? 'both'),
                'events_per_page' => self::normalisePerPage($row['events_per_page'] ?? 10),
                'default_filter' => self::normaliseEventFilter($row['default_filter'] ?? 'upcoming'),
                'show_return_to_list' => (int) ($row['show_return_to_list'] ?? 1) === 1,
                'show_event_navigation' => (int) ($row['show_event_navigation'] ?? 1) === 1,
                'updated_at' => $row['updated_at'] ?? null,
            ];
        }, $rows);
    }

    private static function readEventProfile(Database $db, int $profileId): ?array
    {
        if ($profileId <= 0) {
            return null;
        }

        $row = $db->fetch(
            'SELECT id, name, slug, description, display_mode, events_per_page, default_filter, show_return_to_list, show_event_navigation, updated_at
             FROM event_profiles
             WHERE id = ?
             LIMIT 1',
            [$profileId]
        );

        if (!$row) {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'display_mode' => (string) ($row['display_mode'] ?? 'both'),
            'events_per_page' => self::normalisePerPage($row['events_per_page'] ?? 10),
            'default_filter' => self::normaliseEventFilter($row['default_filter'] ?? 'upcoming'),
            'show_return_to_list' => (int) ($row['show_return_to_list'] ?? 1) === 1,
            'show_event_navigation' => (int) ($row['show_event_navigation'] ?? 1) === 1,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private static function applyEventProfileSettings(array $settings, ?Database $db = null): array
    {
        $profileId = max(0, (int) ($settings['event_profile_id'] ?? ($settings['profile_id'] ?? 0)));
        if ($profileId <= 0) {
            return $settings;
        }

        $db ??= Database::getInstance();
        $profile = self::readEventProfile($db, $profileId);
        if (!$profile) {
            return $settings;
        }

        if (!array_key_exists('mode', $settings) || trim((string) $settings['mode']) === '') {
            $settings['mode'] = $profile['display_mode'];
        }
        if (!array_key_exists('per_page', $settings) || (int) $settings['per_page'] <= 0) {
            $settings['per_page'] = $profile['events_per_page'];
        }
        if (!array_key_exists('filter', $settings) || trim((string) $settings['filter']) === '') {
            $settings['filter'] = $profile['default_filter'];
        }
        if (!array_key_exists('show_return_to_list', $settings)) {
            $settings['show_return_to_list'] = $profile['show_return_to_list'];
        }
        if (!array_key_exists('show_event_navigation', $settings)) {
            $settings['show_event_navigation'] = $profile['show_event_navigation'];
        }

        return $settings;
    }

    private function upsertEventSetting(string $key, ?string $value): void
    {
        $this->db->execute(
            "INSERT INTO settings (`key`, `value`, `group`) VALUES (?, ?, 'events')"
            . " ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `group` = VALUES(`group`)",
            [$key, $value]
        );
    }

    private static function buildEventNavigation(Database $db, array $event, string $basePath, int $perPage): array
    {
        $publishedEvents = $db->fetchAll(
            'SELECT id, slug, title, date_start
             FROM events
             WHERE status = ?
             ORDER BY date_start ASC, id ASC',
            ['published']
        );

        $currentIndex = null;
        $currentId = (int) ($event['id'] ?? 0);
        foreach ($publishedEvents as $index => $publishedEvent) {
            if ((int) ($publishedEvent['id'] ?? 0) === $currentId) {
                $currentIndex = $index;
                break;
            }
        }

        $filter = strtotime((string) ($event['date_start'] ?? '')) < time() ? 'past' : 'upcoming';
        $returnUrl = $basePath . ($filter === 'past' ? '?show=past' : '');
        $previousEvent = null;
        $nextEvent = null;

        if ($currentIndex !== null) {
            $returnPage = (int) floor($currentIndex / max(1, $perPage)) + 1;
            if ($returnPage > 1) {
                $returnUrl .= ($filter === 'past' ? '&' : '?') . 'page=' . $returnPage;
            }
            $returnUrl .= '#event-' . $currentId;

            if (isset($publishedEvents[$currentIndex - 1])) {
                $previousEvent = self::attachPublicUrl($publishedEvents[$currentIndex - 1], $basePath);
            }
            if (isset($publishedEvents[$currentIndex + 1])) {
                $nextEvent = self::attachPublicUrl($publishedEvents[$currentIndex + 1], $basePath);
            }
        }

        return [
            'return_to_list_url' => $returnUrl,
            'previous_event' => $previousEvent,
            'next_event' => $nextEvent,
        ];
    }

    private function normaliseEventProfileInput(?array $existing = null): array
    {
        $name = trim((string) $this->input('name', $existing['name'] ?? ''));
        $manualSlug = trim((string) $this->input('slug', $existing['slug'] ?? ''));
        $slugSource = $manualSlug !== '' ? $manualSlug : $name;
        $slug = $slugSource !== '' ? $this->sanitiseSlug($slugSource) : '';
        if ($slug === '' && $name !== '') {
            $slug = 'events-profile-' . date('YmdHis');
        }

        $displayMode = strtolower(trim((string) $this->input('display_mode', $existing['display_mode'] ?? 'both')));
        if (!in_array($displayMode, ['list', 'detail', 'both'], true)) {
            $displayMode = 'both';
        }

        $profile = [
            'name' => $name,
            'slug' => $slug,
            'description' => trim((string) $this->input('description', $existing['description'] ?? '')),
            'display_mode' => $displayMode,
            'events_per_page' => self::normalisePerPage($this->input('events_per_page', $existing['events_per_page'] ?? 10)),
            'default_filter' => self::normaliseEventFilter($this->input('default_filter', $existing['default_filter'] ?? 'upcoming')),
            'show_return_to_list' => $this->input('show_return_to_list', !empty($existing['show_return_to_list']) ? '1' : '0') === '1',
            'show_event_navigation' => $this->input('show_event_navigation', !empty($existing['show_event_navigation']) ? '1' : '0') === '1',
        ];

        $errors = [];
        if ($profile['name'] === '') {
            $errors['name'] = 'Profile name is required.';
        }
        if ($profile['slug'] === '') {
            $errors['slug'] = 'Profile slug is required.';
        }

        return [$profile, $errors];
    }

    private static function hydrateEventPreviewData(array $events): array
    {
        foreach ($events as &$event) {
            $description = trim((string) ($event['description'] ?? ''));
            $event['preview_text'] = $description !== ''
                ? truncate(strip_tags($description), 220)
                : '';
        }
        unset($event);

        return $events;
    }
}
