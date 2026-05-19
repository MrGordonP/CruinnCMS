# 2026-05-19 Template Zones and Module Content Checkpoint

**Base commit:** `d4b9a77`  
**Date:** 2026-05-19  
**Status:** Implemented and ready for deploy  
**Scope:**
- fix template settings mismatch between layout zones and content-source controls
- introduce module-content block pipeline so module public content can mount inside designated pages instead of hidden backend route templates

---

## Summary

This checkpoint contains two linked improvements:

1. **Template layout/content separation fix**
- header/footer/sidebar inclusion is now governed by template zones as the source of truth
- source selectors remain content choices only, and are disabled when the matching zone is not present
- legacy booleans are preserved but derived from zones for compatibility

2. **Module content mounting foundation (first slice)**
- added provider registry support in the module system
- added a new dynamic editor block type: `module-content`
- wired Blog list as first provider (`blog:list`)
- updated `/blog` to render through `system_pages` mapping (`blog.list`) when available, with fallback to existing `public/articles/index` route template

---

## Files Changed

### Template Zone/Layout Separation
- `CruinnCMS/src/Admin/Controllers/SiteBuilderController.php`
- `CruinnCMS/templates/admin/site-builder/template-settings.php`
- `CruinnCMS/templates/admin/site-builder/structure.php`
- `public_html/js/admin/template-settings.js`

### Module Content Provider + Editor Wiring
- `CruinnCMS/src/Modules/ModuleRegistry.php`
- `CruinnCMS/src/BlockTypes/module-content/definition.php` (new)
- `public_html/js/admin/block-types/module-content.js` (new)
- `CruinnCMS/templates/admin/editor.php`
- `public_html/js/editor.js`

### Blog First Provider Slice
- `CruinnCMS/modules/blog/module.php`
- `CruinnCMS/modules/blog/src/Controllers/ArticleController.php`
- `CruinnCMS/modules/blog/templates/public/articles/module-content/list.php` (new)

---

## Detailed Change Notes

## 1) Template Settings and Structure Alignment

### Problem
Template settings had conflicting control paths:
- legacy include-style fields implied section visibility
- editor/template pipeline actually composed layout by zones/canvases

This caused admin confusion and mismatch between what settings appeared to control and what the runtime/editor actually rendered.

### Implemented Behavior
- Header/footer/sidebar visibility now resolves from zones first.
- Content-source selectors (header source/footer source/sidebar source) are treated as content origins only.
- Source selectors are disabled when the corresponding zone is not active.
- Backward compatibility is retained by deriving old show flags from zone state on save.

### Outcome
The template settings page now reflects real editor/runtime behavior with no hidden divergence between admin controls and template composition.

---

## 2) Module Content Provider Registry

### Problem
Module public pages (Blog/Events/Forum style) were route-template driven, which bypassed page-level composition and made module content hard to place inside template zones and block canvases.

### Implemented Foundation
In `ModuleRegistry`:
- added support for module manifest key `content_providers`
- added `contentProviderCatalog()` for editor dropdown population
- added `renderContentByKey($providerKey, $settings, $context)`:
  - resolves `module:key`
  - executes provider callback
  - renders module template with returned data
  - safe empty return on missing/inactive/invalid provider paths

This mirrors existing `module-widget` patterns while remaining page-editor centered.

---

## 3) New Dynamic Block Type: module-content

### New Server Block Type
`src/BlockTypes/module-content/definition.php`:
- dynamic renderer
- uses `provider_key` from block config
- optional `settings_json` parsed to array
- calls `ModuleRegistry::renderContentByKey(...)`
- outputs helpful placeholder text when provider is missing

### New Client Block Registration
`public_html/js/admin/block-types/module-content.js`:
- registers `module-content` with label/tag metadata

### Editor Integration
In admin editor template and JS:
- palette now includes **Module Content**
- content panel includes:
  - provider select (`provider_key`)
  - optional settings JSON textarea (`settings_json`)
- canvas preview text for selected/missing provider
- `module-content` added to dynamic/config lists and default block defs

---

## 4) Blog First Provider and Route Fallback

### Provider Registration
`modules/blog/module.php` now exposes:
- `content_providers[]` with slug `list`
- provider callback `ArticleController::contentProviderBlogList`
- template `public/articles/module-content/list`

### Controller Behavior
`modules/blog/src/Controllers/ArticleController.php`:
- `/blog` now checks if a `system_pages` mapping exists for `blog.list`
- if mapped, render uses `renderSystemPage('blog.list', ...)` so the page/template/block pipeline controls output
- if not mapped, behavior falls back to existing `public/articles/index` route template
- added `contentProviderBlogList(...)` static method for provider data

### New Provider Template
`modules/blog/templates/public/articles/module-content/list.php`
- renders article cards and pagination for provider mode

---

## Compatibility and Risk Notes

- No new dependencies added.
- No schema/migration files changed.
- Existing behavior preserved via fallback when `blog.list` system-page mapping is absent.
- First-slice scope intentionally limited to Blog list provider only.

---

## Validation

Diagnostics checked on all touched files:
- no syntax/diagnostic errors reported by editor diagnostics tooling

Manual browser runtime verification was not executed in this checkpoint pass.

---

## Deploy/Upload Set

Upload these files for this checkpoint:

- `CruinnCMS/src/Admin/Controllers/SiteBuilderController.php`
- `CruinnCMS/templates/admin/site-builder/template-settings.php`
- `CruinnCMS/templates/admin/site-builder/structure.php`
- `public_html/js/admin/template-settings.js`
- `CruinnCMS/src/Modules/ModuleRegistry.php`
- `CruinnCMS/src/BlockTypes/module-content/definition.php`
- `public_html/js/admin/block-types/module-content.js`
- `CruinnCMS/templates/admin/editor.php`
- `public_html/js/editor.js`
- `CruinnCMS/modules/blog/module.php`
- `CruinnCMS/modules/blog/src/Controllers/ArticleController.php`
- `CruinnCMS/modules/blog/templates/public/articles/module-content/list.php`

---

## Next Session Suggested Follow-up

1. Add providers for Events and Forum using the same `module-content` pattern.
2. Add admin assignment UX for mapping module system keys to designated pages (if needed beyond existing `system_pages` workflows).
3. Add a small validation layer for provider settings JSON (editor-side and server-side) to reduce malformed config risk.
4. Add a regression test checklist for:
   - zones on/off behavior in template settings
   - `/blog` with and without `blog.list` mapping
   - module-content block render inside main/header/sidebar zones
