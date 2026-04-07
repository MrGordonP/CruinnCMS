<?php include __DIR__ . '/_tabs.php'; ?>
<?php \Cruinn\Template::requireCss('admin-site-builder.css'); ?>

<h2>Site Structure</h2>

<p class="sb-subtitle sb-full-width">Overview of your site's pages, templates, and navigation structure.</p>

<?php
$published = array_filter($pages, fn($p) => $p['status'] === 'published');
$drafts    = array_filter($pages, fn($p) => $p['status'] === 'draft');
$archived  = array_filter($pages, fn($p) => $p['status'] === 'archived');
?>

<h3>📄 Published Pages</h3>
<?php if (empty($published)): ?>
    <p class="text-muted">No published pages.</p>
<?php else: ?>
<table class="admin-table sb-table sb-full-width">
    <thead>
        <tr>
            <th>Title</th>
            <th>URL</th>
            <th>Template</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($published as $pg): ?>
        <tr>
            <td><a href="<?= url('/admin/editor/' . (int)$pg['id'] . '/edit') ?>"><?= e($pg['title']) ?></a></td>
            <td><code>/<?= e($pg['slug']) ?></code></td>
            <td><?= e(ucfirst($pg['template'] ?? 'default')) ?></td>
            <td><?php if ($pg['slug'] === 'home'): ?><span class="badge badge-info">Homepage</span><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if (!empty($drafts)): ?>
<h3>📝 Draft Pages</h3>
<table class="admin-table sb-table sb-full-width">
    <thead>
        <tr>
            <th>Title</th>
            <th>URL</th>
            <th>Template</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($drafts as $pg): ?>
        <tr>
            <td><a href="<?= url('/admin/editor/' . (int)$pg['id'] . '/edit') ?>"><?= e($pg['title']) ?></a></td>
            <td><code>/<?= e($pg['slug']) ?></code></td>
            <td><?= e(ucfirst($pg['template'] ?? 'default')) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if (!empty($archived)): ?>
<h3>📦 Archived Pages</h3>
<table class="admin-table sb-table sb-full-width">
    <thead>
        <tr>
            <th>Title</th>
            <th>URL</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($archived as $pg): ?>
        <tr>
            <td><a href="<?= url('/admin/editor/' . (int)$pg['id'] . '/edit') ?>"><?= e($pg['title']) ?></a></td>
            <td><code>/<?= e($pg['slug']) ?></code></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<h3>☰ Navigation Menus</h3>
<?php if (empty($menus)): ?>
    <p class="text-muted">No menus configured.</p>
<?php else: ?>
<table class="admin-table sb-table sb-full-width">
    <thead>
        <tr>
            <th>Name</th>
            <th>Location</th>
            <th>Items</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($menus as $menu): ?>
        <tr>
            <td><a href="<?= url('/admin/menus/' . (int)$menu['id'] . '/edit') ?>"><?= e($menu['name']) ?></a></td>
            <td><?= e($menu['location']) ?></td>
            <td><?= (int)$menu['item_count'] ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<h3>📐 Available Templates</h3>
<table class="admin-table sb-table sb-full-width">
    <thead>
        <tr>
            <th>Name</th>
            <th>Slug</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($templates as $tpl): ?>
        <tr>
            <td><strong><?= e($tpl['name']) ?></strong></td>
            <td><code><?= e($tpl['slug']) ?></code></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="sb-info-box">
    <h3>📊 Overview</h3>
    <p><?= count($pages) ?> total pages (<?= count($published) ?> published, <?= count($drafts) ?> drafts) · <?= count($menus) ?> menus · <?= count($templates) ?> templates</p>
</div>

<?php include __DIR__ . '/_tabs_close.php'; ?>
