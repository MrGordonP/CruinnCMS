<?php
/**
 * CruinnCMS — Subject Controller
 *
 * Admin CRUD for the Subject correlation index.
 * Subjects organise events, articles, documents, and finances under a single umbrella.
 */

namespace Cruinn\Controllers;

use Cruinn\Auth;

class SubjectController extends BaseController
{
    // ── Admin: List ───────────────────────────────────────────

    /**
     * GET /admin/subjects — List subjects with search, filters, pagination.
     */
    public function adminList(): void
    {
        $search  = $this->query('q', '');
        $type    = $this->query('type', '');
        $status  = $this->query('status', '');
        $page    = max(1, (int) $this->query('page', 1));
        $perPage = 25;

        $where  = [];
        $params = [];

        if ($search !== '') {
            $where[]  = '(s.title LIKE ? OR s.code LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        if ($type !== '') {
            $where[]  = 's.type = ?';
            $params[] = $type;
        }
        if ($status !== '') {
            $where[]  = 's.status = ?';
            $params[] = $status;
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM subjects s {$whereSQL}",
            $params
        );
        $totalPages = max(1, (int) ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;

        $subjects = $this->db->fetchAll(
            "SELECT s.*, u.display_name as creator_name,
                    (SELECT COUNT(*) FROM articles a WHERE a.subject_id = s.id) AS article_count
             FROM subjects s
             LEFT JOIN users u ON s.created_by = u.id
             {$whereSQL}
             ORDER BY s.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $this->renderAdmin('admin/subjects/index', [
            'title'       => 'Subjects',
            'subjects'    => $subjects,
            'search'      => $search,
            'type'        => $type,
            'status'      => $status,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'total'       => $total,
            'breadcrumbs' => [['Admin', '/admin'], ['Subjects']],
        ]);
    }

    // ── Admin: New ────────────────────────────────────────────

    /**
     * GET /admin/subjects/new — New subject form.
     */
    public function adminNew(): void
    {
        $parentSubjects = $this->db->fetchAll(
            'SELECT id, code, title FROM subjects ORDER BY title ASC'
        );

        $this->renderAdmin('admin/subjects/edit', [
            'title'          => 'New Subject',
            'subject'        => null,
            'errors'         => [],
            'parentSubjects' => $parentSubjects,
            'breadcrumbs'    => [['Admin', '/admin'], ['Subjects', '/admin/subjects'], ['New Subject']],
        ]);
    }

    // ── Admin: Create ─────────────────────────────────────────

    /**
     * POST /admin/subjects — Create a new subject.
     */
    public function adminCreate(): void
    {
        $errors = $this->validateRequired([
            'code'  => 'Code',
            'title' => 'Title',
        ]);

        $slug = $this->sanitiseSlug($this->input('slug') ?: $this->input('title'));

        // Check unique code
        if (empty($errors['code'])) {
            $existing = $this->db->fetchColumn(
                'SELECT COUNT(*) FROM subjects WHERE code = ?',
                [$this->input('code')]
            );
            if ($existing) {
                $errors['code'] = 'A subject with this code already exists.';
            }
        }

        // Check unique slug
        $existingSlug = $this->db->fetchColumn(
            'SELECT COUNT(*) FROM subjects WHERE slug = ?',
            [$slug]
        );
        if ($existingSlug) {
            $errors['slug'] = 'A subject with this URL slug already exists.';
        }

        if ($errors) {
            $parentSubjects = $this->db->fetchAll(
                'SELECT id, code, title FROM subjects ORDER BY title ASC'
            );
            $this->renderAdmin('admin/subjects/edit', [
                'title'          => 'New Subject',
                'subject'        => $_POST,
                'errors'         => $errors,
                'parentSubjects' => $parentSubjects,
                'breadcrumbs'    => [['Admin', '/admin'], ['Subjects', '/admin/subjects'], ['New Subject']],
            ]);
            return;
        }

        $parentId = $this->input('parent_id');

        $id = $this->db->insert('subjects', [
            'parent_id'   => $parentId ?: null,
            'code'        => $this->input('code'),
            'title'       => $this->input('title'),
            'slug'        => $slug,
            'type'        => $this->input('type', 'general'),
            'status'      => $this->input('status', 'draft'),
            'description' => $this->input('description', ''),
            'starts_at'   => $this->input('starts_at') ?: null,
            'ends_at'     => $this->input('ends_at') ?: null,
            'created_by'  => Auth::userId(),
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        $this->logActivity('create', 'subject', (int) $id, $this->input('title'));
        Auth::flash('success', 'Subject created.');
        $this->redirect("/admin/subjects/{$id}/edit");
    }

    // ── Admin: Edit ───────────────────────────────────────────

    /**
     * GET /admin/subjects/{id}/edit — Edit subject form.
     */
    public function adminEdit(string $id): void
    {
        $subject = $this->db->fetch('SELECT * FROM subjects WHERE id = ?', [$id]);
        if (!$subject) {
            Auth::flash('error', 'Subject not found.');
            $this->redirect('/admin/subjects');
        }

        $parentSubjects = $this->db->fetchAll(
            'SELECT id, code, title FROM subjects WHERE id != ? ORDER BY title ASC',
            [$id]
        );

        $this->renderAdmin('admin/subjects/edit', [
            'title'          => 'Edit: ' . $subject['title'],
            'subject'        => $subject,
            'errors'         => [],
            'parentSubjects' => $parentSubjects,
            'breadcrumbs'    => [['Admin', '/admin'], ['Subjects', '/admin/subjects'], [$subject['title']]],
        ]);
    }

    // ── Admin: Update ─────────────────────────────────────────

    /**
     * POST /admin/subjects/{id} — Update a subject.
     */
    public function adminUpdate(string $id): void
    {
        $subject = $this->db->fetch('SELECT * FROM subjects WHERE id = ?', [$id]);
        if (!$subject) {
            Auth::flash('error', 'Subject not found.');
            $this->redirect('/admin/subjects');
        }

        $errors = $this->validateRequired([
            'code'  => 'Code',
            'title' => 'Title',
        ]);

        $slug = $this->sanitiseSlug($this->input('slug') ?: $this->input('title'));

        // Check unique code (excluding self)
        if (empty($errors['code'])) {
            $existing = $this->db->fetchColumn(
                'SELECT COUNT(*) FROM subjects WHERE code = ? AND id != ?',
                [$this->input('code'), $id]
            );
            if ($existing) {
                $errors['code'] = 'A subject with this code already exists.';
            }
        }

        // Check unique slug (excluding self)
        $existingSlug = $this->db->fetchColumn(
            'SELECT COUNT(*) FROM subjects WHERE slug = ? AND id != ?',
            [$slug, $id]
        );
        if ($existingSlug) {
            $errors['slug'] = 'A subject with this URL slug already exists.';
        }

        if ($errors) {
            $parentSubjects = $this->db->fetchAll(
                'SELECT id, code, title FROM subjects WHERE id != ? ORDER BY title ASC',
                [$id]
            );
            $this->renderAdmin('admin/subjects/edit', [
                'title'          => 'Edit: ' . $subject['title'],
                'subject'        => array_merge($subject, $_POST),
                'errors'         => $errors,
                'parentSubjects' => $parentSubjects,
                'breadcrumbs'    => [['Admin', '/admin'], ['Subjects', '/admin/subjects'], [$subject['title']]],
            ]);
            return;
        }

        $parentId = $this->input('parent_id');

        $this->db->update('subjects', [
            'parent_id'   => $parentId ?: null,
            'code'        => $this->input('code'),
            'title'       => $this->input('title'),
            'slug'        => $slug,
            'type'        => $this->input('type', 'general'),
            'status'      => $this->input('status', 'draft'),
            'description' => $this->input('description', ''),
            'starts_at'   => $this->input('starts_at') ?: null,
            'ends_at'     => $this->input('ends_at') ?: null,
            'updated_at'  => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        $this->logActivity('update', 'subject', (int) $id, $this->input('title'));
        Auth::flash('success', 'Subject updated.');
        $this->redirect("/admin/subjects/{$id}/edit");
    }

    // ── Admin: Delete ─────────────────────────────────────────

    /**
     * POST /admin/subjects/{id}/delete — Delete a subject.
     */
    public function adminDelete(string $id): void
    {
        $subject = $this->db->fetch('SELECT * FROM subjects WHERE id = ?', [$id]);
        if (!$subject) {
            Auth::flash('error', 'Subject not found.');
            $this->redirect('/admin/subjects');
        }

        // Check for linked entities
        $linkedEvents   = $this->db->fetchColumn('SELECT COUNT(*) FROM events WHERE subject_id = ?', [$id]);
        $linkedArticles = $this->db->fetchColumn('SELECT COUNT(*) FROM articles WHERE subject_id = ?', [$id]);

        if ($linkedEvents > 0 || $linkedArticles > 0) {
            $parts = [];
            if ($linkedEvents)   $parts[] = "{$linkedEvents} event(s)";
            if ($linkedArticles) $parts[] = "{$linkedArticles} article(s)";
            Auth::flash('error', 'Cannot delete: this subject is linked to ' . implode(' and ', $parts) . '. Remove the links first.');
            $this->redirect("/admin/subjects/{$id}/edit");
            return;
        }

        $title = $subject['title'];
        $this->db->delete('subjects', 'id = ?', [$id]);
        $this->logActivity('delete', 'subject', (int) $id, $title);
        Auth::flash('success', "Subject \"{$title}\" deleted.");
        $this->redirect('/admin/subjects');
    }
}
