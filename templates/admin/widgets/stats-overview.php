<?php
/**
 * Widget: Stats Overview
 * Displays site-wide counts as icon cards within a dashboard section container.
 * Data keys: pages, articles, events, members, users, subjects, forum_threads (optional)
 */
$d = $data;
?>
<div class="activity-header">
    <h2>Site at a Glance</h2>
</div>
<div class="dash-quick-grid">
    <a href="<?= url('/admin/pages') ?>" class="dash-quick-link">
        <span class="dash-quick-icon">📄</span>
        <strong class="dash-stat-num"><?= (int)($d['pages'] ?? 0) ?></strong>
        <span>Pages</span>
    </a>
    <?php if (isset($d['articles'])): ?>
    <a href="<?= url('/admin/blog') ?>" class="dash-quick-link">
        <span class="dash-quick-icon">📰</span>
        <strong class="dash-stat-num"><?= (int)$d['articles'] ?></strong>
        <span>Blog</span>
    </a>
    <?php endif; ?>
    <?php if (isset($d['events'])): ?>
    <a href="<?= url('/admin/events') ?>" class="dash-quick-link">
        <span class="dash-quick-icon">📅</span>
        <strong class="dash-stat-num"><?= (int)$d['events'] ?></strong>
        <span>Events</span>
    </a>
    <?php endif; ?>
    <a href="<?= url('/admin/users') ?>" class="dash-quick-link">
        <span class="dash-quick-icon">🤝</span>
        <strong class="dash-stat-num"><?= (int)($d['members'] ?? 0) ?></strong>
        <span>Members</span>
    </a>
    <a href="<?= url('/admin/users') ?>" class="dash-quick-link">
        <span class="dash-quick-icon">👤</span>
        <strong class="dash-stat-num"><?= (int)($d['users'] ?? 0) ?></strong>
        <span>Users</span>
    </a>
    <a href="<?= url('/admin/subjects') ?>" class="dash-quick-link">
        <span class="dash-quick-icon">🏷️</span>
        <strong class="dash-stat-num"><?= (int)($d['subjects'] ?? 0) ?></strong>
        <span>Subjects</span>
    </a>
    <?php if (isset($d['forum_threads'])): ?>
    <a href="<?= url('/admin/forum') ?>" class="dash-quick-link">
        <span class="dash-quick-icon">💬</span>
        <strong class="dash-stat-num"><?= (int)$d['forum_threads'] ?></strong>
        <span>Forum Threads</span>
    </a>
    <?php endif; ?>
</div>
