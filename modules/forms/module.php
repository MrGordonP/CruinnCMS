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
    'migrations'   => ['020_forms.sql'],
    'template_path' => __DIR__ . '/templates',

    'acp_sections' => [
        ['group' => 'Content', 'label' => 'Forms', 'url' => '/admin/forms', 'icon' => '📋'],
    ],

    'dashboard_sections' => [
        ['group' => 'Content', 'label' => 'Forms', 'url' => '/admin/forms', 'icon' => '📋', 'roles' => ['admin']],
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
