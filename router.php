<?php
/**
 * Router script for PHP built-in development server.
 *
 * Usage:
 *   php -S localhost:8080 -t public router.php
 *
 * This script replicates Nginx rewrite rules:
 *  - Serves static files directly if they exist in public/
 *  - Routes everything else through public/index.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// If the request is for a real file in public/, serve it directly
$publicPath = __DIR__ . '/public' . $uri;
if ($uri !== '/' && file_exists($publicPath) && is_file($publicPath)) {
    // Set Content-Type based on extension
    $ext = strtolower(pathinfo($publicPath, PATHINFO_EXTENSION));
    $mimeMap = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'svg'  => 'image/svg+xml',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'ico'  => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'ttf'  => 'font/ttf',
        'json' => 'application/json',
        'html' => 'text/html',
    ];
    header('Content-Type: ' . ($mimeMap[$ext] ?? 'application/octet-stream'));
    readfile($publicPath);
    return;
}

// Route everything else through the front controller
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/public/index.php';
