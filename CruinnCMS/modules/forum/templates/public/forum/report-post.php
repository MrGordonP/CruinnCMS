<section class="container forum-page">
    <header class="forum-header">
        <h1>Report Post</h1>
        <p>Reporting a post by <strong><?= e($post['author_name']) ?></strong>.</p>
    </header>

    <div class="forum-post forum-post--preview">
        <div class="forum-post-body">
            <?= sanitise_html($post['body_html']) ?>
        </div>
    </div>

    <form method="post" action="<?= url('/forum/post/' . (int)$post['id'] . '/report') ?>" class="forum-report-form">
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="reason">Reason <span class="required">*</span></label>
            <select id="reason" name="reason" required>
                <option value="">— Select a reason —</option>
                <option value="spam">Spam or advertising</option>
                <option value="harassment">Harassment or abuse</option>
                <option value="misinformation">Misinformation</option>
                <option value="off-topic">Off-topic / wrong category</option>
                <option value="other">Other</option>
            </select>
        </div>

        <div class="form-group">
            <label for="body">Additional Details</label>
            <textarea id="body" name="body" class="form-input" rows="4" placeholder="Optional — provide any extra context for the moderator."></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Submit Report</button>
            <a href="<?= url('/forum/thread/' . (int)$post['thread_id']) ?>" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</section>
