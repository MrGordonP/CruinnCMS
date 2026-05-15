# CruinnCMS — Editor & Zone Architecture Refactor

**Status:** In progress. Stage 4 extended with render pipeline unification.  
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

## Data Model (agreed target)

| Concept | Storage | Notes |
|---|---|---|
| Block | `pages` / `pages_draft` rows | `block_id`, `parent_block_id`, `sort_order`, `css_props` JSON, `inner_html` |
| Page | `pages_index` row + child `pages` rows (`page_id`) | `template` slug, `page_zone` (which zone slot content injects into) |
| Template layout blocks | `pages` rows with `template_id` set (not `page_id`) | Direct ownership — no `pages_index` proxy row needed |
| Template | `page_templates` row | `zones` JSON array, `zone_canvases` JSON, `settings` JSON. `canvas_page_id` **deprecated** — template blocks now live via `template_id` in `pages` |
| Zone canvas content | `pages_index` row with `canvas_type='zone'` + child `pages` rows | Independently editable shared content (header, footer, sidebar etc.) |
| Header/Footer/Sidebar | Zone canvas content above | Currently disguised as system pages with `_` slug prefix — to be cleaned up in Stage 1 |

`page_zone` on `pages_index` is **intentionally correct** — it answers "which template zone slot does this page's block tree inject into." The column stays; it just needs to be surfaced properly in admin UI.

**Key principle:** `pages_index` is the page registry only — it records what template a page uses and which zone its blocks inject into. Template layout structure belongs directly on the template, not via a proxy `pages_index` record.

---

## Todo List

### Stage 1 — Remove engine hardcoding (removals/simplifications only)

- [x] **1. `editor.js` — `refreshZoneOptions()` header/footer skip**  
  Already clean — no zone name skip present.

- [x] **2. `editor.js` — hardcoded zone name suggestions**  
  Already done — `editor.zone_suggestions` seeded in `instance_core.sql`; editor reads `wrap.dataset.zoneSuggestions`.

- [x] **3. `PageController.php` — Collapse two-path header resolution**  
  Remove the `header_source` / `_global_header` branching and the `tpl_header_blocks` / `_header` fallback.  
  Header is a zone — resolve it the same way `resolveSidebarRender()` works today.  
  Unify into a single `resolveZoneRender(string $zoneName, array $tpl, CruinnRenderService $cruinn): array` method.  
  Remove `tpl_header_blocks` and `tpl_footer_blocks` as Template globals.  
  _Note: `tpl_header_blocks`/`tpl_footer_blocks` removed from `SiteBuilderController::builderPreviewTemplate()` (dead code — table gone). Full PageController collapse deferred to Stage 4b._

- [x] **4. `layout.php` — Reduce to HTML document shell**  
  Remove all structural content from layout.php: `<header>`, `<aside>`, `<footer>` wrappers, `$_headerHtml`, `$_footerHtml`, `$_sidebarHtml`, `show_header`/`show_footer` flags, `site-body-wrap`, `<main id="main-content">`.  
  layout.php becomes: `<!DOCTYPE html><html><head>…</head><body><?= $pageHtml ?></body></html>` plus scripts, CSS links, flash messages.  
  All structural content is produced by `buildWithTemplate()` in Stage 4 (items 13–15). This item is a **cleanup** that lands after Stage 4 is complete.

- [x] **5. `instance_core.sql` — Strip structural seeds**  
  Already clean — no `page_templates` or `pages_index` structural seeds present. `editor.zone_suggestions` setting remains (configuration, not structural).

- [x] **6. `SiteBuilderController.php` — Remove hardcoded `_header`/`_footer` slug queries**  
  Replaced with `SELECT ... WHERE canvas_type = 'zone'` in `builderStructure()`. Data array now passes `zoneCanvases` instead of `headerPages`/`footerPages`. Dead `tpl_header_blocks`/`tpl_footer_blocks` globals removed from `builderPreviewTemplate()`.

---

### Stage 2 — Schema alignment

- [x] **7. `page_zone` admin UI**  
  Done — `pages.js` now shows all template zones without filtering. `AdminPageController::resolvePageZoneForTemplate()` validates against the full template zones array. No hardcoded zone name exclusions.

- [x] **8. `CruinnRenderService::buildZone()` — legacy slug fallback**  
  The 4-level resolution (page override → template zone_canvases → global canvas_type='zone' → legacy _slug) was already in place. Levels 1–3 provide fully explicit resolution. The `_slug` fallback (level 4) is annotated as deprecated and will be removed once all instances have been seeded via the theme seed. No immediate removal — safe degradation for pre-seed instances.

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

### Stage 4 — Render pipeline unification + editor context rendering (depends on 1–8)

#### 4a — Schema: template blocks ownership

- [x] **11. `pages` table — add `template_id` column**  
  Done — `template_id INT UNSIGNED NULL` added to `pages` and `pages_draft`. `page_id` made nullable. Deferred FKs added after `page_templates` definition. Schema updated in `instance_core.sql`.

- [x] **12. Migrate existing template canvas blocks**  
  Done — `migrations/core/012_template_id_on_pages.sql` moves published and draft blocks from `page_id = canvas_page_id` to `template_id = page_templates.id`. `canvas_page_id` remains on `page_templates` (deprecated, kept for one release).

#### 4b — Render service: full-pass zone assembly

- [x] **13. `CruinnRenderService::buildWithTemplate()` — accept and inject all zones**  
  Extend signature to accept a zone canvas map (`array<zoneName, canvasPageId>`).  
  When walking the template block tree and hitting a zone slot block:  
  - `zone_name === $page->page_zone` → inject the page's own blocks (existing behaviour)  
  - any other zone name → fetch blocks for that zone's canvas page and inject them  
  Fetch template layout blocks via `template_id` directly (not via `canvas_page_id → pages_index`).  
  Returns one complete HTML string — no further structural assembly needed.

- [x] **14. `PageController` — single render call**  
  Replace the parallel pipelines (separate `buildZone('header')`, `buildZone('footer')`, `resolveSidebarRender()`, `Template::addGlobal('tpl_header_html', ...)`) with a single `buildWithTemplate()` call that receives the full zone canvas map resolved from the 4-level priority chain.  
  Remove `tpl_header_html`, `tpl_footer_html`, `tpl_sidebar_html`, `tpl_header_css`, `tpl_footer_css`, `tpl_sidebar_css` as Template globals.  
  Remove `setZoneGlobals()`, `resolveZoneRender()`, `resolveSidebarRender()` from PageController.

- [x] **15. `layout.php` cleanup (depends on 13–14)**  
  Apply item 4 above once PageController emits a single `$pageHtml` string. layout.php is now a document shell only.

#### 4c — Editor context rendering

- [x] **16. Editor context rendering**  
  When opening a page for editing, load the template's zone canvases (header, footer, sidebar etc.) and render them as non-editable chrome surrounding the primary canvas.  
  - Server side: editor endpoint passes `contextCanvases` (array of `{zone, pageId, html, css, position}`) alongside primary `pageId`. Sidebar unified into `contextCanvases` with `position='right'`; separate `sidebarContext*` path removed.  
  - CSS: zone preview containers render inline HTML with a scoped editor CSS override neutralising `position:fixed`/`sticky` to `relative` so content flows in place within the preview  
  - Context zones are locked (no selection, no drag, no properties panel trigger)  
  - Visually distinct: dimmed, labelled, click navigates to that zone's canvas

- [x] **17. Canvas switching from context zones**  
  Clicking a context zone navigates the editor to that zone's canvas (page or template-owned).  
  Back button / breadcrumb returns to the original page canvas via `?from={pageId}` query parameter — the editor reads it and shows "← [page title]" in the toolbar back button.

---

## Files Affected (summary)

| File | Stage | Nature of change |
|---|---|---|
| `public_html/js/editor.js` | 1, 4c | Remove hardcoding; add context zone rendering |
| `CruinnCMS/src/Controllers/PageController.php` | 1, 4b | Collapse zone resolution; single render call |
| `CruinnCMS/templates/layout.php` | 4b | Reduce to HTML document shell |
| `CruinnCMS/schema/instance_core.sql` | 1 | Remove structural seeds |
| `CruinnCMS/migrations/core/NNN_remove_structural_seeds.sql` | 1 | New migration for existing instances |
| `CruinnCMS/migrations/core/NNN_template_id_on_pages.sql` | 4a | Add `template_id` column; migrate canvas blocks |
| `CruinnCMS/src/Admin/Controllers/SiteBuilderController.php` | 1 | Remove hardcoded `_header` slug query |
| `CruinnCMS/src/Services/CruinnRenderService.php` | 2, 4b | Replace slug-convention zone lookup; full-pass zone assembly |
| `CruinnCMS/themes/default/seed.sql` | 3 | New file — moved structural defaults |
| `CruinnCMS/src/Admin/Controllers/ThemeController.php` | 3 | Add seed apply action |
| `CruinnCMS/src/Admin/Controllers/AdminPageController.php` | 4c | Pass context canvases to editor |
| `CruinnCMS/schema/platform.sql` / `instance_core.sql` | 4a | Schema: `template_id` on `pages` / `pages_draft` |

---

## Implementation Order

Stages 1 → 2 must be done in order (2 depends on 1 being clean).  
Stage 3 is independent — can be done any time after Stage 1 item 5.  
Stage 4a (schema) must land before 4b (render service). 4b must land before 4c (editor context).  
Stage 1 item 4 (`layout.php` shell reduction) is a cleanup that lands last, after 4b is complete and verified.

Within Stage 1, items 1–6 can largely be done together in one pass since they are all removals.
