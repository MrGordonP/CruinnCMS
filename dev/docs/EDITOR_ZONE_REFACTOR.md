# CruinnCMS — Editor & Zone Architecture Refactor

**Status:** Planning complete. Ready for implementation.  
**Version target:** v1.0.0-beta.8  
**Agreed:** May 2026

---

## Background

The current editor and render pipeline contain hardcoded structural assumptions that violate CruinnCMS's core engine principle: the engine makes no assumptions about site structure. These violations accumulated because the editor discriminated between canvas types, forcing structural content (headers, footers) to be stored as fake "pages" rather than zone canvases.

This document tracks the agreed remediation work across all four stages.

---

## Engine Principles (agreed, now in CRUINNCMS_REFERENCE.md)

- **No Structural Assumptions** — No zones, templates, pages, headers, footers, or structural elements may be hardcoded in the engine. The engine provides mechanism only.
- **Canvas Agnosticism** — The editor treats all canvases identically. What a canvas is used for is a labelling and routing concern, not an editor concern.
- **Zone / Page / Template Model** — Block → Page (collection of blocks) → Template (layout shell with named zone slots) → Page declares template + which zone its blocks inject into (`page_zone`). Header/footer/sidebar are zones, not special cases.

---

## Data Model (correct as-is, for reference)

| Concept | Storage | Notes |
|---|---|---|
| Block | `pages` / `pages_draft` rows | `block_id`, `parent_block_id`, `sort_order`, `css_props` JSON, `inner_html` |
| Page | `pages_index` row + child `pages` rows | `template` slug, `page_zone` (which zone slot content injects into) |
| Template | `page_templates` row | `zones` JSON array, optional `canvas_page_id` pointing to a block canvas defining the shell |
| Zone content | A `pages_index` canvas assigned to a zone slot | No special type — just a canvas |
| Header/Footer/Sidebar | Same as zone content above | Currently disguised as system pages with `_` slug prefix |

`page_zone` on `pages_index` is **intentionally correct** — it answers "which template zone slot does this page's block tree inject into." The column stays; it just needs to be surfaced properly in admin UI.

---

## Todo List

### Stage 1 — Remove engine hardcoding (removals/simplifications only)

- [ ] **1. `editor.js` ~line 1992-1993**  
  Remove the `header`/`footer` zone skip in the page-zone selector's `refreshZoneOptions()` function.  
  The editor must not know or care what zone names mean.  
  ```js
  // DELETE these two lines:
  // Skip header/footer zones - they're for template layout, not page content
  if (zone === 'header' || zone === 'footer') { return; }
  ```

- [ ] **2. `editor.js` ~line 430**  
  Move hardcoded `['main', 'header', 'footer', 'sidebar']` zone name suggestions out of JS.  
  **Agreed approach:** Store as `settings` key `editor.zone_suggestions` (value: `main,header,footer,sidebar`), seeded in `instance_core.sql`. Pass to editor via `data-zone-suggestions` on `#editor-wrap`. JS reads the attribute only — knows nothing about the names.  
  - Add `editor.zone_suggestions` seed to `instance_core.sql` settings INSERT  
  - Pass the setting via `data-zone-suggestions` in the editor template (CruinnController or AdminPageController)  
  - Update `getAvailableZoneNames()` in editor.js to use `wrap.dataset.zoneSuggestions`

- [ ] **3. `PageController.php` — Collapse two-path header resolution**  
  Remove the `header_source` / `_global_header` branching and the `tpl_header_blocks` / `_header` fallback.  
  Header is a zone — resolve it the same way `resolveSidebarRender()` works today.  
  Unify into a single `resolveZoneRender(string $zoneName, array $tpl, CruinnRenderService $cruinn): array` method.  
  Remove `tpl_header_blocks` and `tpl_footer_blocks` as Template globals.

- [ ] **4. `layout.php` — Remove hardcoded zone HTML**  
  Remove the hardcoded `<header class="site-header ...">` and `<footer class="site-footer ...">` output blocks (currently two parallel if/else branches each).  
  Zone rendering should be generic: iterate the template's zone definitions and render each one into its declared position.  
  The template's `zones` array + `settings` JSON (which already has `show_header`, `show_footer` etc.) drives what renders and where.

- [ ] **5. `instance_core.sql` — Strip structural seeds**  
  Remove from the seeded INSERTs:  
  - The 6 default `page_templates` rows (default, full-width, landing, blank, sidebar-right, sidebar-left)  
  - The system zone `pages_index` rows (`_header`, `_footer`, `_tpl__global_header`, `_tpl_default`, `_typography`)  
  - The seed `pages` blocks for header and footer  
  Write a **migration file** (`migrations/core/NNN_remove_structural_seeds.sql`) to remove these from existing instances. A fresh install is clean; existing instances get them cleaned up on next migration run.  
  Note: `editor.zone_suggestions` setting seed (from item 2) stays — that is configuration, not structural assumption.

- [ ] **6. `SiteBuilderController.php` ~line 37-38`**  
  Remove the hardcoded `WHERE slug = '_header'` query. Identify what it's used for and replace with a proper lookup that doesn't assume a slug naming convention.

---

### Stage 2 — Schema alignment

- [ ] **7. `page_zone` admin UI**  
  No schema change. Ensure the `page_zone` selector in the admin page editor:  
  - Shows all zones defined on the assigned template (not a hardcoded list)  
  - Is labelled clearly ("Content zone — which zone in this template your page content fills")  
  - Defaults to the first non-structural zone on the template if not set

- [ ] **8. `CruinnRenderService::buildZone()`**  
  Currently does `slug = '_' . $zone` — a hardcoded naming convention.  
  Replace with a query against `page_templates` zone slot definitions so any zone name works without relying on the underscore-prefix slug convention.  
  Requires agreeing on how zone-to-canvas assignment is stored (currently implicit via slug; needs to be explicit via a zone-canvas mapping — possibly a new `template_zones` table or a JSON field on `page_templates`).

---

### Stage 3 — Theme scaffolding

- [ ] **9. `CruinnCMS/themes/default/seed.sql`**  
  Create a theme seed file. Move all structural seeds removed from `instance_core.sql` (item 5) here:  
  - Header, footer, typography canvases  
  - Default page templates  
  - Any other opinionated structural defaults  
  This file is applied explicitly when a user activates the default theme — not at install time.

- [ ] **10. `ThemeController` — Apply theme seed action**  
  Add `POST /admin/theme/apply-seed` route + controller method.  
  Reads `themes/{slug}/seed.sql`, runs it against the active instance DB.  
  Called once on first theme activation. Idempotent (use INSERT IGNORE or similar).

---

### Stage 4 — Editor in-situ context rendering (largest piece, depends on 1–8)

- [ ] **11. Editor context rendering**  
  When opening a page for editing, load the template's zone canvases (header, footer, sidebar etc.) and render them as non-editable chrome surrounding the primary canvas.  
  - Server side: editor endpoint passes `contextCanvases` (array of `{zone, pageId, html, css}`) alongside primary `pageId`  
  - JS: renders context zones as `[data-context-zone]` elements, positioned above/below/beside `#editor-canvas`  
  - Context zones are locked (no selection, no drag, no properties panel trigger)  
  - Visually distinct: dimmed or labelled ("Header — click to edit")

- [ ] **12. Canvas switching from context zones**  
  Clicking a locked context zone shows a "Edit [zone name]" affordance.  
  Activating it navigates the editor to that zone's canvas page ID (same editor route, different `pageId`).  
  Back button / breadcrumb returns to the original page canvas.

---

## Files Affected (summary)

| File | Stage | Nature of change |
|---|---|---|
| `public_html/js/editor.js` | 1, 4 | Remove hardcoding; add context zone rendering |
| `CruinnCMS/src/Controllers/PageController.php` | 1 | Collapse two-path header resolution |
| `CruinnCMS/templates/layout.php` | 1 | Remove hardcoded zone HTML; generic zone iteration |
| `CruinnCMS/schema/instance_core.sql` | 1 | Remove structural seeds |
| `CruinnCMS/migrations/core/NNN_remove_structural_seeds.sql` | 1 | New migration for existing instances |
| `CruinnCMS/src/Admin/Controllers/SiteBuilderController.php` | 1 | Remove hardcoded `_header` slug query |
| `CruinnCMS/src/Services/CruinnRenderService.php` | 2 | Replace slug-convention zone lookup |
| `CruinnCMS/themes/default/seed.sql` | 3 | New file — moved structural defaults |
| `CruinnCMS/src/Admin/Controllers/ThemeController.php` | 3 | Add seed apply action |
| `CruinnCMS/src/Admin/Controllers/AdminPageController.php` | 4 | Pass context canvases to editor |

---

## Implementation Order

Stages 1 → 2 must be done in order (2 depends on 1 being clean).  
Stage 3 is independent — can be done any time after Stage 1 item 5.  
Stage 4 can only land cleanly after Stages 1 and 2 are complete.

Within Stage 1, items 1–6 can largely be done together in one pass since they are all removals.
