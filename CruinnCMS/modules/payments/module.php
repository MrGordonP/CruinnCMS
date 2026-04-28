<?php
/**
 * Forms Module — Dynamic form builder with field management and submission handling.
 */

use Cruinn\Module\Payments\Controllers\PaymentController;

return [
    'slug'        => 'payments',
    'name'        => 'Payments',
    'version'     => '1.0.0',
    'description' => 'Payment processing hub. Handles initiation, gateway callbacks, and manual payment verification. Gateway integrations (Stripe, etc.) are configured here.',
    'provides'    => ['payments'],
    'migrations'  => [
        __DIR__ . '/migrations/schema.sql',
    ],
    'template_path' => __DIR__ . '/templates',

    'acp_sections' => [],

    'dashboard_sections' => [],

    'routes' => static function (\Cruinn\Router $router): void {
        // Public — payment flow
        $router->get('/payments/initiate',          [PaymentController::class, 'initiate']);
        $router->get('/payments/success',           [PaymentController::class, 'success']);
        $router->get('/payments/cancel',            [PaymentController::class, 'cancel']);
        $router->post('/payments/webhook/{gateway}',[PaymentController::class, 'webhook']);

        // Admin — TODO when admin dashboard is built
        // $router->get('/admin/payments', [PaymentAdminController::class, 'index']);
    },
];
