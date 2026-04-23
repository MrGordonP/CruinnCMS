<?php
/**
 * CruinnCMS — Content Set Controller
 *
 * Manages dynamic content sets and their row data.
 * All routes require 'admin' role (enforced by prefix middleware).
 */

namespace Cruinn\Admin\Controllers;

use Cruinn\Auth;
use Cruinn\Services\QueryBuilderService;

class ContentSetController extends \Cruinn\Controllers\BaseController
{
    // ── Sets ──────────────────────────────────────────────────────────

    /**
     * GET /admin/content — List all content sets.
     */
    public function index(): void
    {
        $sets = $this->db->fetchAll(
            'SELECT cs.*, COUNT(r.id) AS row_count
             FROM content_sets cs
             LEFT JOIN content_set_rows r ON r.set_id = cs.id
             GROUP BY cs.id
             ORDER BY cs.name ASC'
        );

        $this->renderAdmin('admin/content/index', [
            'title'       => 'Content Sets',
            'sets'        => $sets,
            'breadcrumbs' => [['Admin', '/admin'], ['Content Sets']],
        ]);
    }

    /**
     * GET /admin/content/new — New set form.
     */
    public function newSet(): void
    {
        $svc    = new QueryBuilderService($this->db);
        $tables = $svc->getTables();
        $this->renderAdmin('admin/content/edit-set', [
            'title'       => 'New Content Set',
            'set'         => null,
            'dbTables'    => $tables,
            'breadcrumbs' => [['Admin', '/admin'], ['Content Sets', '/admin/content'], ['New']],
        ]);
    }

    /**
     * POST /admin/content — Create a new content set.
     */
    public function createSet(): void
    {
        $errors = $this->validateRequired(['name' => 'Name', 'slug' => 'Slug']);
        if (!empty($errors)) {
            Auth::flash('error', implode(' ', $errors));
            $this->redirect('/admin/content/new');
        }

        $slug = $this->sanitiseSlug($this->input('slug'));
        if ($this->db->fetch('SELECT id FROM content_sets WHERE slug = ?', [$slug])) {
            Auth::flash('error', 'A content set with that slug already exists.');
            $this->redirect('/admin/content/new');
        }

        $type = $this->input('type', 'manual') === 'query' ? 'query' : 'manual';

        if ($type === 'query') {
            $queryConfig = $this->parseQueryConfigPost();
            $id = $this->db->insert('content_sets', [
                'name'         => $this->input('name'),
                'slug'         => $slug,
                'type'         => 'query',
                'description'  => $this->input('description', ''),
                'fields'       => json_encode([], JSON_UNESCAPED_UNICODE),
                'query_config' => json_encode($queryConfig, JSON_UNESCAPED_UNICODE),
                'created_by'   => Auth::userId(),
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);
            $this->logActivity('create', 'content_set', (int) $id, $this->input('name'));
            Auth::flash('success', 'Content set created.');
            $this->redirect("/admin/content/{$id}/edit");
        }

        $fields = $this->parseFieldsPost();
        $id = $this->db->insert('content_sets', [
            'name'        => $this->input('name'),
            'slug'        => $slug,
            'type'        => 'manual',
            'description' => $this->input('description', ''),
            'fields'      => json_encode($fields, JSON_UNESCAPED_UNICODE),
            'created_by'  => Auth::userId(),
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        $this->logActivity('create', 'content_set', (int) $id, $this->input('name'));
        Auth::flash('success', 'Content set created.');
        $this->redirect("/admin/content/{$id}/rows");
    }

    /**
     * GET /admin/content/{id}/edit — Edit set schema.
     */
    public function editSet(string $id): void
    {
        $set = $this->requireSet((int) $id);
        $set['fields']       = json_decode($set['fields']       ?? '[]', true) ?: [];
        $set['query_config'] = json_decode($set['query_config'] ?? '{}', true) ?: [];
        $svc    = new QueryBuilderService($this->db);
        $tables = $svc->getTables();

        $rows = [];
        if (($set['type'] ?? 'manual') === 'manual') {
            $rawRows = $this->db->fetchAll(
                'SELECT * FROM content_set_rows WHERE set_id = ? ORDER BY sort_order ASC, id ASC',
                [(int) $id]
            );
            foreach ($rawRows as $r) {
                $r['data'] = json_decode($r['data'] ?? '{}', true) ?: [];
                $rows[] = $r;
            }
        }

        $this->renderAdmin('admin/content/edit-set', [
            'title'       => 'Edit: ' . $set['name'],
            'set'         => $set,
            'dbTables'    => $tables,
            'rows'        => $rows,
            'breadcrumbs' => [['Admin', '/admin'], ['Content Sets', '/admin/content'], [e($set['name'])]],
        ]);
    }

    /**
     * GET /admin/content/{id}/preview — Run saved query config and return JSON rows.
     * Query sets only; manual sets return their stored rows.
     */
    public function previewSet(string $id): void
    {
        $set = $this->requireSet((int) $id);

        if (($set['type'] ?? 'manual') === 'query') {
            $config = json_decode($set['query_config'] ?? '{}', true) ?: [];
            try {
                $svc  = new QueryBuilderService($this->db);
                $rows = $svc->run($config);
                $columns = !empty($rows) ? array_keys($rows[0]) : [];
                $this->json(['ok' => true, 'columns' => $columns, 'rows' => $rows, 'count' => count($rows)]);
            } catch (\Throwable $e) {
                $this->json(['ok' => false, 'error' => $e->getMessage()]);
            }
        } else {
            $rawRows = $this->db->fetchAll(
                'SELECT * FROM content_set_rows WHERE set_id = ? ORDER BY sort_order ASC, id ASC',
                [(int) $id]
            );
            $fields = json_decode($set['fields'] ?? '[]', true) ?: [];
            $columns = array_column($fields, 'name');
            $rows = [];
            foreach ($rawRows as $r) {
                $rows[] = json_decode($r['data'] ?? '{}', true) ?: [];
            }
            $this->json(['ok' => true, 'columns' => $columns, 'rows' => $rows, 'count' => count($rows)]);
        }
    }

    /**
     * POST /admin/content/{id} — Save set schema changes.
     */
    public function updateSet(string $id): void
    {
        $set = $this->requireSet((int) $id);

        $errors = $this->validateRequired(['name' => 'Name', 'slug' => 'Slug']);
        if (!empty($errors)) {
            Auth::flash('error', implode(' ', $errors));
            $this->redirect("/admin/content/{$id}/edit");
        }

        $slug = $this->sanitiseSlug($this->input('slug'));
        $existing = $this->db->fetch('SELECT id FROM content_sets WHERE slug = ? AND id != ?', [$slug, $id]);
        if ($existing) {
            Auth::flash('error', 'A content set with that slug already exists.');
            $this->redirect("/admin/content/{$id}/edit");
        }

        $type = $set['type'] ?? 'manual'; // type is fixed after creation

        if ($type === 'query') {
            $queryConfig = $this->parseQueryConfigPost();
            $this->db->update('content_sets', [
                'name'         => $this->input('name'),
                'slug'         => $slug,
                'description'  => $this->input('description', ''),
                'query_config' => json_encode($queryConfig, JSON_UNESCAPED_UNICODE),
                'updated_at'   => date('Y-m-d H:i:s'),
            ], 'id = ?', [(int) $id]);
        } else {
            $fields = $this->parseFieldsPost();
            $this->db->update('content_sets', [
                'name'        => $this->input('name'),
                'slug'        => $slug,
                'description' => $this->input('description', ''),
                'fields'      => json_encode($fields, JSON_UNESCAPED_UNICODE),
                'updated_at'  => date('Y-m-d H:i:s'),
            ], 'id = ?', [(int) $id]);
        }

        $this->logActivity('update', 'content_set', (int) $id, $this->input('name'));
        Auth::flash('success', 'Content set updated.');
        $this->redirect("/admin/content/{$id}/edit");
    }

    /**
     * POST /admin/content/{id}/delete — Delete a set and all its rows.
     */
    public function deleteSet(string $id): void
    {
        $set = $this->requireSet((int) $id);
        $this->db->execute('DELETE FROM content_sets WHERE id = ?', [(int) $id]);
        $this->logActivity('delete', 'content_set', (int) $id, $set['name']);
        Auth::flash('success', "Content set \"{$set['name']}\" deleted.");
        $this->redirect('/admin/content');
    }

    // ── Rows ─────────────────────────────────────────────────────────

    /**
     * GET /admin/content/{id}/rows — List rows in a set.
     */
    public function rows(string $id): void
    {
        $set  = $this->requireSet((int) $id);
        $set['fields'] = json_decode($set['fields'] ?? '[]', true) ?: [];

        // Query sets have no manual rows — send to edit
        if (($set['type'] ?? 'manual') === 'query') {
            Auth::flash('info', 'Query sets pull live data — no manual rows to manage.');
            $this->redirect('/admin/content/' . (int) $id . '/edit');
        }

        // No fields defined yet — send straight to schema editor
        if (empty($set['fields'])) {
            Auth::flash('info', 'Define your fields first, then you can add rows.');
            $this->redirect('/admin/content/' . (int) $id . '/edit');
        }
        $rows = $this->db->fetchAll(
            'SELECT * FROM content_set_rows WHERE set_id = ? ORDER BY sort_order ASC, id ASC',
            [(int) $id]
        );
        foreach ($rows as &$row) {
            $row['data'] = json_decode($row['data'] ?? '{}', true) ?: [];
        }
        unset($row);

        $this->renderAdmin('admin/content/rows', [
            'title'       => $set['name'] . ' — Rows',
            'set'         => $set,
            'rows'        => $rows,
            'breadcrumbs' => [['Admin', '/admin'], ['Content Sets', '/admin/content'], [e($set['name'])]],
        ]);
    }

    /**
     * POST /admin/content/{id}/rows — Add a new row.
     */
    public function addRow(string $id): void
    {
        $set    = $this->requireSet((int) $id);
        $fields = json_decode($set['fields'] ?? '[]', true) ?: [];

        $data = [];
        foreach ($fields as $field) {
            $key = $field['name'] ?? '';
            if ($key !== '') {
                $data[$key] = $this->input('field_' . $key, '');
            }
        }

        $maxOrder = (int) $this->db->fetchColumn(
            'SELECT COALESCE(MAX(sort_order), 0) FROM content_set_rows WHERE set_id = ?',
            [(int) $id]
        );

        $this->db->insert('content_set_rows', [
            'set_id'     => (int) $id,
            'data'       => json_encode($data, JSON_UNESCAPED_UNICODE),
            'sort_order' => $maxOrder + 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Auth::flash('success', 'Row added.');
        $this->redirect("/admin/content/{$id}/rows");
    }

    /**
     * POST /admin/content/{setId}/rows/{rowId} — Update a row.
     */
    public function updateRow(string $setId, string $rowId): void
    {
        $set    = $this->requireSet((int) $setId);
        $fields = json_decode($set['fields'] ?? '[]', true) ?: [];
        $row    = $this->db->fetch('SELECT * FROM content_set_rows WHERE id = ? AND set_id = ?', [(int) $rowId, (int) $setId]);
        if (!$row) {
            Auth::flash('error', 'Row not found.');
            $this->redirect("/admin/content/{$setId}/rows");
        }

        $data = [];
        foreach ($fields as $field) {
            $key = $field['name'] ?? '';
            if ($key !== '') {
                $data[$key] = $this->input('field_' . $key, '');
            }
        }

        $this->db->update('content_set_rows', [
            'data'       => json_encode($data, JSON_UNESCAPED_UNICODE),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [(int) $rowId]);

        Auth::flash('success', 'Row updated.');
        $this->redirect("/admin/content/{$setId}/rows");
    }

    /**
     * POST /admin/content/{setId}/rows/{rowId}/delete — Delete a row.
     */
    public function deleteRow(string $setId, string $rowId): void
    {
        $this->requireSet((int) $setId);
        $this->db->execute('DELETE FROM content_set_rows WHERE id = ? AND set_id = ?', [(int) $rowId, (int) $setId]);
        Auth::flash('success', 'Row deleted.');
        $this->redirect("/admin/content/{$setId}/rows");
    }

    /**
     * POST /admin/content/{setId}/rows/reorder — Update sort_order via drag-and-drop (JSON body).
     */
    public function reorderRows(string $setId): void
    {
        $this->requireSet((int) $setId);
        $order = json_decode(file_get_contents('php://input'), true)['order'] ?? [];
        foreach ($order as $position => $rowId) {
            $this->db->update('content_set_rows',
                ['sort_order' => (int) $position],
                'id = ? AND set_id = ?',
                [(int) $rowId, (int) $setId]
            );
        }
        $this->json(['ok' => true]);
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function requireSet(int $id): array
    {
        $set = $this->db->fetch('SELECT * FROM content_sets WHERE id = ?', [$id]);
        if (!$set) {
            Auth::flash('error', 'Content set not found.');
            $this->redirect('/admin/content');
        }
        return $set;
    }

    /**
     * Parse the field definitions from the POST body.
     * Expects parallel arrays: field_name[], field_label[], field_type[].
     */
    private function parseFieldsPost(): array
    {
        $names  = $_POST['field_name']  ?? [];
        $labels = $_POST['field_label'] ?? [];
        $types  = $_POST['field_type']  ?? [];
        $fields = [];
        $validTypes = ['text', 'richtext', 'image', 'url', 'date'];

        foreach ($names as $i => $name) {
            $name = trim(preg_replace('/[^a-z0-9_]/', '_', strtolower($name)), '_');
            if ($name === '') {
                continue;
            }
            $type = in_array($types[$i] ?? '', $validTypes, true) ? $types[$i] : 'text';
            $fields[] = [
                'name'  => $name,
                'label' => trim($labels[$i] ?? $name),
                'type'  => $type,
            ];
        }
        return $fields;
    }

    /**
     * Parse query config from POST body (hidden JSON field submitted by the query builder JS).
     */
    private function parseQueryConfigPost(): array
    {
        $raw = $_POST['query_config'] ?? '';
        $cfg = json_decode($raw, true);
        return is_array($cfg) ? $cfg : [];
    }
}
