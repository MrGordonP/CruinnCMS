<?php
/**
 * import-wp-events.php
 *
 * Imports tribe_events from iga_wp_main into iga_cruinn events table.
 * Venue name (tribe_venue post title) is mapped to the location field.
 * Venue URL mapped to location_url.
 * post_content (Gutenberg stripped) mapped to description.
 *
 * Usage:
 *   php dev/tools/import-wp-events.php           # dry run
 *   php dev/tools/import-wp-events.php --commit  # write to DB
 */

define('CRUINN_ROOT', dirname(__DIR__, 2) . '/CruinnCMS');
require_once CRUINN_ROOT . '/src/App.php';

$DRY_RUN    = !in_array('--commit', $argv, true);
$targetUser = 'cruinn';
$targetPass = 'cruinn-local';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function stripGutenbergComments(string $html): string
{
    return trim(preg_replace('/<!--\s*\/?wp:[^\-]*?-->/s', '', $html));
}

function log_line(string $msg): void { echo $msg . PHP_EOL; }

// ---------------------------------------------------------------------------
// Connect
// ---------------------------------------------------------------------------

try {
    $srcDb = new PDO('mysql:host=127.0.0.1;dbname=iga_wp_main;charset=utf8mb4', $targetUser, $targetPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ]);
    $dstDb = new PDO('mysql:host=127.0.0.1;dbname=iga_cruinn;charset=utf8mb4', $targetUser, $targetPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ]);
} catch (PDOException $e) {
    log_line('ERROR: ' . $e->getMessage());
    exit(1);
}

// ---------------------------------------------------------------------------
// Pre-load venues: WP venue post ID → [name, url]
// ---------------------------------------------------------------------------

$venues = [];
$venueRows = $srcDb->query(
    "SELECT p.ID, p.post_title,
            MAX(CASE WHEN m.meta_key='_VenueURL' THEN m.meta_value END) as url
       FROM KMrwsdZdR_posts p
       JOIN KMrwsdZdR_postmeta m ON m.post_id = p.ID
      WHERE p.post_type='tribe_venue'
      GROUP BY p.ID"
)->fetchAll();

foreach ($venueRows as $v) {
    $venues[(int) $v['ID']] = [
        'name' => html_entity_decode($v['post_title'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'url'  => $v['url'] ?? '',
    ];
}

// ---------------------------------------------------------------------------
// Load events with meta
// ---------------------------------------------------------------------------

$events = $srcDb->query(
    "SELECT p.ID, p.post_title, p.post_name, p.post_content, p.post_status,
            p.post_date, p.post_modified,
            MAX(CASE WHEN m.meta_key='_EventStartDate'  THEN m.meta_value END) as start_dt,
            MAX(CASE WHEN m.meta_key='_EventEndDate'    THEN m.meta_value END) as end_dt,
            MAX(CASE WHEN m.meta_key='_EventAllDay'     THEN m.meta_value END) as all_day,
            MAX(CASE WHEN m.meta_key='_EventVenueID'    THEN m.meta_value END) as venue_id,
            MAX(CASE WHEN m.meta_key='_EventCost'       THEN m.meta_value END) as cost,
            MAX(CASE WHEN m.meta_key='_EventURL'        THEN m.meta_value END) as event_url
       FROM KMrwsdZdR_posts p
       JOIN KMrwsdZdR_postmeta m ON m.post_id = p.ID
      WHERE p.post_type='tribe_events'
        AND p.post_status IN ('publish', 'draft')
      GROUP BY p.ID
      ORDER BY start_dt ASC"
)->fetchAll();

// Existing slugs in target
$existingSlugs = [];
foreach ($dstDb->query('SELECT slug FROM events')->fetchAll() as $row) {
    $existingSlugs[$row['slug']] = true;
}

$insert = $dstDb->prepare(
    'INSERT INTO events
        (title, slug, description, location, location_url,
         start_date, start_time, end_date, end_time, is_all_day,
         price, currency, status, created_by, created_at, updated_at)
     VALUES
        (:title, :slug, :description, :location, :location_url,
         :start_date, :start_time, :end_date, :end_time, :is_all_day,
         :price, \'EUR\', :status, 1, :created_at, :updated_at)'
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

foreach ($events as $ev) {
    $title = $ev['post_title'];

    // Slug
    $rawSlug = $ev['post_name'] ?: preg_replace('/[^a-z0-9]+/', '-', strtolower($title));
    $rawSlug = trim($rawSlug, '-') ?: 'event-' . $ev['ID'];
    $slug    = $rawSlug;
    $n       = 1;
    while (isset($existingSlugs[$slug])) { $slug = $rawSlug . '-' . $n++; }

    // Dates/times
    $startDt   = $ev['start_dt'] ?? '';
    $endDt     = $ev['end_dt']   ?? '';
    $startDate = $startDt ? substr($startDt, 0, 10) : null;
    $startTime = ($startDt && !$ev['all_day']) ? substr($startDt, 11, 5) : null;
    $endDate   = $endDt ? substr($endDt, 0, 10) : null;
    $endTime   = ($endDt && !$ev['all_day']) ? substr($endDt, 11, 5) : null;
    $isAllDay  = $ev['all_day'] ? 1 : 0;

    if (!$startDate) {
        log_line("  SKIP [{$ev['ID']}] {$title} — no start date");
        $skipped++;
        continue;
    }

    // Venue
    $venueId     = (int) ($ev['venue_id'] ?? 0);
    $location    = $venueId && isset($venues[$venueId]) ? $venues[$venueId]['name'] : '';
    $locationUrl = $venueId && isset($venues[$venueId]) ? ($venues[$venueId]['url'] ?: ($ev['event_url'] ?? '')) : ($ev['event_url'] ?? '');

    // Price
    $priceRaw = trim($ev['cost'] ?? '');
    $price    = 0.00;
    if ($priceRaw !== '') {
        // Strip currency symbols, keep numeric
        $numeric = preg_replace('/[^0-9.]/', '', $priceRaw);
        $price   = $numeric !== '' ? (float) $numeric : 0.00;
    }

    // Description
    $description = stripGutenbergComments($ev['post_content']);
    // Strip the redundant <!-- wp:tribe/event-datetime /--> placeholder that Tribe injects
    $description = trim(preg_replace('/^\s*$\n/m', "\n", $description));

    $status = $ev['post_status'] === 'publish' ? 'published' : 'draft';

    $venueNote = $location ? " @ {$location}" : '';
    log_line("  [{$ev['ID']}] {$title} → {$slug} | {$startDate}{$venueNote}");

    if (!$DRY_RUN) {
        try {
            $insert->execute([
                ':title'        => $title,
                ':slug'         => $slug,
                ':description'  => $description ?: null,
                ':location'     => $location ?: null,
                ':location_url' => $locationUrl ?: null,
                ':start_date'   => $startDate,
                ':start_time'   => $startTime,
                ':end_date'     => $endDate,
                ':end_time'     => $endTime,
                ':is_all_day'   => $isAllDay,
                ':price'        => $price,
                ':status'       => $status,
                ':created_at'   => $ev['post_date'],
                ':updated_at'   => $ev['post_modified'],
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
log_line("=== {$mode}: {$inserted} events imported, {$skipped} skipped ===");
