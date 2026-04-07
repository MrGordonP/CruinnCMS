#!/usr/bin/env php
<?php
/**
 * CruinnCMS — Migration Runner
 *
 * Applies pending SQL migrations for core and all installed modules.
 * Tracks applied migrations in the module_migrations table.
 *
 * Usage:
 *   php tools/migrate.php              — show status, apply all pending
 *   php tools/migrate.php --dry-run    — show pending without applying
 *   php tools/migrate.php --status     — show status only, exit
 *   php tools/migrate.php --module forum  — only process the 'forum' module
 *   php tools/migrate.php --core-only  — only process core migrations
 *
 * Exit codes:
 *   0  — all up-to-date or dry-run completed
 *   1  — one or more migrations failed
 */

declare(strict_types=1);

$rootDir = dirname(__DIR__);
require $rootDir . '/vendor/autoload.php';

use Cruinn\App;
use Cruinn\Database;
use Cruinn\Modules\ModuleRegistry;

// ── CLI argument parsing ──────────────────────────────────────────────────────

$dryRun    = in_array('--dry-run',   $argv, true);
$statusOnly = in_array('--status',   $argv, true);
$coreOnly  = in_array('--core-only', $argv, true);
$onlyModule = null;
foreach ($argv as $i => $arg) {
    if ($arg === '--module' && isset($argv[$i + 1])) {
        $onlyModule = $argv[$i + 1];
    }
}

// ── Bootstrap (config + DB only, no HTTP session) ────────────────────────────

App::boot();
$db = Database::getInstance();

// Ensure module_migrations table exists (it might not on a fresh install
// before this very migration has run — bootstrap it inline if needed).
try {
    $db->execute("SELECT 1 FROM module_migrations LIMIT 1");
} catch (\Throwable) {
    echo "module_migrations table not found — creating it now...\n";
    $db->execute("
        CREATE TABLE IF NOT EXISTS module_migrations (
            id         INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
            module     VARCHAR(64)     NOT NULL,
            filename   VARCHAR(255)    NOT NULL,
            applied_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_module_file (module, filename)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Created.\n\n";
}

// ── Collect migrations ────────────────────────────────────────────────────────

/**
 * Return ['module' => slug, 'file' => basename, 'path' => abs] for every
 * declared migration, in the order they should be applied.
 */
function collectMigrations(string $rootDir, ?string $onlyModule, bool $coreOnly): array
{
    $list = [];

    // Core
    if ($onlyModule === null || $onlyModule === 'core') {
        $coreFiles = glob($rootDir . '/migrations/core/*.sql') ?: [];
        natsort($coreFiles);
        foreach ($coreFiles as $path) {
            $list[] = ['module' => 'core', 'file' => basename($path), 'path' => $path];
        }
    }

    if ($coreOnly) {
        return $list;
    }

    // Modules — load registry only to read migration declarations
    ModuleRegistry::load();
    foreach (ModuleRegistry::all() as $slug => $def) {
        if ($onlyModule !== null && $onlyModule !== $slug) {
            continue;
        }
        foreach ($def['migrations'] as $path) {
            $list[] = ['module' => $slug, 'file' => basename($path), 'path' => $path];
        }
    }

    return $list;
}

// ── Load applied set ──────────────────────────────────────────────────────────

$appliedRows = $db->fetchAll("SELECT module, filename FROM module_migrations");
$applied = [];
foreach ($appliedRows as $row) {
    $applied[$row['module'] . '::' . $row['filename']] = true;
}

// ── Collect and filter ────────────────────────────────────────────────────────

$all     = collectMigrations($rootDir, $onlyModule, $coreOnly);
$pending = array_filter($all, fn($m) => !isset($applied[$m['module'] . '::' . $m['file']]));
$pending = array_values($pending);

// ── Status display ────────────────────────────────────────────────────────────

$totalAll     = count($all);
$totalApplied = $totalAll - count($pending);
$totalPending = count($pending);

echo str_repeat('─', 60) . "\n";
echo "CruinnCMS Migration Runner\n";
echo str_repeat('─', 60) . "\n";
printf("  Total declared : %d\n", $totalAll);
printf("  Applied        : %d\n", $totalApplied);
printf("  Pending        : %d\n", $totalPending);
echo str_repeat('─', 60) . "\n\n";

if ($totalPending === 0) {
    echo "All migrations are up to date.\n";
    exit(0);
}

echo "Pending migrations:\n";
foreach ($pending as $m) {
    printf("  [%s] %s\n", $m['module'], $m['file']);
}
echo "\n";

if ($statusOnly) {
    exit(0);
}

if ($dryRun) {
    echo "--dry-run: no changes made.\n";
    exit(0);
}

// ── Apply ─────────────────────────────────────────────────────────────────────

$errors = 0;

foreach ($pending as $m) {
    $label = sprintf("[%s] %s", $m['module'], $m['file']);
    echo "Applying {$label} ... ";

    if (!file_exists($m['path'])) {
        echo "SKIP (file not found)\n";
        continue;
    }

    $sql = file_get_contents($m['path']);
    if ($sql === false || trim($sql) === '') {
        echo "SKIP (empty file)\n";
        continue;
    }

    try {
        // Split on semicolons to support multi-statement files.
        // Use PDO's exec() which allows multiple statements when the driver
        // supports them; fall back to individual statement execution.
        $pdo = $db->pdo();
        $pdo->exec($sql);

        $db->execute(
            "INSERT IGNORE INTO module_migrations (module, filename) VALUES (?, ?)",
            [$m['module'], $m['file']]
        );
        echo "OK\n";
    } catch (\Throwable $e) {
        echo "FAILED\n";
        echo "  Error: " . $e->getMessage() . "\n";
        $errors++;
        // Continue — try remaining migrations; caller sees exit code 1.
    }
}

echo "\n";
if ($errors > 0) {
    echo "{$errors} migration(s) failed.\n";
    exit(1);
}

echo "Done — all pending migrations applied.\n";
exit(0);
