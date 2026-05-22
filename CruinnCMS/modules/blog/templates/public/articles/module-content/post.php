<?php \Cruinn\Template::requireCss('blog.css'); ?>

<?php if (!empty($article) && is_array($article)): ?>
<?php $returnToListUrl = $return_to_list_url ?? ($blog_base_path ?? '/blog'); ?>
<?php
$truncateNavTitle = static function (?string $title): string {
    $text = trim((string) $title);
    if ($text === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($text) > 48
            ? rtrim(mb_substr($text, 0, 48)) . '...'
            : $text;
    }

    return strlen($text) > 48
        ? rtrim(substr($text, 0, 48)) . '...'
        : $text;
};
?>
<article class="blog-post">
    <?php if (!empty($show_return_to_list)): ?>
    <nav class="blog-post-return blog-post-return-top" aria-label="Return to blog list">
        <a href="<?= e($returnToListUrl) ?>">&larr; Return to list</a>
    </nav>
    <?php endif; ?>

    <?php if (!empty($show_post_navigation) && (!empty($previous_article) || !empty($next_article))): ?>
    <nav class="blog-post-nav blog-post-nav-top" aria-label="Post navigation">
        <?php if (!empty($previous_article)): ?>
        <a href="<?= e($previous_article['public_url'] ?? '#') ?>" class="blog-post-nav-link blog-post-nav-link-prev">
            <span class="blog-post-nav-label">&laquo;&laquo; Previous post</span>
            <strong class="blog-post-nav-title"><?= e($truncateNavTitle($previous_article['title'] ?? '')) ?></strong>
        </a>
        <?php endif; ?>

        <?php if (!empty($next_article)): ?>
        <a href="<?= e($next_article['public_url'] ?? '#') ?>" class="blog-post-nav-link blog-post-nav-link-next">
            <span class="blog-post-nav-label">Next post &raquo;&raquo;</span>
            <strong class="blog-post-nav-title"><?= e($truncateNavTitle($next_article['title'] ?? '')) ?></strong>
        </a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>

    <header class="blog-post-header">
        <p class="blog-post-meta">
            <time datetime="<?= e($article['published_at'] ?? '') ?>"><?= format_date($article['published_at'] ?? null, 'l, j F Y') ?></time>
            <?php if (!empty($article['author_name'])): ?>
                <span><?= e($article['author_name']) ?></span>
            <?php endif; ?>
            <?php if (!empty($article['subject_title'])): ?>
                <span class="blog-post-subject"><?= e($article['subject_title']) ?></span>
            <?php endif; ?>
        </p>
        <h1 class="blog-post-title"><?= e($article['title'] ?? '') ?></h1>
        <?php if (!empty($article['excerpt'])): ?>
        <p class="blog-post-excerpt"><?= e($article['excerpt']) ?></p>
        <?php endif; ?>
        <?php if (!empty($article['featured_image'])): ?>
        <div class="blog-post-featured-image">
            <img src="<?= e($article['featured_image']) ?>" alt="<?= e($article['title'] ?? '') ?>">
        </div>
        <?php endif; ?>
    </header>

    <?php if (!empty($body_html)): ?>
    <div class="blog-post-body">
        <?= $body_html ?>
    </div>
    <?php endif; ?>

    <footer class="blog-post-footer">
        <?php include CRUINN_ROOT . '/templates/components/share.php'; ?>

        <?php if (!empty($show_post_navigation) && (!empty($previous_article) || !empty($next_article))): ?>
        <nav class="blog-post-nav" aria-label="Post navigation">
            <?php if (!empty($previous_article)): ?>
            <a href="<?= e($previous_article['public_url'] ?? '#') ?>" class="blog-post-nav-link blog-post-nav-link-prev">
                <span class="blog-post-nav-label">&laquo;&laquo; Previous post</span>
                <strong class="blog-post-nav-title"><?= e($truncateNavTitle($previous_article['title'] ?? '')) ?></strong>
            </a>
            <?php endif; ?>

            <?php if (!empty($next_article)): ?>
            <a href="<?= e($next_article['public_url'] ?? '#') ?>" class="blog-post-nav-link blog-post-nav-link-next">
                <span class="blog-post-nav-label">Next post &raquo;&raquo;</span>
                <strong class="blog-post-nav-title"><?= e($truncateNavTitle($next_article['title'] ?? '')) ?></strong>
            </a>
            <?php endif; ?>
        </nav>
        <?php endif; ?>

        <?php if (!empty($show_return_to_list)): ?>
        <nav class="blog-post-return blog-post-return-bottom" aria-label="Return to blog list">
            <a href="<?= e($returnToListUrl) ?>">&larr; Return to list</a>
        </nav>
        <?php endif; ?>
    </footer>
</article>
<?php else: ?>
<div class="blog-empty-state">
    <p>That post could not be rendered.</p>
</div>
<?php endif; ?>
