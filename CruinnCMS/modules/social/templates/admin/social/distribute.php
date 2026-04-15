<?php \Cruinn\Template::requireCss('admin-social.css'); ?>
<div class="social-hub">
    <div class="social-hub-header">
        <h1>Distribute Content</h1>
        <a href="<?= url('/admin/social') ?>" class="btn btn-outline">Back to Hub</a>
    </div>

    <form action="<?= url('/admin/social/distribute') ?>" method="POST" class="distribute-form" id="distributeForm">
        <?= csrf_field() ?>

        <!-- Step 1: Select Content -->
        <div class="distribute-step">
            <h2><span class="step-number">1</span> Select Content</h2>
            <div class="form-row">
                <div class="form-group form-group-half">
                    <label>Content Type</label>
                    <select name="content_type" id="contentType" class="form-control" required>
                        <option value="">Choose type...</option>
                        <option value="article" <?= $selectedType === 'article' ? 'selected' : '' ?>>Blog Post</option>
                        <option value="event" <?= $selectedType === 'event' ? 'selected' : '' ?>>Event</option>
                    </select>
                </div>
                <div class="form-group form-group-half">
                    <label>Select Item</label>
                    <select name="content_id" id="contentId" class="form-control" required>
                        <option value="">Choose item...</option>
                    </select>
                </div>
            </div>

            <!-- Hidden data for JS to populate content_id dropdown -->
            <script>
            var distributeArticles = <?= json_encode(array_map(function($a) {
                return ['id' => $a['id'], 'title' => $a['title'], 'image' => $a['featured_image'] ?? ''];
            }, $articles)) ?>;
            var distributeEvents = <?= json_encode(array_map(function($e) {
                return ['id' => $e['id'], 'title' => $e['title'], 'image' => $e['image'] ?? ''];
            }, $events)) ?>;
            <?php if ($selectedContent): ?>
            var preselectedId = <?= (int)$selectedContent['id'] ?>;
            <?php else: ?>
            var preselectedId = null;
            <?php endif; ?>
            </script>

            <?php if ($selectedContent): ?>
            <div class="content-preview">
                <strong>Selected:</strong> <?= e($selectedContent['title'] ?? '') ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Step 2: Compose Message -->
        <div class="distribute-step">
            <h2><span class="step-number">2</span> Compose Message</h2>
            <div class="form-group">
                <label>Message</label>
                <textarea name="message" rows="4" class="form-control" id="distributeMessage"
                    placeholder="Write your message here. This will be posted to selected platforms..." required></textarea>
                <div class="char-count">
                    <span id="charCount">0</span> / 280 characters (Twitter limit)
                </div>
            </div>
            <div class="form-group">
                <label>Image URL (optional — required for Instagram)</label>
                <input type="url" name="image_url" class="form-control" id="distributeImage"
                    placeholder="https://example.com/uploads/images/...">
            </div>
        </div>

        <!-- Step 3: Select Channels -->
        <div class="distribute-step">
            <h2><span class="step-number">3</span> Select Channels</h2>

            <?php if (!empty($accounts)): ?>
            <div class="channel-section">
                <h3>Social Media</h3>
                <div class="channel-checkboxes">
                    <?php foreach ($accounts as $acct): ?>
                    <label class="checkbox-label channel-option platform-check-<?= e($acct['platform']) ?>">
                        <input type="checkbox" name="channels[]" value="<?= (int)$acct['id'] ?>">
                        <span class="platform-dot platform-dot-<?= e($acct['platform']) ?>"></span>
                        <?= ucfirst($acct['platform']) ?>
                        <?php if ($acct['account_name']): ?>
                            <small>(<?= e($acct['account_name']) ?>)</small>
                        <?php endif; ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($mailingLists)): ?>
            <div class="channel-section">
                <h3>Mailing Lists</h3>
                <div class="channel-checkboxes">
                    <?php foreach ($mailingLists as $list): ?>
                    <label class="checkbox-label channel-option channel-option-email">
                        <input type="checkbox" name="mailing_lists[]" value="<?= (int)$list['id'] ?>">
                        &#9993; <?= e($list['name']) ?>
                        <?php if ($list['description']): ?>
                            <small>(<?= e($list['description']) ?>)</small>
                        <?php endif; ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="distribute-actions">
            <button type="submit" class="btn btn-primary btn-large">Distribute Now</button>
        </div>
    </form>

    <!-- Distribution History -->
    <?php if (!empty($history)): ?>
    <div class="social-section" style="margin-top: var(--space-xl);">
        <h2>Recent Distribution History</h2>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Content</th>
                    <th>Channel</th>
                    <th>Status</th>
                    <th>When</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $dist): ?>
                <tr>
                    <td><?= e(ucfirst($dist['content_type'])) ?> #<?= (int)$dist['content_id'] ?></td>
                    <td><?= e($dist['channel_name']) ?></td>
                    <td>
                        <span class="badge badge-<?= $dist['status'] === 'sent' ? 'success' : ($dist['status'] === 'failed' ? 'danger' : 'warning') ?>">
                            <?= e(ucfirst($dist['status'])) ?>
                        </span>
                        <?php if ($dist['error_message']): ?>
                            <small class="text-danger"><?= e(truncate($dist['error_message'], 50)) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= format_date($dist['created_at'], 'j M Y, H:i') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
