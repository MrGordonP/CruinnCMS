<?php
\Cruinn\Template::requireCss('admin-acp.css');

$activeId = $subject['id'] ?? null;

// Build a parent→children map for tree rendering
$treeChildren = [];
$treeRoots    = [];
foreach ($allSubjects as $s) {
    if ($s['parent_id']) {
        $treeChildren[(int) $s['parent_id']][] = $s;
    } else {
        $treeRoots[] = $s;
    }
}

/**
 * Recursive tree node renderer.
 */
function renderSubjectTreeNode(array $node, array $childMap, ?int $activeId, int $depth = 0): void
{
    $id       = (int) $node['id'];
    $isActive = $id === $activeId;
    $hasKids  = !empty($childMap[$id]);
    $indent   = $depth * 14;
    ?>
    <a href="/admin/subjects/<?= $id ?>"
       class="sws-tree-item<?= $isActive ? ' sws-tree-item--active' : '' ?>"
       style="padding-left: <?= 10 + $indent ?>px">
        <?php if ($hasKids): ?>
            <span class="sws-tree-toggle"><?= $isActive ? '▾' : '▸' ?></span>
        <?php else: ?>
            <span class="sws-tree-toggle sws-tree-toggle--leaf">·</span>
        <?php endif; ?>
        <span class="sws-tree-code"><?= e($node['code']) ?></span>
        <span class="sws-tree-title"><?= e($node['title']) ?></span>
        <span class="sws-badge sws-badge--<?= e($node['status']) ?>"><?= e($node['status']) ?></span>
    </a>
    <?php
    if ($hasKids) {
        foreach ($childMap[$id] as $child) {
            renderSubjectTreeNode($child, $childMap, $activeId, $depth + 1);
        }
    }
}
?>
<style>
/* ── Subjects Workspace ──────────────────────────────────────────── */
.sws {
    display: flex;
    gap: 0;
    height: calc(100vh - 120px);
    min-height: 500px;
    border: 1px solid var(--color-border, #dee2e6);
    border-radius: 6px;
    overflow: hidden;
    background: #fff;
}

/* Left panel — tree */
.sws-left {
    width: 240px;
    flex-shrink: 0;
    border-right: 1px solid var(--color-border, #dee2e6);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.sws-left-header {
    padding: 0.6rem 0.75rem;
    font-size: 0.78rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--color-text-light, #6c757d);
    border-bottom: 1px solid var(--color-border, #dee2e6);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}
.sws-tree {
    overflow-y: auto;
    flex: 1;
    padding: 0.25rem 0;
}
.sws-tree-item {
    display: flex;
    align-items: baseline;
    gap: 0.3rem;
    padding: 0.3rem 0.75rem;
    font-size: 0.82rem;
    color: var(--color-text, #333);
    text-decoration: none;
    border-left: 3px solid transparent;
    line-height: 1.4;
}
.sws-tree-item:hover {
    background: var(--color-bg-light, #f8f9fa);
    text-decoration: none;
}
.sws-tree-item--active {
    background: #eef4fb;
    border-left-color: var(--color-primary, #4a90d9);
    color: var(--color-primary, #4a90d9);
}
.sws-tree-toggle {
    font-size: 0.7rem;
    width: 10px;
    flex-shrink: 0;
    color: var(--color-text-light, #6c757d);
}
.sws-tree-toggle--leaf { opacity: 0.3; }
.sws-tree-code {
    font-family: monospace;
    font-size: 0.75rem;
    color: var(--color-text-light, #6c757d);
    flex-shrink: 0;
}
.sws-tree-item--active .sws-tree-code { color: inherit; }
.sws-tree-title {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Middle panel — summary */
.sws-middle {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border-right: 1px solid var(--color-border, #dee2e6);
    min-width: 0;
}
.sws-middle-header {
    padding: 0.65rem 1rem;
    border-bottom: 1px solid var(--color-border, #dee2e6);
    flex-shrink: 0;
    background: var(--color-bg-light, #f8f9fa);
}
.sws-middle-header h2 {
    margin: 0 0 0.15rem;
    font-size: 1.1rem;
}
.sws-breadcrumb {
    display: flex;
    flex-wrap: wrap;
    gap: 0.2rem;
    align-items: center;
    font-size: 0.8rem;
    color: var(--color-text-light, #6c757d);
    margin-bottom: 0.35rem;
}
.sws-breadcrumb a { color: var(--color-primary, #4a90d9); text-decoration: none; }
.sws-breadcrumb a:hover { text-decoration: underline; }
.sws-breadcrumb-sep { opacity: 0.4; }
.sws-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
    font-size: 0.8rem;
    color: var(--color-text-light, #6c757d);
}
.sws-middle-body {
    overflow-y: auto;
    flex: 1;
    padding: 0.75rem 1rem;
}
.sws-empty {
    color: var(--color-text-light, #6c757d);
    font-size: 0.9rem;
    padding: 2rem 1rem;
    text-align: center;
}

/* Right panel — settings */
.sws-right {
    width: 280px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.sws-right-header {
    padding: 0.6rem 0.75rem;
    font-size: 0.78rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--color-text-light, #6c757d);
    border-bottom: 1px solid var(--color-border, #dee2e6);
    flex-shrink: 0;
    background: var(--color-bg-light, #f8f9fa);
}
.sws-right-body {
    overflow-y: auto;
    flex: 1;
    padding: 0.75rem;
}

/* Shared */
.sws-badge {
    font-size: 0.65rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding: 0.1rem 0.4rem;
    border-radius: 3px;
    flex-shrink: 0;
}
.sws-badge--active   { background: #d1fae5; color: #065f46; }
.sws-badge--draft    { background: #fef9c3; color: #713f12; }
.sws-badge--archived { background: #f3f4f6; color: #6b7280; }
.sws-badge--published { background: #dbeafe; color: #1e40af; }

.sws-section { margin-bottom: 1.25rem; }
.sws-section-title {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--color-text-light, #6c757d);
    margin: 0 0 0.4rem;
    padding-bottom: 0.25rem;
    border-bottom: 1px solid var(--color-border, #dee2e6);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.sws-section-title a {
    font-size: 0.7rem;
    font-weight: 400;
    text-transform: none;
    letter-spacing: 0;
    color: var(--color-primary, #4a90d9);
    text-decoration: none;
}
.sws-section-title a:hover { text-decoration: underline; }

.sws-item-list { list-style: none; margin: 0; padding: 0; }
.sws-item {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
    padding: 0.3rem 0;
    border-bottom: 1px solid var(--color-border-light, #f0f0f0);
    font-size: 0.83rem;
}
.sws-item:last-child { border-bottom: none; }
.sws-item-title { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sws-item-title a { color: var(--color-text, #333); text-decoration: none; }
.sws-item-title a:hover { text-decoration: underline; color: var(--color-primary, #4a90d9); }
.sws-item-meta { font-size: 0.75rem; color: var(--color-text-light, #6c757d); white-space: nowrap; flex-shrink: 0; }

.sws-child-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 0.4rem;
}

/* Discussion inline form */
.sws-link-btn {
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
    font-size: .7rem;
    font-weight: 400;
    color: var(--color-primary, #4a90d9);
}
.sws-link-btn:hover { text-decoration: underline; }
.sws-discussion-form {
    display: none;
    margin: .4rem 0 .5rem;
    padding: .6rem .75rem;
    background: var(--color-bg-light, #f8f9fa);
    border: 1px solid var(--color-border, #dee2e6);
    border-radius: 4px;
}
.sws-discussion-form.is-open { display: block; }
.sws-form-input {
    display: block;
    width: 100%;
    margin-bottom: .35rem;
    font-size: .83rem;
    padding: .3rem .5rem;
    border: 1px solid var(--color-border, #dee2e6);
    border-radius: 3px;
    box-sizing: border-box;
}
.sws-child-card {
    display: block;
    padding: 0.45rem 0.6rem;
    border: 1px solid var(--color-border, #dee2e6);
    border-radius: 4px;
    text-decoration: none;
    color: var(--color-text, #333);
    font-size: 0.82rem;
    transition: border-color 0.15s, background 0.15s;
}
.sws-child-card:hover {
    border-color: var(--color-primary, #4a90d9);
    background: #eef4fb;
    text-decoration: none;
}
.sws-child-card-code {
    font-family: monospace;
    font-size: 0.72rem;
    color: var(--color-text-light, #6c757d);
    display: block;
}
.sws-child-card-title { font-weight: 500; }

/* Settings form in right panel */
.sws-form .form-group { margin-bottom: 0.6rem; }
.sws-form label { font-size: 0.78rem; font-weight: 600; display: block; margin-bottom: 0.15rem; }
.sws-form .form-input {
    width: 100%;
    font-size: 0.82rem;
    padding: 0.3rem 0.45rem;
    border: 1px solid var(--color-border, #dee2e6);
    border-radius: 3px;
    box-sizing: border-box;
}
.sws-form textarea.form-input { resize: vertical; }
.sws-form .form-actions {
    padding-top: 0.5rem;
    display: flex;
    gap: 0.4rem;
    flex-wrap: wrap;
}

@media (max-width: 900px) {
    .sws { flex-direction: column; height: auto; }
    .sws-left  { width: 100%; height: 200px; border-right: none; border-bottom: 1px solid var(--color-border, #dee2e6); }
    .sws-right { width: 100%; border-left: none; border-top: 1px solid var(--color-border, #dee2e6); }
    .sws-middle { border-right: none; }
}
</style>

<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:.75rem">
    <h1 style="margin:0; font-size:1.4rem">Subjects</h1>
    <a href="/admin/subjects/new" class="btn btn-primary btn-small">+ New Subject</a>
</div>

<div class="sws">

    <!-- ── LEFT: Subject tree ── -->
    <div class="sws-left">
        <div class="sws-left-header">
            <span>Subjects</span>
            <span style="font-weight:400; font-size:0.75rem"><?= count($allSubjects) ?></span>
        </div>
        <div class="sws-tree">
            <?php if (empty($allSubjects)): ?>
                <div style="padding:.75rem; font-size:.82rem; color:#9ca3af">No subjects yet.</div>
            <?php else: ?>
                <?php foreach ($treeRoots as $root): ?>
                    <?php renderSubjectTreeNode($root, $treeChildren, $activeId); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── MIDDLE: Summary ── -->
    <div class="sws-middle">
        <?php if (!$subject): ?>
        <div class="sws-middle-header">
            <h2>Select a subject</h2>
        </div>
        <div class="sws-middle-body">
            <div class="sws-empty">Choose a subject from the tree to view its summary.</div>
        </div>

        <?php else: ?>
        <div class="sws-middle-header">
            <!-- Subject breadcrumb chain -->
            <?php if (!empty($ancestors)): ?>
            <div class="sws-breadcrumb">
                <a href="/admin/subjects">Subjects</a>
                <?php foreach ($ancestors as $anc): ?>
                    <span class="sws-breadcrumb-sep">›</span>
                    <a href="/admin/subjects/<?= (int) $anc['id'] ?>"><?= e($anc['title']) ?></a>
                <?php endforeach; ?>
                <span class="sws-breadcrumb-sep">›</span>
                <strong><?= e($subject['title']) ?></strong>
            </div>
            <?php else: ?>
            <div class="sws-breadcrumb">
                <a href="/admin/subjects">Subjects</a>
                <span class="sws-breadcrumb-sep">›</span>
                <strong><?= e($subject['title']) ?></strong>
            </div>
            <?php endif; ?>

            <h2><?= e($subject['title']) ?></h2>
            <div class="sws-meta">
                <code style="font-size:.78rem"><?= e($subject['code']) ?></code>
                <span class="sws-badge sws-badge--<?= e($subject['status']) ?>"><?= e($subject['status']) ?></span>
                <span><?= e(ucfirst($subject['type'])) ?></span>
                <?php if ($subject['starts_at'] || $subject['ends_at']): ?>
                    <span><?= $subject['starts_at'] ? format_date($subject['starts_at'], 'j M Y') : '?' ?>
                    <?= ($subject['starts_at'] && $subject['ends_at']) ? '–' : '' ?>
                    <?= $subject['ends_at'] ? format_date($subject['ends_at'], 'j M Y') : '' ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($subject['description'])): ?>
            <p style="margin:.5rem 0 0; font-size:.83rem; color:var(--color-text-light,#6c757d)"><?= e($subject['description']) ?></p>
            <?php endif; ?>
        </div>
        <div class="sws-middle-body">

            <!-- Child subjects (collapsible) -->
            <?php if (!empty($children)): ?>
            <div class="sws-section">
                <details open>
                    <summary class="sws-section-title" style="cursor:pointer; list-style:none">
                        <span>Sub-Subjects (<?= count($children) ?>)</span>
                    </summary>
                    <div class="sws-child-grid" style="margin-top:.25rem">
                        <?php foreach ($children as $child): ?>
                        <a href="/admin/subjects/<?= (int) $child['id'] ?>" class="sws-child-card">
                            <span class="sws-child-card-code"><?= e($child['code']) ?></span>
                            <span class="sws-child-card-title"><?= e($child['title']) ?></span>
                            <span class="sws-badge sws-badge--<?= e($child['status']) ?>" style="margin-top:.2rem"><?= e($child['status']) ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </details>
            </div>
            <?php endif; ?>

            <!-- Articles -->
            <?php if (!empty($articles)): ?>
            <div class="sws-section">
                <div class="sws-section-title">
                    <span>Articles (<?= count($articles) ?>)</span>
                    <a href="/admin/blog/new?subject_id=<?= (int) $subject['id'] ?>">+ New</a>
                </div>
                <ul class="sws-item-list">
                    <?php foreach ($articles as $a): ?>
                    <li class="sws-item">
                        <span class="sws-item-title">
                            <a href="/admin/blog/<?= (int) $a['id'] ?>/edit"><?= e($a['title']) ?></a>
                        </span>
                        <span class="sws-badge sws-badge--<?= e($a['status']) ?>"><?= e($a['status']) ?></span>
                        <span class="sws-item-meta"><?= format_date($a['created_at'], 'j M Y') ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php elseif (isset($articles)): ?>
            <div class="sws-section">
                <div class="sws-section-title"><span>Articles</span><a href="/admin/blog/new?subject_id=<?= (int) $subject['id'] ?>">+ New</a></div>
                <p style="font-size:.82rem; color:#9ca3af; margin:.25rem 0">None yet.</p>
            </div>
            <?php endif; ?>

            <!-- Events -->
            <?php if (!empty($events)): ?>
            <div class="sws-section">
                <div class="sws-section-title">
                    <span>Events (<?= count($events) ?>)</span>
                    <a href="/admin/events/new?subject_id=<?= (int) $subject['id'] ?>">+ New</a>
                </div>
                <ul class="sws-item-list">
                    <?php foreach ($events as $ev): ?>
                    <li class="sws-item">
                        <span class="sws-item-title">
                            <a href="/admin/events/<?= (int) $ev['id'] ?>/edit"><?= e($ev['title']) ?></a>
                        </span>
                        <span class="sws-badge sws-badge--<?= e($ev['status']) ?>"><?= e($ev['status']) ?></span>
                        <span class="sws-item-meta"><?= $ev['date_start'] ? format_date($ev['date_start'], 'j M Y') : '—' ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php elseif (isset($events)): ?>
            <div class="sws-section">
                <div class="sws-section-title"><span>Events</span><a href="/admin/events/new?subject_id=<?= (int) $subject['id'] ?>">+ New</a></div>
                <p style="font-size:.82rem; color:#9ca3af; margin:.25rem 0">None yet.</p>
            </div>
            <?php endif; ?>

            <!-- Files / Documents -->
            <?php if (!empty($files)): ?>
            <div class="sws-section">
                <div class="sws-section-title">
                    <span>Files (<?= count($files) ?>)</span>
                    <a href="/admin/drivespace?subject_id=<?= (int) $subject['id'] ?>">View in DriveSpace</a>
                </div>
                <ul class="sws-item-list">
                    <?php foreach ($files as $f): ?>
                    <li class="sws-item">
                        <span class="sws-item-title"><?= e($f['name']) ?></span>
                        <span class="sws-item-meta"><?= e($f['mime_type'] ?? '') ?></span>
                        <span class="sws-item-meta"><?= format_date($f['created_at'], 'j M Y') ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php elseif (isset($files)): ?>
            <div class="sws-section">
                <div class="sws-section-title"><span>Files</span><a href="/admin/drivespace">DriveSpace</a></div>
                <p style="font-size:.82rem; color:#9ca3af; margin:.25rem 0">None yet.</p>
            </div>
            <?php endif; ?>

            <!-- Folders -->
            <?php if (!empty($folders)): ?>
            <div class="sws-section">
                <div class="sws-section-title"><span>Folders (<?= count($folders) ?>)</span></div>
                <ul class="sws-item-list">
                    <?php foreach ($folders as $fo): ?>
                    <li class="sws-item">
                        <span class="sws-item-title"><?= e($fo['name']) ?></span>
                        <span class="sws-item-meta"><?= format_date($fo['created_at'], 'j M Y') ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Discussions -->
            <div class="sws-section">
                <div class="sws-section-title">
                    <span>Discussions (<?= count($discussions ?? []) ?>)</span>
                    <button type="button" class="sws-link-btn"
                            onclick="this.closest('.sws-section').querySelector('.sws-discussion-form').classList.toggle('is-open')">+ New</button>
                </div>
                <form method="post" action="/admin/subjects/<?= (int) $subject['id'] ?>/discussion"
                      class="sws-discussion-form">
                    <?= csrf_field() ?>
                    <input type="text" name="title" class="sws-form-input" placeholder="Discussion title" required>
                    <textarea name="body" class="sws-form-input" placeholder="Opening post (optional)" rows="3"></textarea>
                    <div style="display:flex; gap:.4rem; margin-top:.3rem">
                        <button type="submit" class="btn btn-primary btn-small">Create</button>
                        <button type="button" class="btn btn-small btn-outline"
                                onclick="this.closest('.sws-discussion-form').classList.remove('is-open')">Cancel</button>
                    </div>
                </form>
                <?php if (!empty($discussions)): ?>
                <ul class="sws-item-list">
                    <?php foreach ($discussions as $disc): ?>
                    <li class="sws-item">
                        <span class="sws-item-title"><a href="/organisation/discussions/<?= (int) $disc['id'] ?>"><?= e($disc['title']) ?></a></span>
                        <?php if ($disc['pinned']): ?><span class="sws-badge sws-badge--active">pinned</span><?php endif; ?>
                        <?php if ($disc['locked']): ?><span class="sws-badge sws-badge--archived">locked</span><?php endif; ?>
                        <span class="sws-item-meta"><?= (int) $disc['post_count'] ?> posts</span>
                        <span class="sws-item-meta"><?= format_date($disc['last_post_at'] ?? $disc['created_at'], 'j M') ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <p style="font-size:.82rem; color:#9ca3af; margin:.25rem 0">No discussions yet.</p>
                <?php endif; ?>
            </div>

            <!-- Forum Thread -->
            <div class="sws-section">
                <div class="sws-section-title"><span>Forum Thread</span></div>
                <?php if (!empty($forumThread)): ?>
                <div style="font-size:.83rem; padding:.3rem 0">
                    <a href="/forum/thread/<?= (int) $forumThread['id'] ?>" style="font-weight:600"><?= e($forumThread['title']) ?></a>
                    <div style="color:var(--color-text-light,#6c757d); font-size:.76rem; margin-top:.15rem">
                        <?= (int) $forumThread['reply_count'] ?> repl<?= $forumThread['reply_count'] == 1 ? 'y' : 'ies' ?>
                        &nbsp;&middot;&nbsp; <?= e($forumThread['category_title']) ?>
                        <?php if ($forumThread['last_post_at']): ?>
                        &nbsp;&middot;&nbsp; Last post <?= format_date($forumThread['last_post_at'], 'j M Y') ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <p style="font-size:.82rem; color:#9ca3af; margin:.25rem 0">No forum thread provisioned.</p>
                <form method="post" action="/admin/subjects/<?= (int) $subject['id'] ?>/forum-thread" style="margin-top:.4rem">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-small btn-outline">Provision Forum Thread</button>
                </form>
                <?php endif; ?>
            </div>

        </div><!-- /sws-middle-body -->
        <?php endif; ?>
    </div><!-- /sws-middle -->

    <!-- ── RIGHT: Settings ── -->
    <div class="sws-right">
        <div class="sws-right-header">
            <?php if ($subject): ?>Settings<?php else: ?>New Subject<?php endif; ?>
        </div>
        <div class="sws-right-body">

        <?php if (!$subject): ?>
            <p style="font-size:.82rem; color:#9ca3af">Select a subject to edit its settings, or <a href="/admin/subjects/new">create a new one</a>.</p>

        <?php else: ?>
            <form method="post" action="/admin/subjects/<?= (int) $subject['id'] ?>" class="sws-form">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="sws-code">Code</label>
                    <input type="text" id="sws-code" name="code" required
                           value="<?= e($subject['code']) ?>" class="form-input">
                </div>

                <div class="form-group">
                    <label for="sws-title">Title</label>
                    <input type="text" id="sws-title" name="title" required
                           value="<?= e($subject['title']) ?>" class="form-input">
                </div>

                <div class="form-group">
                    <label for="sws-slug">Slug</label>
                    <input type="text" id="sws-slug" name="slug"
                           value="<?= e($subject['slug']) ?>" class="form-input">
                </div>

                <div class="form-group">
                    <label for="sws-type">Type</label>
                    <select id="sws-type" name="type" class="form-input">
                        <?php foreach (['general','series','event','news','campaign','project'] as $t): ?>
                        <option value="<?= $t ?>" <?= $subject['type'] === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="sws-status">Status</label>
                    <select id="sws-status" name="status" class="form-input">
                        <?php foreach (['draft','active','archived'] as $s): ?>
                        <option value="<?= $s ?>" <?= $subject['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="sws-parent">Parent Subject</label>
                    <select id="sws-parent" name="parent_id" class="form-input">
                        <option value="">— None —</option>
                        <?php foreach ($parentSubjects as $ps): ?>
                        <option value="<?= (int) $ps['id'] ?>"
                            <?= ($subject['parent_id'] ?? '') == $ps['id'] ? 'selected' : '' ?>>
                            <?= e($ps['code']) ?> — <?= e($ps['title']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="sws-desc">Description</label>
                    <textarea id="sws-desc" name="description" rows="3"
                              class="form-input"><?= e($subject['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="sws-starts">Starts</label>
                    <input type="datetime-local" id="sws-starts" name="starts_at"
                           value="<?= e(!empty($subject['starts_at']) ? date('Y-m-d\TH:i', strtotime($subject['starts_at'])) : '') ?>"
                           class="form-input">
                </div>

                <div class="form-group">
                    <label for="sws-ends">Ends</label>
                    <input type="datetime-local" id="sws-ends" name="ends_at"
                           value="<?= e(!empty($subject['ends_at']) ? date('Y-m-d\TH:i', strtotime($subject['ends_at'])) : '') ?>"
                           class="form-input">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-small">Save</button>
                    <a href="/admin/subjects/<?= (int) $subject['id'] ?>/edit" class="btn btn-small btn-outline">Full Edit</a>
                </div>
            </form>

            <hr style="margin:1rem 0; border:none; border-top:1px solid var(--color-border,#dee2e6)">

            <form method="post" action="/admin/subjects/<?= (int) $subject['id'] ?>/delete"
                  onsubmit="return confirm('Delete this subject? This cannot be undone.')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-small btn-danger" style="width:100%">Delete Subject</button>
            </form>
        <?php endif; ?>

        </div>
    </div><!-- /sws-right -->

</div><!-- /sws -->
