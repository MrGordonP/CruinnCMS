<?php
/**
 * import-phpbb-forum.php
 *
 * Imports phpBB forum structure + content from iga_phpbb into iga_cruinn.
 *
 * Maps:
 *   phpbb_users   → users (by email; creates missing ones with locked placeholder)
 *   phpbb_forums  → forum_categories (with parent_id hierarchy)
 *   phpbb_topics  → forum_threads
 *   phpbb_posts   → forum_posts (phpBB XML text → HTML)
 *
 * Access roles:
 *   Forums under phpBB forum_id 4 (Council Area) → council
 *   All others → member
 *
 * Usage:
 *   php dev/tools/import-phpbb-forum.php           # dry run
 *   php dev/tools/import-phpbb-forum.php --commit  # write to DB
 */

define('CRUINN_ROOT', dirname(__DIR__, 2) . '/CruinnCMS');
require_once CRUINN_ROOT . '/src/App.php';

$DRY_RUN    = !in_array('--commit', $argv, true);
$srcUser    = 'cruinn';
$srcPass    = 'cruinn-local';

// phpBB forum_id that means "council only" (and all its descendants)
const COUNCIL_ROOT_FORUM_ID = 4;
// phpBB root container forum (not imported as a category)
const PHPBB_ROOT_FORUM_ID   = 3;
// phpBB system account
const PHPBB_ADMIN_USER_ID   = 2;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function log_line(string $msg): void { echo $msg . PHP_EOL; }

/**
 * Convert phpBB XML-encoded post_text to clean HTML.
 * phpBB 3.2+ stores posts as XML: <r>/<t> root, <E> emoji, <B>/<I>/<U> etc.
 */
function phpbbXmlToHtml(string $text): string
{
    // Strip outer XML root containers <r>…</r> and <t>…</t> (keep content)
    $text = preg_replace('/<\/?[rt]>/', '', $text);

    // Strip <s> and <e> (BBCode open/close marker tags — no content needed)
    $text = preg_replace('/<[se][^>]*>.*?<\/[se]>/s', '', $text);

    // Emoji <E>:)</E> → just the text
    $text = preg_replace('/<E>([^<]*)<\/E>/', '$1', $text);

    // Bold, italic, underline
    $text = preg_replace('/<B>(.*?)<\/B>/s',     '<strong>$1</strong>', $text);
    $text = preg_replace('/<I>(.*?)<\/I>/s',     '<em>$1</em>',         $text);
    $text = preg_replace('/<U>(.*?)<\/U>/s',     '<u>$1</u>',           $text);

    // URL: <URL url="http://...">text</URL>
    $text = preg_replace_callback(
        '/<URL url="([^"]+)">(.+?)<\/URL>/s',
        fn($m) => '<a href="' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '" rel="noopener noreferrer">' . $m[2] . '</a>',
        $text
    );

    // Quote blocks
    $text = preg_replace_callback(
        '/<QUOTE author="([^"]*)">(.+?)<\/QUOTE>/s',
        function ($m) {
            $author = $m[1] !== '' ? '<cite>' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . ' wrote:</cite>' : '';
            return '<blockquote>' . $author . $m[2] . '</blockquote>';
        },
        $text
    );
    // Unnamed quotes
    $text = preg_replace('/<QUOTE>(.*?)<\/QUOTE>/s', '<blockquote>$1</blockquote>', $text);

    // Code blocks
    $text = preg_replace('/<CODE>(.*?)<\/CODE>/s', '<pre><code>$1</code></pre>', $text);

    // Lists
    $text = preg_replace('/<LIST>(.*?)<\/LIST>/s',   '<ul>$1</ul>',   $text);
    $text = preg_replace('/<LI>(.*?)<\/LI>/s',       '<li>$1</li>',   $text);

    // Images
    $text = preg_replace_callback(
        '/<IMG src="([^"]+)"[^\/]*\/>/s',
        fn($m) => '<img src="' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '" alt="">',
        $text
    );

    // Strip any remaining unknown XML tags (keep content)
    $text = preg_replace('/<[A-Z][A-Z0-9_]*(?:\s[^>]*)?>/', '', $text);
    $text = preg_replace('/<\/[A-Z][A-Z0-9_]*>/', '',           $text);

    // Normalise line breaks: <br/> → <br>
    $text = str_replace(['<br/>', '<br />'], '<br>', $text);

    // Wrap double-newline separated paragraphs
    $paragraphs = preg_split('/\n{2,}/', trim($text));
    $html = '';
    foreach ($paragraphs as $para) {
        $para = trim($para);
        if ($para === '') { continue; }
        // Don't double-wrap block elements
        if (preg_match('/^<(blockquote|ul|ol|pre|h[1-6])/i', $para)) {
            $html .= $para . "\n";
        } else {
            $html .= '<p>' . $para . "</p>\n";
        }
    }

    return trim($html) ?: '<p>' . trim($text) . '</p>';
}

function slugify(string $text, int $maxLen = 120): string
{
    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($text));
    return trim(substr($slug, 0, $maxLen), '-') ?: 'item';
}

// ---------------------------------------------------------------------------
// Connect
// ---------------------------------------------------------------------------

try {
    $srcDb = new PDO('mysql:host=127.0.0.1;dbname=iga_phpbb;charset=utf8mb4', $srcUser, $srcPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ]);
    $dstDb = new PDO('mysql:host=127.0.0.1;dbname=iga_cruinn;charset=utf8mb4', $srcUser, $srcPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ]);
} catch (PDOException $e) {
    log_line('ERROR: ' . $e->getMessage());
    exit(1);
}

if ($DRY_RUN) {
    log_line('[DRY RUN] Pass --commit to write to DB.');
    log_line('');
}

// ---------------------------------------------------------------------------
// STEP 1: Map phpBB users → CruinnCMS user IDs
// ---------------------------------------------------------------------------
log_line('=== Step 1: Users ===');

$phpbbUsers = $srcDb->query(
    "SELECT user_id, username, user_email, user_type, user_posts
       FROM phpbb_users
      WHERE user_type IN (0, 3)
        AND user_posts > 0
      ORDER BY user_id"
)->fetchAll();

// Load existing CruinnCMS users by email
$existingUsers = [];
foreach ($dstDb->query('SELECT id, email FROM users')->fetchAll() as $row) {
    $existingUsers[strtolower($row['email'])] = (int) $row['id'];
}

// phpBB user_id → cruinn user_id
$userMap = [];

// phpBB system admin (Administratium) → CruinnCMS user id 1
$userMap[PHPBB_ADMIN_USER_ID] = 1;

$insertUser = $dstDb->prepare(
    "INSERT INTO users (email, password_hash, display_name, role, active, created_at, updated_at)
     VALUES (:email, :hash, :display_name, :role, 0, NOW(), NOW())"
);

foreach ($phpbbUsers as $pu) {
    $email = strtolower(trim($pu['user_email']));
    if ($email === '') {
        log_line("  SKIP user [{$pu['user_id']}] {$pu['username']} — no email");
        $userMap[(int)$pu['user_id']] = 1; // fall back to admin
        continue;
    }

    if (isset($existingUsers[$email])) {
        $cruinnId = $existingUsers[$email];
        log_line("  MATCH [{$pu['user_id']}] {$pu['username']} → cruinn #{$cruinnId} (existing)");
        $userMap[(int)$pu['user_id']] = $cruinnId;
    } else {
        // Determine role: user_type 3 = founder = admin; others = council (they were all council members)
        $role = $pu['user_type'] == 3 ? 'admin' : 'council';
        log_line("  CREATE [{$pu['user_id']}] {$pu['username']} <{$email}> role={$role}");
        if (!$DRY_RUN) {
            $insertUser->execute([
                ':email'        => $email,
                ':hash'         => '', // locked — no valid hash, must reset to log in
                ':display_name' => $pu['username'],
                ':role'         => $role,
            ]);
            $newId = (int) $dstDb->lastInsertId();
            $existingUsers[$email] = $newId;
            $userMap[(int)$pu['user_id']] = $newId;
        } else {
            // Dry run: assign placeholder negative ID for counting
            $userMap[(int)$pu['user_id']] = -($pu['user_id']);
        }
    }
}

// ---------------------------------------------------------------------------
// STEP 2: Forum categories with hierarchy
// ---------------------------------------------------------------------------
log_line('');
log_line('=== Step 2: Forum categories ===');

$phpbbForums = $srcDb->query(
    "SELECT forum_id, forum_name, parent_id, forum_desc, left_id
       FROM phpbb_forums
      ORDER BY left_id ASC"
)->fetchAll();

// Build ancestry map to determine council vs member
$phpbbParentMap = [];
foreach ($phpbbForums as $f) {
    $phpbbParentMap[(int)$f['forum_id']] = (int)$f['parent_id'];
}

function isCouncilForum(int $forumId, array $parentMap): bool
{
    $current = $forumId;
    $visited = [];
    while ($current !== 0) {
        if ($current === COUNCIL_ROOT_FORUM_ID) { return true; }
        if (isset($visited[$current])) { break; } // cycle guard
        $visited[$current] = true;
        $current = $parentMap[$current] ?? 0;
    }
    return false;
}

// phpBB forum_id → cruinn category ID
$catMap      = [];
$existingSlugs = [];
foreach ($dstDb->query('SELECT id, slug FROM forum_categories')->fetchAll() as $row) {
    $existingSlugs[$row['slug']] = (int) $row['id'];
}

$insertCat = $dstDb->prepare(
    "INSERT INTO forum_categories (parent_id, title, slug, description, access_role, is_active, sort_order)
     VALUES (:parent_id, :title, :slug, :description, :access_role, 1, :sort_order)"
);

$sortOrder = 10;
foreach ($phpbbForums as $f) {
    $fid = (int) $f['forum_id'];

    // Skip the root container
    if ($fid === PHPBB_ROOT_FORUM_ID) {
        continue;
    }

    $parentPhpbbId = (int) $f['parent_id'];
    // Parent is the root container → no CruinnCMS parent
    $cruinnParentId = ($parentPhpbbId === 0 || $parentPhpbbId === PHPBB_ROOT_FORUM_ID)
        ? null
        : ($catMap[$parentPhpbbId] ?? null);

    $title  = $f['forum_name'];
    $rawSlug = slugify($title);
    $slug    = $rawSlug;
    $n       = 1;
    while (isset($existingSlugs[$slug])) { $slug = $rawSlug . '-' . $n++; }

    $role = isCouncilForum($fid, $phpbbParentMap) ? 'council' : 'member';
    $parentNote = $cruinnParentId ? " (parent cat #{$cruinnParentId})" : '';
    log_line("  [{$fid}] {$title} → {$slug} [{$role}]{$parentNote}");

    if (!$DRY_RUN) {
        $insertCat->execute([
            ':parent_id'   => $cruinnParentId,
            ':title'       => $title,
            ':slug'        => $slug,
            ':description' => $f['forum_desc'] ?: null,
            ':access_role' => $role,
            ':sort_order'  => $sortOrder,
        ]);
        $newCatId = (int) $dstDb->lastInsertId();
        $catMap[$fid] = $newCatId;
        $existingSlugs[$slug] = $newCatId;
    } else {
        $catMap[$fid] = -$fid; // placeholder for dry run
        $existingSlugs[$slug] = -$fid;
    }
    $sortOrder += 10;
}

// ---------------------------------------------------------------------------
// STEP 3: Topics → forum_threads
// ---------------------------------------------------------------------------
log_line('');
log_line('=== Step 3: Threads ===');

$topics = $srcDb->query(
    "SELECT topic_id, forum_id, topic_poster, topic_title,
            topic_type, topic_status, topic_posts_approved,
            topic_time, topic_last_post_time, topic_last_poster_id
       FROM phpbb_topics
      ORDER BY topic_time ASC"
)->fetchAll();

// phpBB topic_id → cruinn thread ID
$threadMap     = [];
$threadSlugs   = [];

$insertThread = $dstDb->prepare(
    "INSERT INTO forum_threads
        (category_id, user_id, title, slug, is_pinned, is_locked, reply_count, last_post_at, last_post_user_id, created_at)
     VALUES
        (:category_id, :user_id, :title, :slug, :is_pinned, :is_locked, :reply_count, :last_post_at, :last_post_user_id, :created_at)"
);

$threadsInserted = 0;
$threadsSkipped  = 0;

foreach ($topics as $topic) {
    $tid = (int) $topic['topic_id'];
    $fid = (int) $topic['forum_id'];

    if (!isset($catMap[$fid])) {
        log_line("  SKIP topic [{$tid}] {$topic['topic_title']} — forum {$fid} not imported");
        $threadsSkipped++;
        continue;
    }

    $cruinnCatId = $catMap[$fid];
    $userId      = $userMap[(int)$topic['topic_poster']] ?? 1;
    $lastUserId  = $userMap[(int)$topic['topic_last_poster_id']] ?? null;

    $rawSlug = slugify($topic['topic_title']);
    $slug    = $rawSlug;
    $n       = 1;
    while (isset($threadSlugs[$slug])) { $slug = $rawSlug . '-' . $n++; }

    $isPinned  = (int)$topic['topic_type'] === 1 ? 1 : 0; // type 1 = sticky
    $isLocked  = (int)$topic['topic_status'] === 1 ? 1 : 0;
    $replyCount = max(0, (int)$topic['topic_posts_approved'] - 1);

    log_line("  [{$tid}] {$topic['topic_title']} → cat#{$cruinnCatId} {$slug}");

    if (!$DRY_RUN) {
        $insertThread->execute([
            ':category_id'       => $cruinnCatId,
            ':user_id'           => abs($userId), // guard for dry-run negatives
            ':title'             => $topic['topic_title'],
            ':slug'              => $slug,
            ':is_pinned'         => $isPinned,
            ':is_locked'         => $isLocked,
            ':reply_count'       => $replyCount,
            ':last_post_at'      => date('Y-m-d H:i:s', (int)$topic['topic_last_post_time']),
            ':last_post_user_id' => $lastUserId ? abs($lastUserId) : null,
            ':created_at'        => date('Y-m-d H:i:s', (int)$topic['topic_time']),
        ]);
        $threadMap[$tid] = (int) $dstDb->lastInsertId();
    } else {
        $threadMap[$tid] = -$tid;
    }
    $threadSlugs[$slug] = true;
    $threadsInserted++;
}

// ---------------------------------------------------------------------------
// STEP 4: Posts → forum_posts
// ---------------------------------------------------------------------------
log_line('');
log_line('=== Step 4: Posts ===');

$posts = $srcDb->query(
    "SELECT post_id, topic_id, poster_id, post_time, post_text
       FROM phpbb_posts
      ORDER BY post_time ASC"
)->fetchAll();

$insertPost = $dstDb->prepare(
    "INSERT INTO forum_posts (thread_id, user_id, body_html, created_at)
     VALUES (:thread_id, :user_id, :body_html, :created_at)"
);

$postsInserted = 0;
$postsSkipped  = 0;

foreach ($posts as $post) {
    $topicId = (int) $post['topic_id'];

    if (!isset($threadMap[$topicId])) {
        $postsSkipped++;
        continue;
    }

    $threadId  = $threadMap[$topicId];
    $userId    = $userMap[(int)$post['poster_id']] ?? 1;
    $bodyHtml  = phpbbXmlToHtml($post['post_text']);
    $createdAt = date('Y-m-d H:i:s', (int)$post['post_time']);

    if (!$DRY_RUN) {
        $insertPost->execute([
            ':thread_id' => abs($threadId),
            ':user_id'   => abs($userId),
            ':body_html' => $bodyHtml,
            ':created_at'=> $createdAt,
        ]);
        $postsInserted++;
    } else {
        $postsInserted++;
    }
}

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------
log_line('');
$mode = $DRY_RUN ? 'DRY RUN' : 'COMMITTED';
log_line("=== {$mode} ===");
log_line("  Categories : " . count(array_filter(array_keys($catMap), fn($k) => $k > 0 || !$DRY_RUN)));
log_line("  Threads    : {$threadsInserted} imported, {$threadsSkipped} skipped");
log_line("  Posts      : {$postsInserted} imported, {$postsSkipped} skipped");
