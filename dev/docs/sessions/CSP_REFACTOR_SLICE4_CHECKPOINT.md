# CSP Inline-JS Extraction — Slice 4 Checkpoint

**HEAD:** `41f7195`
**Branch:** `main`
**Version:** `v1.0.0-beta.6`
**Date:** 2026-04-28

---

## Contract

Zero inline `<script>` blocks, zero `on*` attributes, zero `javascript:` URLs in any PHP template.
All scripts must be `src="/js/..."` external only.
PHP → JS data handoff via `data-*` attributes on container elements only.

---

## What Has Been Done (all pushed, clean working tree)

### Slice 1 — `fcbd5c8` / `293b309`
- `templates/platform/layout.php`
- `templates/platform/source-editor.php`
- `src/Platform/Controllers/PlatformController.php`

### Slice 2 — `d14b125`
- `templates/platform/migrations.php` → `public_html/js/platform/migrations.js`
- `templates/platform/database-table.php` → `public_html/js/platform/database-table.js`

### Slice 3 — `97392dc`
- `templates/admin/layout.php`
- `templates/admin/editor.php`
- New: `public_html/js/admin/boot.js`, `shell.js`, `editor-shell.js`

### Slice 3b — `666b813`
- `templates/admin/settings/database-browse.php` → `public_html/js/admin/database-browse.js`

### Slice 4 — `41f7195` (current HEAD)

**shell.js extended** with 8 document-level delegations:
- `data-confirm` (submit + click)
- `data-show-id` + `data-hide-self`
- `data-hide-id`
- `data-close-panel="panelClass"` (hides panel, restores install btn in `.module-card`)
- `data-action="window-close"`
- `data-stop-propagation`
- `data-media-input` + `data-media-preview`
- `data-toggle-target` + `data-toggle-class` (checkbox `change` event)

**New JS files created:**
| File | Extracted from | PHP data attrs used |
|---|---|---|
| `admin/content-set-editor.js` | `content/edit-set.php` script block | `data-tables`, `data-filter-ops`, `data-preview-url`, `data-set-type` on `#set-editor` |
| `admin/content-rows.js` | `content/rows.php` script block | `data-set-id`, `data-edit-row-id` on `#rows-app` |
| `admin/template-settings.js` | `site-builder/template-settings.php` onchange IIFE | none (uses existing element ids) |
| `admin/maintenance-migrations.js` | `maintenance/migrations.php` onclick | none (uses `id="admin-migrations-apply"`) |
| `admin/import-review.js` | `import/review.php` script block | none (uses `.import-row` class) |
| `admin/templates.js` | `site-builder/templates.php` script block | none (uses existing element ids) |

**Templates patched (inline handlers removed):**
- `admin/settings/database.php` — 2 confirms
- `admin/settings/modules.php` — 3 confirms + show/hide panel handlers (7 total)
- `admin/settings/site.php` — 2 media browser callbacks
- `admin/subjects/edit.php` — 1 onsubmit confirm
- `admin/roles/edit.php` — 1 onsubmit confirm (script block NOT yet extracted)
- `admin/roles/index.php` — 1 onsubmit confirm (script block NOT yet extracted)
- `admin/groups/index.php` — 1 onsubmit confirm (script block NOT yet extracted)
- `admin/content/index.php` — 1 onsubmit confirm
- `admin/content/edit-set.php` — all onclick + oninput handlers + full script block removed
- `admin/content/rows.php` — all onclick + onsubmit handlers + full script block removed
- `admin/import/review.php` — onchange + script block removed
- `admin/maintenance/migrations.php` — onclick removed
- `admin/site-builder/pages.php` — 1 stopPropagation onclick
- `admin/site-builder/template-preview.php` — 1 window.close onclick
- `admin/site-builder/template-settings.php` — onchange IIFE + inline JS delete button → real `<form>`
- `admin/site-builder/templates.php` — 1 confirm onclick + script block removed
- `admin/users/show.php` — 3 confirms (script blocks NOT yet extracted)
- `admin/users/edit.php` — 3 confirms (script blocks NOT yet extracted)

---

## Remaining Work (Next Slice)

### Files still containing inline `<script>` blocks or `on*` handlers:

| File | What's in it | Proposed JS file |
|---|---|---|
| `admin/menus/index.php` | AJAX menu panel loader + `showDetail()` fn. PHP injects `csrfToken` + `locations`. Inner HTML contains `onsubmit` confirm. | `admin/menus.js` |
| `admin/pages/index.php` | Large AJAX page list/filter/detail. PHP injects `csrfToken` + `templates` JSON. Inner HTML confirm. | `admin/pages.js` |
| `admin/roles/edit.php` | AJAX role member management (add/remove, colour preview, perm toggle-all). PHP injects `$selectedId`. | `admin/roles.js` (shared) |
| `admin/roles/index.php` | Identical script to roles/edit.php. | same `admin/roles.js` |
| `admin/groups/index.php` | AJAX group/position/member management. PHP injects `$selectedId`. | `admin/groups.js` |
| `admin/users/show.php` | Two blocks: (a) member search typeahead (PHP URL), (b) user role/group management (PHP userId). | `admin/member-search.js` + `admin/user-profile.js` |
| `admin/users/edit.php` | Same two blocks as show.php. | same shared files |
| `admin/menus/edit.php` | Script block at line ~414 — not yet read | TBD |
| `admin/media/index.php` | Two script blocks — not yet read | TBD |
| `admin/site-builder/structure.php` | Two script blocks — not yet read | TBD |
| `admin/site-builder/template-editor-edit.php` | Script block at line ~45 — not yet read | TBD |
| `admin/pages/html-editor.php` | Script block at line ~46 — not yet read | TBD |
| `admin/site-builder/_tabs_close.php` | Shared include — script at line 4, covers many pages | TBD |
| `admin/settings/_tabs_end.php` | Shared include — script at line 4, covers many pages | TBD |

### Priority order for next slice:
1. Read the shared includes (`_tabs_close.php`, `_tabs_end.php`) — fix covers multiple pages at once
2. Extract `admin/roles.js` (covers two files)
3. Extract `admin/groups.js`
4. Extract `admin/member-search.js` + `admin/user-profile.js` (covers show + edit)
5. Extract `admin/menus.js` + `admin/pages.js`
6. Read and assess the unread files (menus/edit, media, structure, template-editor-edit, html-editor)

---

## Architecture Reference

- **Admin JS path:** `public_html/js/admin/`
- **Always-loaded modules:** `$adminModules` array in `templates/admin/layout.php`
- **Per-page JS:** `\Cruinn\Template::requireJs('filename.js')` at top of template
- **Layout flushes:** `Template::flushJs()` → `<script src="/js/admin/{file}">` in layout
- **PHP→JS data:** `data-*` on container element, read via `el.dataset` in JS
- **CSRF in AJAX:** `document.querySelector('meta[name="csrf-token"]')?.content` or explicit `data-csrf` attr
