<?php
/**
 * Widget: Communications & Social (combined)
 * Shows article stats + recent articles alongside social platform quick links.
 * Data keys: articles[], draftCount, totalArticles, facebook, twitter, instagram
 */
\Cruinn\Template::requireCss('admin-social.css');

$articles     = $data['articles']      ?? [];
$draftCount   = $data['draftCount']    ?? 0;
$totalArticles = $data['totalArticles'] ?? 0;
$fbUrl = $data['facebook']  ?? '';
$twUrl = $data['twitter']   ?? '';
$igUrl = $data['instagram'] ?? '';
?>
<div class="activity-header">
    <h2>Communications &amp; Social</h2>
    <div style="display:flex;gap:0.5rem;">
        <a href="<?= url('/admin/blog/new') ?>" class="btn btn-primary btn-small">+ New Blog Post</a>
        <a href="<?= url('/admin/social') ?>" class="btn btn-secondary btn-small">Social Command Centre</a>
    </div>
</div>

<div class="comms-social-inner">
        <!-- Communications column -->
        <div class="comms-social-col">
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
                    <a href="<?= url('/admin/blog/' . (int)$ra['id'] . '/edit') ?>"><?= e($ra['title']) ?></a>
                    <span class="badge badge-<?= $ra['status'] === 'published' ? 'success' : ($ra['status'] === 'draft' ? 'warning' : 'muted') ?>">
                        <?= e(ucfirst($ra['status'])) ?>
                    </span>
                    <?php if ($ra['published_at']): ?>
                    <time class="text-muted"><?= format_date($ra['published_at'], 'j M') ?></time>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="text-muted" style="font-size:0.875rem;margin-top:var(--space-md);">No blog posts yet. <a href="<?= url('/admin/blog/new') ?>">Create your first</a>.</p>
            <?php endif; ?>
        </div>

        <!-- Social column -->
        <div class="comms-social-col">
            <?php if ($fbUrl || $twUrl || $igUrl): ?>
            <div class="social-dashboard-links">
                <?php if ($fbUrl): ?>
                <a href="<?= e($fbUrl) ?>" target="_blank" rel="noopener noreferrer" class="social-dash-link social-dash-facebook">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    <span>
                        <strong>Facebook</strong>
                        <small>Post updates &amp; events</small>
                    </span>
                </a>
                <?php endif; ?>
                <?php if ($twUrl): ?>
                <a href="<?= e($twUrl) ?>" target="_blank" rel="noopener noreferrer" class="social-dash-link social-dash-twitter">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                    <span>
                        <strong>Twitter / X</strong>
                        <small>Share news &amp; updates</small>
                    </span>
                </a>
                <?php endif; ?>
                <?php if ($igUrl): ?>
                <a href="<?= e($igUrl) ?>" target="_blank" rel="noopener noreferrer" class="social-dash-link social-dash-instagram">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                    <span>
                        <strong>Instagram</strong>
                        <small>Photos &amp; fieldtrip highlights</small>
                    </span>
                </a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <p class="text-muted" style="font-size:0.875rem;">No social accounts configured. <a href="<?= url('/admin/settings/site') ?>">Add links in Site Settings</a>.</p>
            <?php endif; ?>

            <h3 class="dash-widget-label">Share Tips</h3>
            <ul class="comms-tips">
                <li>Published blog posts include share buttons for Facebook, Twitter/X, and email</li>
                <li>All public pages include Open Graph metadata for rich social previews</li>
                <li>Tag fieldtrip photos with location for better Instagram reach</li>
            </ul>
        </div>
    </div>

