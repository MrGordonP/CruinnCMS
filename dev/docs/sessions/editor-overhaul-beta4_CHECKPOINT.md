# Session Checkpoint — editor-overhaul-beta4
**Branch:** `editor-overhaul-beta4`
**Date:** 23 April 2026
**Base commit at session start:** `8bccbf2` — fix: missing <?php tags in roles/groups edit templates, nav dropdown gap, debug output visibility, activity_log migration [v1.0.0-beta.9]

---

## What This Branch Covers

This branch is a running overhaul accumulating across multiple sessions. This checkpoint documents all work staged across the full branch to date, with particular focus on the final session's additions: **System B — content binding / data-driven pages**.

---

## System B: Content Binding & Data-Driven Pages

### Goal
Allow a `data-list` block on any page to loop over a content set (query or manual) and render each row using a visually designed **content template**, with individual block fields bound to content set columns.

### New Database Migrations
- `migrations/core/003_content_templates.sql` — `page_templates` table: `id`, `name`, `template_type` (`content`/`layout`), `canvas_page_id`
- `migrations/core/004_content_template_context_source.sql` — adds `context_source VARCHAR(255)` to `page_templates`; used to declare e.g. `content_set:counci-current`

### `CruinnController.php` additions
- **`resolveContextFields()`** — called by the editor endpoint; resolves the `context_source` on a page's template, runs the query (LIMIT 1) via `QueryBuilderService`, and returns bind-able field keys derived from actual result columns (not static JSON). Works for both `type=query` and `type=manual` content sets.
- **`edit()`** — now also fetches all `template_type='content'` templates from `page_templates` and passes them as `contentTemplates` to the editor render array.
- **`publishPage()`** — unchanged; publish path already correct.

### `CruinnRenderService.php` additions
- **`setContext(array $row)`** — sets a per-render context row; bindings (`cfg['bind']['inner_html']` etc.) resolve to values from this row.
- **`buildHtml(int $pageId)`** — renders a template canvas at the given `page_id` using the current context row. Called once per data row in `data-list` rendering.
- **`resolveBinding()`** — resolves a single bind slot against the context row.

### `BlockTypes/data-list/definition.php`
- Added `template_slug` config key alongside existing `set_slug`/`view`/`card_html`.
- When `template_slug` is set: fetches the matching `page_templates` record, resolves its `canvas_page_id`, instantiates `CruinnRenderService`, calls `setContext($row)` + `buildHtml($canvasPageId)` for each row, wraps in `cruinn-data-list-item` divs.
- Falls back to legacy `card_html` token substitution when no template is set.
- **Bug fixed:** File previously had a duplicate `BlockRegistry::register()` call (old version at bottom of file) which overwrote the new registration on every load. Second registration removed.

### `SiteBuilderController.php`
- `builderEditTemplate()` now fetches `$contentSets` from DB and passes to `template-settings.php`.

### `templates/admin/site-builder/template-settings.php`
- **Data Source** field changed from free-text input to a `<select>` with optgroups: *Content Sets* (from DB) and *Built-in* (`blog.post`, `blog.list`).

### `templates/admin/editor.php`
- **Bind accordion HTML fixed**: was nested inside an unclosed `php-code` accordion div — moved to correct position after the real php-code accordion.
- `data-list` config group: added *Content Template* `<select>` (`data-config="template_slug"`) populated from `$contentTemplates`.
- Card HTML wrap (`#prop-data-list-card-wrap`) hidden/shown based on whether a template is selected.

### `public_html/js/editor.js`
- **`populateBindAccordion()`**: bind `onchange` handler now calls `recordAction()` after updating `block.dataset.blockConfig`. Without this, binding changes were never written to `pages_draft` and were lost on publish.
- Show/hide logic for `#prop-data-list-card-wrap` when `template_slug` select changes on a `data-list` block.
- `BIND_INNER_TYPES`, `BIND_SRC_TYPES`, `BIND_HREF_TYPES` — bind slot type arrays.
- `populateBindAccordion()` — populates bind selects with `CONTEXT_FIELDS`, restores saved binding from `block_config.bind`, writes changes back to `block.dataset.blockConfig` and records action.

---

## Other Changes Accumulated on This Branch

### Roles & Permissions System (RoleService, RoleAdminController, templates)
- `RoleService.php` — substantially extended: permission resolution, dashboard config, navigation config, role inheritance.
- `RoleAdminController.php` — full CRUD for roles; dashboard config + navigation config sub-pages.
- `templates/admin/roles/` — `index.php`, `edit.php`, `dashboard-config.php`, `navigation-config.php`, `_role-fields.php` (partial).

### Groups (GroupController, templates)
- `GroupController.php` — extended with group positions, member assignment.
- `templates/admin/groups/index.php` / `edit.php` — full group management UI with positions and member list.

### Users (UserAdminController, templates)
- `UserAdminController.php` — role/group assignment, profile editing.
- `templates/admin/users/edit.php` — expanded user edit form.

### Membership Module
- `MembershipAdminController.php` — members index improvements.
- `templates/admin/membership/members/index.php` — full member list table.

### Auth
- `Auth.php` — role/permission checking extensions.

### Content Sets
- `ContentSetController.php` — extended with query builder preview.
- `templates/admin/content/edit-set.php` — query builder UI additions.

### Maintenance
- `MaintenanceController.php` — link checker and migration runner additions.
- `templates/admin/maintenance/link-check.php` / `migrations.php` — UI updates.

### Schema
- `instance_core.sql` — updated with group_positions, content_templates, context_source columns.
- `migrations/core/002_group_positions.sql` — group_positions table.
- `migrations/core/005_users_groups_roles.sql` — users/groups/roles relationship tables.

### CSS
- `public_html/css/admin-panel-layout.css` — 3-panel admin layout additions.

### Blog Module
- `ArticleEditorController.php` — minor fix.

### App / Router
- `App.php` — uninitialized/no-instance guard adjustments.
- `config/routes.php` — new routes for roles, groups, content sets, template editor.

---

## Bugs Fixed This Session
| Bug | Root cause | Fix |
|-----|-----------|-----|
| Council page output was "Council Current / New text block." × 4 | Duplicate `BlockRegistry::register()` at bottom of `data-list/definition.php` — second call overwrote first, template rendering code never ran | Removed the old second registration |
| Bind changes not persisting | `bind onchange` wrote to `block.dataset.blockConfig` but never called `recordAction()`, so `pages_draft` never updated | Added `recordAction()` call after writing bind config |
| `resolveContextFields` returning empty | Was reading static `fields: []` from `content_sets` row; query-type sets have no static fields | Now runs the query (LIMIT 1) via `QueryBuilderService` and derives keys from real result columns |
| Bind accordion not visible | Was HTML-nested inside an unclosed `php-code` div in `editor.php` | Moved to correct position after closing the php-code accordion div |

---

## State at Checkpoint
- Content binding end-to-end working: template canvas blocks bind to content set fields, `data-list` renders per-row using the template via `CruinnRenderService`.
- All changes uncommitted at time of writing — committed immediately after this file.
