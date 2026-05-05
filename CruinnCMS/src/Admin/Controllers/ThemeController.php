<?php
/**
 * CruinnCMS — Theme Controller
 *
 * Provides the Theme Editor: reads and writes CSS custom properties
 * from/to the active theme file in public/css/themes/{name}.css.
 */

namespace Cruinn\Admin\Controllers;

use Cruinn\Auth;
use Cruinn\Controllers\BaseController;

class ThemeController extends BaseController
{
    /**
     * Display the theme editor.
     */
    public function edit(): void
    {
        Auth::requireRole('admin');

        $theme    = $this->activeTheme();
        $filePath = $this->themeFilePath($theme);
        $vars     = [];
        $error    = null;

        if (!file_exists($filePath)) {
            $error = "Theme file not found: css/themes/{$theme}.css";
        } else {
            $vars = $this->parseVariables(file_get_contents($filePath));
        }

        $this->renderAdmin('admin/theme-editor', [
            'title'    => 'Theme Editor',
            'theme'    => $theme,
            'vars'     => $vars,
            'filePath' => "css/themes/{$theme}.css",
            'error'    => $error,
        ]);
    }

    /**
     * Save the edited variables back into the theme file.
     */
    public function save(): void
    {
        Auth::requireRole('admin');
        \Cruinn\CSRF::verify();

        $theme    = $this->activeTheme();
        $filePath = $this->themeFilePath($theme);

        if (!file_exists($filePath)) {
            $_SESSION['flash_error'] = "Theme file not found: css/themes/{$theme}.css";
            header('Location: ' . url('/admin/theme'));
            exit;
        }

        $submitted = $_POST['vars'] ?? [];
        // Sanitise: keys must look like CSS custom property names, values are strings
        $cleaned = [];
        foreach ($submitted as $name => $value) {
            if (preg_match('/^--[a-zA-Z][a-zA-Z0-9_-]*$/', $name)) {
                $cleaned[$name] = $value;
            }
        }

        $css     = file_get_contents($filePath);
        $updated = $this->applyVariables($css, $cleaned);

        if (file_put_contents($filePath, $updated) === false) {
            $_SESSION['flash_error'] = 'Could not write theme file. Check file permissions.';
        } else {
            $_SESSION['flash_success'] = 'Theme saved.';
        }

        header('Location: ' . url('/admin/theme'));
        exit;
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function activeTheme(): string
    {
        $raw = $this->db->fetchColumn("SELECT `value` FROM `settings` WHERE `key` = 'site.active_theme'") ?: 'default';
        // Restrict to safe filename characters
        return preg_replace('/[^a-z0-9_-]/i', '', $raw) ?: 'default';
    }

    private function themeFilePath(string $theme): string
    {
        return CRUINN_PUBLIC . '/css/themes/' . $theme . '.css';
    }

    /**
     * Extract --variable: value pairs from the :root {} block.
     * Returns an ordered array of ['name' => '--foo', 'value' => 'bar', 'comment' => '/* … *\/'].
     */
    private function parseVariables(string $css): array
    {
        // Find the :root { … } block (first occurrence)
        if (!preg_match('/\:root\s*\{([^}]+)\}/s', $css, $m)) {
            return [];
        }

        $block   = $m[1];
        $vars    = [];
        $lines   = explode("\n", $block);
        $pending = '';

        foreach ($lines as $line) {
            $trim = trim($line);
            // Capture inline comments as group headings
            if (preg_match('/^\/\*\s*(.*?)\s*\*\/$/', $trim, $cm)) {
                $pending = $cm[1];
                continue;
            }
            if (preg_match('/^(--[a-zA-Z][a-zA-Z0-9_-]*)\s*:\s*(.+?)\s*;/', $trim, $vm)) {
                $vars[] = [
                    'name'    => $vm[1],
                    'value'   => $vm[2],
                    'comment' => $pending,
                ];
                $pending = '';
            }
        }

        return $vars;
    }

    /**
     * Write updated variable values back into the :root {} block of the CSS.
     * Lines not matching a submitted variable are left untouched.
     */
    private function applyVariables(string $css, array $values): string
    {
        return preg_replace_callback(
            '/(\:root\s*\{)([^}]+)(\})/s',
            function (array $m) use ($values): string {
                $block = $m[2];
                foreach ($values as $name => $value) {
                    $block = preg_replace(
                        '/(' . preg_quote($name, '/') . '\s*:\s*)([^;]+)(;)/',
                        '${1}' . addcslashes($value, '\\$') . '${3}',
                        $block
                    );
                }
                return $m[1] . $block . $m[3];
            },
            $css
        );
    }
}
