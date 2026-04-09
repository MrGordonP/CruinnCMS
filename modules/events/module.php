<?php
/**
 * Events Module
 *
 * Public event listing, registration, and admin CRUD with attendee management.
 */

use IGA\Router;
use IGA\Module\Events\Controllers\EventController;

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
        __DIR__ . '/migrations/004_event_enhancements.sql',
    ],

    'template_path' => __DIR__ . '/templates',

    'acp_sections' => [
        ['group' => 'Community', 'label' => 'Events', 'url' => '/admin/events', 'icon' => '📅'],
    ],

    'dashboard_sections' => [
        ['group' => 'Community', 'label' => 'Events', 'url' => '/admin/events', 'icon' => '📅', 'roles' => ['admin']],
    ],

    'provides' => ['events'],
];
