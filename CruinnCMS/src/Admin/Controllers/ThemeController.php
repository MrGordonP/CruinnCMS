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
     * GET /admin/theme — redirect to the _typography page in the editor.
     */
    public function edit(): void
    {
        Auth::requireRole('admin');

        $db   = \Cruinn\Database::getInstance();
        $id   = (int) ($db->fetchColumn("SELECT id FROM pages_index WHERE slug = '_typography' LIMIT 1") ?: 0);
        $dest = $id ? url('/admin/editor/' . $id . '/edit') : url('/admin/dashboard');
        header('Location: ' . $dest);
        exit;
    }

    /**
     * Save the edited variables back into the theme file.
     */
    public function save(): void
    {
        Auth::requireRole('admin');
        \Cruinn\CSRF::verify();

        $theme    = self::activeTheme();
        $filePath = self::themeFilePath($theme);

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
        $updated = self::applyVariables($css, $cleaned);

        if (file_put_contents($filePath, $updated) === false) {
            $_SESSION['flash_error'] = 'Could not write theme file. Check file permissions.';
        } else {
            $_SESSION['flash_success'] = 'Theme saved.';
        }

        header('Location: ' . url('/admin/theme'));
        exit;
    }

    // ── Helpers ──────────────────────────────────────────────────

    /**
     * GET /admin/theme/seeds — List available theme seeds and their apply status.
     */
    public function listSeeds(): void
    {
        Auth::requireRole('admin');

        $themesDir = CRUINN_ROOT . '/themes';
        $seeds = [];

        if (is_dir($themesDir)) {
            foreach (glob($themesDir . '/*/seed.sql') ?: [] as $file) {
                $slug = basename(dirname($file));
                $appliedKey = 'theme_seed.' . $slug . '.applied';
                $applied = (bool) (\Cruinn\Database::getInstance()->fetchColumn(
                    "SELECT value FROM settings WHERE `key` = ? LIMIT 1",
                    [$appliedKey]
                ));
                $seeds[] = [
                    'slug'    => $slug,
                    'file'    => $file,
                    'applied' => $applied,
                ];
            }
        }

        $this->renderAdmin('admin/theme/seeds', [
            'title' => 'Theme Seeds',
            'section' => 'theme',
            'seeds' => $seeds,
        ]);
    }

    /**
     * POST /admin/theme/apply-seed — Apply a theme seed SQL file to the instance DB.
     * The seed is idempotent (INSERT IGNORE) — safe to run multiple times.
     */
    public function applySeed(): void
    {
        Auth::requireRole('admin');
        \Cruinn\CSRF::verify();

        $slug = preg_replace('/[^a-z0-9_-]/i', '', $this->input('theme', 'default'));
        if (!$slug) {
            Auth::flash('error', 'Invalid theme slug.');
            $this->redirect('/admin/theme/seeds');
        }

        $seedFile = CRUINN_ROOT . '/themes/' . $slug . '/seed.sql';
        if (!file_exists($seedFile)) {
            Auth::flash('error', 'Seed file not found for theme: ' . $slug);
            $this->redirect('/admin/theme/seeds');
        }

        $sql = file_get_contents($seedFile);
        if ($sql === false || trim($sql) === '') {
            Auth::flash('error', 'Seed file is empty or unreadable.');
            $this->redirect('/admin/theme/seeds');
        }

        try {
            $pdo = \Cruinn\Database::createMigrationPdo();
            $this->execSqlWithDelimiters($pdo, $sql);
        } catch (\Throwable $e) {
            Auth::flash('error', 'Seed failed: ' . $e->getMessage());
            $this->redirect('/admin/theme/seeds');
        }

        // Record that this seed has been applied
        $db = \Cruinn\Database::getInstance();
        $appliedKey = 'theme_seed.' . $slug . '.applied';
        $db->execute(
            "INSERT INTO settings (`key`, `value`, `group`) VALUES (?, '1', 'theme')
             ON DUPLICATE KEY UPDATE `value` = '1'",
            [$appliedKey]
        );

        Auth::flash('success', 'Theme seed "' . $slug . '" applied successfully.');
        $this->redirect('/admin/theme/seeds');
    }

    /**
     * Execute SQL that may contain DELIMITER directives (e.g. stored procedures).
     * Mirrors MaintenanceController::execSqlWithDelimiters().
     */
    private function execSqlWithDelimiters(\PDO $pdo, string $sql): void
    {
        $delimiter = ';';
        $buffer    = '';

        foreach (explode("\n", $sql) as $line) {
            $trimmed = rtrim($line);

            if (preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $trimmed, $m)) {
                $delimiter = $m[1];
                continue;
            }

            $buffer .= $line . "\n";

            if (str_ends_with(rtrim($buffer), $delimiter)) {
                $stmt = rtrim($buffer);
                $stmt = substr($stmt, 0, strlen($stmt) - strlen($delimiter));
                $stmt = trim($stmt);
                if ($stmt !== '') {
                    $result = $pdo->query($stmt);
                    if ($result !== false) {
                        $result->closeCursor();
                    }
                }
                $buffer = '';
            }
        }

        $stmt = trim($buffer);
        if ($stmt !== '' && $stmt !== $delimiter) {
            $result = $pdo->query($stmt);
            if ($result !== false) {
                $result->closeCursor();
            }
        }
    }

    public static function activeTheme(): string
    {
        $raw = \Cruinn\Database::getInstance()->fetchColumn("SELECT `value` FROM `settings` WHERE `key` = 'site.active_theme'") ?: 'default';
        return preg_replace('/[^a-z0-9_-]/i', '', $raw) ?: 'default';
    }

    public static function themeFilePath(string $theme): string
    {
        return CRUINN_PUBLIC . '/css/themes/' . $theme . '.css';
    }

    /**
     * Extract --variable: value pairs from the :root {} block.
     * Returns an ordered array of ['name' => '--foo', 'value' => 'bar', 'comment' => '/* … *\/'].
     */
    public static function parseVariables(string $css): array
    {
        // Match the :root block that contains at least one CSS custom property (--).
        // This naturally skips any ":root { }" mentions in file-header comments.
        if (!preg_match('/:root\s*\{([^}]*--[^}]+)\}/s', $css, $m)) {
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
    private static function applyVariables(string $css, array $values): string
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
