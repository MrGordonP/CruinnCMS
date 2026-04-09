<?php
/**
 * IGA Portal — Event Controller
 *
 * Public event listing, detail pages, and event registration.
 * Admin event CRUD, attendee management, and export.
 */

namespace IGA\Module\Events\Controllers;

use IGA\Auth;
use IGA\App;
use IGA\Database;
use IGA\Mailer;
use IGA\Controllers\BaseController;

class EventController extends BaseController
{
    // ══════════════════════════════════════════════════════════════
    // PUBLIC — Event Listing
    // ══════════════════════════════════════════════════════════════

    /**
     * GET /events — List upcoming and past events.
     */
    public function index(): void
    {
        $filter = $this->query('show', 'upcoming');

        if ($filter === 'past') {
            $events = $this->db->fetchAll(
                'SELECT * FROM events WHERE date_start < NOW() AND status = ?
                 ORDER BY date_start DESC
                 LIMIT 50',
                ['published']
            );
        } else {
            $events = $this->db->fetchAll(
                'SELECT * FROM events WHERE date_start >= NOW() AND status = ?
                 ORDER BY date_start ASC',
                ['published']
            );
        }

        $this->render('public/events/index', [
            'title'  => 'Events',
            'events' => $events,
            'filter' => $filter,
        ]);
    }

    /**
     * GET /events/{slug} — Show a single event.
     */
    public function show(string $slug): void
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

        // Get active registration count
        $registrationCount = $this->db->fetchColumn(
            'SELECT COUNT(*) FROM event_registrations WHERE event_id = ? AND status = ?',
            [$event['id'], 'confirmed']
        );

        $spotsRemaining = null;
        if ($event['capacity'] > 0) {
            $spotsRemaining = max(0, $event['capacity'] - $registrationCount);
        }

        // Check if current user is already registered
        $userRegistered = false;
        if (Auth::check()) {
            $member = $this->db->fetch('SELECT id FROM members WHERE user_id = ?', [Auth::userId()]);
            if ($member) {
                $existing = $this->db->fetch(
                    'SELECT id FROM event_registrations WHERE event_id = ? AND member_id = ? AND status = ?',
                    [$event['id'], $member['id'], 'confirmed']
                );
                $userRegistered = (bool) $existing;
            }
        }

        // Can this event accept registrations?
        $canRegister = $this->canRegister($event, $spotsRemaining);

        $siteUrl = \IGA\App::config('site.url', '');
        $defaultImg = $siteUrl . \IGA\App::config('social.default_image', '');
        $eventDesc = truncate(strip_tags($event['description'] ?? ''), 200);

        $this->render('public/events/show', [
            'title'             => $event['title'],
            'event'             => $event,
            'registrationCount' => $registrationCount,
            'spotsRemaining'    => $spotsRemaining,
            'userRegistered'    => $userRegistered,
            'canRegister'       => $canRegister,
            'meta_description'  => $eventDesc,
            'og_title'          => $event['title'],
            'og_type'           => 'event',
            'og_url'            => $siteUrl . '/events/' . $event['slug'],
            'og_description'    => $eventDesc,
            'og_image'          => $defaultImg,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // PUBLIC — Event Registration
    // ══════════════════════════════════════════════════════════════

    /**
     * GET /events/{slug}/register — Show registration form.
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
            $this->redirect("/events/{$slug}");
        }

        // Pre-fill fields for logged-in members
        $prefill = [];
        if (Auth::check()) {
            $member = $this->db->fetch(
                'SELECT m.*, u.email as user_email FROM members m JOIN users u ON m.user_id = u.id WHERE m.user_id = ?',
                [Auth::userId()]
            );
            if ($member) {
                // Check if already registered
                $existing = $this->db->fetch(
                    'SELECT id FROM event_registrations WHERE event_id = ? AND member_id = ? AND status = ?',
                    [$event['id'], $member['id'], 'confirmed']
                );
                if ($existing) {
                    Auth::flash('info', 'You are already registered for this event.');
                    $this->redirect("/events/{$slug}");
                }
                $prefill = [
                    'name'      => trim(($member['forenames'] ?? '') . ' ' . ($member['surnames'] ?? '')),
                    'email'     => $member['user_email'] ?? $member['email'] ?? '',
                    'member_id' => $member['id'],
                ];
            }
        }

        $this->render('public/events/register', [
            'title'          => 'Register — ' . $event['title'],
            'event'          => $event,
            'spotsRemaining' => $spotsRemaining,
            'prefill'        => $prefill,
        ]);
    }

    /**
     * POST /events/{slug}/register — Process event registration.
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
            $this->redirect("/events/{$slug}");
        }

        // Validate required fields
        $name  = $this->input('name');
        $email = $this->input('email');

        if (empty($name) || empty($email)) {
            Auth::flash('error', 'Name and email are required.');
            $this->redirect("/events/{$slug}/register");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Auth::flash('error', 'Please enter a valid email address.');
            $this->redirect("/events/{$slug}/register");
        }

        // Determine member_id
        $memberId = null;
        if (Auth::check()) {
            $member = $this->db->fetch('SELECT id FROM members WHERE user_id = ?', [Auth::userId()]);
            if ($member) {
                $memberId = $member['id'];

                // Check for duplicate registration
                $existing = $this->db->fetch(
                    'SELECT id FROM event_registrations WHERE event_id = ? AND member_id = ? AND status = ?',
                    [$event['id'], $memberId, 'confirmed']
                );
                if ($existing) {
                    Auth::flash('info', 'You are already registered for this event.');
                    $this->redirect("/events/{$slug}");
                }
            }
        } else {
            // Check for duplicate guest email registration
            $existing = $this->db->fetch(
                'SELECT id FROM event_registrations WHERE event_id = ? AND guest_email = ? AND status = ?',
                [$event['id'], strtolower($email), 'confirmed']
            );
            if ($existing) {
                Auth::flash('info', 'This email is already registered for this event.');
                $this->redirect("/events/{$slug}");
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
                $this->redirect("/events/{$slug}");
            }
        }

        // Determine payment status
        $paymentStatus = ($event['price'] > 0) ? 'pending' : 'free';

        // Generate confirmation token
        $token = bin2hex(random_bytes(32));

        // Create registration
        $regId = $this->db->insert('event_registrations', [
            'event_id'           => $event['id'],
            'member_id'          => $memberId,
            'guest_name'         => $memberId ? null : $name,
            'guest_email'        => $memberId ? null : strtolower($email),
            'dietary_notes'      => $this->input('dietary_notes') ?: null,
            'access_notes'       => $this->input('access_notes') ?: null,
            'payment_status'     => $paymentStatus,
            'payment_ref'        => null,
            'payment_method'     => null,
            'amount_paid'        => 0.00,
            'status'             => 'confirmed',
            'confirmation_token' => $token,
            'registered_at'      => date('Y-m-d H:i:s'),
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

        $this->redirect("/events/{$slug}");
    }

    /**
     * GET /events/{slug}/cancel/{token} — Cancel a registration via confirmation token.
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
            $this->redirect("/events/{$slug}");
        }

        $this->db->update('event_registrations', [
            'status'        => 'cancelled',
            'cancelled_at'  => date('Y-m-d H:i:s'),
            'cancel_reason' => 'Self-cancelled via link',
        ], 'id = ?', [$reg['id']]);

        $registrantName = $reg['guest_name'] ?? 'Member #' . $reg['member_id'];
        $this->logActivity('cancel', 'event', (int) $event['id'], "Registration #{$reg['id']} cancelled by {$registrantName}");

        Auth::flash('success', 'Your registration has been cancelled.');
        $this->redirect("/events/{$slug}");
    }

    // ══════════════════════════════════════════════════════════════
    // ADMIN — Event List
    // ══════════════════════════════════════════════════════════════

    /**
     * GET /admin/events — Admin event listing.
     */
    public function adminList(): void
    {
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
            'search'      => $search,
            'status'      => $status,
            'type'        => $type,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'totalCount'  => $totalCount,
            'breadcrumbs' => [['Admin', '/admin'], ['Events']],
        ]);
    }

    /**
     * GET /admin/events/{id} — Event detail with registration summary.
     */
    public function adminShow(string $id): void
    {
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
            'SELECT r.*, m.forenames, m.surnames, m.email as member_email
             FROM event_registrations r
             LEFT JOIN members m ON r.member_id = m.id
             WHERE r.event_id = ?
             ORDER BY r.registered_at DESC',
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
                if ($reg['payment_status'] === 'pending') {
                    $pendingPayment++;
                }
            } elseif ($reg['status'] === 'cancelled') {
                $cancelledCount++;
            }
        }

        $this->renderAdmin('admin/events/show', [
            'title'          => $event['title'],
            'event'          => $event,
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
        $this->renderAdmin('admin/events/edit', [
            'title'       => 'New Event',
            'event'       => null,
            'breadcrumbs' => [['Admin', '/admin'], ['Events', '/admin/events'], ['New Event']],
        ]);
    }

    /**
     * POST /admin/events — Create event.
     */
    public function adminCreate(): void
    {
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

        $id = $this->db->insert('events', [
            'title'             => $this->input('title'),
            'slug'              => $slug,
            'event_type'        => $this->input('event_type'),
            'description'       => $this->input('description') ?: null,
            'date_start'        => $this->input('date_start'),
            'date_end'          => $this->input('date_end') ?: null,
            'location'          => $this->input('location') ?: null,
            'location_lat'      => $this->input('location_lat') ?: null,
            'location_lng'      => $this->input('location_lng') ?: null,
            'capacity'          => (int) ($this->input('capacity') ?: 0),
            'price'             => (float) ($this->input('price') ?: 0.00),
            'currency'          => $this->input('currency', 'EUR'),
            'reg_deadline'      => $this->input('reg_deadline') ?: null,
            'registration_open' => $this->input('registration_open') ? 1 : 0,
            'status'            => $this->input('status', 'draft'),
            'created_by'        => Auth::userId(),
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        $this->logActivity('create', 'event', (int) $id, $this->input('title'));
        Auth::flash('success', 'Event created.');
        $this->redirect("/admin/events/{$id}");
    }

    /**
     * GET /admin/events/{id}/edit — Show edit event form.
     */
    public function adminEdit(string $id): void
    {
        $event = $this->db->fetch('SELECT * FROM events WHERE id = ?', [$id]);
        if (!$event) {
            Auth::flash('error', 'Event not found.');
            $this->redirect('/admin/events');
        }

        $this->renderAdmin('admin/events/edit', [
            'title'       => 'Edit: ' . $event['title'],
            'event'       => $event,
            'breadcrumbs' => [['Admin', '/admin'], ['Events', '/admin/events'], [$event['title']]],
        ]);
    }

    /**
     * POST /admin/events/{id} — Update event.
     */
    public function adminUpdate(string $id): void
    {
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

        $this->db->update('events', [
            'title'             => $this->input('title'),
            'slug'              => $slug,
            'event_type'        => $this->input('event_type'),
            'description'       => $this->input('description') ?: null,
            'date_start'        => $this->input('date_start'),
            'date_end'          => $this->input('date_end') ?: null,
            'location'          => $this->input('location') ?: null,
            'location_lat'      => $this->input('location_lat') ?: null,
            'location_lng'      => $this->input('location_lng') ?: null,
            'capacity'          => (int) ($this->input('capacity') ?: 0),
            'price'             => (float) ($this->input('price') ?: 0.00),
            'currency'          => $this->input('currency', 'EUR'),
            'reg_deadline'      => $this->input('reg_deadline') ?: null,
            'registration_open' => $this->input('registration_open') ? 1 : 0,
            'status'            => $this->input('status', 'draft'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        $this->logActivity('update', 'event', (int) $id, $this->input('title'));
        Auth::flash('success', 'Event updated.');
        $this->redirect("/admin/events/{$id}");
    }

    /**
     * POST /admin/events/{id}/delete — Delete event and all registrations.
     */
    public function adminDelete(string $id): void
    {
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

        $registrantName = $reg['guest_name'] ?? "Member #{$reg['member_id']}";
        $this->logActivity('cancel', 'event', (int) $id, "Admin cancelled registration #{$regId} ({$registrantName})");

        Auth::flash('success', 'Registration cancelled.');
        $this->redirect("/admin/events/{$id}");
    }

    /**
     * POST /admin/events/{id}/registrations/{regId}/payment — Mark registration as paid.
     */
    public function adminMarkPaid(string $id, string $regId): void
    {
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
            'payment_status' => 'paid',
            'payment_method' => $this->input('payment_method', 'manual'),
            'payment_ref'    => $this->input('payment_ref') ?: 'Admin: ' . date('Y-m-d'),
            'amount_paid'    => (float) $event['price'],
        ], 'id = ?', [$regId]);

        $registrantName = $reg['guest_name'] ?? "Member #{$reg['member_id']}";
        $this->logActivity('payment', 'event', (int) $id, "Marked registration #{$regId} as paid ({$registrantName})");

        Auth::flash('success', 'Payment recorded.');
        $this->redirect("/admin/events/{$id}");
    }

    /**
     * GET /admin/events/{id}/export — Export registrations as CSV.
     */
    public function adminExportRegistrations(string $id): void
    {
        $event = $this->db->fetch('SELECT * FROM events WHERE id = ?', [$id]);
        if (!$event) {
            Auth::flash('error', 'Event not found.');
            $this->redirect('/admin/events');
        }

        $registrations = $this->db->fetchAll(
            'SELECT r.*, m.forenames, m.surnames, m.email as member_email
             FROM event_registrations r
             LEFT JOIN members m ON r.member_id = m.id
             WHERE r.event_id = ?
             ORDER BY r.registered_at ASC',
            [$id]
        );

        $filename = 'registrations-' . $event['slug'] . '-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");

        $out = fopen('php://output', 'w');
        // BOM for Excel UTF-8
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, [
            'Reg #', 'Name', 'Email', 'Type', 'Dietary Notes', 'Access Notes',
            'Payment Status', 'Amount Paid', 'Payment Method', 'Payment Ref',
            'Status', 'Registered At', 'Cancelled At',
        ]);

        foreach ($registrations as $reg) {
            $name  = $reg['member_id']
                ? trim(($reg['forenames'] ?? '') . ' ' . ($reg['surnames'] ?? ''))
                : ($reg['guest_name'] ?? '');
            $email = $reg['member_id']
                ? ($reg['member_email'] ?? '')
                : ($reg['guest_email'] ?? '');
            $type  = $reg['member_id'] ? 'Member' : 'Guest';

            fputcsv($out, [
                $reg['id'],
                $name,
                $email,
                $type,
                $reg['dietary_notes'] ?? '',
                $reg['access_notes'] ?? '',
                ucfirst($reg['payment_status']),
                number_format((float) $reg['amount_paid'], 2),
                $reg['payment_method'] ?? '',
                $reg['payment_ref'] ?? '',
                ucfirst($reg['status']),
                $reg['registered_at'],
                $reg['cancelled_at'] ?? '',
            ]);
        }

        fclose($out);
        exit;
    }

    // ══════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════

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
        $eventUrl  = "{$siteUrl}/events/{$event['slug']}";
        $cancelUrl = "{$siteUrl}/events/{$event['slug']}/cancel/{$token}";

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
            <p style='margin-top:24px;font-size:13px;color:#666'>Need to cancel? <a href='{$cancelUrl}'>Cancel your registration</a></p>
        ";

        $text = "Registration Confirmed\n\nHi {$name},\n\nYou're registered for {$event['title']}.\n\nDate: {$dateStr}\nLocation: " . ($event['location'] ?? 'TBC') . "\n\nView event: {$eventUrl}\nCancel: {$cancelUrl}";

        Mailer::send($email, "Registration Confirmed — {$event['title']}", $html, $text);
    }
}
