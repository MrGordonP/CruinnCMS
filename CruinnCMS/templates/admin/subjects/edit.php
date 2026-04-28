<?php $isNew = empty($subject['id']); ?>
<?php \Cruinn\Template::requireCss('admin-acp.css'); ?>
<div class="admin-page-editor">
    <h1><?= $isNew ? 'New Subject' : 'Edit: ' . e($subject['title']) ?></h1>

    <?php if (!empty($errors)): ?>
    <div class="flash flash-error" role="alert">
        <?php foreach ($errors as $msg): ?>
            <p><?= e($msg) ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="post" action="<?= $isNew ? '/admin/subjects' : '/admin/subjects/' . (int) $subject['id'] ?>">
        <?= csrf_field() ?>

        <div class="form-grid">
            <div class="form-section">
                <h3>Details</h3>

                <div class="form-group">
                    <label for="code">Code <small>(unique reference, e.g. IGRM-2026)</small></label>
                    <input type="text" id="code" name="code" required
                           value="<?= e($subject['code'] ?? '') ?>"
                           class="form-input" placeholder="e.g. IGRM-2026">
                </div>

                <div class="form-group">
                    <label for="title">Title</label>
                    <input type="text" id="title" name="title" required
                           value="<?= e($subject['title'] ?? '') ?>"
                           class="form-input">
                </div>

                <div class="form-group">
                    <label for="slug">URL Slug</label>
                    <div class="input-with-prefix">
                        <span class="input-prefix">/subjects/</span>
                        <input type="text" id="slug" name="slug"
                               value="<?= e($subject['slug'] ?? '') ?>"
                               class="form-input" pattern="[a-z0-9\-]+">
                    </div>
                    <small class="form-help">Leave blank to auto-generate from title.</small>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="4"
                              class="form-input"><?= e($subject['description'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-section">
                <h3>Classification</h3>

                <div class="form-group">
                    <label for="type">Type</label>
                    <select id="type" name="type" class="form-input">
                        <?php foreach (['general', 'series', 'event', 'news', 'campaign', 'project'] as $t): ?>
                        <option value="<?= $t ?>" <?= ($subject['type'] ?? 'general') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-input">
                        <?php foreach (['draft', 'active', 'archived'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($subject['status'] ?? 'draft') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="parent_id">Parent Subject <small>(optional)</small></label>
                    <select id="parent_id" name="parent_id" class="form-input">
                        <option value="">— None —</option>
                        <?php foreach ($parentSubjects as $ps): ?>
                        <option value="<?= (int) $ps['id'] ?>" <?= ($subject['parent_id'] ?? '') == $ps['id'] ? 'selected' : '' ?>>
                            <?= e($ps['code']) ?> — <?= e($ps['title']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <h3>Date Range <small>(optional)</small></h3>

                <div class="form-group">
                    <label for="starts_at">Starts</label>
                    <input type="datetime-local" id="starts_at" name="starts_at"
                           value="<?= e(!empty($subject['starts_at']) ? date('Y-m-d\TH:i', strtotime($subject['starts_at'])) : '') ?>"
                           class="form-input">
                </div>

                <div class="form-group">
                    <label for="ends_at">Ends</label>
                    <input type="datetime-local" id="ends_at" name="ends_at"
                           value="<?= e(!empty($subject['ends_at']) ? date('Y-m-d\TH:i', strtotime($subject['ends_at'])) : '') ?>"
                           class="form-input">
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $isNew ? 'Create Subject' : 'Update Subject' ?></button>
            <a href="/admin/subjects" class="btn btn-outline">Cancel</a>
        </div>
    </form>

    <?php if (!$isNew): ?>
    <section class="danger-zone">
        <h3>Danger Zone</h3>
        <form method="post" action="/admin/subjects/<?= (int) $subject['id'] ?>/delete"
              data-confirm="Are you sure you want to delete this subject? This cannot be undone?">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger">Delete this subject</button>
        </form>
    </section>
    <?php endif; ?>
</div>
