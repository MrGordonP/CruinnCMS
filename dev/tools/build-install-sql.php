<?php
/**
 * CruinnCMS — Combined SQL Build Tool
 *
 * Concatenates all migrations + instance/seed.sql into a single SQL file
 * suitable for import via phpMyAdmin or the MySQL command line:
 *
 *   php tools/build-install-sql.php
 *
 * Output: install-all.sql in the project root.
 * This is an alternative to the web installer for environments with phpMyAdmin.
 */

$root      = dirname(__DIR__, 2);
$migrDir   = $root . '/migrations/core';

// Resolve instance directory for seed.sql.
// Set CRUINN_INSTANCE=slug in the environment before running this script,
// e.g.:  CRUINN_INSTANCE=mysite php dev/tools/build-install-sql.php
$_instName = getenv('CRUINN_INSTANCE') ?: '';
$_instDir  = $_instName !== '' && is_dir($root . '/instance/' . basename($_instName))
    ? $root . '/instance/' . basename($_instName) : null;

$seedFile  = $_instDir ? $_instDir . '/seed.sql' : null;
$outFile   = $root . '/install-all.sql';

// Core migrations
$files = glob($migrDir . '/*.sql') ?: [];
natsort($files);
$files = array_values($files);

// Module migrations (in module manifest order for each module)
$moduleManifests = glob($root . '/modules/*/module.php') ?: [];
foreach ($moduleManifests as $manifest) {
    // Extract migration paths without executing arbitrary module code.
    // We capture only the 'migrations' array by parsing the file safely.
    // Simplest safe approach: include the manifest in an isolated scope where
    // ModuleRegistry::register() is stubbed to capture the definition.
    $captured = null;
    $origRegister = null;
    // Use output buffering in case manifests echo anything unexpected
    ob_start();
    try {
        // Temporarily override register via a file-scoped trick:
        // rather than monkey-patching, just include directly and let the real
        // registry capture it — if it's already been loaded we can read from it.
        // For the build tool, we parse the migrations array from the file text.
        $src = file_get_contents($manifest);
        if (preg_match("/'migrations'\s*=>\s*\[([^\]]*)\]/s", $src, $m)) {
            preg_match_all("/__DIR__\s*\.\s*'([^']+)'/", $m[1], $paths);
            $moduleDir = dirname($manifest);
            foreach ($paths[1] as $rel) {
                $abs = $moduleDir . $rel;
                if (file_exists($abs)) {
                    $files[] = $abs;
                }
            }
        }
    } catch (\Throwable) {}
    ob_end_clean();
}

if ($seedFile !== null && file_exists($seedFile)) {
    $files[] = $seedFile;
}

$header = implode("\n", [
    '-- ============================================================',
    '-- CruinnCMS — Combined Installation SQL',
    '-- Generated: ' . date('Y-m-d H:i:s'),
    '--',
    '-- Import via phpMyAdmin or:',
    '--   mysql -u your_user -p your_database < install-all.sql',
    '--',
    '-- This file includes all ' . count($files) . ' migration(s) + instance seed.',
    '-- ============================================================',
    '',
    'SET NAMES utf8mb4;',
    'SET FOREIGN_KEY_CHECKS = 0;',
    '',
]);

$combined = $header;

foreach ($files as $file) {
    $combined .= "\n-- ────────────────────────────────────────────────────────────\n";
    $combined .= "-- File: " . basename($file) . "\n";
    $combined .= "-- ────────────────────────────────────────────────────────────\n\n";
    $combined .= file_get_contents($file) . "\n";
}

$combined .= "\nSET FOREIGN_KEY_CHECKS = 1;\n";

if (file_put_contents($outFile, $combined)) {
    $kb = round(strlen($combined) / 1024, 1);
    echo "Written: install-all.sql ({$kb} KB, " . count($files) . " files)\n";
} else {
    echo "ERROR: Could not write install-all.sql — check permissions.\n";
    exit(1);
}
