<?php
/**
 * File Manager — Upload Form
 *
 * Upload file with auto-parse for Word/PDF documents.
 */
\IGA\Template::requireCss('admin-file-manager.css');

?>

<div class="admin-page">
    <div class="admin-page-header">
        <h1>Upload File</h1>
        <a href="/files" class="btn btn-secondary">← Back to Files</a>
    </div>

    <form method="post" action="/files/upload" enctype="multipart/form-data" class="form-card">
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="file">File <span class="required">*</span></label>
            <div class="fm-upload-zone" id="upload-zone">
                <input type="file" name="file" id="file" required class="fm-upload-input">
                <div class="fm-upload-prompt">
                    <span class="fm-upload-icon">📤</span>
                    <p><strong>Choose a file</strong> or drag it here</p>
                    <p class="text-muted">Word, PDF, Excel, PowerPoint, text, images, ZIP — up to 10 MB</p>
                </div>
                <div class="fm-upload-preview" id="upload-preview" style="display:none">
                    <span class="fm-upload-file-icon" id="preview-icon"></span>
                    <span class="fm-upload-file-name" id="preview-name"></span>
                    <span class="fm-upload-file-size" id="preview-size"></span>
                </div>
            </div>
            <p class="form-help">
                <strong>Parseable formats</strong> (content will be extracted for preview/editing):<br>
                .docx, .doc, .pdf, .txt, .rtf, .html, .md
            </p>
        </div>

        <div class="form-group">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" placeholder="Auto-generated from filename if blank">
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="2" placeholder="Optional description"></textarea>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="folder_id">Folder</label>
                <select id="folder_id" name="folder_id">
                    <option value="">— Root —</option>
                    <?php foreach ($folders as $f): ?>
                        <option value="<?= (int)$f['id'] ?>" <?= ($currentFolderId ?? '') == $f['id'] ? 'selected' : '' ?>>
                            <?= str_repeat('— ', $f['depth'] ?? 0) ?><?= e($f['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="subject_id">Subject</label>
                <select id="subject_id" name="subject_id">
                    <option value="">— None —</option>
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"><?= e($s['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Upload & Parse</button>
            <a href="/files" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
// File input preview
document.getElementById('file').addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;

    const preview = document.getElementById('upload-preview');
    const prompt = document.querySelector('.fm-upload-prompt');
    const titleInput = document.getElementById('title');

    document.getElementById('preview-icon').textContent = getFileIcon(file.name);
    document.getElementById('preview-name').textContent = file.name;
    document.getElementById('preview-size').textContent = formatSize(file.size);

    preview.style.display = 'flex';
    prompt.style.display = 'none';

    // Auto-fill title from filename
    if (!titleInput.value) {
        titleInput.value = file.name.replace(/\.[^.]+$/, '').replace(/[-_]/g, ' ');
    }
});

// Drag and drop
const zone = document.getElementById('upload-zone');
zone.addEventListener('dragover', (e) => { e.preventDefault(); zone.classList.add('drag-over'); });
zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
zone.addEventListener('drop', (e) => {
    e.preventDefault();
    zone.classList.remove('drag-over');
    const input = document.getElementById('file');
    input.files = e.dataTransfer.files;
    input.dispatchEvent(new Event('change'));
});

function getFileIcon(name) {
    const ext = name.split('.').pop().toLowerCase();
    const icons = {pdf:'📄', doc:'📝', docx:'📝', xls:'📊', xlsx:'📊', ppt:'📊', pptx:'📊', txt:'📃', csv:'📃', md:'📃', html:'🌐', htm:'🌐', jpg:'🖼️', jpeg:'🖼️', png:'🖼️', gif:'🖼️', webp:'🖼️', zip:'📦'};
    return icons[ext] || '📎';
}

function formatSize(bytes) {
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
    if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return bytes + ' B';
}
</script>
