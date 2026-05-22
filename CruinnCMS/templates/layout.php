<!DOCTYPE html>
<html lang="en">
<head>
<?php
// Protect the page content HTML from being clobbered by block.php includes
// (block.php assigns $content = $block['content'] which is an array).
$_pageContent = $content ?? '';
?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? $site_name ?? 'Portal') ?> — <?= e($site_name ?? 'Portal') ?></title>
    <?php if (!empty($meta_description)): ?>
    <meta name="description" content="<?= e($meta_description) ?>">
    <?php endif; ?>

    <!-- Open Graph -->
    <meta property="og:title" content="<?= e($og_title ?? $title ?? $site_name ?? 'Portal') ?>">
    <meta property="og:site_name" content="<?= e($site_name ?? 'Portal') ?>">
    <meta property="og:type" content="<?= e($og_type ?? 'website') ?>">
    <?php if (!empty($og_url) || !empty($canonical_url)): ?>
    <meta property="og:url" content="<?= e($og_url ?? $canonical_url) ?>">
    <?php endif; ?>
    <?php if (!empty($og_description) || !empty($meta_description)): ?>
    <meta property="og:description" content="<?= e($og_description ?? $meta_description) ?>">
    <?php endif; ?>
    <?php if (!empty($og_image)): ?>
    <meta property="og:image" content="<?= e($og_image) ?>">
    <?php endif; ?>

    <!-- Twitter Card -->
    <meta name="twitter:card" content="<?= !empty($og_image) ? 'summary_large_image' : 'summary' ?>">
    <meta name="twitter:title" content="<?= e($og_title ?? $title ?? $site_name ?? 'Portal') ?>">
    <?php if (!empty($og_description) || !empty($meta_description)): ?>
    <meta name="twitter:description" content="<?= e($og_description ?? $meta_description) ?>">
    <?php endif; ?>
    <?php if (!empty($og_image)): ?>
    <meta name="twitter:image" content="<?= e($og_image) ?>">
    <?php endif; ?>

    <link rel="stylesheet" href="<?= url('/css/style.css') ?>">
    <?php foreach (\Cruinn\Template::flushCss() as $_cssFile): ?>
    <link rel="stylesheet" href="<?= url('/css/' . ltrim($_cssFile, '/')) ?>">
    <?php endforeach; ?>
    <?php
    $_activeTheme = \Cruinn\App::config('site.active_theme', 'default');
    $_themeFile = CRUINN_PUBLIC . '/css/themes/' . preg_replace('/[^a-z0-9_-]/i', '', $_activeTheme) . '.css';
    if (file_exists($_themeFile)):
    ?>
    <link rel="stylesheet" href="<?= url('/css/themes/' . e(preg_replace('/[^a-z0-9_-]/i', '', $_activeTheme)) . '.css') ?>">
    <?php endif; ?>
    <?php if (!empty($cruinn_css)): ?>
    <style id="cruinn-page-styles"><?= $cruinn_css ?></style>
    <?php endif; ?>
</head>
<body>

<!-- Skip to content for accessibility -->
<a href="#main-content" class="skip-link">Skip to content</a>

<?php if (!empty($flashes)): ?>
<div class="flash-messages container">
    <?php foreach ($flashes as $flash): ?>
        <div class="flash flash-<?= e($flash['type']) ?>" role="alert">
            <?= e($flash['message']) ?>
            <button class="flash-close" aria-label="Dismiss">&times;</button>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?= $_pageContent ?>

<script src="<?= url('/js/main.js') ?>"></script>
</body>
</html>
