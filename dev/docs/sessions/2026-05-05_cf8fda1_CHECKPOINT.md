# v1.0.0-beta.7 Checkpoint

**Version:** v1.0.0-beta.7
**HEAD:** 3abe4a5
**Date:** 2025-05-07

---

## What Changed

### 1. Platform migrations re-run feature
- Added "Re-run migrations" button to platform DB dashboard
- POST `/cms/database/migrate/rerun` runs all `migrations/core/*.sql` files unconditionally

### 2. setHomePage UPSERT fix
- Fixed 500 error on "Set as Home Page" — changed INSERT to `INSERT ... ON DUPLICATE KEY UPDATE`
- Affected: `AdminPageController::setHomePage()`

### 3. Documents module: hardcoded `public/` path fix
- Documents module was generating download URLs with `public/uploads/…` prefix
- Fixed to use `CRUINN_PUBLIC` via the `url()` helper

### 4. Drivespace module: hardcoded `public/` path fixes
- Same class of bug as documents — multiple paths fixed in service + controller

### 5. Theme System (new feature)

**Concept:** Flat `.css` files per theme stored in `public_html/css/themes/{name}.css`, each containing only a `:root {}` block of CSS custom properties. The active theme file overrides `style.css` variables without touching the main stylesheet.

**New files:**
- `public_html/css/themes/default.css` — 24 CSS custom properties seeded from `style.css :root {}`
- `CruinnCMS/src/Admin/Controllers/ThemeController.php` — GET redirect + POST save + static helpers

**Modified files:**
- `CruinnCMS/schema/instance_core.sql` — added `('site.active_theme', 'default', 'site')` seed
- `CruinnCMS/templates/layout.php` — loads `css/themes/{active_theme}.css` after `style.css`
- `CruinnCMS/config/routes.php` — `GET/POST /admin/theme` + `use ThemeController`
- `CruinnCMS/src/Controllers/CruinnController.php` — detection block for `_typography` slug page
- `CruinnCMS/templates/admin/editor.php` — canvas override (live preview) + right panel (Theme controls)

**Theme Editor entry point:**
- A seeded page with `slug = '_typography'` acts as the Theme Editor page
- Opening it in the block editor detects `isThemePage=true`
- Canvas becomes a live preview pane (colour swatches, typography, buttons, card, spacing)
- Right panel becomes Theme controls: CSS variable inputs grouped by section comment, colour pickers for hex values, JS updates `<style id="theme-preview-vars">` on every input event
- "Site Theme" link in editor left panel navigates to this page

**ThemeController static helpers:**
- `activeTheme()` — reads `site.active_theme` from settings, sanitises
- `themeFilePath(string $theme)` — returns `CRUINN_PUBLIC . '/css/themes/' . $theme . '.css'`
- `parseVariables(string $css)` — parses `:root {}` into `[['name','value','comment'], …]`
- `applyVariables(string $css, array $values)` — regex-replaces matching `--var: value;` lines in `:root {}`

### 6. Bug fixes during theme implementation
- `CruinnController::openEditor()` — double-comma caused 500 on any editor open
- `editor.php` theme vars loop — alternative-syntax `foreach` caused parse error; replaced with curly-brace syntax
- `CruinnController::edit()` — `$isThemePage`/`$themeVars` detection block missing; added explicitly

### 7. Mailbox route ordering fix
- `/mail/{mailbox_id}/compose` was matching against `/{folder}` route, trying to IMAP SELECT "compose" folder
- Moved `/compose` and `/search` routes before generic `/{folder}` route — specific routes must register first

### 8. Organisation module document link fixes
- Organisation dashboard + layout had hardcoded links to `/organisation/documents/upload` and `/organisation/documents/*`
- Those routes don't exist — module.php comment states "Document management lives in the documents module"
- Fixed all links to point to `/documents` module routes (`/documents/new`, `/documents/{id}`, etc.)
- Also updated orphaned templates in `modules/organisation/templates/organisation/documents/` (index.php, show.php, upload.php)
- Note: Those templates have no routes and shouldn't be accessible, but updating prevents issues with cached pages or future route additions

### 9. Document upload feedback indicators
- Added JavaScript to upload form (`/documents/new`) to show "Uploading..." feedback when submitting
- Added same feedback to version upload form on document detail page
- Disables submit button, changes text to "Uploading..." with hourglass emoji, sets opacity/cursor to indicate processing
- Prevents confusion when large files take time to upload

### 10. Organisation dashboard action button clarity
- Changed "⬆ Upload Doc" to "📄 New Document" for clarity
- Changed "+ Discussion" to "💬 New Discussion" for consistency
- Makes it clearer that the Document button creates a new document (via /documents module)

---

## Known Pending Issues

- **Save Theme 404** — POST `/admin/theme` route is registered and middleware does not produce a 404. Routes, CSRF, and middleware all check out in static analysis. Needs live testing.
- **Load Theme / theme switcher** — not yet built. Planned: dropdown of `.css` files in `public_html/css/themes/`, POST to `/admin/theme/activate`.

-abe4a5  fix(organisation): clarify action button labels on dashboard [v1.0.0-beta.7]
9b4b326  docs: fix checkpoint commits section [v1.0.0-beta.7]
183fa76  docs: update checkpoint — upload feedback indicators [v1.0.0-beta.7]
3--

## Commits in this session

```
399dfb3  feat(documents): add upload feedback indicators to prevent confusion during file uploads [v1.0.0-beta.7]
d625910  docs: update checkpoint — orphaned template fixes [v1.0.0-beta.7]
4278bee  fix(organisation): update orphaned document templates to use /documents paths [v1.0.0-beta.7]
fc0fdfe  docs: update checkpoint — organisation document link fixes [v1.0.0-beta.7]
4fc60e9  fix(organisation): update document links to use /documents module routes instead of nonexistent /organisation/documents [v1.0.0-beta.7]
115cd2b  docs: update checkpoint — mailbox route ordering fix [v1.0.0-beta.7]
5ff8b7e  fix(mailbox): reorder routes — /compose and /search before /{folder} to prevent misrouting [v1.0.0-beta.7]
cf8fda1  fix(editor): add missing isThemePage/themeVars detection block to edit() [v1.0.0-beta.7]
b772fd2  fix(editor): rewrite theme vars loop with curly-brace syntax [v1.0.0-beta.7]
12dbd85  fix(editor): fix double-comma parse error in openEditor render array [v1.0.0-beta.7]
1bc4e53  feat(theme): Theme Editor inside block editor — canvas preview, right panel controls [v1.0.0-beta.7]
0eb3834  feat(theme): add live preview pane to Theme Editor [v1.0.0-beta.7]
06f0433  feat(theme): theme system — default.css, settings seed, layout.php, routes, ThemeController [v1.0.0-beta.7]
74e1007  feat(editor): add Site Theme link to editor left panel [v1.0.0-beta.7]
d999484  fix(drivespace): replace hardcoded public/ path with CRUINN_PUBLIC [v1.0.0-beta.7]
c3281b2  fix(documents): replace hardcoded public/ path with CRUINN_PUBLIC [v1.0.0-beta.7]
bd334cf  fix(admin): setHomePage uses UPSERT to fix 500 on duplicate home setting [v1.0.0-beta.7]
d263fd1  feat(platform): add re-run migrations to DB dashboard [v1.0.0-beta.7]
```
