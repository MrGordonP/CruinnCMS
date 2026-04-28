<?php \Cruinn\Template::requireCss('admin-acp.css'); ?>

<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
    <div>
        <h1 style="margin:0;">Import Members</h1>
        <p class="text-muted" style="margin:0.25rem 0 0;">Upload a CSV file to import or update membership records. You will map your columns to the correct fields before the import runs.</p>
    </div>
    <a class="btn btn-outline" href="<?= url('/admin/membership') ?>">Back to Members</a>
</div>

<?php if (!empty($result)): ?>
<?php $r = $result; $hasErrors = !empty($r['errors']); ?>
<div class="card" style="border:1px solid <?= $hasErrors ? '#fca5a5' : '#6ee7b7' ?>;border-radius:8px;background:<?= $hasErrors ? '#fef2f2' : '#f0fdf4' ?>;padding:1.25rem;margin-bottom:1.5rem;">
    <h2 style="margin:0 0 0.75rem;font-size:1.1rem;">Import Complete</h2>
    <div style="display:flex;gap:1.5rem;flex-wrap:wrap;margin-bottom:<?= $hasErrors ? '1rem' : '0' ?>;">
        <div><strong><?= (int) $r['created'] ?></strong> <span class="text-muted">created</span></div>
        <div><strong><?= (int) $r['updated'] ?></strong> <span class="text-muted">updated</span></div>
        <div><strong><?= (int) $r['skipped'] ?></strong> <span class="text-muted">skipped (duplicate)</span></div>
        <div><strong><?= count($r['errors']) ?></strong> <span class="text-muted">errors</span></div>
    </div>
    <?php if ($hasErrors): ?>
    <details style="margin-top:0.5rem;">
        <summary style="cursor:pointer;font-weight:600;">Show errors</summary>
        <ul style="margin:0.75rem 0 0;padding-left:1.25rem;">
            <?php foreach ($r['errors'] as $err): ?>
            <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </details>
    <?php endif; ?>
</div>
<?php endif; ?>

<form method="post" action="<?= url('/admin/membership/import') ?>" enctype="multipart/form-data"
      class="card" style="border:1px solid #e5e7eb;border-radius:8px;background:#fff;padding:1.25rem;display:grid;gap:1rem;max-width:640px;">
    <?= csrf_field() ?>

    <div>
        <label class="form-label" for="csv_file">CSV File <span style="color:#ef4444;">*</span></label>
        <input class="form-input" type="file" id="csv_file" name="csv_file" accept=".csv,text/csv" required>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
        <div>
            <label class="form-label" for="on_duplicate">If email already exists</label>
            <select class="form-input" id="on_duplicate" name="on_duplicate">
                <option value="skip">Skip (keep existing)</option>
                <option value="update">Update existing record</option>
            </select>
        </div>
        <div>
            <label class="form-label" for="default_status">Default status (if column absent)</label>
            <select class="form-input" id="default_status" name="default_status">
                <?php foreach (['applicant','active','lapsed','suspended','resigned','archived'] as $s): ?>
                <option value="<?= e($s) ?>"><?= e(ucfirst($s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div>
        <button class="btn btn-primary" type="submit">Upload &amp; Map Columns</button>
    </div>
</form>
