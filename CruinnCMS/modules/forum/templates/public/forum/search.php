<section class="container forum-page">
    <header class="forum-header">
        <nav class="forum-breadcrumbs">
            <a href="<?= url('/forum') ?>">Forum</a>
            <span class="sep">›</span>
            <span class="current">Search</span>
        </nav>
        <h1>Forum Search</h1>
    </header>

    <form method="get" action="<?= url('/forum/search') ?>" class="forum-search-form">
        <div class="forum-search-fields">
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search posts and threads…" class="forum-search-input" autofocus>
            <select name="category_id" class="form-input forum-search-cat">
                <option value="0">All categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>" <?= (int)$categoryId === (int)$cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Search</button>
        </div>
    </form>

    <?php if ($q !== ''): ?>
        <p class="forum-search-count">
            <?= $total > 0 ? $total . ' result' . ($total !== 1 ? 's' : '') . ' for' : 'No results for' ?>
            <strong><?= e($q) ?></strong>
        </p>
    <?php endif; ?>

    <?php if (!empty($results)): ?>
        <div class="forum-search-results">
            <?php foreach ($results as $r): ?>
                <article class="forum-search-result">
                    <div class="forum-search-result__thread">
                        <a href="<?= url('/forum/thread/' . (int)$r['thread_id'] . '#post-' . (int)$r['post_id']) ?>">
                            <?= e($r['thread_title']) ?>
                        </a>
                        <span class="forum-meta">in <?= e($r['category_title']) ?></span>
                    </div>
                    <div class="forum-search-result__excerpt">
                        <?= e(mb_substr(strip_tags($r['body_html']), 0, 200)) ?>…
                    </div>
                    <div class="forum-meta">
                        By <?= e($r['author_name']) ?> &bull; <?= e(format_date($r['created_at'], 'j M Y H:i')) ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
