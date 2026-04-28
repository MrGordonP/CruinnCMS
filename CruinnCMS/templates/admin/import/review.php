<div class="acp-page">
  <div class="acp-page-header">
    <h1>Review Import</h1>
    <p class="acp-page-subtitle">
      Found <strong><?= count($pages) ?></strong> page<?= count($pages) !== 1 ? 's' : '' ?>.
      Mode: <span class="badge badge-<?= $mode === 'cruinn' ? 'green' : 'amber' ?>"><?= $this->escape($mode) ?></span>
      — Edit slugs/titles, uncheck pages you want to skip, then confirm.
    </p>
  </div>

  <form method="post" action="/admin/import/confirm" class="form-standard">
    <input type="hidden" name="csrf_token" value="<?= \Cruinn\CSRF::getToken() ?>">
    <input type="hidden" name="import_mode" value="<?= $this->escape($mode) ?>">

    <div class="acp-card">
      <table class="acp-table">
        <thead>
          <tr>
            <th style="width:3rem">Import</th>
            <th>Filename</th>
            <th>Title</th>
            <th>Slug <span class="form-hint" style="font-weight:normal">(URL path)</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pages as $i => $page): ?>
            <tr class="import-row" id="row-<?= $i ?>">
              <td>
                <input type="checkbox" name="pages[<?= $i ?>][import]" value="1" checked
                       data-toggle-target="row-<?= $i ?>" data-toggle-class="import-row-skip">
                <input type="hidden" name="pages[<?= $i ?>][skip]" value="0" id="skip-<?= $i ?>">
              </td>
              <td>
                <code><?= $this->escape($page['filename']) ?></code>
              </td>
              <td>
                <input type="text" name="pages[<?= $i ?>][title]"
                       value="<?= $this->escape($page['title']) ?>"
                       class="form-input" required>
              </td>
              <td>
                <div class="slug-field">
                  <span class="slug-prefix">/</span>
                  <input type="text" name="pages[<?= $i ?>][slug]"
                         value="<?= $this->escape($page['slug']) ?>"
                         class="form-input" pattern="[a-z0-9-]+" required>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Confirm Import</button>
      <a href="/admin/import" class="btn btn-ghost">Start Over</a>
    </div>
  </form>
</div>

<?php \Cruinn\Template::requireJs('import-review.js'); ?>
