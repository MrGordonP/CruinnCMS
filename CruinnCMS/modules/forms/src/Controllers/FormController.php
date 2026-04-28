<?php

namespace Cruinn\Module\Forms\Controllers;

use Cruinn\Auth;
use Cruinn\Controllers\BaseController;
use Cruinn\Module\Forms\Services\FormService;

class FormController extends BaseController
{
    private FormService $formService;

    public function __construct()
    {
        parent::__construct();
        $this->formService = new FormService();
    }

    // â”€â”€ Admin: Form CRUD â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function adminList(): void
    {
        Auth::requirePermission('forms.manage');

        $forms = $this->formService->allForms();

        $this->renderAdmin('admin/forms/index', [
            'title'       => 'Forms',
            'forms'       => $forms,
            'breadcrumbs' => [['Admin', '/admin'], ['Forms']],
        ]);
    }

    public function adminNew(): void
    {
        Auth::requirePermission('forms.manage');

        $this->renderAdmin('admin/forms/edit', [
            'title'       => 'New Form',
            'form'        => null,
            'paymentOptions' => [],
            'breadcrumbs' => [['Admin', '/admin'], ['Forms', '/admin/forms'], ['New']],
        ]);
    }

    public function adminCreate(): void
    {
        Auth::requirePermission('forms.manage');

        $errors = $this->validateRequired(['title' => 'Title']);

        $slug = $this->sanitiseSlug($this->input('slug') ?: $this->input('title'));

        // Check slug uniqueness
        $existing = $this->formService->getForm($slug);
        if ($existing) {
            $errors[] = 'A form with this slug already exists.';
        }

        if ($errors) {
            $this->renderAdmin('admin/forms/edit', [
                'title'  => 'New Form',
                'form'   => $_POST,
                'paymentOptions' => [],
                'errors' => $errors,
            ]);
            return;
        }

        $settings = [];
        if ($this->input('require_login'))      $settings['require_login'] = true;
        if ($this->input('require_approval'))   $settings['require_approval'] = true;
        if ($this->input('require_payment'))    $settings['require_payment'] = true;
        if ($this->input('notification_email'))  $settings['notification_email'] = $this->input('notification_email');
        if ($this->input('success_message'))    $settings['success_message'] = $this->input('success_message');
        if ($this->input('redirect_url'))       $settings['redirect_url'] = $this->input('redirect_url');
        if ($this->input('max_submissions'))    $settings['max_submissions'] = (int) $this->input('max_submissions');

        $id = $this->formService->createForm([
            'title'     => $this->input('title'),
            'slug'      => $slug,
            'description' => $this->input('description'),
            'form_type' => $this->input('form_type') ?: 'general',
            'status'    => $this->input('status') ?: 'draft',
            'settings'  => $settings,
        ]);

        $this->logActivity('create', 'form', (int) $id, $this->input('title'));
        Auth::flash('success', 'Form created. Add fields below.');
        $this->redirect("/admin/forms/{$id}/edit");
    }

    public function adminEdit(int $id): void
    {
        Auth::requirePermission('forms.manage');

        $form = $this->formService->getFormWithFields($id);
        if (!$form) {
            Auth::flash('error', 'Form not found.');
            $this->redirect('/admin/forms');
            return;
        }

        $paymentOptions = $this->formService->getPaymentOptions($form['id']);

        $this->renderAdmin('admin/forms/edit', [
            'title'          => 'Edit: ' . $form['title'],
            'form'           => $form,
            'paymentOptions' => $paymentOptions,
            'breadcrumbs'    => [['Admin', '/admin'], ['Forms', '/admin/forms'], [$form['title']]],
        ]);
    }

    public function adminUpdate(int $id): void
    {
        Auth::requirePermission('forms.manage');

        $form = $this->formService->getForm($id);
        if (!$form) {
            Auth::flash('error', 'Form not found.');
            $this->redirect('/admin/forms');
            return;
        }

        $errors = $this->validateRequired(['title' => 'Title']);
        if ($errors) {
            $formData = $this->formService->getFormWithFields($id);
            $formData = array_merge($formData, $_POST);
            $this->renderAdmin('admin/forms/edit', [
                'title'  => 'Edit: ' . ($form['title'] ?? ''),
                'form'   => $formData,
                'paymentOptions' => $this->formService->getPaymentOptions($id),
                'errors' => $errors,
            ]);
            return;
        }

        $settings = [];
        if ($this->input('require_login'))      $settings['require_login'] = true;
        if ($this->input('require_approval'))   $settings['require_approval'] = true;
        if ($this->input('require_payment'))    $settings['require_payment'] = true;
        if ($this->input('notification_email'))  $settings['notification_email'] = $this->input('notification_email');
        if ($this->input('success_message'))    $settings['success_message'] = $this->input('success_message');
        if ($this->input('redirect_url'))       $settings['redirect_url'] = $this->input('redirect_url');
        if ($this->input('max_submissions'))    $settings['max_submissions'] = (int) $this->input('max_submissions');

        $this->formService->updateForm($id, [
            'title'       => $this->input('title'),
            'description' => $this->input('description'),
            'form_type'   => $this->input('form_type') ?: 'general',
            'status'      => $this->input('status') ?: 'draft',
            'settings'    => $settings,
        ]);

        $this->logActivity('update', 'form', $id, $this->input('title'));
        Auth::flash('success', 'Form updated.');
        $this->redirect("/admin/forms/{$id}/edit");
    }

    public function adminDelete(int $id): void
    {
        Auth::requirePermission('forms.manage');

        $form = $this->formService->getForm($id);
        if (!$form) {
            Auth::flash('error', 'Form not found.');
            $this->redirect('/admin/forms');
            return;
        }

        $this->formService->deleteForm($id);
        $this->logActivity('delete', 'form', $id, $form['title']);
        Auth::flash('success', 'Form deleted.');
        $this->redirect('/admin/forms');
    }

    // â”€â”€ Admin: Field Management (AJAX) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function addField(int $formId): void
    {
        Auth::requirePermission('forms.manage');

        $form = $this->formService->getForm($formId);
        if (!$form) {
            $this->json(['error' => 'Form not found.'], 404);
            return;
        }

        $label = $this->input('label');
        if (!$label) {
            $this->json(['error' => 'Label is required.'], 400);
            return;
        }

        $name = $this->sanitiseSlug($this->input('name') ?: $label);

        $options = null;
        if ($this->input('options_raw')) {
            $lines = array_filter(array_map('trim', explode("\n", $this->input('options_raw'))));
            $options = array_map(function ($line) {
                $parts = explode('|', $line, 2);
                return ['value' => trim($parts[0]), 'label' => trim($parts[1] ?? $parts[0])];
            }, $lines);
        }

        $validation = [];
        if ($this->input('required'))   $validation['required'] = true;
        if ($this->input('min_length')) $validation['min_length'] = (int) $this->input('min_length');
        if ($this->input('max_length')) $validation['max_length'] = (int) $this->input('max_length');

        $fieldId = $this->formService->addField($formId, [
            'field_type'  => $this->input('field_type') ?: 'text',
            'label'       => $label,
            'name'        => $name,
            'placeholder' => $this->input('placeholder'),
            'help_text'   => $this->input('help_text'),
            'options'     => $options,
            'validation'  => $validation,
        ]);

        $this->json(['success' => true, 'field_id' => (int) $fieldId]);
    }

    public function updateField(int $formId, int $fieldId): void
    {
        Auth::requirePermission('forms.manage');

        $data = [];
        if ($this->input('label'))       $data['label'] = $this->input('label');
        if ($this->input('name'))        $data['name'] = $this->input('name');
        if ($this->input('field_type'))  $data['field_type'] = $this->input('field_type');
        if (array_key_exists('placeholder', $_POST)) $data['placeholder'] = $this->input('placeholder');
        if (array_key_exists('help_text', $_POST))   $data['help_text'] = $this->input('help_text');

        if ($this->input('options_raw')) {
            $lines = array_filter(array_map('trim', explode("\n", $this->input('options_raw'))));
            $data['options'] = array_map(function ($line) {
                $parts = explode('|', $line, 2);
                return ['value' => trim($parts[0]), 'label' => trim($parts[1] ?? $parts[0])];
            }, $lines);
        }

        $validation = [];
        if ($this->input('required'))   $validation['required'] = true;
        if ($this->input('min_length')) $validation['min_length'] = (int) $this->input('min_length');
        if ($this->input('max_length')) $validation['max_length'] = (int) $this->input('max_length');
        $data['validation'] = $validation;

        $this->formService->updateField($fieldId, $data);

        $this->json(['success' => true]);
    }

    public function deleteField(int $formId, int $fieldId): void
    {
        Auth::requirePermission('forms.manage');

        $this->formService->deactivateField($fieldId);

        $this->json(['success' => true]);
    }

    public function reorderFields(int $formId): void
    {
        Auth::requirePermission('forms.manage');

        $input = json_decode(file_get_contents('php://input'), true);
        $items = $input['items'] ?? [];

        if (!$items) {
            $this->json(['error' => 'No items provided.'], 400);
            return;
        }

        $this->formService->reorderFields($formId, $items);

        $this->json(['success' => true]);
    }

    // ── Admin: Payment Options (AJAX) ─────────────────────────────────

    public function addPaymentOption(int $formId): void
    {
        Auth::requirePermission('forms.manage');

        $form = $this->formService->getForm($formId);
        if (!$form) {
            $this->json(['error' => 'Form not found.'], 404);
            return;
        }

        $label  = trim($this->input('label') ?? '');
        $amount = (float) $this->input('amount');

        if (!$label) {
            $this->json(['error' => 'Label is required.'], 400);
            return;
        }
        if ($amount <= 0) {
            $this->json(['error' => 'Amount must be greater than zero.'], 400);
            return;
        }

        $id = $this->formService->addPaymentOption($formId, [
            'label'      => $label,
            'amount'     => $amount,
            'currency'   => $this->input('currency') ?: 'EUR',
            'sort_order' => (int) $this->input('sort_order'),
        ]);

        $this->json(['success' => true, 'option_id' => (int) $id]);
    }

    public function deletePaymentOption(int $formId, int $optionId): void
    {
        Auth::requirePermission('forms.manage');

        $form = $this->formService->getForm($formId);
        if (!$form) {
            $this->json(['error' => 'Form not found.'], 404);
            return;
        }

        $this->formService->deletePaymentOption($optionId);
        $this->json(['success' => true]);
    }


    // â”€â”€ Admin: Submissions â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function submissions(int $formId): void
    {
        Auth::requirePermission('forms.manage');

        $form = $this->formService->getFormWithFields($formId);
        if (!$form) {
            Auth::flash('error', 'Form not found.');
            $this->redirect('/admin/forms');
            return;
        }

        $filters = [];
        if ($this->query('status'))  $filters['status'] = $this->query('status');
        if ($this->query('search'))  $filters['search'] = $this->query('search');

        $page = max(1, (int) ($this->query('page') ?: 1));
        $perPage = 25;

        $submissions = $this->formService->getSubmissions($formId, $filters, $perPage, ($page - 1) * $perPage);
        $total = $this->formService->countSubmissions($formId, $filters['status'] ?? null);

        $this->renderAdmin('admin/forms/submissions', [
            'title'       => 'Submissions: ' . $form['title'],
            'form'        => $form,
            'submissions' => $submissions,
            'total'       => $total,
            'page'        => $page,
            'perPage'     => $perPage,
            'filters'     => $filters,
            'breadcrumbs' => [['Admin', '/admin'], ['Forms', '/admin/forms'], [$form['title'], "/admin/forms/{$formId}/edit"], ['Submissions']],
        ]);
    }

    public function submissionDetail(int $formId, int $submissionId): void
    {
        Auth::requirePermission('forms.manage');

        $form = $this->formService->getFormWithFields($formId);
        $submission = $this->formService->getSubmission($submissionId);

        if (!$form || !$submission || (int) $submission['form_id'] !== $formId) {
            Auth::flash('error', 'Submission not found.');
            $this->redirect("/admin/forms/{$formId}/submissions");
            return;
        }

        $this->renderAdmin('admin/forms/submission-detail', [
            'title'       => 'Submission #' . $submissionId,
            'form'        => $form,
            'submission'  => $submission,
            'breadcrumbs' => [
                ['Admin', '/admin'],
                ['Forms', '/admin/forms'],
                [$form['title'], "/admin/forms/{$formId}/edit"],
                ['Submissions', "/admin/forms/{$formId}/submissions"],
                ['#' . $submissionId],
            ],
        ]);
    }

    public function approveSubmission(int $formId, int $submissionId): void
    {
        Auth::requirePermission('forms.manage');

        $notes = $this->input('reviewer_notes');
        $result = $this->formService->approveSubmission($submissionId, $notes);

        if ($result) {
            $this->logActivity('approve', 'form_submission', $submissionId, "Form #{$formId}");
            Auth::flash('success', 'Submission approved.');
        } else {
            Auth::flash('error', 'Could not approve submission (may already be processed).');
        }

        $this->redirect("/admin/forms/{$formId}/submissions/{$submissionId}");
    }

    public function rejectSubmission(int $formId, int $submissionId): void
    {
        Auth::requirePermission('forms.manage');

        $notes = $this->input('reviewer_notes');
        $result = $this->formService->rejectSubmission($submissionId, $notes);

        if ($result) {
            $this->logActivity('reject', 'form_submission', $submissionId, "Form #{$formId}");
            Auth::flash('success', 'Submission rejected.');
        } else {
            Auth::flash('error', 'Could not reject submission.');
        }

        $this->redirect("/admin/forms/{$formId}/submissions/{$submissionId}");
    }

    public function exportCsv(int $formId): void
    {
        Auth::requirePermission('forms.manage');

        $form = $this->formService->getForm($formId);
        if (!$form) {
            Auth::flash('error', 'Form not found.');
            $this->redirect('/admin/forms');
            return;
        }

        $csv = $this->formService->exportCsv($formId);
        $filename = $this->sanitiseSlug($form['title']) . '_' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $csv;
        exit;
    }

    // â”€â”€ Public: Form Display & Submission â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function publicShow(string $slug): void
    {
        $form = $this->formService->getFormWithFields($slug);

        if (!$form || $form['status'] !== 'published') {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Not Found']);
            return;
        }

        $settings = $form['settings'];

        if (!empty($settings['require_login']) && !Auth::check()) {
            Auth::flash('error', 'Please log in to access this form.');
            $this->redirect('/login');
            return;
        }

        $this->render('public/forms/show', [
            'title' => $form['title'],
            'form'  => $form,
        ]);
    }

    public function publicSubmit(string $slug): void
    {
        $form = $this->formService->getFormWithFields($slug);

        if (!$form || $form['status'] !== 'published') {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Not Found']);
            return;
        }

        $settings = $form['settings'];

        if (!empty($settings['require_login']) && !Auth::check()) {
            Auth::flash('error', 'Please log in to submit this form.');
            $this->redirect('/login');
            return;
        }

        $userId = Auth::check() ? Auth::userId() : null;
        $result = $this->formService->submitForm((int) $form['id'], $_POST, $userId);

        if (!$result['success']) {
            $this->render('public/forms/show', [
                'title'  => $form['title'],
                'form'   => $form,
                'errors' => $result['errors'],
                'old'    => $_POST,
            ]);
            return;
        }

        $successMessage = $settings['success_message'] ?? 'Thank you! Your submission has been received.';

        // If payment is required, hand off to the payments module
        if (!empty($settings['require_payment'])) {
            $this->redirect('/payments/initiate?source=form_submission&source_id=' . $result['submission_id']);
            return;
        }

        if (!empty($settings['redirect_url'])) {
            Auth::flash('success', $successMessage);
            $this->redirect($settings['redirect_url']);
            return;
        }

        $this->render('public/forms/success', [
            'title'   => $form['title'],
            'form'    => $form,
            'message' => $successMessage,
        ]);
    }
}
