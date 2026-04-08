<?php
// ONE-TIME USE — DELETE THIS FILE IMMEDIATELY AFTER RUNNING
// Removes a directory and all its contents recursively.
// Upload to public_html/CruinnCMS/, visit in browser, then delete.

$target = isset($_GET['dir']) ? basename($_GET['dir']) : null;
$allowed = ['src', 'templates', 'schema', 'config', 'instance'];

// Check sibling (landed in public web root) first,
// then home root with CruinnCMS/ wrapper, then home root directly.
function findDir(string $name): ?string {
    $sibling = __DIR__ . '/' . $name;
    if (is_dir($sibling)) return $sibling;
    $withWrapper = dirname(__DIR__) . '/CruinnCMS/' . $name;
    if (is_dir($withWrapper)) return $withWrapper;
    $homeRoot = dirname(__DIR__) . '/' . $name;
    if (is_dir($homeRoot)) return $homeRoot;
    return null;
}

if (!$target || !in_array($target, $allowed, true)) {
    echo '<p><strong>__DIR__:</strong> ' . __DIR__ . '</p>';
    echo '<p><strong>Checking above public_html:</strong> ' . dirname(__DIR__) . '</p>';
    echo '<form>Directory: <select name="dir">';
    foreach ($allowed as $d) {
        $found = findDir($d);
        echo '<option value="' . $d . '"' . ($found ? '' : ' disabled') . '>'
            . $d . ($found ? ' (' . $found . ')' : ' (not found)') . '</option>';
    }
    echo '</select> <button type="submit">Delete</button></form>';
    exit;
}

$realpath = realpath(findDir($target) ?? '');

if (!$realpath || !is_dir($realpath)) {
    echo 'Directory not found: ' . htmlspecialchars($path);
    exit;
}

function rrmdir(string $dir): bool {
    chmod($dir, 0755);
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') continue;
        $full = $dir . '/' . $item;
        if (is_dir($full)) {
            rrmdir($full);
        } else {
            chmod($full, 0644);
            unlink($full);
        }
    }
    return rmdir($dir);
}

if (rrmdir($realpath)) {
    echo 'Deleted: ' . htmlspecialchars($target) . ' — <strong>Delete this script now.</strong>';
} else {
    echo 'Failed to delete: ' . htmlspecialchars($realpath);
}
