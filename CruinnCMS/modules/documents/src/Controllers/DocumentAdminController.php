<?php
/**
 * CruinnCMS — Document Admin Controller
 *
 * Admin-only document management: full list across all statuses,
 * status workflow actions (approve, reject, archive), and
 * category CRUD.
 */

namespace Cruinn\Module\Documents\Controllers;

use Cruinn\Auth;
use Cruinn\Controllers\BaseController;
use Cruinn\CSRF;

class DocumentAdminController extends BaseController
{
    // ══════════════════════════════════════════════════════════════════
    //  DOCUMENT LIST
    // ══════════════════════════════════════════════════════════════════

    /**
     * GET /admin/documents — Full document list (all statuses, all users).
     */
    public function index(): void
    {
        Auth::requireRole('admin');

        $status     = $this->query('status', '');
        $categoryId = $this->query('category_id', '');
        $search     = $this->query('q', '');
        $page       = max(1, (int) $this->query('page', 1));
        $perPage    = 25;

        $where  = [];
        $params = [];

        if ($status !== '') {
            $where[]  = 'd.status = ?';
            $params[] = $status;
        }
        if ($categoryId !== '') {
            $where[]  = 'd.category_id = ?';
            $params[] = (int) $categoryId;
        }
        if ($search !== '') {
            $where[]  = '(d.title LIKE ? OR d.description LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM documents d {$whereSQL}",
            $params
        );
        $totalPages = max(1, (int) ceil($total / $perPage));
        $offset     = ($page - 1) * $perPage;

        $documents = $this->db->fetchAll(
            "SELECT d.*, u.display_name AS uploader_name,
                    a.display_name AS approver_name,
                    c.name AS category_name
             FROM documents d
             LEFT JOIN users u ON d.uploaded_by = u.id
             LEFT JOIN users a ON d.approved_by = a.id
             LEFT JOIN document_categories c ON d.category_id = c.id
             {$whereSQL}
             ORDER BY d.updated_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $categories = $this->db->fetchAll(
            'SELECT * FROM document_categories ORDER BY sort_order, name'
        );

        $this->renderAdmin('admin/documents/admin-index', [
            'title'       => 'Documents — Admin',
            'breadcrumbs' => [
                ['Dashboard', '/admin/dashboard'],
                ['Documents Admin'],
            ],
            'documents'   => $documents,
            'categories'  => $categories,
            'status'      => $status,
            'categoryId'  => $categoryId,
            'search'      => $search,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'total'       => $total,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════
    //  WORKFLOW ACTIONS
    // ══════════════════════════════════════════════════════════════════

    /**
     * POST /admin/documents/{id}/approve
     */
    public function approve(int $id): void
    {
        Auth::requireRole('admin');
        CSRF::verify();

        $doc = $this->db->fetch('SELECT id, status FROM documents WHERE id = ?', [$id]);
        if (!$doc || $doc['status'] !== 'submitted') {
            Auth::flash('error', 'Document cannot be approved in its current state.');
            $this->redirect('/admin/documents');
        }

        $this->db->execute(
            'UPDATE documents SET status = ?, approved_by = ?, approved_at = NOW(), updated_at = NOW() WHERE id = ?',
            ['approved', Auth::userId(), $id]
        );

        Auth::flash('success', 'Document approved.');
        $this->redirect('/admin/documents');
    }

    /**
     * POST /admin/documents/{id}/reject — Return to draft.
     */
    public function reject(int $id): void
    {
        Auth::requireRole('admin');
        CSRF::verify();

        $doc = $this->db->fetch('SELECT id, status FROM documents WHERE id = ?', [$id]);
        if (!$doc || $doc['status'] !== 'submitted') {
            Auth::flash('error', 'Document cannot be rejected in its current state.');
            $this->redirect('/admin/documents');
        }

        $this->db->execute(
            'UPDATE documents SET status = ?, updated_at = NOW() WHERE id = ?',
            ['draft', $id]
        );

        Auth::flash('success', 'Document returned to draft.');
        $this->redirect('/admin/documents');
    }

    /**
     * POST /admin/documents/{id}/archive
     */
    public function archive(int $id): void
    {
        Auth::requireRole('admin');
        CSRF::verify();

        $doc = $this->db->fetch('SELECT id FROM documents WHERE id = ?', [$id]);
        if (!$doc) {
            Auth::flash('error', 'Document not found.');
            $this->redirect('/admin/documents');
        }

        $this->db->execute(
            'UPDATE documents SET status = ?, updated_at = NOW() WHERE id = ?',
            ['archived', $id]
        );

        Auth::flash('success', 'Document archived.');
        $this->redirect('/admin/documents');
    }

    // ══════════════════════════════════════════════════════════════════
    //  CATEGORIES
    // ══════════════════════════════════════════════════════════════════

    /**
     * GET /admin/documents/categories — List and manage categories.
     */
    public function categories(): void
    {
        Auth::requireRole('admin');

        $categories = $this->db->fetchAll(
            "SELECT c.*, COUNT(d.id) AS document_count
             FROM document_categories c
             LEFT JOIN documents d ON d.category_id = c.id
             GROUP BY c.id
             ORDER BY c.sort_order, c.name"
        );

        $this->renderAdmin('admin/documents/categories', [
            'title'       => 'Document Categories',
            'breadcrumbs' => [
                ['label' => 'Dashboard',        'url' => '/admin/dashboard'],
                ['label' => 'Documents Admin',  'url' => '/admin/documents'],
                ['Categories'],
            ],
            'categories'  => $categories,
        ]);
    }

    /**
     * POST /admin/documents/categories — Create a new category.
     */
    public function createCategory(): void
    {
        Auth::requireRole('admin');
        CSRF::verify();

        $name  = trim($this->input('name', ''));
        $desc  = trim($this->input('description', ''));
        $order = (int) $this->input('sort_order', 0);

        if ($name === '') {
            Auth::flash('error', 'Category name is required.');
            $this->redirect('/admin/documents/categories');
        }

        $slug = $this->makeSlug($name);

        // Ensure unique slug
        $existing = $this->db->fetch('SELECT id FROM document_categories WHERE slug = ?', [$slug]);
        if ($existing) {
            $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 4);
        }

        $this->db->insert('document_categories', [
            'name'        => $name,
            'slug'        => $slug,
            'description' => $desc ?: null,
            'sort_order'  => $order,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        Auth::flash('success', "Category \"{$name}\" created.");
        $this->redirect('/admin/documents/categories');
    }

    /**
     * POST /admin/documents/categories/{id}/update
     */
    public function updateCategory(int $id): void
    {
        Auth::requireRole('admin');
        CSRF::verify();

        $cat = $this->db->fetch('SELECT id FROM document_categories WHERE id = ?', [$id]);
        if (!$cat) {
            Auth::flash('error', 'Category not found.');
            $this->redirect('/admin/documents/categories');
        }

        $name  = trim($this->input('name', ''));
        $desc  = trim($this->input('description', ''));
        $order = (int) $this->input('sort_order', 0);

        if ($name === '') {
            Auth::flash('error', 'Category name is required.');
            $this->redirect('/admin/documents/categories');
        }

        $this->db->execute(
            'UPDATE document_categories SET name = ?, description = ?, sort_order = ? WHERE id = ?',
            [$name, $desc ?: null, $order, $id]
        );

        Auth::flash('success', 'Category updated.');
        $this->redirect('/admin/documents/categories');
    }

    /**
     * POST /admin/documents/categories/{id}/delete
     */
    public function deleteCategory(int $id): void
    {
        Auth::requireRole('admin');
        CSRF::verify();

        $cat = $this->db->fetch(
            'SELECT c.id, COUNT(d.id) AS doc_count
             FROM document_categories c
             LEFT JOIN documents d ON d.category_id = c.id
             WHERE c.id = ?
             GROUP BY c.id',
            [$id]
        );

        if (!$cat) {
            Auth::flash('error', 'Category not found.');
            $this->redirect('/admin/documents/categories');
        }

        if ((int) $cat['doc_count'] > 0) {
            Auth::flash('error', 'Cannot delete a category that has documents assigned to it.');
            $this->redirect('/admin/documents/categories');
        }

        $this->db->delete('document_categories', 'id = ?', [$id]);

        Auth::flash('success', 'Category deleted.');
        $this->redirect('/admin/documents/categories');
    }

    // ══════════════════════════════════════════════════════════════════
    //  HELPERS
    // ══════════════════════════════════════════════════════════════════

    private function makeSlug(string $name): string
    {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
        return trim($slug, '-');
    }
}
