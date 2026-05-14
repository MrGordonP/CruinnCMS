<?php /** @var array $seeds */ ?>
<div class="admin-page-header">
    <h1>Theme Seeds</h1>
    <p class="admin-page-desc">A theme seed applies structural defaults (zone canvases, page templates, seed blocks) to this instance. Seeds are idempotent — safe to re-run.</p>
</div>

<?php if (empty($seeds)): ?>
<div class="notice notice-info">No theme seed files found in <code>themes/*/seed.sql</code>.</div>
<?php else: ?>
<table class="admin-table">
    <thead>
        <tr>
            <th>Theme</th>
            <th>Status</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($seeds as $seed): ?>
        <tr>
            <td><strong><?= e($seed['slug']) ?></strong></td>
            <td>
                <?php if ($seed['applied']): ?>
                <span class="badge badge-success">Applied</span>
                <?php else: ?>
                <span class="badge badge-warning">Not applied</span>
                <?php endif; ?>
            </td>
            <td>
                <form method="post" action="/admin/theme/apply-seed" onsubmit="return confirm('Apply seed for theme \'<?= e($seed['slug']) ?>\'?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="theme" value="<?= e($seed['slug']) ?>">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <?= $seed['applied'] ? 'Re-apply seed' : 'Apply seed' ?>
                    </button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<p style="margin-top:1.5rem;"><a href="/admin/theme" class="btn btn-secondary btn-sm">← Theme Editor</a></p>
