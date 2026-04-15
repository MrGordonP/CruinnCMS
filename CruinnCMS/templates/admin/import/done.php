<div class="acp-page">
  <div class="acp-page-header">
    <h1>Import Complete</h1>
  </div>

  <div class="acp-card">
    <p>
      <strong><?= count($imported) ?></strong> page<?= count($imported) !== 1 ? 's' : '' ?> imported.
      <?php if ($skipped > 0): ?>
        <strong><?= $skipped ?></strong> skipped.
      <?php endif; ?>
    </p>

    <?php if (!empty($imported)): ?>
      <table class="acp-table">
        <thead>
          <tr>
            <th>Title</th>
            <th>Slug</th>
            <th>Mode</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($imported as $p): ?>
            <tr>
              <td><?= $this->escape($p['title']) ?></td>
              <td><code>/<?= $this->escape($p['slug']) ?></code></td>
              <td>
                <span class="badge badge-<?= $p['mode'] === 'cruinn' ? 'green' : 'amber' ?>">
                  <?= $this->escape($p['mode']) ?>
                </span>
              </td>
              <td>
                <a href="/<?= $this->escape($p['slug']) ?>" target="_blank" class="btn btn-xs btn-ghost">View</a>
                <?php if ($p['mode'] === 'cruinn'): ?>
                  <a href="/admin/pages/<?= $p['id'] ?>/edit" class="btn btn-xs btn-ghost">Edit in Cruinn</a>
                <?php else: ?>
                  <a href="/admin/pages/<?= $p['id'] ?>/html" class="btn btn-xs btn-ghost">Edit HTML</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="form-actions">
    <a href="/admin/pages" class="btn btn-primary">Go to Pages</a>
    <a href="/admin/import" class="btn btn-ghost">Import More</a>
  </div>
</div>
