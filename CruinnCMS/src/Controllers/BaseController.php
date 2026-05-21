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
use Cruinn\Services\CruinnRenderService;

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
     * Render a system page (login, profile, register, etc.) through the block/template
     * system so it inherits site chrome (header, footer zones) like any other page.
     *
     * Resolution is by system_key in the system_pages table — never by public slug —
     * so renaming a page slug never breaks engine routing.
     *
     * Falls back to a bare render('public/{key}', $data) if no system_pages row exists —
     * graceful degradation for instances that have not had migration 019 applied.
     */
    protected function renderSystemPage(string $key, array $data = []): void
    {
        // Push all data into Template globals so php-include partials can see them.
        foreach ($data as $k => $value) {
            Template::addGlobal($k, $value);
        }

        $page = null;
        try {
            $mapping = $this->db->fetch(
                'SELECT p.* FROM system_pages sp
                  JOIN pages_index p ON p.id = sp.page_id
                  WHERE sp.system_key = ? LIMIT 1',
                [$key]
            );
            if ($mapping) {
                $page = $mapping;
            }
        } catch (\Throwable $e) {
            // system_pages table doesn't exist yet (pre-migration-019 instance)
        }

        if (!$page) {
            // Graceful fallback: system_pages not seeded yet.
            try {
                $this->render('public/' . $key, $data);
            } catch (\Throwable $e) {
                // Last-resort fail-safe: always allow access to the login form.
                if ($key === 'login') {
                    $this->renderBareLoginFallback($data);
                    return;
                }
                throw $e;
            }
            return;
        }

        $cruinn = new CruinnRenderService();
        // Pass page-level data as render context so dynamic blocks (e.g. php-include)
        // receive variables like $user, $oauth_providers, $errors directly.
        $cruinn->setContext($data);

        $tplRow = $this->db->fetch(
            'SELECT * FROM page_templates WHERE slug = ? LIMIT 1',
            [$page['template'] ?? 'default']
        );
        $tpl = $tplRow ?: ['id' => 0, 'slug' => 'default', 'settings' => '{}'];
        $tpl['settings'] = json_decode($tpl['settings'] ?? '{}', true) ?: [];

        $templateId = (int)($tpl['id'] ?? 0);
        $pageZone   = (string)($page['page_zone'] ?? 'main');

        $merged = $cruinn->buildWithTemplate($templateId, $pageZone, (int)$page['id']);
        Template::addGlobal('cruinn_css', $merged['css']);

        try {
            $html = $this->template->render('public/cruinn-page', [
                'title'   => $data['title'] ?? $page['title'],
                'page'    => $page,
                'content' => $merged['html'],
            ]);

            if ($key === 'login') {
                $hasLoginForm = str_contains($html, 'action="/login"') || str_contains($html, "action='/login'");
                if (!$hasLoginForm) {
                    $this->renderBareLoginFallback($data);
                    return;
                }
            }

            echo $html;
        } catch (\Throwable $e) {
            // If the page wrapper/template chain is broken, keep login reachable.
            if ($key === 'login') {
                $this->renderBareLoginFallback($data);
                return;
            }
            throw $e;
        }
    }

    /**
     * Render an ultra-minimal standalone login form when template rendering is broken.
     */
    private function renderBareLoginFallback(array $data = []): void
    {
        $title = (string) ($data['title'] ?? 'Login');
        $token = \Cruinn\CSRF::getToken();
        $flashes = $_SESSION['_flashes'] ?? [];

        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html>';
        echo '<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
        echo '</head><body style="font-family: system-ui, sans-serif; max-width: 480px; margin: 2rem auto; padding: 0 1rem;">';
        echo '<h1 style="font-size: 1.4rem; margin-bottom: 1rem;">Login</h1>';
        echo '<p style="margin-bottom: 1rem; color: #444;">Fallback login mode is active.</p>';

        foreach (['error', 'success'] as $type) {
            if (empty($flashes[$type]) || !is_array($flashes[$type])) {
                continue;
            }
            foreach ($flashes[$type] as $msg) {
                $color = $type === 'error' ? '#8a1f1f' : '#1f6f3d';
                echo '<p style="margin: 0 0 0.5rem; color: ' . $color . ';">' . htmlspecialchars((string) $msg, ENT_QUOTES, 'UTF-8') . '</p>';
            }
        }

        echo '<form method="post" action="/login">';
        echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
        echo '<label for="email" style="display:block; margin: 0.75rem 0 0.25rem;">Email</label>';
        echo '<input type="email" id="email" name="email" required autofocus autocomplete="email" style="width:100%; padding:0.5rem; box-sizing:border-box;">';
        echo '<label for="password" style="display:block; margin: 0.75rem 0 0.25rem;">Password</label>';
        echo '<input type="password" id="password" name="password" required autocomplete="current-password" style="width:100%; padding:0.5rem; box-sizing:border-box;">';
        echo '<button type="submit" style="margin-top:1rem; padding:0.5rem 0.9rem;">Login</button>';
        echo '</form>';
        echo '</body></html>';

        // Consume flashes once rendered, matching normal template flow behavior.
        unset($_SESSION['_flashes']);
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
        try {
            $this->db->insert('activity_log', [
                'user_id'     => $actorId ?? Auth::userId(),
                'action'      => $action,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'details'     => $details,
                'ip_address'  => App::clientIp() ?: null,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // Non-fatal — activity_log table may not yet exist (migration pending).
            error_log('logActivity failed: ' . $e->getMessage());
        }
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
     * Generate a date-based article slug: YYYY-MM-DD-01, YYYY-MM-DD-02, etc.
     * Checks the given $table for existing slugs with the same date prefix
     * and increments the counter.
     *
     * @param string $table  DB table to check uniqueness against (e.g. 'articles')
     * @param int|null $excludeId  Row to exclude (for updates)
     */
    protected function generateDateSlug(string $table, ?int $excludeId = null): string
    {
        $prefix = date('Y-m-d');
        $count  = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$table}` WHERE slug LIKE ?",
            [$prefix . '%']
        );
        return $prefix . '-' . str_pad((string)($count + 1), 2, '0', STR_PAD_LEFT);
    }
}
