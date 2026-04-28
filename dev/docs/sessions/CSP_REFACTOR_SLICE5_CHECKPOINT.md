# CSP Inline-JS Extraction — Slice 5 Checkpoint

**HEAD:** `9c951d2`
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

### Slice 4 — `41f7195`
- `shell.js` extended with 8 document-level delegations (`data-confirm`, `data-show-id`, `data-hide-id`, `data-close-panel`, `data-action="window-close"`, `data-stop-propagation`, `data-media-input/preview`, `data-toggle-target/class`)
- 6 new page JS modules + 12+ templates cleaned (see slice 4 checkpoint)

### Slice 5 — `9c951d2` (current HEAD)

**New JS files created:**

| File | Extracted from | PHP data attrs used |
|---|---|---|
| `admin/layout-toggle.js` | `settings/_tabs_end.php`, `site-builder/_tabs_close.php`, `menus/edit.php` | none (reads CSRF from hidden `input[name="_csrf_token"]`) |
| `admin/roles.js` | `roles/edit.php`, `roles/index.php` | `data-role-id` on `.panel-layout` |
| `admin/groups.js` | `groups/index.php` | `data-group-id` on `.panel-layout` |
| `admin/member-search.js` | `users/show.php`, `users/edit.php` (typeahead block) | `data-search-url` on `#member-search-input` |
| `admin/user-profile.js` | `users/show.php`, `users/edit.php` (roles/groups AJAX) | `data-user-id` on outer container div |
| `admin/menus.js` | `menus/index.php` | `data-csrf` + `data-locations` on `#menus-layout` |
| `admin/pages.js` | `pages/index.php` | `data-csrf` + `data-templates` on `#pages-layout` |
| `admin/tab-insert.js` | `site-builder/template-editor-edit.php`, `pages/html-editor.php` | `data-tab-insert="true"` on `<textarea>` |

**Templates patched (inline handlers/blocks removed):**
- `admin/settings/_tabs_end.php` — `<script>` block → `requireJs('layout-toggle.js')`
- `admin/site-builder/_tabs_close.php` — `<script>` block → `requireJs('layout-toggle.js')`
- `admin/menus/edit.php` — `<script>` layout-toggle block → `requireJs('layout-toggle.js')`
- `admin/roles/edit.php` — `<script>` block → `data-role-id` on `#role-edit-layout` + `requireJs('roles.js')`
- `admin/roles/index.php` — `<script>` block → `data-role-id` on `#roles-layout` + `requireJs('roles.js')`
- `admin/groups/index.php` — `<script>` block → `data-group-id` on `#groups-layout` + `requireJs('groups.js')`
- `admin/users/show.php` — member-search `<script>` block → `data-search-url` on input + `requireJs('member-search.js')`; added `data-user-id` on `.user-detail` + `requireJs('user-profile.js')`
- `admin/users/edit.php` — both `<script>` blocks removed; `data-search-url` on input, `data-user-id` on outer grid div, `requireJs('member-search.js' + 'user-profile.js')`
- `admin/menus/index.php` — `<script>` block → `data-csrf`+`data-locations` on `#menus-layout` + `requireJs('menus.js')`
- `admin/pages/index.php` — `<script>` block → `data-csrf`+`data-templates` on `#pages-layout` + `requireJs('pages.js')`
- `admin/site-builder/template-editor-edit.php` — `<script>` Tab handler → `data-tab-insert` on textarea + `requireJs('tab-insert.js')`
- `admin/pages/html-editor.php` — `<script>` Tab handler → `data-tab-insert` on textarea + `requireJs('tab-insert.js')`

---

## Remaining Work (Next Slice)

### Files still containing inline `<script>` blocks:

| File | What's in it | Proposed JS file |
|---|---|---|
| `admin/media/index.php` | Two large script blocks (lines 112–346, 409–601) — not yet read in detail | `admin/media.js` |
| `admin/site-builder/structure.php` | Two script blocks (line 279 tiny, lines 418–774 large) | `admin/structure.js` |

These are the last two files. Once done, zero inline `<script>` blocks remain anywhere in the admin templates.

### Suggested scan to confirm nothing was missed:
```
grep -rl '<script>' CruinnCMS/templates/admin/
```

---

## Architecture Reference

- **Admin JS path:** `public_html/js/admin/`
- **Always-loaded modules:** `$adminModules` array in `templates/admin/layout.php`
- **Per-page JS:** `\Cruinn\Template::requireJs('filename.js')` at top of template
- **Layout flushes:** `Template::flushJs()` → `<script src="/js/admin/{file}">` in layout
- **PHP→JS data:** `data-*` on container element, read via `el.dataset` in JS
- **CSRF in AJAX:** `document.querySelector('meta[name="csrf-token"]')?.content` or explicit `data-csrf` attr
