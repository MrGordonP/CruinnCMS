<?php
/**
 * Widget: Council Stats
 * Document and discussion counts.
 * Data keys: documents, pending, discussions, posts
 */
$d = $data;
?>
<div class="activity-header">
    <h2>Council Stats</h2>
</div>
<div class="dash-quick-grid">
    <a href="/council/documents" class="dash-quick-link">
        <span class="dash-quick-icon">📄</span>
        <strong class="dash-stat-num"><?= (int)($d['documents'] ?? 0) ?></strong>
        <span>Documents</span>
    </a>
    <a href="/council/documents?status=submitted" class="dash-quick-link">
        <span class="dash-quick-icon">⏳</span>
        <strong class="dash-stat-num"><?= (int)($d['pending'] ?? 0) ?></strong>
        <span>Pending Approval</span>
    </a>
    <a href="/council/discussions" class="dash-quick-link">
        <span class="dash-quick-icon">💬</span>
        <strong class="dash-stat-num"><?= (int)($d['discussions'] ?? 0) ?></strong>
        <span>Discussions</span>
    </a>
    <div class="dash-quick-link">
        <span class="dash-quick-icon">✉️</span>
        <strong class="dash-stat-num"><?= (int)($d['posts'] ?? 0) ?></strong>
        <span>Posts</span>
    </div>
</div>
