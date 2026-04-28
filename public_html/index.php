<?php
/**
 * CruinnCMS — Front Controller
 *
 * All HTTP requests are routed through this file via Nginx rewrite rules.
 * This is the only PHP file in the public web root.
 */

// Start output buffering so AJAX/JSON responses can discard stray warnings.
ob_start();

// Define the application root.
// IMPORTANT: The engine lives in CruinnCMS/ alongside public_html/, NOT directly
// in the repo/hosting root. dirname(__DIR__) reaches the repo root; we then step
// into CruinnCMS/ where src/, config/, templates/, modules/ etc. live.
// Do NOT change this to dirname(__DIR__) alone — that breaks all engine paths.
define('CRUINN_ROOT', dirname(__DIR__) . '/CruinnCMS');

// Public web root (this directory - works whether it is public/ or public_html/)
define('CRUINN_PUBLIC', __DIR__);

// ── Autoloader ────────────────────────────────────────────────────
// Use Composer autoloader if available, otherwise fall back to simple PSR-4 autoloader.
$composerAutoload = CRUINN_ROOT . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require $composerAutoload;
} else {
    spl_autoload_register(function (string $class) {
        $prefix = 'Cruinn\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $relativeClass = substr($class, strlen($prefix));
        $file = CRUINN_ROOT . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
}

// ── Load global helper functions ──────────────────────────────────
// Template helpers (e(), url(), csrf_field(), etc.) are defined in Template.php
// and loaded when that file is autoloaded. We require it early so helpers
// are available everywhere.
require_once CRUINN_ROOT . '/src/Template.php';

// ── Boot & Run ────────────────────────────────────────────────────
$app = \Cruinn\App::boot();
$app->run();
