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
    /**
     * GET /subjects/{slug} — Public subject page.
     */
    public function show(string $slug): void
    {
        $subject = $this->db->fetch(
            'SELECT * FROM subjects WHERE slug = ? AND status = ? LIMIT 1',
            [$slug, 'active']
        );

        if (!$subject) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Subject Not Found']);
            return;
        }

        $articles = [];
        $events = [];

        try {
            $articles = $this->db->fetchAll(
                'SELECT a.id, a.title, a.slug, a.published_at
                 FROM articles a
                 INNER JOIN subject_content sc ON sc.item_type = ? AND sc.item_id = a.id
                 WHERE sc.subject_id = ? AND a.status = ?
                 ORDER BY COALESCE(a.published_at, a.created_at) DESC
                 LIMIT 10',
                ['article', (int) $subject['id'], 'published']
            );
        } catch (\Throwable) {
            $articles = [];
        }

        try {
            $events = $this->db->fetchAll(
                'SELECT e.id, e.title, e.slug, e.date_start
                 FROM events e
                 INNER JOIN subject_content sc ON sc.item_type = ? AND sc.item_id = e.id
                 WHERE sc.subject_id = ? AND e.status = ?
                 ORDER BY COALESCE(e.date_start, e.created_at) DESC
                 LIMIT 10',
                ['event', (int) $subject['id'], 'published']
            );
        } catch (\Throwable) {
            $events = [];
        }

        $this->render('public/subjects/show', [
            'title'   => $subject['title'],
            'subject' => $subject,
            'articles'=> $articles,
            'events'  => $events,
        ]);
    }

    // ── Admin: List (workspace root) ──────────────────────────

    /**
     * GET /admin/subjects — Three-panel workspace with no subject selected.
     */
    public function adminList(): void
    {
        Auth::requireAdmin();

        $allSubjects = $this->db->fetchAll(
            'SELECT id, parent_id, code, title, type, status FROM subjects ORDER BY title ASC'
        );

        $this->renderAdmin('admin/subjects/workspace', [
            'title'       => 'Subjects',
            'allSubjects' => $allSubjects,
            'subject'     => null,
            'breadcrumbs' => [['Admin', '/admin'], ['Subjects']],
        ]);
    }

    // ── Admin: View (workspace with subject selected) ─────────

    /**
     * GET /admin/subjects/{id} — Three-panel workspace with subject selected.
     */
    public function adminView(string $id): void
    {
        Auth::requireAdmin();

        $subject = $this->db->fetch('SELECT * FROM subjects WHERE id = ?', [$id]);
        if (!$subject) {
            Auth::flash('error', 'Subject not found.');
            $this->redirect('/admin/subjects');
        }

        // Build ancestor breadcrumb chain (walk up parent_id)
        $ancestors = [];
        $cursor = $subject;
        while (!empty($cursor['parent_id'])) {
            $cursor = $this->db->fetch(
                'SELECT id, parent_id, code, title FROM subjects WHERE id = ?',
                [$cursor['parent_id']]
            );
            if (!$cursor) break;
            array_unshift($ancestors, $cursor);
        }

        // Direct children
        $children = $this->db->fetchAll(
            'SELECT id, code, title, type, status FROM subjects WHERE parent_id = ? ORDER BY title ASC',
            [$id]
        );

        // All subjects for tree panel
        $allSubjects = $this->db->fetchAll(
            'SELECT id, parent_id, code, title, type, status FROM subjects ORDER BY title ASC'
        );

        // Content associations via subject_content bridge — each wrapped in try/catch; modules may not be installed
        $articles = [];
        try {
            $articles = $this->db->fetchAll(
                'SELECT a.id, a.title, a.slug, a.status, a.created_at
                 FROM articles a
                 INNER JOIN subject_content sc ON sc.item_type = ? AND sc.item_id = a.id
                 WHERE sc.subject_id = ?
                 ORDER BY a.created_at DESC',
                ['article', $id]
            );
        } catch (\Throwable) {}

        $events = [];
        try {
            $events = $this->db->fetchAll(
                'SELECT e.id, e.title, e.slug, e.status, e.date_start
                 FROM events e
                 INNER JOIN subject_content sc ON sc.item_type = ? AND sc.item_id = e.id
                 WHERE sc.subject_id = ?
                 ORDER BY e.date_start DESC',
                ['event', $id]
            );
        } catch (\Throwable) {}

        $availableArticles = [];
        try {
            $availableArticles = $this->db->fetchAll(
                'SELECT a.id, a.title, a.status, a.created_at
                 FROM articles a
                 WHERE NOT EXISTS (
                     SELECT 1 FROM subject_content sc
                     WHERE sc.item_type = ? AND sc.item_id = a.id AND sc.subject_id = ?
                 )
                 ORDER BY a.updated_at DESC
                 LIMIT 150',
                ['article', $id]
            );
        } catch (\Throwable) {}

        $availableEvents = [];
        try {
            $availableEvents = $this->db->fetchAll(
                'SELECT e.id, e.title, e.status, e.date_start
                 FROM events e
                 WHERE NOT EXISTS (
                     SELECT 1 FROM subject_content sc
                     WHERE sc.item_type = ? AND sc.item_id = e.id AND sc.subject_id = ?
                 )
                 ORDER BY COALESCE(e.date_start, e.created_at) DESC
                 LIMIT 150',
                ['event', $id]
            );
        } catch (\Throwable) {}

        $files = [];
        try {
            $files = $this->db->fetchAll(
                'SELECT f.id, f.name, f.mime_type, f.created_at
                 FROM files f
                 INNER JOIN subject_content sc ON sc.item_type = ? AND sc.item_id = f.id
                 WHERE sc.subject_id = ?
                 ORDER BY f.created_at DESC',
                ['file', $id]
            );
        } catch (\Throwable) {}

        $folders = [];
        try {
            $folders = $this->db->fetchAll(
                'SELECT fo.id, fo.name, fo.created_at
                 FROM folders fo
                 INNER JOIN subject_content sc ON sc.item_type = ? AND sc.item_id = fo.id
                 WHERE sc.subject_id = ?
                 ORDER BY fo.name ASC',
                ['folder', $id]
            );
        } catch (\Throwable) {}

        // All subjects except self for the settings form parent selector
        $parentSubjects = $this->db->fetchAll(
            'SELECT id, code, title FROM subjects WHERE id != ? ORDER BY title ASC',
            [$id]
        );

        // Discussions scoped to this subject
        $discussions = [];
        try {
            $discussions = $this->db->fetchAll(
                'SELECT d.id, d.title, d.category, d.pinned, d.locked, d.post_count, d.last_post_at, d.created_at,
                        u.display_name AS created_by_name
                 FROM discussions d
                 LEFT JOIN users u ON u.id = d.created_by
                 WHERE d.context_type = ? AND d.context_id = ?
                 ORDER BY d.pinned DESC, COALESCE(d.last_post_at, d.created_at) DESC',
                ['subject', (int) $id]
            );
        } catch (\Throwable) {}

        // Existing forum thread for this subject (forum module may not be installed)
        $forumThread = null;
        try {
            $forumThread = $this->db->fetch(
                'SELECT t.id, t.title, t.reply_count, t.last_post_at,
                        c.title AS category_title, c.slug AS category_slug
                 FROM forum_threads t
                 JOIN forum_categories c ON c.id = t.category_id
                 WHERE t.subject_id = ?
                 LIMIT 1',
                [(int) $id]
            ) ?: null;
        } catch (\Throwable) {}

        $this->renderAdmin('admin/subjects/workspace', [
            'title'          => $subject['title'] . ' — Subjects',
            'allSubjects'    => $allSubjects,
            'subject'        => $subject,
            'ancestors'      => $ancestors,
            'children'       => $children,
            'articles'       => $articles,
            'events'         => $events,
            'availableArticles' => $availableArticles,
            'availableEvents'   => $availableEvents,
            'files'          => $files,
            'folders'        => $folders,
            'parentSubjects' => $parentSubjects,
            'discussions'    => $discussions,
            'forumThread'    => $forumThread,
            'breadcrumbs'    => [['Admin', '/admin'], ['Subjects', '/admin/subjects'], [$subject['title']]],
        ]);
    }

    /**
     * POST /admin/subjects/{id}/articles/attach — Attach an existing article to this subject.
     */
    public function adminAttachArticle(string $id): void
    {
        Auth::requireAdmin();

        $subject = $this->db->fetch('SELECT id FROM subjects WHERE id = ?', [$id]);
        if (!$subject) {
            Auth::flash('error', 'Subject not found.');
            $this->redirect('/admin/subjects');
        }

        $articleId = (int) $this->input('article_id', 0);
        if ($articleId <= 0) {
            Auth::flash('error', 'Select an article to add.');
            $this->redirect('/admin/subjects/' . (int) $id);
        }

        $articleExists = false;
        try {
            $articleExists = (bool) $this->db->fetchColumn('SELECT id FROM articles WHERE id = ? LIMIT 1', [$articleId]);
        } catch (\Throwable) {}

        if (!$articleExists) {
            Auth::flash('error', 'Article not found.');
            $this->redirect('/admin/subjects/' . (int) $id);
        }

        try {
            $this->db->execute(
                'INSERT IGNORE INTO subject_content (subject_id, item_type, item_id) VALUES (?, ?, ?)',
                [(int) $id, 'article', $articleId]
            );
            Auth::flash('success', 'Article added to subject.');
        } catch (\Throwable) {
            Auth::flash('error', 'Could not add article to subject.');
        }

        $this->redirect('/admin/subjects/' . (int) $id);
    }

    /**
     * POST /admin/subjects/{id}/events/attach — Attach an existing event to this subject.
     */
    public function adminAttachEvent(string $id): void
    {
        Auth::requireAdmin();

        $subject = $this->db->fetch('SELECT id FROM subjects WHERE id = ?', [$id]);
        if (!$subject) {
            Auth::flash('error', 'Subject not found.');
            $this->redirect('/admin/subjects');
        }

        $eventId = (int) $this->input('event_id', 0);
        if ($eventId <= 0) {
            Auth::flash('error', 'Select an event to add.');
            $this->redirect('/admin/subjects/' . (int) $id);
        }

        $eventExists = false;
        try {
            $eventExists = (bool) $this->db->fetchColumn('SELECT id FROM events WHERE id = ? LIMIT 1', [$eventId]);
        } catch (\Throwable) {}

        if (!$eventExists) {
            Auth::flash('error', 'Event not found.');
            $this->redirect('/admin/subjects/' . (int) $id);
        }

        try {
            $this->db->execute(
                'INSERT IGNORE INTO subject_content (subject_id, item_type, item_id) VALUES (?, ?, ?)',
                [(int) $id, 'event', $eventId]
            );
            Auth::flash('success', 'Event added to subject.');
        } catch (\Throwable) {
            Auth::flash('error', 'Could not add event to subject.');
        }

        $this->redirect('/admin/subjects/' . (int) $id);
    }

    // ── Admin: New ────────────────────────────────────────────

    /**
     * POST /admin/subjects/{id}/discussion — Create a new discussion thread scoped to this subject.
     */
    public function adminCreateDiscussion(string $id): void
    {
        Auth::requireAdmin();

        $subject = $this->db->fetch('SELECT id, title FROM subjects WHERE id = ?', [$id]);
        if (!$subject) {
            Auth::flash('error', 'Subject not found.');
            $this->redirect('/admin/subjects');
        }

        $title = trim($this->input('title', ''));
        if ($title === '') {
            Auth::flash('error', 'Discussion title is required.');
            $this->redirect('/admin/subjects/' . (int) $id);
        }

        $body = trim($this->input('body', ''));
        $postCount  = $body !== '' ? 1 : 0;
        $lastPostAt = $body !== '' ? date('Y-m-d H:i:s') : null;

        $discussionId = $this->db->insert('discussions', [
            'title'        => $title,
            'category'     => $this->input('category') ?: null,
            'context_type' => 'subject',
            'context_id'   => (int) $id,
            'created_by'   => Auth::userId(),
            'pinned'       => 0,
            'locked'       => 0,
            'post_count'   => $postCount,
            'last_post_at' => $lastPostAt,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        if ($body !== '') {
            $this->db->insert('discussion_posts', [
                'discussion_id' => $discussionId,
                'author_id'     => Auth::userId(),
                'body'          => $body,
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
        }

        Auth::flash('success', 'Discussion created.');
        $this->redirect('/admin/subjects/' . (int) $id);
    }

    /**
     * POST /admin/subjects/{id}/forum-thread — Provision a public forum thread for this subject.
     */
    public function adminProvisionForumThread(string $id): void
    {
        Auth::requireAdmin();

        $subject = $this->db->fetch('SELECT * FROM subjects WHERE id = ?', [$id]);
        if (!$subject) {
            Auth::flash('error', 'Subject not found.');
            $this->redirect('/admin/subjects');
        }

        // Check a thread doesn't already exist
        $existing = null;
        try {
            $existing = $this->db->fetchColumn(
                'SELECT id FROM forum_threads WHERE subject_id = ? LIMIT 1',
                [(int) $id]
            );
        } catch (\Throwable) {}

        if ($existing) {
            Auth::flash('info', 'A forum thread already exists for this subject.');
            $this->redirect('/admin/subjects/' . (int) $id);
        }

        $service = new \Cruinn\Services\SubjectThreadProvisionService($this->db);
        $slug    = $subject['slug'] ?? ('subject-' . $id);
        $summary = $subject['description'] ?? '';

        $threadId = $service->ensurePublishedContentThread(
            'subject',
            (int) $id,
            (int) $id,
            $subject['title'],
            $slug,
            $summary,
            Auth::userId()
        );

        if ($threadId) {
            Auth::flash('success', 'Forum thread provisioned.');
        } else {
            Auth::flash('error', 'Could not provision forum thread — check the Forum module is active and has a category configured.');
        }

        $this->redirect('/admin/subjects/' . (int) $id);
    }

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
