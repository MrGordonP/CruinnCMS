<?php \Cruinn\Template::requireCss('admin-acp.css'); ?>

<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
    <div>
        <h1 style="margin:0;">Import Members — Map Columns</h1>
        <p class="text-muted" style="margin:0.25rem 0 0;">
            Match your CSV columns to the correct fields. Unmapped columns will be ignored.
            <strong><?= (int) $totalRows ?></strong> data row<?= $totalRows !== 1 ? 's' : '' ?> found.
        </p>
    </div>
    <a class="btn btn-outline" href="<?= url('/admin/membership/import') ?>">Start Over</a>
</div>

<?php if (!empty($preview)): ?>
<details class="card" style="border:1px solid #e5e7eb;border-radius:8px;background:#fff;padding:1.25rem;margin-bottom:1.5rem;" open>
    <summary style="cursor:pointer;font-weight:600;font-size:0.95rem;">Preview (first <?= count($preview) ?> row<?= count($preview) !== 1 ? 's' : '' ?>)</summary>
    <div style="overflow-x:auto;margin-top:1rem;">
        <table style="border-collapse:collapse;font-size:0.8rem;width:100%;white-space:nowrap;">
            <thead>
                <tr style="background:#f9fafb;">
                    <?php foreach ($csvHeaders as $h): ?>
                    <th style="text-align:left;padding:0.4rem 0.75rem;border:1px solid #e5e7eb;"><?= e($h) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($preview as $row): ?>
                <tr>
                    <?php foreach ($csvHeaders as $h): ?>
                    <td style="padding:0.4rem 0.75rem;border:1px solid #e5e7eb;color:#374151;max-width:200px;overflow:hidden;text-overflow:ellipsis;"><?= e($row[$h] ?? '') ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</details>
<?php endif; ?>

<form method="post" action="<?= url('/admin/membership/import/confirm') ?>">
    <?= csrf_field() ?>

    <div class="card" style="border:1px solid #e5e7eb;border-radius:8px;background:#fff;padding:1.25rem;margin-bottom:1.5rem;">
        <h2 style="margin:0 0 1rem;font-size:1rem;">Column Mapping</h2>
        <table style="border-collapse:collapse;width:100%;font-size:0.875rem;">
            <thead>
                <tr style="background:#f9fafb;">
                    <th style="text-align:left;padding:0.5rem 0.75rem;border:1px solid #e5e7eb;width:40%;">System field</th>
                    <th style="text-align:left;padding:0.5rem 0.75rem;border:1px solid #e5e7eb;">CSV column</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($systemFields as $fieldKey => $fieldLabel): ?>
                <tr>
                    <td style="padding:0.5rem 0.75rem;border:1px solid #e5e7eb;font-weight:<?= str_ends_with($fieldLabel, '*') ? '600' : 'normal' ?>;">
                        <?= e($fieldLabel) ?>
                    </td>
                    <td style="padding:0.4rem 0.75rem;border:1px solid #e5e7eb;">
                        <select name="map[<?= e($fieldKey) ?>]" class="form-input" style="width:100%;max-width:360px;">
                            <option value="">— ignore —</option>
                            <?php foreach ($csvHeaders as $csvHeader): ?>
                            <option value="<?= e($csvHeader) ?>"<?= ($autoMapping[$fieldKey] ?? '') === $csvHeader ? ' selected' : '' ?>><?= e($csvHeader) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card" style="border:1px solid #e5e7eb;border-radius:8px;background:#fff;padding:1.25rem;margin-bottom:1.5rem;display:grid;grid-template-columns:1fr 1fr;gap:1rem;max-width:640px;">
        <div>
            <label class="form-label">If email already exists</label>
            <p class="text-muted" style="margin:0;font-size:0.875rem;"><?= $onDuplicate === 'update' ? 'Update existing record' : 'Skip (keep existing)' ?></p>
        </div>
        <div>
            <label class="form-label">Default status (if column absent)</label>
            <p class="text-muted" style="margin:0;font-size:0.875rem;"><?= e(ucfirst($defaultStatus)) ?></p>
        </div>
    </div>

    <div style="display:flex;gap:0.75rem;">
        <button class="btn btn-primary" type="submit">Run Import</button>
        <a class="btn btn-outline" href="<?= url('/admin/membership/import') ?>">Cancel</a>
    </div>
</form>
