<?php \Cruinn\Template::requireCss('admin-forms.css'); ?>
<div class="admin-submissions-list">
    <div class="admin-list-header">
        <h1>Submissions: <?= e($form['title']) ?></h1>
        <div>
            <a href="/admin/forms/<?= (int)$form['id'] ?>/export" class="btn">Export CSV</a>
            <a href="/admin/forms/<?= (int)$form['id'] ?>/edit" class="btn">Edit Form</a>
        </div>
    </div>

    <!-- Filters -->
    <form method="get" class="admin-filters">
        <select name="status" class="form-input form-input-inline" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <option value="pending"  <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="approved" <?= ($filters['status'] ?? '') === 'approved' ? 'selected' : '' ?>>Approved</option>
            <option value="rejected" <?= ($filters['status'] ?? '') === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            <option value="processed" <?= ($filters['status'] ?? '') === 'processed' ? 'selected' : '' ?>>Processed</option>
        </select>
        <input type="text" name="search" value="<?= e($filters['search'] ?? '') ?>"
               placeholder="Search submissions…" class="form-input form-input-inline">
        <button type="submit" class="btn btn-small">Filter</button>
    </form>

    <p class="text-muted"><?= (int)$total ?> submission<?= $total !== 1 ? 's' : '' ?> found.</p>

    <?php if (empty($submissions)): ?>
        <p class="admin-empty">No submissions match your criteria.</p>
    <?php else: ?>

    <?php
        // Pick the first few data fields for the table preview
        $previewFields = array_filter($form['fields'] ?? [], fn($f) => !in_array($f['field_type'], ['heading', 'paragraph', 'hidden']));
        $previewFields = array_slice($previewFields, 0, 4);
    ?>

    <table class="admin-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <?php foreach ($previewFields as $pf): ?>
                    <th><?= e($pf['label']) ?></th>
                <?php endforeach; ?>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($submissions as $s): ?>
            <?php
                $statusClass = match($s['status']) {
                    'approved', 'processed' => 'badge-success',
                    'rejected'              => 'badge-danger',
                    default                 => 'badge-warning',
                };
            ?>
            <tr>
                <td><?= (int)$s['id'] ?></td>
                <td><?= format_date($s['submitted_at'], 'j M Y H:i') ?></td>
                <?php foreach ($previewFields as $pf): ?>
                    <td>
                        <?php
                            $val = $s['data'][$pf['name']] ?? '';
                            if (is_array($val)) $val = implode(', ', $val);
                            if (is_bool($val)) $val = $val ? 'Yes' : 'No';
                            echo e(mb_strimwidth((string)$val, 0, 60, '…'));
                        ?>
                    </td>
                <?php endforeach; ?>
                <td><span class="badge <?= $statusClass ?>"><?= e(ucfirst($s['status'])) ?></span></td>
                <td>
                    <a href="/admin/forms/<?= (int)$form['id'] ?>/submissions/<?= (int)$s['id'] ?>" class="btn btn-small">View</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php
        $totalPages = (int) ceil($total / $perPage);
        if ($totalPages > 1):
    ?>
    <div class="pagination">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <?php
                $queryParams = $filters;
                $queryParams['page'] = $p;
                $qs = http_build_query($queryParams);
            ?>
            <?php if ($p === $page): ?>
                <span class="pagination-current"><?= $p ?></span>
            <?php else: ?>
                <a href="?<?= e($qs) ?>" class="btn btn-small"><?= $p ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>
