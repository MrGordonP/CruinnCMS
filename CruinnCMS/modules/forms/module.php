<?php
/**
 * Forms Module — Dynamic form builder with field management and submission handling.
 */

use Cruinn\Module\Forms\Controllers\FormController;

return [
    'slug'         => 'forms',
    'name'         => 'Forms',
    'description'  => 'Dynamic form builder with custom fields, validation, approval workflow, and CSV export.',
    'provides'     => ['forms'],
    'migrations'   => [
        __DIR__ . '/migrations/schema.sql',
        __DIR__ . '/migrations/002_forms_subject_id.sql',
    ],

    'submodules' => [
        'payment-fields' => [
            'name'        => 'Payment Fields',
            'description' => 'Adds payment options and payment tracking to form submissions.',
            'requires'    => ['payments'],
            'migrations'  => [
                __DIR__ . '/migrations/payment_fields.sql',
            ],
        ],
    ],
    'template_path' => __DIR__ . '/templates',

    'acp_sections' => [
        ['group' => 'Content', 'label' => 'Forms', 'url' => '/admin/forms', 'icon' => '📋'],
    ],

    'dashboard_sections' => [
        ['group' => 'Content', 'label' => 'Forms', 'url' => '/admin/forms', 'icon' => '📋', 'roles' => ['admin']],
    ],

    'widget_providers' => [
        [
            'slug'     => 'quick-links',
            'label'    => 'Forms Quick Links',
            'provider' => 'Cruinn\\Module\\Forms\\Widgets\\DashboardWidgets::quickLinksData',
            'template' => 'widgets/dashboard-quick-links',
        ],
        [
            'slug'     => 'status-summary',
            'label'    => 'Forms Status Summary',
            'provider' => 'Cruinn\\Module\\Forms\\Widgets\\DashboardWidgets::statusSummaryData',
            'template' => 'widgets/dashboard-status-summary',
        ],
        [
            'slug'     => 'form-summary',
            'label'    => 'Forms Summary (Selected Form)',
            'provider' => 'Cruinn\\Module\\Forms\\Widgets\\DashboardWidgets::formSummaryData',
            'template' => 'widgets/dashboard-status-summary',
        ],
    ],

    'routes' => function (\Cruinn\Router $router) {
        // Admin — Forms
        $router->get('/admin/forms',                                    [FormController::class, 'adminList']);
        $router->get('/admin/forms/new',                                [FormController::class, 'adminNew']);
        $router->post('/admin/forms',                                   [FormController::class, 'adminCreate']);
        $router->get('/admin/forms/{id}/edit',                          [FormController::class, 'adminEdit']);
        $router->post('/admin/forms/{id}',                              [FormController::class, 'adminUpdate']);
        $router->post('/admin/forms/{id}/delete',                       [FormController::class, 'adminDelete']);
        $router->post('/admin/forms/{formId}/fields',                   [FormController::class, 'addField']);
        $router->post('/admin/forms/{formId}/fields/{fieldId}',         [FormController::class, 'updateField']);
        $router->post('/admin/forms/{formId}/fields/{fieldId}/delete',  [FormController::class, 'deleteField']);
        $router->post('/admin/forms/{formId}/fields/reorder',           [FormController::class, 'reorderFields']);
        $router->post('/admin/forms/{formId}/payment-options',           [FormController::class, 'addPaymentOption']);
        $router->post('/admin/forms/{formId}/payment-options/{optionId}/delete', [FormController::class, 'deletePaymentOption']);
        $router->get('/admin/forms/{formId}/submissions',               [FormController::class, 'submissions']);
        $router->get('/admin/forms/{formId}/submissions/{submissionId}',[FormController::class, 'submissionDetail']);
        $router->post('/admin/forms/{formId}/submissions/{submissionId}/approve', [FormController::class, 'approveSubmission']);
        $router->post('/admin/forms/{formId}/submissions/{submissionId}/reject',  [FormController::class, 'rejectSubmission']);
        $router->get('/admin/forms/{formId}/export',                    [FormController::class, 'exportCsv']);

        // Public — Forms
        $router->get('/forms/{slug}',  [FormController::class, 'publicShow']);
        $router->post('/forms/{slug}', [FormController::class, 'publicSubmit']);
    },
];
