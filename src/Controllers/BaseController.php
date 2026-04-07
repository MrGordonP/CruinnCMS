<?php
/**
 * CruinnCMS — Base Controller
 *
 * Common functionality shared by all controllers.
 */

namespace Cruinn\Controllers;

use Cruinn\App;
use Cruinn\Template;
use Cruinn\Database;
use Cruinn\Auth;

abstract class BaseController
{
    protected Template $template;
    protected Database $db;

    public function __construct()
    {
        $this->template = new Template();
        $this->db = Database::getInstance();
    }

    /**
     * Render a template and send it to the browser.
     */
    protected function render(string $view, array $data = []): void
    {
        echo $this->template->render($view, $data);
    }

    /**
     * Render with the admin layout instead of public layout.
     */
    protected function renderAdmin(string $view, array $data = []): void
    {
        $this->template->setLayout('admin/layout');
        echo $this->template->render($view, $data);
    }

    /**
     * Render with the admin layout in ACP mode (simplified nav).
     */
    protected function renderAcp(string $view, array $data = []): void
    {
        $data['acp_mode'] = true;
        $this->template->setLayout('admin/layout');
        echo $this->template->render($view, $data);
    }

    /**
     * Redirect to a URL.
     */
    protected function redirect(string $url, int $status = 302): never
    {
        http_response_code($status);
        header('Location: ' . $url);
        exit;
    }

    /**
     * Send a JSON response (for AJAX endpoints).
     */
    protected function json(mixed $data, int $status = 200): never
    {
        // Capture and discard any stray output (PHP notices/warnings with display_errors on)
        $stray = '';
        while (ob_get_level()) {
            $stray .= ob_get_clean();
        }
        if ($stray !== '') {
            error_log('[JSON response had stray output] ' . substr($stray, 0, 500));
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Get a POST field value, trimmed and optionally sanitised.
     */
    protected function input(string $key, mixed $default = null): mixed
    {
        $value = $_POST[$key] ?? $default;
        if (is_string($value)) {
            $value = trim($value);
        }
        return $value;
    }

    /**
     * Get a GET query parameter.
     */
    protected function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * Validate that required POST fields are present and non-empty.
     * Returns an array of error messages (empty if all valid).
     */
    protected function validateRequired(array $fields): array
    {
        $errors = [];
        foreach ($fields as $field => $label) {
            $value = $this->input($field);
            if ($value === null || $value === '') {
                $errors[$field] = "{$label} is required.";
            }
        }
        return $errors;
    }

    /**
     * Log an activity to the audit trail.
     *
     * @param string      $action     Action performed (login, create, update, delete, etc.)
     * @param string      $entityType Entity type affected (user, member, page, etc.)
     * @param int|null    $entityId   ID of the affected entity
     * @param string|null $details    Human-readable details
     * @param int|null    $actorId    Override the acting user ID (e.g. during registration
     *                                when the user isn't logged in yet)
     */
    protected function logActivity(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?string $details = null,
        ?int $actorId = null
    ): void {
        $this->db->insert('activity_log', [
            'user_id'     => $actorId ?? Auth::userId(),
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'details'     => $details,
            'ip_address'  => App::clientIp() ?: null,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Sanitise a URL slug: lowercase, alphanumeric + hyphens only.
     */
    protected function sanitiseSlug(string $input): string
    {
        $slug = strtolower(trim($input));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }

    /**
     * Generate a date-based article slug: YYYYMMDD01, YYYYMMDD02, etc.
     * Checks the given $table for existing slugs with the same date prefix
     * and increments the counter.
     *
     * @param string $table  DB table to check uniqueness against (e.g. 'articles')
     * @param int|null $excludeId  Row to exclude (for updates)
     */
    protected function generateDateSlug(string $table, ?int $excludeId = null): string
    {
        $prefix = date('Ymd');
        $where  = "slug LIKE '" . $prefix . "%'";
        if ($excludeId !== null) {
            $where .= " AND id != " . $excludeId;
        }
        $count  = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$table}` WHERE slug LIKE ?",
            [$prefix . '%']
        );
        return $prefix . str_pad((string)($count + 1), 2, '0', STR_PAD_LEFT);
    }
}
