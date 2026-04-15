<?php include __DIR__ . '/_tabs.php'; ?>
<?php \Cruinn\Template::requireCss('admin-site-builder.css'); ?>

<h2>PHP Templates</h2>
<p class="sb-subtitle sb-full-width">View and edit the PHP view files that render public-facing pages, member areas, council workspace, and shared components.</p>

<?php
$groupLabels = [
    'root'          => 'Root',
    'public'        => 'Public Pages',
    'components'    => 'Components',
    'council'       => 'Council Workspace',
    'errors'        => 'Error Pages',
];
?>

<?php foreach ($groups as $group => $files): ?>
<div class="sb-full-width" style="margin-bottom: var(--space-lg)">
    <h3><?= e($groupLabels[$group] ?? ucfirst($group)) ?></h3>
    <table class="admin-table sb-table sb-full-width">
        <thead>
            <tr>
                <th>File</th>
                <th>Path</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($files as $rel): ?>
            <?php $name = basename($rel); ?>
            <tr>
                <td><strong><?= e($name) ?></strong></td>
                <td><code class="text-muted">templates/<?= e($rel) ?></code></td>
                <td>
                    <a href="<?= url('/admin/template-editor/edit?f=' . rawurlencode($rel)) ?>" class="btn btn-small">Edit</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endforeach; ?>

<?php include __DIR__ . '/_tabs_close.php'; ?>
