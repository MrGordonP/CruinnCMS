<?php
/**
 * Reimport a page using the updated ForeignImportService.
 * Usage: php tools/reimport_page.php <pageId> [--dry-run]
 *
 * --dry-run (default): show what blocks would be created without touching DB
 * --apply: delete existing blocks and reimport
 */
require __DIR__ . '/../../vendor/autoload.php';

// Register RC namespace — PSR-4 maps Cruinn\ to RCEnvironment/src/
spl_autoload_register(function ($class) {
    $prefix = 'Cruinn\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    $file = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (file_exists($file)) require $file;
});

$cfg = require __DIR__ . '/../config/CruinnCMS.php';
$db = $cfg['db'];
$pdo = new PDO("mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}", $db['user'], $db['password']);

$pageId = (int)($argv[1] ?? 15);
$apply  = in_array('--apply', $argv ?? [], true);

// Get page info
$page = $pdo->query("SELECT * FROM pages WHERE id = {$pageId}")->fetch(PDO::FETCH_ASSOC);
if (!$page) { echo "Page {$pageId} not found\n"; exit(1); }
echo "Page: {$page['title']} (render_mode={$page['render_mode']})\n";

// Resolve source file — same logic as PlatformController::resolveRenderFilePath
$absFilePath = null;
if ($page['render_mode'] === 'file') {
    $rcRoot = realpath(__DIR__ . '/..');
    $renderFile = $page['render_file'] ?? '';
    if (str_starts_with($renderFile, '@cms/')) {
        $abs = realpath($rcRoot . '/' . substr($renderFile, 5));
        $absFilePath = ($abs && str_starts_with($abs, $rcRoot)) ? $abs : null;
    }
}
echo "Source: " . ($absFilePath ?? '(none)') . "\n\n";

$svc = new \Cruinn\Services\ForeignImportService();
$blocks = $svc->autoImport($page, $pageId, $absFilePath);

echo "Generated " . count($blocks) . " blocks" . ($apply ? " (APPLYING)" : " (dry-run)") . ":\n\n";
foreach ($blocks as $b) {
    $cfg = $b['block_config'] ? json_decode($b['block_config'], true) : null;
    $isContainer = !empty($cfg['_container']);
    $isRaw = !empty($cfg['_raw']);
    $tag = $cfg['_tag'] ?? '';
    $label = $isContainer ? 'CONTAINER' : ($isRaw ? 'RAW' : 'LEAF');
    $indent = str_repeat('  ', substr_count($b['parent_block_id'] ?? '', '-') > 0 ? 1 : 0);
    
    // Count nesting depth by looking up parents
    $depth = 0;
    $pid = $b['parent_block_id'];
    while ($pid !== null) {
        $depth++;
        foreach ($blocks as $pb) {
            if ($pb['block_id'] === $pid) { $pid = $pb['parent_block_id']; break; }
        }
        if ($depth > 10) break;
    }
    $indent = str_repeat('  ', $depth);
    
    $htmlLen = strlen($b['inner_html'] ?? '');
    echo "{$indent}{$b['block_id']} [{$label}] <{$b['block_type']}:{$tag}> parent={$b['parent_block_id']} sort={$b['sort_order']} html={$htmlLen}b\n";
}

if ($apply) {
    $pdo->exec("DELETE FROM cruinn_draft_blocks WHERE page_id = {$pageId}");
    $pdo->exec("DELETE FROM cruinn_page_state WHERE page_id = {$pageId}");
    echo "\nDeleted old blocks. Inserting new...\n";
    
    // Need Database wrapper for persistImportedBlocks
    // Simple insert loop instead
    $stmt = $pdo->prepare('INSERT INTO cruinn_page_state (page_id, current_edit_seq, max_edit_seq, last_edited_at) VALUES (?, 1, 1, NOW())');
    $stmt->execute([$pageId]);
    
    $stmt = $pdo->prepare('INSERT INTO cruinn_draft_blocks (page_id, edit_seq, block_id, block_type, inner_html, css_props, block_config, sort_order, parent_block_id, is_active, is_deletion) VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, 1, 0)');
    foreach ($blocks as $b) {
        $stmt->execute([$pageId, $b['block_id'], $b['block_type'], $b['inner_html'], $b['css_props'], $b['block_config'], $b['sort_order'], $b['parent_block_id']]);
    }
    echo "Done — {$pageId} reimported with " . count($blocks) . " blocks.\n";
} else {
    echo "\n(dry-run — pass --apply to reimport)\n";
}
