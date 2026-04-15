<div class="container">
    <div class="directory-page">
        <h1>Member Directory</h1>
        <p class="directory-intro">Browse registered members.</p>

        <!-- Search -->
        <div class="search-bar">
            <form method="get" action="/directory" class="search-form">
                <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search members by name or institute…" class="form-input search-input">
                <button type="submit" class="btn btn-primary btn-small">Search</button>
                <?php if ($search !== ''): ?>
                <a href="/directory" class="btn btn-outline btn-small">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($totalCount > 0): ?>
        <p class="results-count"><?= number_format($totalCount) ?> member<?= $totalCount !== 1 ? 's' : '' ?> found</p>
        <?php endif; ?>

        <?php if (empty($members)): ?>
        <div class="block-empty">
            <p>No members found<?= $search !== '' ? ' matching your search' : ' in the directory' ?>.</p>
        </div>
        <?php else: ?>

        <div class="member-grid">
            <?php foreach ($members as $m): ?>
            <div class="member-card">
                <div class="member-card-avatar">
                    <?= strtoupper(mb_substr($m['forenames'], 0, 1) . mb_substr($m['surnames'], 0, 1)) ?>
                </div>
                <div class="member-card-info">
                    <h3><?= e($m['forenames'] . ' ' . $m['surnames']) ?></h3>
                    <?php if (!empty($m['institute'])): ?>
                    <p class="member-card-institute"><?= e($m['institute']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($m['type_name'])): ?>
                    <span class="badge badge-member-type"><?= e($m['type_name']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="/directory?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>" class="btn btn-small btn-outline">&laquo; Previous</a>
            <?php endif; ?>

            <span class="pagination-info">Page <?= $page ?> of <?= $totalPages ?></span>

            <?php if ($page < $totalPages): ?>
                <a href="/directory?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>" class="btn btn-small btn-outline">Next &raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div>
