<?php
/**
 * import-wp-pages.php
 *
 * Imports pages from iga_wp_main into iga_cruinn pages table.
 * Content is imported as render_mode=html (body_html), no cruinn_blocks needed.
 *
 * WooCommerce, plugin-shortcode, and placeholder pages are skipped.
 *
 * Usage:
 *   php dev/tools/import-wp-pages.php           # dry run
 *   php dev/tools/import-wp-pages.php --commit  # write to DB
 */

define('CRUINN_ROOT', dirname(__DIR__, 2) . '/CruinnCMS');
require_once CRUINN_ROOT . '/src/App.php';

// ---------------------------------------------------------------------------
// Config
// ---------------------------------------------------------------------------

$DRY_RUN = !in_array('--commit', $argv, true);

$targetUser = 'cruinn';
$targetPass = 'cruinn-local';

// WP page IDs to skip
$SKIP_IDS = [
    3126,  // Basket (WooCommerce)
    3129,  // Checkout (WooCommerce)
    3132,  // My account (WooCommerce)
    3123,  // Shop (WooCommerce)
    1957,  // Cookie Policy (EU) — complianz plugin shortcode
    3135,  // Refund and Returns Policy — draft/sample
       2,  // Sample Page
    2857,  // Posts — WP query block, meaningless
    2761,  // Facebook — stale inline SDK script
];

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function stripGutenbergComments(string $html): string
{
    return trim(preg_replace('/<!--\s*\/?wp:[^\-]*?-->/s', '', $html));
}

function log_line(string $msg): void
{
    echo $msg . PHP_EOL;
}

// ---------------------------------------------------------------------------
// Connect
// ---------------------------------------------------------------------------

try {
    $srcDb = new PDO('mysql:host=127.0.0.1;dbname=iga_wp_main;charset=utf8mb4', $targetUser, $targetPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ]);
    $dstDb = new PDO('mysql:host=127.0.0.1;dbname=iga_cruinn;charset=utf8mb4', $targetUser, $targetPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ]);
} catch (PDOException $e) {
    log_line('ERROR: ' . $e->getMessage());
    exit(1);
}

// ---------------------------------------------------------------------------
// Load WP pages
// ---------------------------------------------------------------------------

$pages = $srcDb->query(
    "SELECT ID, post_title, post_name, post_content, post_excerpt,
            post_status, post_parent, post_date, post_modified
       FROM KMrwsdZdR_posts
      WHERE post_type = 'page'
        AND post_status IN ('publish', 'draft')
      ORDER BY post_parent ASC, menu_order ASC, post_title ASC"
)->fetchAll();

// Existing slugs in target
$existingSlugs = [];
foreach ($dstDb->query('SELECT slug FROM pages')->fetchAll() as $row) {
    $existingSlugs[$row['slug']] = true;
}

$insertPage = $dstDb->prepare(
    'INSERT INTO pages
        (title, slug, status, template, editor_mode, meta_description, render_mode, body_html, created_by, created_at, updated_at)
     VALUES
        (:title, :slug, :status, :template, \'freeform\', :meta, \'html\', :body_html, 1, :created_at, :updated_at)'
);

// ---------------------------------------------------------------------------
// Process
// ---------------------------------------------------------------------------

if ($DRY_RUN) {
    log_line('[DRY RUN] Pass --commit to write to DB.');
    log_line('');
}

$inserted = 0;
$skipped  = 0;
$prefix   = 'KMrwsdZdR_';

foreach ($pages as $page) {
    $id    = (int) $page['ID'];
    $title = $page['post_title'];

    if (in_array($id, $SKIP_IDS, true)) {
        log_line("  SKIP [{$id}] {$title}");
        $skipped++;
        continue;
    }

    $rawSlug = $page['post_name'] ?: preg_replace('/[^a-z0-9]+/', '-', strtolower($title));
    $rawSlug = trim($rawSlug, '-') ?: 'page-' . $id;

    // Deduplicate slug
    $slug = $rawSlug;
    $n    = 1;
    while (isset($existingSlugs[$slug])) {
        $slug = $rawSlug . '-' . $n++;
    }

    $html   = stripGutenbergComments($page['post_content']);
    $status = $page['post_status'] === 'publish' ? 'published' : 'draft';
    $meta   = trim($page['post_excerpt'] ?? '');

    // Use 'home' template for the welcome/landing page (WP ID 68)
    $template = ($id === 68) ? 'home' : 'default';

    $parent = (int) $page['post_parent'];
    $parentNote = $parent > 0 ? " (child of WP#{$parent})" : '';

    log_line("  [{$id}] {$title} → {$slug}{$parentNote}" . ($html === '' ? ' [EMPTY]' : ''));

    if (!$DRY_RUN) {
        try {
            $insertPage->execute([
                ':title'      => $title,
                ':slug'       => $slug,
                ':status'     => $status,
                ':template'   => $template,
                ':meta'       => $meta,
                ':body_html'  => $html,
                ':created_at' => $page['post_date'],
                ':updated_at' => $page['post_modified'],
            ]);
            $existingSlugs[$slug] = true;
            $inserted++;
        } catch (PDOException $e) {
            log_line("    ERROR: " . $e->getMessage());
            $skipped++;
        }
    } else {
        $existingSlugs[$slug] = true;
        $inserted++;
    }
}

log_line('');
$mode = $DRY_RUN ? 'DRY RUN' : 'COMMITTED';
log_line("=== {$mode}: {$inserted} pages imported, {$skipped} skipped ===");
