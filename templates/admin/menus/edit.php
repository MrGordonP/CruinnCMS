<?php $acpLayout = $_SESSION['acp_layout'] ?? '1'; ?>
<?php \Cruinn\Template::requireCss('admin-menus.css'); ?>
<div class="admin-menu-edit">
    <div style="display:flex; align-items:center; justify-content:space-between">
        <a href="/admin/menus" class="btn btn-outline btn-small" style="margin-right:auto">&larr; Menus</a>
        <h1><?= e($title) ?></h1>
        <div class="acp-layout-toggle">
            <button class="acp-layout-btn <?= $acpLayout === '1' ? 'active' : '' ?>" data-layout="1" title="Single column">☐</button>
            <button class="acp-layout-btn <?= $acpLayout === '2' ? 'active' : '' ?>" data-layout="2" title="Two columns">☐☐</button>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="flash flash-error" role="alert">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Menu Metadata Form -->
    <form method="post" action="<?= $menu && isset($menu['id']) ? '/admin/menus/' . (int)$menu['id'] : '/admin/menus' ?>" class="form-menu-meta">
        <?= csrf_field() ?>

        <div class="form-row">
            <div class="form-group form-group-wide">
                <label for="name">Menu Name <span class="required">*</span></label>
                <input type="text" id="name" name="name" required
                       value="<?= e($menu['name'] ?? '') ?>" class="form-input"
                       placeholder="e.g. Main Navigation">
            </div>
            <div class="form-group">
                <label for="location">Display Position <span class="required">*</span></label>
                <select id="location" name="location" required class="form-input">
                    <option value="">— Select position —</option>
                    <?php foreach ($locations ?? [] as $slug => $loc): ?>
                    <option value="<?= e($slug) ?>"
                            <?= ($menu['location'] ?? '') === $slug ? 'selected' : '' ?>>
                        <?= e($loc['label']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <small><?php if (!empty($menu['location'])): ?>Template: <code>get_menu('<?= e($menu['location']) ?>')</code><?php else: ?>Choose where this menu appears on the site<?php endif; ?></small>
            </div>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <input type="text" id="description" name="description"
                   value="<?= e($menu['description'] ?? '') ?>" class="form-input"
                   placeholder="Where this menu is displayed">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <?= $menu && isset($menu['id']) ? 'Update Menu' : 'Create Menu' ?>
            </button>
            <a href="/admin/menus" class="btn btn-outline">Cancel</a>
            <?php if ($menu && isset($menu['id'])): ?>
            <a href="/admin/menus/<?= (int)$menu['id'] ?>/block-editor" class="btn btn-outline" style="margin-left:auto">Edit Block Layout →</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($menu && isset($menu['id'])): ?>
    <!-- Menu Items Editor -->
    <section class="menu-item-editor <?= $acpLayout === '1' ? 'menu-editor-stacked' : '' ?>" data-menu-id="<?= (int)$menu['id'] ?>">
        <h2>Menu Items</h2>

        <div class="menu-editor-columns">
            <!-- LEFT: Menu Tree -->
            <div class="menu-editor-main">
                <div class="menu-item-list" id="menu-item-list">
            <?php if (empty($items)): ?>
                <p class="block-empty" id="menu-empty-msg">No items yet. Use the sidebar to add pages, routes, or links.</p>
            <?php else: ?>
                <?php
                // Build tree structure
                $tree = [];
                $itemsById = [];
                foreach ($items as $item) {
                    $itemsById[$item['id']] = $item;
                    $itemsById[$item['id']]['children'] = [];
                }
                foreach ($itemsById as $id => &$item) {
                    if ($item['parent_id'] && isset($itemsById[$item['parent_id']])) {
                        $itemsById[$item['parent_id']]['children'][] = &$item;
                    } else {
                        $tree[] = &$item;
                    }
                }
                unset($item);

                // Pass pages/subjects into global scope so renderMenuItemTree can access them
                $GLOBALS['_menu_pages'] = $pages ?? [];
                $GLOBALS['_menu_subjects'] = $subjects ?? [];
                $GLOBALS['_menu_all_items'] = $items;

                function renderMenuItemTree(array $item): void {
                    $resolvedUrl = '';
                    $typeLabel = '';
                    switch ($item['link_type']) {
                        case 'page':
                            $typeLabel = 'Page';
                            $resolvedUrl = '/' . ($item['page_slug'] ?? '');
                            break;
                        case 'subject':
                            $typeLabel = 'Subject';
                            $resolvedUrl = $item['subject_title'] ?? '';
                            break;
                        case 'route':
                            $typeLabel = 'Route';
                            $resolvedUrl = $item['route'] ?? '';
                            break;
                        case 'url':
                            $typeLabel = 'URL';
                            $resolvedUrl = $item['url'] ?? '';
                            break;
                    }
                    ?>
                    <li class="menu-tree-node <?= $item['is_active'] ? '' : 'menu-item-inactive' ?>" data-item-id="<?= (int)$item['id'] ?>">
                        <div class="menu-tree-item" data-parent-id="<?= (int)($item['parent_id'] ?? 0) ?>">
                            <span class="menu-tree-handle" title="Drag to reorder">&#x2630;</span>
                            <span class="menu-tree-label"><?= e($item['label']) ?></span>
                            <span class="menu-tree-meta">
                                <span class="badge badge-muted"><?= $typeLabel ?></span>
                                <span class="menu-tree-url"><?= e($resolvedUrl) ?></span>
                                <?php if ($item['open_new_tab']): ?>
                                    <span class="badge badge-info" title="Opens in new tab">↗</span>
                                <?php endif; ?>
                                <?php if (!$item['is_active']): ?>
                                    <span class="badge badge-warning">Hidden</span>
                                <?php endif; ?>
                                <?php if (($item['visibility'] ?? 'always') !== 'always'): ?>
                                    <span class="badge badge-info"><?= ($item['visibility'] ?? 'always') === 'logged_in' ? 'Auth' : 'Guest' ?></span>
                                <?php endif; ?>
                                <?php if (!empty($item['min_role'])): ?>
                                    <span class="badge badge-info"><?= e(ucfirst($item['min_role'])) ?>+</span>
                                <?php endif; ?>
                            </span>
                            <span class="menu-tree-actions">
                                <button class="btn btn-small btn-edit-menu-item" data-item-id="<?= (int)$item['id'] ?>">Edit</button>
                                <button class="btn btn-small btn-danger btn-delete-menu-item" data-item-id="<?= (int)$item['id'] ?>">Delete</button>
                            </span>
                        </div>

                        <!-- Inline edit form (hidden by default) -->
                        <div class="menu-tree-edit-form" id="edit-form-<?= (int)$item['id'] ?>" style="display:none">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Label</label>
                                    <input type="text" class="form-input mi-label" value="<?= e($item['label']) ?>">
                                </div>
                                <div class="form-group">
                                    <label>Type</label>
                                    <select class="form-input mi-link-type">
                                        <option value="route" <?= $item['link_type'] === 'route' ? 'selected' : '' ?>>Route</option>
                                        <option value="page" <?= $item['link_type'] === 'page' ? 'selected' : '' ?>>Page</option>
                                        <option value="subject" <?= $item['link_type'] === 'subject' ? 'selected' : '' ?>>Subject</option>
                                        <option value="url" <?= $item['link_type'] === 'url' ? 'selected' : '' ?>>Custom URL</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group mi-field-route" <?= $item['link_type'] !== 'route' ? 'style="display:none"' : '' ?>>
                                    <label>Route</label>
                                    <input type="text" class="form-input mi-route" value="<?= e($item['route'] ?? '') ?>" placeholder="/about">
                                </div>
                                <div class="form-group mi-field-url" <?= $item['link_type'] !== 'url' ? 'style="display:none"' : '' ?>>
                                    <label>URL</label>
                                    <input type="text" class="form-input mi-url" value="<?= e($item['url'] ?? '') ?>" placeholder="https://...">
                                </div>
                                <div class="form-group mi-field-page" <?= $item['link_type'] !== 'page' ? 'style="display:none"' : '' ?>>
                                    <label>Page</label>
                                    <select class="form-input mi-page-id">
                                        <option value="">— Select page —</option>
                                        <?php if (!empty($GLOBALS['_menu_pages'])): ?>
                                        <?php foreach ($GLOBALS['_menu_pages'] as $p): ?>
                                        <option value="<?= (int)$p['id'] ?>" <?= ($item['page_id'] ?? 0) == $p['id'] ? 'selected' : '' ?>><?= e($p['title']) ?></option>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="form-group mi-field-subject" <?= $item['link_type'] !== 'subject' ? 'style="display:none"' : '' ?>>
                                    <label>Subject</label>
                                    <select class="form-input mi-subject-id">
                                        <option value="">— Select subject —</option>
                                        <?php if (!empty($GLOBALS['_menu_subjects'])): ?>
                                        <?php foreach ($GLOBALS['_menu_subjects'] as $s): ?>
                                        <option value="<?= (int)$s['id'] ?>" <?= ($item['subject_id'] ?? 0) == $s['id'] ? 'selected' : '' ?>><?= e($s['title']) ?></option>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>CSS Class</label>
                                    <input type="text" class="form-input mi-css-class" value="<?= e($item['css_class'] ?? '') ?>" placeholder="optional">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Parent</label>
                                    <select class="form-input mi-parent-id">
                                        <option value="">— Top level —</option>
                                        <?php foreach ($GLOBALS['_menu_all_items'] as $parentItem): ?>
                                            <?php if ((int)$parentItem['id'] !== (int)$item['id']): ?>
                                            <option value="<?= (int)$parentItem['id'] ?>" <?= ($item['parent_id'] ?? 0) == $parentItem['id'] ? 'selected' : '' ?>><?= e($parentItem['label']) ?></option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" class="mi-new-tab" <?= $item['open_new_tab'] ? 'checked' : '' ?>>
                                        Open in new tab
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" class="mi-active" <?= $item['is_active'] ? 'checked' : '' ?>>
                                        Visible
                                    </label>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Show When</label>
                                    <select class="form-input mi-visibility">
                                        <option value="always" <?= ($item['visibility'] ?? 'always') === 'always' ? 'selected' : '' ?>>Always</option>
                                        <option value="logged_in" <?= ($item['visibility'] ?? 'always') === 'logged_in' ? 'selected' : '' ?>>Logged In</option>
                                        <option value="logged_out" <?= ($item['visibility'] ?? 'always') === 'logged_out' ? 'selected' : '' ?>>Logged Out</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Min Role</label>
                                    <select class="form-input mi-min-role">
                                        <option value="" <?= empty($item['min_role']) ? 'selected' : '' ?>>— Any —</option>
                                        <option value="member" <?= ($item['min_role'] ?? '') === 'member' ? 'selected' : '' ?>>Member</option>
                                        <option value="council" <?= ($item['min_role'] ?? '') === 'council' ? 'selected' : '' ?>>Council</option>
                                        <option value="admin" <?= ($item['min_role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-actions">
                                <button class="btn btn-small btn-primary btn-save-menu-item" data-item-id="<?= (int)$item['id'] ?>">Save</button>
                                <button class="btn btn-small btn-cancel-edit-item" data-item-id="<?= (int)$item['id'] ?>">Cancel</button>
                            </div>
                        </div>

                        <?php if (!empty($item['children'])): ?>
                        <ul class="menu-tree-children">
                            <?php foreach ($item['children'] as $child): ?>
                                <?php renderMenuItemTree($child); ?>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </li>
                    <?php
                }
                ?>

                <div class="menu-tree-root">
                    <div class="menu-tree-heading"><?= e($menu['name']) ?></div>
                    <ul class="menu-tree">
                        <?php foreach ($tree as $item): ?>
                            <?php renderMenuItemTree($item); ?>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <?php unset($GLOBALS['_menu_pages'], $GLOBALS['_menu_subjects'], $GLOBALS['_menu_all_items']); ?>
            <?php endif; ?>
            </div><!-- /.menu-item-list -->
            </div><!-- /.menu-editor-main -->

            <!-- RIGHT: Available Items Sidebar -->
            <div class="menu-editor-sidebar">

                <!-- Pages -->
                <div class="menu-source-panel" id="panel-pages">
                    <button class="menu-source-toggle" data-panel="pages">Pages <span class="nav-caret">▾</span></button>
                    <div class="menu-source-body">
                        <?php if (empty($pages)): ?>
                            <p class="menu-source-empty">No published pages.</p>
                        <?php else: ?>
                            <ul class="menu-source-list">
                                <?php foreach ($pages as $p): ?>
                                <li>
                                    <label class="menu-source-item">
                                        <input type="checkbox" class="source-check" data-type="page" data-id="<?= (int)$p['id'] ?>" data-label="<?= e($p['title']) ?>">
                                        <span class="menu-source-label"><?= e($p['title']) ?></span>
                                        <span class="menu-source-slug">/<?= e($p['slug']) ?></span>
                                    </label>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <div class="menu-source-actions">
                                <button class="btn btn-small btn-primary btn-add-checked" data-type="page">Add to Menu</button>
                                <button class="btn btn-small btn-text btn-select-all" data-panel="pages">Select All</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php
                // Build list of already-used routes for visual reference
                $usedRoutes = [];
                foreach ($items as $mi) {
                    if ($mi['link_type'] === 'route' && $mi['route']) $usedRoutes[] = $mi['route'];
                }
                $commonRoutes = [
                    ['route' => '/',          'label' => 'Home'],
                    ['route' => '/events',    'label' => 'Events'],
                    ['route' => '/news',      'label' => 'News'],
                    ['route' => '/forum',     'label' => 'Forum'],
                    ['route' => '/directory', 'label' => 'Directory'],
                    ['route' => '/login',     'label' => 'Login'],
                    ['route' => '/register',  'label' => 'Register'],
                    ['route' => '/council',   'label' => 'Council'],
                ];
                ?>

                <!-- Common Routes -->
                <div class="menu-source-panel" id="panel-routes">
                    <button class="menu-source-toggle" data-panel="routes">Routes <span class="nav-caret">▾</span></button>
                    <div class="menu-source-body">
                        <ul class="menu-source-list">
                            <?php foreach ($commonRoutes as $cr): ?>
                            <li>
                                <label class="menu-source-item <?= in_array($cr['route'], $usedRoutes) ? 'menu-source-used' : '' ?>">
                                    <input type="checkbox" class="source-check" data-type="route" data-route="<?= e($cr['route']) ?>" data-label="<?= e($cr['label']) ?>">
                                    <span class="menu-source-label"><?= e($cr['label']) ?></span>
                                    <span class="menu-source-slug"><?= e($cr['route']) ?></span>
                                    <?php if (in_array($cr['route'], $usedRoutes)): ?>
                                        <span class="badge badge-muted menu-source-in-use">in menu</span>
                                    <?php endif; ?>
                                </label>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="menu-source-actions">
                            <button class="btn btn-small btn-primary btn-add-checked" data-type="route">Add to Menu</button>
                        </div>
                    </div>
                </div>

                <?php if (!empty($subjects)): ?>
                <!-- Subjects -->
                <div class="menu-source-panel" id="panel-subjects">
                    <button class="menu-source-toggle" data-panel="subjects">Subjects <span class="nav-caret">▾</span></button>
                    <div class="menu-source-body" style="display:none">
                        <ul class="menu-source-list">
                            <?php foreach ($subjects as $s): ?>
                            <li>
                                <label class="menu-source-item">
                                    <input type="checkbox" class="source-check" data-type="subject" data-id="<?= (int)$s['id'] ?>" data-label="<?= e($s['title']) ?>">
                                    <span class="menu-source-label"><?= e($s['title']) ?></span>
                                </label>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="menu-source-actions">
                            <button class="btn btn-small btn-primary btn-add-checked" data-type="subject">Add to Menu</button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Custom Link -->
                <div class="menu-source-panel" id="panel-custom">
                    <button class="menu-source-toggle" data-panel="custom">Custom Link <span class="nav-caret">▾</span></button>
                    <div class="menu-source-body" style="display:none">
                        <div class="form-group">
                            <label>URL</label>
                            <input type="text" id="custom-url" class="form-input" placeholder="https://...">
                        </div>
                        <div class="form-group">
                            <label>Label</label>
                            <input type="text" id="custom-label" class="form-input" placeholder="Link text">
                        </div>
                        <div class="menu-source-actions">
                            <button class="btn btn-small btn-primary btn-add-custom-link">Add to Menu</button>
                        </div>
                    </div>
                </div>

                <!-- Custom Route -->
                <div class="menu-source-panel" id="panel-custom-route">
                    <button class="menu-source-toggle" data-panel="custom-route">Custom Route <span class="nav-caret">▾</span></button>
                    <div class="menu-source-body" style="display:none">
                        <div class="form-group">
                            <label>Route Path</label>
                            <input type="text" id="custom-route" class="form-input" placeholder="/my-route">
                        </div>
                        <div class="form-group">
                            <label>Label</label>
                            <input type="text" id="custom-route-label" class="form-input" placeholder="Link text">
                        </div>
                        <div class="menu-source-actions">
                            <button class="btn btn-small btn-primary btn-add-custom-route">Add to Menu</button>
                        </div>
                    </div>
                </div>

            </div><!-- /.menu-editor-sidebar -->
        </div><!-- /.menu-editor-columns -->
    </section>

    <!-- Danger Zone -->
    <section class="danger-zone">
        <h3>Danger Zone</h3>
        <form method="post" action="/admin/menus/<?= (int)$menu['id'] ?>/delete">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger">Delete this menu</button>
        </form>
    </section>
    <?php endif; ?>
</div>

<script>
(function() {
    // Layout toggle
    document.querySelectorAll('.acp-layout-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var layout = this.dataset.layout;
            var editor = document.querySelector('.menu-item-editor');
            if (editor) {
                editor.classList.toggle('menu-editor-stacked', layout === '1');
            }
            document.querySelectorAll('.acp-layout-btn').forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
            fetch('/admin/settings/layout', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'layout=' + layout + '&_csrf_token=' + encodeURIComponent(document.querySelector('input[name=_csrf_token]').value)
            });
        });
    });
})();
</script>
