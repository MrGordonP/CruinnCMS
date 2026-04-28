<?php
/**
 * Events Module
 *
 * Public event listing, registration, and admin CRUD with attendee management.
 */

use Cruinn\Router;
use Cruinn\Module\Events\Controllers\EventController;

return [
    'slug'        => 'events',
    'name'        => 'Events',
    'version'     => '1.0.0',
    'description' => 'Event management with public registration, capacity control, and CSV export.',

    'routes' => function (Router $router): void {
        // Admin
        $router->get('/admin/events',                 [EventController::class, 'adminList']);
        $router->get('/admin/events/new',             [EventController::class, 'adminNew']);
        $router->post('/admin/events',                [EventController::class, 'adminCreate']);
        $router->get('/admin/events/{id}',            [EventController::class, 'adminShow']);
        $router->get('/admin/events/{id}/edit',       [EventController::class, 'adminEdit']);
        $router->post('/admin/events/{id}',           [EventController::class, 'adminUpdate']);
        $router->post('/admin/events/{id}/delete',    [EventController::class, 'adminDelete']);
        $router->get('/admin/events/{id}/export',     [EventController::class, 'adminExportRegistrations']);
        $router->post('/admin/events/{id}/registrations/{regId}/cancel',  [EventController::class, 'adminCancelRegistration']);
        $router->post('/admin/events/{id}/registrations/{regId}/payment', [EventController::class, 'adminMarkPaid']);

        // Public
        $router->get('/events',                      [EventController::class, 'index']);
        $router->get('/events/{slug}',               [EventController::class, 'show']);
        $router->get('/events/{slug}/register',      [EventController::class, 'showRegisterForm']);
        $router->post('/events/{slug}/register',     [EventController::class, 'register']);
        $router->get('/events/{slug}/cancel/{token}',[EventController::class, 'cancelRegistration']);
    },

    'migrations' => [
        __DIR__ . '/migrations/schema.sql',
    ],

    'template_path' => __DIR__ . '/templates',

    'acp_sections' => [
        ['group' => 'Community', 'label' => 'Events', 'url' => '/admin/events', 'icon' => '📅'],
    ],

    'dashboard_sections' => [
        ['group' => 'Community', 'label' => 'Events', 'url' => '/admin/events', 'icon' => '📅', 'roles' => ['admin']],
    ],

    'provides' => ['events'],

    'public_routes' => [
        ['route' => '/events', 'label' => 'Events'],
    ],

    'widgets' => function (): array {
        try {
            $db = \Cruinn\Database::getInstance();

            // ── Upcoming Events widget ─────────────────────────────────────
            $upcoming = $db->fetchAll(
                'SELECT id, title, slug, date_start, location
                 FROM events
                 WHERE date_start >= NOW() AND status = ?
                 ORDER BY date_start ASC
                 LIMIT 5',
                ['published']
            );

            $eventsHtml = '<ul class="widget-event-list">';
            if (empty($upcoming)) {
                $eventsHtml .= '<li class="widget-event-empty">No upcoming events.</li>';
            } else {
                foreach ($upcoming as $ev) {
                    $date  = date('j M Y', strtotime($ev['date_start']));
                    $title = htmlspecialchars($ev['title'], ENT_QUOTES, 'UTF-8');
                    $slug  = htmlspecialchars($ev['slug'],  ENT_QUOTES, 'UTF-8');
                    $loc   = $ev['location'] ? ' <span class="widget-event-loc">' . htmlspecialchars($ev['location'], ENT_QUOTES, 'UTF-8') . '</span>' : '';
                    $eventsHtml .= "<li class=\"widget-event-item\">"
                        . "<time class=\"widget-event-date\">{$date}</time>"
                        . "<a href=\"/events/{$slug}\" class=\"widget-event-title\">{$title}</a>"
                        . $loc
                        . "</li>";
                }
            }
            $eventsHtml .= '</ul><a href="/events" class="widget-more">All events &rarr;</a>';

            // ── Mini Calendar widget ───────────────────────────────────────
            $year  = (int) date('Y');
            $month = (int) date('n');
            $daysInMonth = (int) date('t');
            $firstDow    = (int) date('N', mktime(0, 0, 0, $month, 1, $year)); // 1=Mon

            // Fetch days that have at least one published event this month
            $eventDays = $db->fetchAll(
                'SELECT DISTINCT DAY(date_start) as day
                 FROM events
                 WHERE YEAR(date_start) = ? AND MONTH(date_start) = ? AND status = ?',
                [$year, $month, 'published']
            );
            $eventDaySet = array_column($eventDays, 'day');

            $monthName = date('F Y', mktime(0, 0, 0, $month, 1, $year));
            $today     = (int) date('j');

            $cal  = "<div class=\"widget-calendar\">";
            $cal .= "<div class=\"widget-calendar-header\">{$monthName}</div>";
            $cal .= '<table class="widget-calendar-table" aria-label="' . htmlspecialchars($monthName, ENT_QUOTES, 'UTF-8') . '">';
            $cal .= '<thead><tr><th>M</th><th>T</th><th>W</th><th>T</th><th>F</th><th>S</th><th>S</th></tr></thead>';
            $cal .= '<tbody><tr>';

            // Leading empty cells
            for ($i = 1; $i < $firstDow; $i++) {
                $cal .= '<td></td>';
            }

            $dow = $firstDow;
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $cls  = '';
                if ($day === $today) { $cls .= ' cal-today'; }
                if (in_array($day, $eventDaySet, true)) { $cls .= ' cal-has-event'; }
                $cls = trim($cls);
                $cellClass = $cls ? " class=\"{$cls}\"" : '';
                $cal .= "<td{$cellClass}>{$day}</td>";
                if ($dow === 7 && $day < $daysInMonth) {
                    $cal .= '</tr><tr>';
                    $dow = 1;
                } else {
                    $dow++;
                }
            }

            // Trailing empty cells
            while ($dow <= 7) {
                $cal .= '<td></td>';
                $dow++;
            }
            $cal .= '</tr></tbody></table></div>';

            return [
                ['title' => 'Upcoming Events', 'html' => $eventsHtml],
                ['title' => date('F Y'), 'html' => $cal],
            ];
        } catch (\Exception $e) {
            return [];
        }
    },
];
