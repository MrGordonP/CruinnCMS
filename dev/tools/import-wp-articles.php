<?php
/**
 * import-wp-articles.php
 *
 * Imports published posts from iga_wp_blog (wp_ prefix) and iga_wp_main (KMrwsdZdR_ prefix)
 * into iga_cruinn articles + article_blocks tables.
 *
 * Each WP post_content becomes a single `html` block in article_blocks.
 *
 * Usage:
 *   php dev/tools/import-wp-articles.php          # dry run (no DB writes)
 *   php dev/tools/import-wp-articles.php --commit  # write to DB
 */

define('CRUINN_ROOT', dirname(__DIR__, 2) . '/CruinnCMS');

require_once CRUINN_ROOT . '/src/App.php';

// ---------------------------------------------------------------------------
// Config
// ---------------------------------------------------------------------------

$DRY_RUN = !in_array('--commit', $argv, true);

$targetDsn  = 'mysql:host=127.0.0.1;dbname=iga_cruinn;charset=utf8mb4';
$targetUser = 'cruinn';
$targetPass = 'cruinn-local';

$sourceDsn  = 'mysql:host=127.0.0.1;dbname=iga_wp_blog;charset=utf8mb4';
$sourceUser = 'cruinn';
$sourcePass = 'cruinn-local';

// ---------------------------------------------------------------------------
// Sources
// ---------------------------------------------------------------------------

$sources = [
    [
        'dsn'    => 'mysql:host=127.0.0.1;dbname=iga_wp_blog;charset=utf8mb4',
        'prefix' => 'wp_',
        'label'  => 'iga_wp_blog (2011-2014)',
        'status_filter' => "'publish'",
    ],
    [
        'dsn'    => 'mysql:host=127.0.0.1;dbname=iga_wp_main;charset=utf8mb4',
        'prefix' => 'KMrwsdZdR_',
        'label'  => 'iga_wp_main (2014-2026)',
        'status_filter' => "'publish'",
    ],
];

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function wpSlugToUnique(PDO $targetDb, string $slug, int $attempt = 0): string
{
    $candidate = $attempt === 0 ? $slug : $slug . '-' . $attempt;
    $existing  = $targetDb->prepare('SELECT id FROM articles WHERE slug = ?');
    $existing->execute([$candidate]);
    if ($existing->fetch()) {
        return wpSlugToUnique($targetDb, $slug, $attempt + 1);
    }
    return $candidate;
}

function generateBlockId(): string
{
    return 'b' . substr(bin2hex(random_bytes(9)), 0, 18);
}

function stripGutenbergComments(string $html): string
{
    // Remove <!-- wp:... --> and <!-- /wp:... --> comment tags
    $html = preg_replace('/<!--\s*\/?wp:[^\-]*?-->/s', '', $html);
    return trim($html);
}

function log_line(string $msg): void
{
    echo $msg . PHP_EOL;
}

// ---------------------------------------------------------------------------
// Connect to target
// ---------------------------------------------------------------------------

try {
    $targetDb = new PDO($targetDsn, $targetUser, $targetPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ]);
} catch (PDOException $e) {
    log_line('ERROR: Cannot connect to target DB: ' . $e->getMessage());
    exit(1);
}

// ---------------------------------------------------------------------------
// Existing slugs in target (to detect collisions)
// ---------------------------------------------------------------------------

$existingSlugs = [];
foreach ($targetDb->query('SELECT slug FROM articles')->fetchAll() as $row) {
    $existingSlugs[$row['slug']] = true;
}

// ---------------------------------------------------------------------------
// Main loop
// ---------------------------------------------------------------------------

$totalInserted = 0;
$totalSkipped  = 0;

if ($DRY_RUN) {
    log_line('[DRY RUN] Pass --commit to write to DB.');
    log_line('');
}

foreach ($sources as $source) {
    log_line("=== {$source['label']} ===");

    try {
        $srcDb = new PDO($source['dsn'], $targetUser, $targetPass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        ]);
    } catch (PDOException $e) {
        log_line('  ERROR: ' . $e->getMessage());
        continue;
    }

    $prefix = $source['prefix'];
    $status = $source['status_filter'];

    $posts = $srcDb->query(
        "SELECT ID, post_title, post_name, post_content, post_excerpt, post_date, post_modified
           FROM {$prefix}posts
          WHERE post_type = 'post'
            AND post_status IN ({$status})
          ORDER BY post_date ASC"
    )->fetchAll();

    log_line('  Posts found: ' . count($posts));

    $insertArticle = $targetDb->prepare(
        'INSERT INTO articles
            (subject_id, title, slug, excerpt, featured_image, author_id, status, published_at, created_at, updated_at)
         VALUES
            (NULL, :title, :slug, :excerpt, \'\', 1, \'published\', :published_at, :created_at, :updated_at)'
    );

    $insertBlock = $targetDb->prepare(
        'INSERT INTO article_blocks
            (block_id, article_id, block_type, inner_html, css_props, block_config, sort_order, parent_block_id)
         VALUES
            (:block_id, :article_id, :block_type, :inner_html, \'[]\', \'{}\', 0, NULL)'
    );

    foreach ($posts as $post) {
        $rawSlug = $post['post_name'] ?: preg_replace('/[^a-z0-9]+/', '-', strtolower($post['post_title']));
        $rawSlug = trim($rawSlug, '-');

        // Check for duplicate slug
        if (isset($existingSlugs[$rawSlug])) {
            $n = 1;
            while (isset($existingSlugs[$rawSlug . '-' . $n])) { $n++; }
            $slug = $rawSlug . '-' . $n;
        } else {
            $slug = $rawSlug;
        }

        $html    = stripGutenbergComments($post['post_content']);
        $excerpt = trim($post['post_excerpt'] ?? '');

        log_line("  [{$post['ID']}] {$post['post_title']} → {$slug}");

        if (!$DRY_RUN) {
            $targetDb->beginTransaction();
            try {
                $insertArticle->execute([
                    ':title'        => $post['post_title'],
                    ':slug'         => $slug,
                    ':excerpt'      => $excerpt,
                    ':published_at' => $post['post_date'],
                    ':created_at'   => $post['post_date'],
                    ':updated_at'   => $post['post_modified'],
                ]);
                $articleId = (int) $targetDb->lastInsertId();

                $insertBlock->execute([
                    ':block_id'   => generateBlockId(),
                    ':article_id' => $articleId,
                    ':block_type' => 'html',
                    ':inner_html' => $html,
                ]);

                $targetDb->commit();
                $existingSlugs[$slug] = true;
                $totalInserted++;
            } catch (PDOException $e) {
                $targetDb->rollBack();
                log_line("    ERROR inserting: " . $e->getMessage());
                $totalSkipped++;
            }
        } else {
            $existingSlugs[$slug] = true; // track in dry run to simulate collision detection
            $totalInserted++;
        }
    }

    log_line('');
}

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

$mode = $DRY_RUN ? 'DRY RUN' : 'COMMITTED';
log_line("=== {$mode}: {$totalInserted} articles " . ($DRY_RUN ? 'would be' : '') . " imported, {$totalSkipped} skipped ===");
