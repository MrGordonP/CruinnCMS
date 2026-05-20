# 2026-05-20 Template Layout Inheritance Checkpoint

**Base commit:** `e18555b`
**Date:** 2026-05-20
**Status:** Implemented, validated by syntax/diagnostics, awaiting fuller browser testing
**Scope:**
- split template layout structure from page template content assignments
- keep page template settings inside the editor instead of a separate settings screen
- preserve fallback behaviour for older template records where practical

---

## Summary

This checkpoint completes the missing separation in the editor/template architecture:

1. **Template Layouts** now define only the zone arrangement.
2. **Page Templates** now point at a chosen layout and store zone-to-canvas assignments.
3. **Pages** continue to use a page template and provide unique content for their active page zone.

The root fix was introducing an explicit `layout_page_id` on `page_templates`. Before this, `canvas_page_id` was doing two jobs at once: acting as the template editor anchor and implicitly acting as the source of layout structure. That overlap is what kept the refactor half-broken.

---

## Files Changed

- `CruinnCMS/schema/instance_core.sql`
- `CruinnCMS/migrations/core/021_template_layout_inheritance.sql`
- `CruinnCMS/src/Admin/Controllers/AdminPageController.php`
- `CruinnCMS/src/Services/CruinnRenderService.php`
- `CruinnCMS/src/Controllers/CruinnController.php`
- `CruinnCMS/templates/admin/editor.php`
- `public_html/js/editor.js`

---

## Detailed Change Notes

## 1) Schema: explicit layout inheritance

### `CruinnCMS/schema/instance_core.sql`
- Added `page_templates.layout_page_id`.
- Added foreign key `fk_tpl_layout_page` to `pages_index(id)`.

### `CruinnCMS/migrations/core/021_template_layout_inheritance.sql`
- Adds `layout_page_id` for existing instances if missing.
- Adds the foreign key if missing.
- Keeps `canvas_page_id` unchanged as the page template editor anchor.

This is the actual architecture fix. Without a separate layout reference, page templates could never cleanly inherit structure from standalone layout canvases.

---

## 2) Admin zone resolution now follows the inherited layout

### `CruinnCMS/src/Admin/Controllers/AdminPageController.php`
- `getTemplateZones(int $templateId)` now:
  - reads zones from `layout_page_id` when a page template has an assigned template layout
  - falls back to template-owned zone rows where no inherited layout exists yet

This means page editing surfaces now use the layout’s zone arrangement as the source of truth.

---

## 3) Render pipeline now separates structure from assignments

### `CruinnCMS/src/Services/CruinnRenderService.php`
- `buildWithTemplate()` now fetches both `canvas_page_id` and `layout_page_id`.
- If `layout_page_id` is present:
  - layout blocks come from the standalone layout page
  - zone canvas assignments come from the page template’s own zone rows
- If no inherited layout is present, the older fallback path still works.

This is the runtime counterpart to the schema change: layouts define structure, page templates define repeated content mapping.

---

## 4) Editor controller now distinguishes page templates from layout canvases

### `CruinnCMS/src/Controllers/CruinnController.php`

Added helper methods:
- `getLayoutZones()`
- `getTemplateZoneAssignments()`
- `getTemplateDisplayZones()`
- `syncTemplateZoneBlocks()`
- `updateTemplateZoneAssignments()`
- `stripPreviewEditorAttrs()`

Updated editor flow:
- page-template editor pages are detected by `page_templates.canvas_page_id = current page`
- standalone `template-shell` pages not claimed as a page-template editor remain true template layout canvases
- page-template editor pages now load:
  - selected `layout_page_id`
  - zone assignment data
  - preview HTML/CSS built through the render pipeline
- page-template editor pages suppress draft/block-edit assumptions with `hasDraft = false`

Updated metadata save flow:
- saves `layout_page_id`
- validates selected layout canvases
- synchronises template zone assignment rows to match inherited layout zones
- saves `zone_assignments`

This moves the controller fully onto the intended 3-level model.

---

## 5) Editor UI: page template settings live inside the editor

### `CruinnCMS/templates/admin/editor.php`
- Added `data-is-template-page` to the editor wrapper.
- Toolbar labels now distinguish:
  - **Page Template** for page-template editor pages
  - **Template Layout** for standalone layout canvases
- Page-template editor pages no longer show code/publish controls.
- Block palette is disabled on page-template editor pages.
- Replaced the old template-layout settings accordion with a page-template settings panel containing:
  - a template layout selector
  - per-zone canvas assignment selectors

This matches the requested UX: templates do not get a separate settings page; they are configured inside the editor.

### `public_html/js/editor.js`
- Added `IS_TEMPLATE_PAGE` detection.
- Added `bindTemplatePageSettings()`.
- Layout selection and zone assignment changes now POST to the existing metadata endpoint and reload the editor after save.

---

## Validation

Validated during this checkpoint:

- `php -l CruinnCMS/src/Admin/Controllers/AdminPageController.php`
- `php -l CruinnCMS/src/Services/CruinnRenderService.php`
- `php -l CruinnCMS/src/Controllers/CruinnController.php`
- `php -l CruinnCMS/templates/admin/editor.php`
- editor diagnostics reported no errors in the touched PHP/JS/template files

Not completed in this session:

- full browser walkthrough of layout canvas -> page template -> normal content page flow
- applying migration 021 against a live instance database

---

## Deploy / Apply Notes

For existing instances, migration `021_template_layout_inheritance.sql` must be applied before relying on the new inheritance path.

Recommended first manual checks after deploy:

1. Open a standalone template layout canvas and confirm zone structure editing still works.
2. Open a page template editor page and confirm layout selection plus zone canvas assignment persist correctly.
3. Open a normal page using that template and confirm inherited zones render in the expected order.

---

## Notes

This checkpoint intentionally does not claim full runtime sign-off. The implementation is in place and the code validates cleanly, but browser-level verification is still pending.

The unrelated whitespace-only modification in `dev/docs/sessions/v1.0.0-beta.14_PARTIAL_FAILURE.md` was left out of the commit so this checkpoint stays scoped to the actual architecture fix.