<?php
/**
 * Widget: Communications
 * Recent articles, draft count, quick actions.
 * Data keys: articles[], draftCount, totalArticles
 */
\Cruinn\Template::requireCss('admin-social.css');

$articles = $data['articles'] ?? [];
$draftCount = $data['draftCount'] ?? 0;
$totalArticles = $data['totalArticles'] ?? 0;
?>
<div class="activity-header">
    <h2>Communications</h2>
    <a href="/admin/blog/new" class="btn btn-primary btn-small">+ New Blog Post</a>
</div>

<div class="dash-quick-grid">
    <a href="<?= url('/admin/blog') ?>" class="dash-quick-link">
        <span class="dash-quick-icon">📰</span>
        <strong class="dash-stat-num"><?= (int)$totalArticles ?></strong>
        <span>Blog Posts</span>
    </a>
    <a href="<?= url('/admin/blog?status=draft') ?>" class="dash-quick-link">
        <span class="dash-quick-icon">📝</span>
        <strong class="dash-stat-num"><?= (int)$draftCount ?></strong>
        <span>Drafts</span>
    </a>
</div>

<?php if (!empty($articles)): ?>
<h3 class="dash-widget-label">Recent Blog Posts</h3>
<ul class="comms-article-list">
    <?php foreach ($articles as $ra): ?>
    <li>
        <a href="/admin/blog/<?= (int)$ra['id'] ?>/edit"><?= e($ra['title']) ?></a>
        <span class="badge badge-<?= $ra['status'] === 'published' ? 'success' : ($ra['status'] === 'draft' ? 'warning' : 'muted') ?>">
            <?= e(ucfirst($ra['status'])) ?>
        </span>
        <?php if ($ra['published_at']): ?>
        <time class="text-muted"><?= format_date($ra['published_at'], 'j M') ?></time>
        <?php endif; ?>
    </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>
