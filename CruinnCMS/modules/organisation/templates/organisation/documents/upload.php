<div class="organisation-document-upload">
    <div class="page-header">
        <a href="/organisation/documents" class="back-link">&larr; All Documents</a>
        <h1>Upload Document</h1>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="form-errors">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="post" action="/organisation/documents" enctype="multipart/form-data" class="organisation-form">
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="title">Title <span class="required">*</span></label>
            <input type="text" name="title" id="title" class="form-input"
                   value="<?= e($document['title'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea name="description" id="description" class="form-input" rows="3"
                      placeholder="Optional description of the document"><?= e($document['description'] ?? '') ?></textarea>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="category">Category</label>
                <select name="category" id="category" class="form-select">
                    <?php foreach (['minutes', 'reports', 'policies', 'correspondence', 'financial', 'other'] as $cat): ?>
                        <option value="<?= e($cat) ?>" <?= ($document['category'] ?? 'other') === $cat ? 'selected' : '' ?>>
                            <?= e(ucfirst($cat)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="status">Initial Status</label>
                <select name="status" id="status" class="form-select">
                    <option value="draft" <?= ($document['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="submitted" <?= ($document['status'] ?? '') === 'submitted' ? 'selected' : '' ?>>Submit for Approval</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="file">File <span class="required">*</span></label>
            <input type="file" name="file" id="file" class="form-input" required>
            <p class="form-help">
                Accepted formats: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, CSV, ZIP, JPG, PNG.
                Maximum size: 10 MB.
            </p>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Upload Document</button>
            <a href="/organisation/documents" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
