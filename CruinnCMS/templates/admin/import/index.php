<div class="acp-page">
  <div class="acp-page-header">
    <h1>Import Pages</h1>
    <p class="acp-page-subtitle">Import HTML files as new pages. Choose file-mode (served as-is) or convert to Cruinn blocks.</p>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
      <?php foreach ($errors as $err): ?>
        <p><?= $this->escape($err) ?></p>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="acp-card">
    <form method="post" action="/admin/import/upload" enctype="multipart/form-data" class="form-standard">
      <input type="hidden" name="csrf_token" value="<?= \Cruinn\CSRF::getToken() ?>">

      <div class="form-group">
        <label for="import_file">HTML file or ZIP archive</label>
        <input type="file" id="import_file" name="import_file" accept=".html,.htm,.zip" required>
        <p class="form-hint">Single <code>.html</code> / <code>.htm</code> file, or a <code>.zip</code> containing multiple HTML files.</p>
      </div>

      <div class="form-group">
        <label>Import mode</label>
        <div class="radio-group">
          <label class="radio-option">
            <input type="radio" name="import_mode" value="file" checked>
            <span>
              <strong>File mode</strong> — store HTML as-is, serve raw (fastest, no editing)
            </span>
          </label>
          <label class="radio-option">
            <input type="radio" name="import_mode" value="cruinn">
            <span>
              <strong>Cruinn blocks</strong> — parse into blocks (headings, paragraphs, images become native blocks; everything else becomes HTML blocks)
            </span>
          </label>
        </div>
        <p class="form-hint import-mode-note">
          Cruinn-converted blocks are colour-coded in the editor:
          <span class="badge-native">green border</span> = Cruinn primitive,
          <span class="badge-imported">amber border</span> = imported HTML fragment.
        </p>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Upload &amp; Review</button>
        <a href="/admin/pages" class="btn btn-ghost">Cancel</a>
      </div>
    </form>
  </div>

  <div class="acp-card acp-card-info">
    <h3>What gets imported?</h3>
    <ul>
      <li>Each <code>.html</code>/<code>.htm</code> file in the zip becomes a new page.</li>
      <li>Filename (without extension) is used as the slug — you can edit it in the next step.</li>
      <li>The <code>&lt;title&gt;</code> tag (or first <code>&lt;h1&gt;</code>) becomes the page title.</li>
      <li>Files named <code>index</code> or <code>home</code> appear at the top of the review list.</li>
    </ul>
  </div>
</div>
