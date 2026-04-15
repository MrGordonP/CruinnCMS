<?php
/**
 * Router script for PHP built-in development server.
 *
 * Usage:
 *   php -S localhost:8000 -t public_html dev/router.php
 *
 * Pass the actual doc root via -t. This script uses $_SERVER['DOCUMENT_ROOT']
 * so it works regardless of whether the doc root is public/, public_html/, or
 * anything else — no hardcoded paths.
 *
 * This script replicates Nginx/Apache rewrite rules:
 *  - Serves static files directly if they exist in the doc root
 *  - Routes everything else through index.php in the doc root
 */

$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$uri     = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// If the request is for a real file in the doc root, serve it directly
$publicPath = $docRoot . $uri;
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
require $docRoot . '/index.php';
