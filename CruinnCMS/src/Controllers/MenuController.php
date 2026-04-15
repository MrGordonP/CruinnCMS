<?php
/**
 * CruinnCMS — Menu Controller
 *
 * Admin CRUD for navigation menus and their items.
 * Menus are assigned to theme locations (main, footer, etc.).
 * Menu items are hierarchical and can link to pages, subjects,
 * custom URLs, or fixed application routes.
 */

namespace Cruinn\Controllers;

use Cruinn\Auth;

class MenuController extends BaseController
{
    /**
     * Registered template positions a menu can be assigned to.
     * Key = location slug stored in DB, value = human label + description.
     */
    public const LOCATIONS = [
        'main'    => ['label' => 'Header — Primary Navigation',   'description' => 'Main navigation bar in the site header'],
        'footer'  => ['label' => 'Footer Navigation',             'description' => 'Links shown in the site footer'],
        'sidebar' => ['label' => 'Sidebar Navigation',            'description' => 'Optional sidebar/widget navigation area'],
        'topbar'  => ['label' => 'Top Bar — Utility Links',       'description' => 'Slim utility bar above the header (login, register, etc.)'],
        'mobile'  => ['label' => 'Mobile Navigation',             'description' => 'Dedicated menu for mobile/hamburger drawer'],
        'custom'  => ['label' => 'Custom / Unassigned',           'description' => 'Not tied to a template zone — rendered manually'],
    ];

    // ══════════════════════════════════════════════════════════════
    //  MENU CRUD
    // ══════════════════════════════════════════════════════════════

    /**
     * GET /admin/menus — List all menus.
     */
    public function adminList(): void
    {
        $menus = $this->db->fetchAll(
            'SELECT m.*,
                    (SELECT COUNT(*) FROM menu_items mi WHERE mi.menu_id = m.id) AS item_count
             FROM menus m
             ORDER BY m.name ASC'
        );

        $this->renderAdmin('admin/menus/index', [
            'title'       => 'Menus',
            'menus'       => $menus,
            'locations'   => self::LOCATIONS,
            'breadcrumbs' => [['Admin', '/admin'], ['Menus']],
        ]);
    }

    /**
     * GET /admin/menus/new — New menu form.
     */
    public function adminNew(): void
    {
        $this->renderAdmin('admin/menus/edit', [
            'title'       => 'New Menu',
            'menu'        => null,
            'items'       => [],
            'pages'       => $this->getAvailablePages(),
            'subjects'    => $this->getAvailableSubjects(),
            'locations'   => self::LOCATIONS,
            'errors'      => [],
            'breadcrumbs' => [['Admin', '/admin'], ['Menus', '/admin/menus'], ['New Menu']],
        ]);
    }

    /**
     * POST /admin/menus — Create a new menu.
     */
    public function adminCreate(): void
    {
        $errors = $this->validateRequired(['name' => 'Name', 'location' => 'Display Position']);

        $location = $this->input('location');

        // Validate against registered positions
        if ($location && !isset(self::LOCATIONS[$location])) {
            $errors['location'] = 'Invalid display position.';
        }

        // Check location uniqueness
        $existing = $this->db->fetchColumn(
            'SELECT COUNT(*) FROM menus WHERE location = ?',
            [$location]
        );
        if ($existing) {
            $errors['location'] = 'Another menu is already assigned to this position.';
        }

        if ($errors) {
            $this->renderAdmin('admin/menus/edit', [
                'title'       => 'New Menu',
                'menu'        => $_POST,
                'items'       => [],
                'pages'       => $this->getAvailablePages(),
                'subjects'    => $this->getAvailableSubjects(),
                'locations'   => self::LOCATIONS,
                'errors'      => $errors,
                'breadcrumbs' => [['Admin', '/admin'], ['Menus', '/admin/menus'], ['New Menu']],
            ]);
            return;
        }

        $id = $this->db->insert('menus', [
            'name'        => $this->input('name'),
            'location'    => $location,
            'description' => $this->input('description', ''),
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        $this->logActivity('create', 'menu', (int) $id, $this->input('name'));
        Auth::flash('success', 'Menu created. Add some items to it.');
        $this->redirect("/admin/menus/{$id}/edit");
    }

    /**
     * GET /admin/menus/{id}/edit — Edit a menu and its items.
     */
    public function adminEdit(string $id): void
    {
        $menu = $this->db->fetch('SELECT * FROM menus WHERE id = ?', [$id]);
        if (!$menu) {
            Auth::flash('error', 'Menu not found.');
            $this->redirect('/admin/menus');
        }

        $items = $this->getMenuItems((int) $id);
        $pages = $this->getAvailablePages();
        $subjects = $this->getAvailableSubjects();

        $this->renderAdmin('admin/menus/edit', [
            'title'       => 'Edit: ' . $menu['name'],
            'menu'        => $menu,
            'items'       => $items,
            'pages'       => $pages,
            'subjects'    => $subjects,
            'locations'   => self::LOCATIONS,
            'errors'      => [],
            'breadcrumbs' => [['Admin', '/admin'], ['Menus', '/admin/menus'], [$menu['name']]],
        ]);
    }

    /**
     * POST /admin/menus/{id} — Update menu metadata.
     */
    public function adminUpdate(string $id): void
    {
        $menu = $this->db->fetch('SELECT * FROM menus WHERE id = ?', [$id]);
        if (!$menu) {
            Auth::flash('error', 'Menu not found.');
            $this->redirect('/admin/menus');
        }

        $errors = $this->validateRequired(['name' => 'Name', 'location' => 'Display Position']);

        $location = $this->input('location');

        // Validate against registered positions
        if ($location && !isset(self::LOCATIONS[$location])) {
            $errors['location'] = 'Invalid display position.';
        }

        $existing = $this->db->fetchColumn(
            'SELECT COUNT(*) FROM menus WHERE location = ? AND id != ?',
            [$location, $id]
        );
        if ($existing) {
            $errors['location'] = 'Another menu is already assigned to this position.';
        }

        if ($errors) {
            $items = $this->getMenuItems((int) $id);
            $pages = $this->getAvailablePages();
            $subjects = $this->getAvailableSubjects();
            $this->renderAdmin('admin/menus/edit', [
                'title'       => 'Edit: ' . $menu['name'],
                'menu'        => array_merge($menu, $_POST),
                'items'       => $items,
                'pages'       => $pages,
                'subjects'    => $subjects,
                'locations'   => self::LOCATIONS,
                'errors'      => $errors,
                'breadcrumbs' => [['Admin', '/admin'], ['Menus', '/admin/menus'], [$menu['name']]],
            ]);
            return;
        }

        $this->db->update('menus', [
            'name'        => $this->input('name'),
            'location'    => $location,
            'description' => $this->input('description', ''),
            'updated_at'  => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        $this->logActivity('update', 'menu', (int) $id, $this->input('name'));
        Auth::flash('success', 'Menu updated.');
        $this->redirect("/admin/menus/{$id}/edit");
    }

    /**
     * POST /admin/menus/{id}/delete — Delete a menu and its items.
     */
    public function adminDelete(string $id): void
    {
        $menu = $this->db->fetch('SELECT * FROM menus WHERE id = ?', [$id]);
        if (!$menu) {
            Auth::flash('error', 'Menu not found.');
            $this->redirect('/admin/menus');
        }

        // FK cascade will delete menu_items
        $this->db->delete('menus', 'id = ?', [$id]);
        $this->logActivity('delete', 'menu', (int) $id, $menu['name']);

        Auth::flash('success', 'Menu "' . $menu['name'] . '" deleted.');
        $this->redirect('/admin/menus');
    }

    // ══════════════════════════════════════════════════════════════
    //  MENU ITEMS (AJAX)
    // ══════════════════════════════════════════════════════════════

    /**
     * POST /admin/menus/{id}/items — Add a new item to a menu.
     */
    public function addItem(string $menuId): void
    {
        $menu = $this->db->fetch('SELECT * FROM menus WHERE id = ?', [$menuId]);
        if (!$menu) {
            $this->json(['error' => 'Menu not found'], 404);
            return;
        }

        $linkType = $this->input('link_type', 'url');
        if (!in_array($linkType, ['url', 'page', 'subject', 'route'])) {
            $this->json(['error' => 'Invalid link type'], 400);
            return;
        }

        $label = trim($this->input('label', ''));
        if ($label === '') {
            // Auto-populate label from linked entity
            $label = $this->autoLabel($linkType);
        }

        if ($label === '') {
            $this->json(['error' => 'Label is required'], 400);
            return;
        }

        $maxOrder = $this->db->fetchColumn(
            'SELECT COALESCE(MAX(sort_order), 0) FROM menu_items WHERE menu_id = ?',
            [$menuId]
        );

        $parentId = $this->input('parent_id') ?: null;

        $visibility = $this->input('visibility', 'always');
        if (!in_array($visibility, ['always', 'logged_in', 'logged_out'])) {
            $visibility = 'always';
        }
        $minRole = $this->input('min_role', '') ?: null;

        $itemId = $this->db->insert('menu_items', [
            'menu_id'      => $menuId,
            'parent_id'    => $parentId,
            'label'        => $label,
            'link_type'    => $linkType,
            'url'          => $linkType === 'url'     ? $this->input('url', '') : null,
            'page_id'      => $linkType === 'page'    ? ($this->input('page_id') ?: null) : null,
            'subject_id'   => $linkType === 'subject' ? ($this->input('subject_id') ?: null) : null,
            'route'        => $linkType === 'route'   ? $this->input('route', '') : null,
            'sort_order'   => $maxOrder + 1,
            'css_class'    => $this->input('css_class', ''),
            'open_new_tab' => $this->input('open_new_tab') ? 1 : 0,
            'is_active'    => 1,
            'visibility'   => $visibility,
            'min_role'     => $minRole,
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);

        $this->json(['success' => true, 'item_id' => $itemId]);
    }

    /**
     * POST /admin/menus/{menuId}/items/{itemId} — Update a menu item.
     */
    public function updateItem(string $menuId, string $itemId): void
    {
        $item = $this->db->fetch(
            'SELECT * FROM menu_items WHERE id = ? AND menu_id = ?',
            [$itemId, $menuId]
        );
        if (!$item) {
            $this->json(['error' => 'Menu item not found'], 404);
            return;
        }

        $linkType = $this->input('link_type', $item['link_type']);
        $label = trim($this->input('label', $item['label']));

        $visibility = $this->input('visibility', $item['visibility'] ?? 'always');
        if (!in_array($visibility, ['always', 'logged_in', 'logged_out'])) {
            $visibility = 'always';
        }
        $minRole = $this->input('min_role', '') ?: null;

        $this->db->update('menu_items', [
            'label'        => $label,
            'link_type'    => $linkType,
            'url'          => $linkType === 'url'     ? $this->input('url', '') : null,
            'page_id'      => $linkType === 'page'    ? ($this->input('page_id') ?: null) : null,
            'subject_id'   => $linkType === 'subject' ? ($this->input('subject_id') ?: null) : null,
            'route'        => $linkType === 'route'   ? $this->input('route', '') : null,
            'parent_id'    => $this->input('parent_id') ?: null,
            'css_class'    => $this->input('css_class', ''),
            'open_new_tab' => $this->input('open_new_tab') ? 1 : 0,
            'is_active'    => $this->input('is_active', 1) ? 1 : 0,
            'visibility'   => $visibility,
            'min_role'     => $minRole,
            'updated_at'   => date('Y-m-d H:i:s'),
        ], 'id = ?', [$itemId]);

        $this->json(['success' => true]);
    }

    /**
     * POST /admin/menus/{menuId}/items/{itemId}/delete — Delete a menu item.
     */
    public function deleteItem(string $menuId, string $itemId): void
    {
        $item = $this->db->fetch(
            'SELECT * FROM menu_items WHERE id = ? AND menu_id = ?',
            [$itemId, $menuId]
        );
        if (!$item) {
            $this->json(['error' => 'Menu item not found'], 404);
            return;
        }

        // Orphan children (move them up to root level of this menu)
        $this->db->update('menu_items',
            ['parent_id' => $item['parent_id']],
            'parent_id = ?',
            [$itemId]
        );

        $this->db->delete('menu_items', 'id = ?', [$itemId]);

        $this->json(['success' => true]);
    }

    /**
     * POST /admin/menus/{id}/reorder — Reorder menu items (AJAX).
     * Expects JSON body: { items: [ { id: 1, parent_id: null, sort_order: 0 }, ... ] }
     */
    public function reorderItems(string $menuId): void
    {
        $menu = $this->db->fetch('SELECT * FROM menus WHERE id = ?', [$menuId]);
        if (!$menu) {
            $this->json(['error' => 'Menu not found'], 404);
            return;
        }

        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        $itemsData = $data['items'] ?? [];

        if (empty($itemsData)) {
            $this->json(['error' => 'No items data provided'], 400);
            return;
        }

        foreach ($itemsData as $entry) {
            $this->db->update('menu_items', [
                'sort_order' => (int) $entry['sort_order'],
                'parent_id'  => $entry['parent_id'] ?: null,
            ], 'id = ? AND menu_id = ?', [$entry['id'], $menuId]);
        }

        $this->json(['success' => true]);
    }

    // ── Helpers ───────────────────────────────────────────────

    /**
     * Get all items for a menu, ordered, with page/subject titles pre-fetched.
     */
    private function getMenuItems(int $menuId): array
    {
        return $this->db->fetchAll(
            'SELECT mi.*, p.title AS page_title, p.slug AS page_slug,
                    s.title AS subject_title
             FROM menu_items mi
             LEFT JOIN pages p ON mi.page_id = p.id
             LEFT JOIN subjects s ON mi.subject_id = s.id
             WHERE mi.menu_id = ?
             ORDER BY mi.sort_order ASC',
            [$menuId]
        );
    }

    private function getAvailablePages(): array
    {
        return $this->db->fetchAll(
            'SELECT id, title, slug FROM pages WHERE status = ? ORDER BY title ASC',
            ['published']
        );
    }

    private function getAvailableSubjects(): array
    {
        return $this->db->fetchAll(
            'SELECT id, title FROM subjects WHERE status = ? ORDER BY title ASC',
            ['active']
        );
    }

    /**
     * Generate a label automatically from the linked entity.
     */
    private function autoLabel(string $linkType): string
    {
        return match ($linkType) {
            'page'    => $this->db->fetchColumn('SELECT title FROM pages WHERE id = ?', [$this->input('page_id')]) ?: 'Untitled Page',
            'subject' => $this->db->fetchColumn('SELECT title FROM subjects WHERE id = ?', [$this->input('subject_id')]) ?: 'Untitled Subject',
            'route'   => ucfirst(trim($this->input('route', '/'), '/')),
            default   => '',
        };
    }

    // ══════════════════════════════════════════════════════════════
    //  MENU BLOCK LAYOUT EDITOR
    // ══════════════════════════════════════════════════════════════

    /**
     * GET /admin/menus/{id}/block-editor
     * Ensures a dedicated block-layout page exists for this menu and redirects
     * to the Cruinn editor so the admin can style how this menu renders.
     */
    public function blockEditor(string $id): void
    {
        Auth::requireRole('admin');

        $menu = $this->db->fetch('SELECT * FROM menus WHERE id = ?', [(int) $id]);
        if (!$menu) {
            Auth::flash('error', 'Menu not found.');
            $this->redirect('/admin/menus');
        }

        // If a block layout page already exists, go straight to it
        if (!empty($menu['block_page_id'])) {
            $existing = $this->db->fetch(
                'SELECT id FROM pages WHERE id = ? LIMIT 1',
                [(int) $menu['block_page_id']]
            );
            if ($existing) {
                $this->redirect('/admin/editor/' . (int) $existing['id'] . '/edit');
                return;
            }
        }

        // Create the block layout page
        $slug  = '_menu_' . preg_replace('/[^a-z0-9]/', '-', strtolower($menu['name'])) . '_' . $menu['id'];
        $title = 'Menu Layout: ' . $menu['name'];

        $page = $this->db->fetch('SELECT id FROM pages WHERE slug = ? LIMIT 1', [$slug]);
        if ($page) {
            $pageId = (int) $page['id'];
        } else {
            $pageId = $this->db->insert('pages', [
                'title'       => $title,
                'slug'        => $slug,
                'status'      => 'published',
                'template'    => 'none',
                'render_mode' => 'cruinn',
            ]);
        }

        $this->db->update('menus', ['block_page_id' => $pageId], 'id = ?', [(int) $id]);
        $this->redirect('/admin/editor/' . $pageId . '/edit');
    }
}
