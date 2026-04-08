<?php
/**
 * CMS Portal — Migrate legacy content_blocks → cruinn_blocks
 *
 * Reads legacy content_blocks rows (parent_type='page'), strips WordPress
 * Gutenberg markup, and inserts clean published rows into cruinn_blocks.
 *
 * Once blocks are in cruinn_blocks the page is automatically served by
 * CruinnRenderService (no other changes needed).
 *
 * What is migrated:
 *   - All non-system pages that have content_blocks but NO cruinn_blocks yet.
 *   - Home page (slug=home): typed blocks are mapped 1-to-1 (heading, text, event-list).
 *   - All other pages: content is cleaned and placed in a single text block each.
 *
 * What is skipped:
 *   - _header, _footer (system zone pages — managed separately in block editor).
 *   - Pages that already have published cruinn_blocks (idempotent).
 *   - Pages with no content_blocks at all.
 *
 * Usage:
 *   php tools/migrate-to-cruinn.php            # dry-run (no DB changes)
 *   php tools/migrate-to-cruinn.php --commit   # write to database
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$commit = in_array('--commit', $argv, true);
if (!$commit) {
    echo "[DRY RUN] Pass --commit to write changes to the database.\n\n";
}

// ── Bootstrap ─────────────────────────────────────────────────────────────
define('ROOT', dirname(__DIR__, 2));

$cfg = array_replace_recursive(
    require ROOT . '/config/config.php',
    file_exists(ROOT . '/config/config.local.php') ? require ROOT . '/config/config.local.php' : []
);

// Instance config overlay.
// Set CRUINN_INSTANCE=slug in the environment before running this script,
// e.g.:  CRUINN_INSTANCE=mysite php dev/tools/migrate-to-cruinn.php --commit
$instanceDir = null;
if (($env = getenv('CRUINN_INSTANCE')) !== false && $env !== '') {
    $candidate = ROOT . '/instance/' . basename($env);
    if (is_dir($candidate)) {
        $instanceDir = $candidate;
    }
}
if ($instanceDir && file_exists($instanceDir . '/config.php')) {
    $cfg = array_replace_recursive($cfg, require $instanceDir . '/config.php');
}

// ── Database ──────────────────────────────────────────────────────────────
$db = $cfg['db'];
try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $db['host'], $db['port'] ?? 3306, $db['name']
    );
    $pdo = new PDO($dsn, $db['user'], $db['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    echo "ERROR: DB connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// ── Helpers ───────────────────────────────────────────────────────────────

/**
 * Generate a Cruinn block ID: b-XXXXXXXX (8 lowercase alphanumeric chars).
 */
function genBlockId(): string
{
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $id    = 'b-';
    for ($i = 0; $i < 8; $i++) {
        $id .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $id;
}

/**
 * Strip Gutenberg block comment wrappers and rewrite upload URLs.
 */
function cleanGutenberg(string $html, string $siteUrl = ''): string
{
    // Remove self-closing WP block comments: <!-- wp:something /-->
    $html = preg_replace('/<!--\s*wp:[a-zA-Z0-9\/\-_].*?\/-->/s', '', $html) ?? $html;
    // Remove opening WP block comments: <!-- wp:something { ... } -->
    $html = preg_replace('/<!--\s*wp:[a-zA-Z0-9\/\-_][^>]*-->/s', '', $html) ?? $html;
    // Remove closing WP block comments: <!-- /wp:something -->
    $html = preg_replace('/<!--\s*\/wp:[a-zA-Z0-9\/\-_]+\s*-->/s', '', $html) ?? $html;
    // Rewrite WP upload URLs to local /uploads/ path
    if ($siteUrl !== '') {
        $uploadBase = rtrim($siteUrl, '/') . '/wp-content/uploads/';
        $html       = str_replace($uploadBase, '/uploads/', $html);
    }
    return $html;
}

/**
 * Strip WordPress-specific class names and wrappers from HTML using DOMDocument.
 *
 * - Removes wp-block-* and align(wide|full) classes.
 * - Unwraps <figure class="wp-block-table"> → keeps inner <table>.
 * - Unwraps <div class="wp-block-columns"> and <div class="wp-block-column">.
 * - Unwraps <div class="wp-block-group">.
 * - Removes empty class="" attributes left behind.
 */
function stripWpMarkup(string $html): string
{
    if (trim($html) === '') {
        return '';
    }

    // Use DOMDocument for robust nested-element handling
    $doc = new DOMDocument('1.0', 'UTF-8');
    // Suppress warnings from imperfect HTML; wrap in a div to give a single root
    libxml_use_internal_errors(true);
    $doc->loadHTML(
        '<?xml encoding="UTF-8"><html><body><div id="__mig">' . $html . '</div></body></html>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();

    $xpath = new DOMXPath($doc);

    // Unwrap patterns: element types + class prefixes that should be stripped
    $unwrapSelectors = [
        // <figure class="wp-block-table">…</figure>
        '//figure[contains(@class,"wp-block-table")]',
        // <div class="wp-block-columns …">…</div>
        '//div[contains(@class,"wp-block-columns")]',
        // <div class="wp-block-column …">…</div>
        '//div[contains(@class,"wp-block-column")]',
        // <div class="wp-block-group …">…</div>
        '//div[contains(@class,"wp-block-group")]',
        // <div class="wp-block-cover …">…</div>  (background image blocks)
        '//div[contains(@class,"wp-block-cover")]',
    ];

    // We may need multiple passes because unwrapping can expose new matches
    for ($pass = 0; $pass < 3; $pass++) {
        $changed = false;
        foreach ($unwrapSelectors as $sel) {
            $nodes = $xpath->query($sel);
            if (!$nodes) {
                continue;
            }
            foreach (iterator_to_array($nodes) as $node) {
                /** @var DOMElement $node */
                $parent = $node->parentNode;
                if (!$parent) {
                    continue;
                }
                // Move all children of $node before $node in the parent
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);
                $changed = true;
            }
        }
        if (!$changed) {
            break;
        }
    }

    // Strip wp-* class names and align(wide|full) from remaining elements
    $allElements = $xpath->query('//*[@class]');
    if ($allElements) {
        foreach ($allElements as $el) {
            /** @var DOMElement $el */
            $classes    = preg_split('/\s+/', $el->getAttribute('class'));
            $cleaned    = array_filter($classes ?? [], function (string $c): bool {
                return !str_starts_with($c, 'wp-')
                    && !in_array($c, ['alignwide', 'alignfull', 'aligncenter', 'alignleft', 'alignright', 'has-fixed-layout'], true);
            });
            if (empty($cleaned)) {
                $el->removeAttribute('class');
            } else {
                $el->setAttribute('class', implode(' ', $cleaned));
            }
        }
    }

    // Extract inner HTML of our wrapper div
    $wrapper = $doc->getElementById('__mig');
    if (!$wrapper) {
        return $html; // fallback
    }

    $result = '';
    foreach ($wrapper->childNodes as $child) {
        $result .= $doc->saveHTML($child);
    }

    // Collapse excessive blank lines
    $result = preg_replace('/(\n\s*){3,}/', "\n\n", $result) ?? $result;
    return trim($result);
}

/**
 * Insert a single block row (or print in dry-run mode).
 */
function insertBlock(
    PDO    $pdo,
    bool   $commit,
    string $blockId,
    int    $pageId,
    string $type,
    ?string $innerHtml,
    ?string $cssProps,
    ?string $blockConfig,
    int    $sortOrder,
    ?string $parentBlockId
): void {
    if ($commit) {
        $stmt = $pdo->prepare(
            'INSERT INTO cruinn_blocks
               (block_id, page_id, block_type, inner_html, css_props, block_config, sort_order, parent_block_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$blockId, $pageId, $type, $innerHtml, $cssProps, $blockConfig, $sortOrder, $parentBlockId]);
    }

    $parent  = $parentBlockId ? " [child of $parentBlockId]" : '';
    $preview = $innerHtml !== null ? ('  → ' . substr(strip_tags($innerHtml), 0, 80)) : '';
    echo sprintf(
        "  %s  type=%-12s  sort=%d%s%s\n",
        $blockId, $type, $sortOrder, $parent,
        $preview !== '' ? "\n" . $preview : ''
    );
}

// ── Main ──────────────────────────────────────────────────────────────────

$siteUrl = rtrim($cfg['site']['url'] ?? '', '/');

// Load all pages (skip template pages _tpl*)
$pages = $pdo->query(
    "SELECT id, title, slug FROM pages WHERE slug NOT LIKE '_tpl%' ORDER BY id"
)->fetchAll();

// Load all legacy content_blocks for pages, indexed by page ID
$legacyRows = $pdo->query(
    "SELECT parent_id, block_type, content, sort_order
       FROM content_blocks
      WHERE parent_type = 'page'
      ORDER BY parent_id, sort_order"
)->fetchAll();

$legacyByPage = [];
foreach ($legacyRows as $row) {
    $legacyByPage[(int) $row['parent_id']][] = $row;
}

// Which pages already have published Cruinn blocks?
$publishedPageIds = array_flip(array_column(
    $pdo->query('SELECT DISTINCT page_id FROM cruinn_blocks')->fetchAll(),
    'page_id'
));

// ── Per-page migration ────────────────────────────────────────────────────

$stats = ['migrated' => 0, 'skipped' => 0, 'blocks_written' => 0];

foreach ($pages as $page) {
    $pageId = (int) $page['id'];
    $slug   = $page['slug'];
    $title  = $page['title'];

    // System zone pages — authored in block editor, not here
    if (in_array($slug, ['_header', '_footer'], true)) {
        echo "SKIP  [$pageId] $title (system zone page)\n";
        $stats['skipped']++;
        continue;
    }

    // Already has published blocks — idempotent
    if (isset($publishedPageIds[$pageId])) {
        echo "SKIP  [$pageId] $title (already has cruinn_blocks)\n";
        $stats['skipped']++;
        continue;
    }

    $legacy = $legacyByPage[$pageId] ?? [];

    if (empty($legacy)) {
        echo "SKIP  [$pageId] $title (no content_blocks)\n";
        $stats['skipped']++;
        continue;
    }

    echo "\nPAGE  [$pageId] $title (/$slug)\n";
    $stats['migrated']++;

    $sortOrder = 1;

    // ── Home page: map typed blocks directly ──────────────────────────────
    if ($slug === 'home') {
        foreach ($legacy as $block) {
            $data    = json_decode((string) $block['content'], true) ?? [];
            $type    = $block['block_type'];
            $blockId = genBlockId();

            switch ($type) {
                case 'heading':
                    $level     = max(1, min(6, (int) ($data['level'] ?? 2)));
                    $text      = htmlspecialchars($data['text'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $innerHtml = "<h{$level}>{$text}</h{$level}>";
                    // Use a text block so the heading element renders at the correct level.
                    // The Cruinn 'heading' block type is hardcoded h2 — for migrated content
                    // we preserve the original heading level inside a text block (div wrapper).
                    insertBlock($pdo, $commit, $blockId, $pageId, 'text', $innerHtml, null, null, $sortOrder++, null);
                    $stats['blocks_written']++;
                    break;

                case 'event-list':
                    $cfg = json_encode([
                        'count' => (int) ($data['count'] ?? 3),
                        'type'  => $data['type'] ?? 'upcoming',
                    ]);
                    insertBlock($pdo, $commit, $blockId, $pageId, 'event-list', null, null, $cfg, $sortOrder++, null);
                    $stats['blocks_written']++;
                    break;

                case 'text':
                    $html = cleanGutenberg($data['html'] ?? '', $siteUrl);
                    $html = stripWpMarkup($html);
                    if (trim(strip_tags($html)) !== '') {
                        insertBlock($pdo, $commit, $blockId, $pageId, 'text', $html, null, null, $sortOrder++, null);
                        $stats['blocks_written']++;
                    }
                    break;

                default:
                    // Unknown typed block — treat as raw HTML
                    $html = cleanGutenberg((string) $block['content'], $siteUrl);
                    if (trim(strip_tags($html)) !== '') {
                        insertBlock($pdo, $commit, $blockId, $pageId, 'html', $html, null, null, $sortOrder++, null);
                        $stats['blocks_written']++;
                    }
                    break;
            }
        }
        continue;
    }

    // ── All other pages: one text block per legacy block ──────────────────
    foreach ($legacy as $block) {
        $data = json_decode((string) $block['content'], true) ?? [];
        $html = $data['html'] ?? $data['text'] ?? '';

        if (trim($html) === '') {
            echo "  (empty block — skipping)\n";
            continue;
        }

        $html    = cleanGutenberg($html, $siteUrl);
        $html    = stripWpMarkup($html);
        $blockId = genBlockId();

        insertBlock($pdo, $commit, $blockId, $pageId, 'text', $html, null, null, $sortOrder++, null);
        $stats['blocks_written']++;
    }
}

// ── Summary ───────────────────────────────────────────────────────────────
echo "\n";
echo str_repeat('─', 50) . "\n";
echo sprintf(
    "Pages migrated: %d   Skipped: %d   Blocks written: %d\n",
    $stats['migrated'], $stats['skipped'], $stats['blocks_written']
);

if (!$commit) {
    echo "\nDry run — no changes made. Run with --commit to apply.\n";
} else {
    echo "\nMigration complete.\n";
}
