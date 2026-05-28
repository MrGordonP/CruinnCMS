# 2026-05-28 CHECKPOINT — Editor Dynamic Include Follow-up and Canvas Insertion Fixes

Date: 2026-05-28
Branch: main
Status: checkpoint committed for testing

## Scope

This checkpoint captures the editor follow-up work completed after the earlier `v1.0.0-beta.15` follow-up slice, focused on dynamic include editing and active editor runtime regressions.

The session covered four related areas:

1. Restoring child-element editing for dynamic include / php-include content in the active editor runtime.
2. Making collapsed block identity state visibly represented in the editor canvas.
3. Starting the engine-side plumbing for reusable core fragment style presets.
4. Fixing block insertion/selectability regressions in the active canvas editor.

## Why These Changes Were Made

### 1) Dynamic include child editing was only implemented in legacy JS

The server-side render path was already annotating dynamic include descendants with `data-phpi-el` and `data-phpi-classes`, but the active runtime in `public_html/js/editor.js` did not provide the selection/editing behavior that existed in the older block-type JS path. That meant only wrapper-level styling was effectively usable in the current editor.

### 2) Collapsed was invisible in the editor

The Identity panel exposed a `Collapsed` option, but the editor did not visually represent what that state meant. Users had to infer the result from the class name alone.

### 3) Fragment styling needs a reusable/global path

The requirement shifted from page-instance-only child styles toward styles derived from reusable fragment-level definitions. The controller/route/template plumbing for this was started in this session, but the workflow is not yet finished.

### 4) Canvas insertion/selectability regressions were blocking basic editing

Table and list blocks could fail to appear in the canvas, and image/gallery/html blocks could become effectively unselectable except via the block tree. These were active editor regressions and needed to be corrected in the runtime path.

## Implemented Changes

### A. Active runtime child-element editing for php/dynamic includes

- Added child-element selection handling in `public_html/js/editor.js` using the existing server-side `data-phpi-el` annotations.
- Added floating child-style editing panel support wired to `block_config.childStyles`.
- Added live editor stylesheet emission so child-style changes render immediately in the canvas.
- Hooked preview refresh paths so include previews rehydrate correctly after selection/config changes.

Files:

- `public_html/js/editor.js`
- `public_html/css/editor.css`

### B. Visible collapsed-state representation in the editor

- Added editor-only collapsed styling so the Identity panel setting is now represented on-canvas.
- Updated the editor hint text to describe actual editor-visible behavior instead of implying CSS-only effect.

Files:

- `public_html/css/editor.css`
- `CruinnCMS/templates/admin/editor.php`

### C. Core fragment style preset plumbing (partial)

- Added route/controller/template plumbing for core-fragment style preset load/save exposure.
- Exposed fragment style data into the editor wrapper for runtime use.
- This is partial infrastructure only; the full right-hand-panel workflow and render-time merge behavior remain unfinished.

Files:

- `CruinnCMS/config/routes.php`
- `CruinnCMS/src/Controllers/CruinnController.php`
- `CruinnCMS/templates/admin/editor.php`

### D. Active editor insertion/selectability hardening

- Hardened block insertion in `editor.js` so adding after an active selection climbs to a safe ancestor when the current context is a list/table/text-only parent that cannot safely host inserted block siblings.
- Added a final fallback so newly-created blocks are appended to the canvas root if browser DOM normalization rejects the intended insertion point.
- Added minimum editor footprint rules for `image`, `gallery`, and `html` blocks so they remain directly selectable from the canvas.

Files:

- `public_html/js/editor.js`
- `public_html/css/editor.css`

## Validation

- Diagnostics check: no reported errors in:
  - `CruinnCMS/config/routes.php`
  - `CruinnCMS/src/Controllers/CruinnController.php`
  - `CruinnCMS/templates/admin/editor.php`
  - `public_html/css/editor.css`
  - `public_html/js/editor.js`
- Commit and push completed for testing during this session.
- Manual browser runtime verification was not executed from this environment.

## Outstanding Issues / Follow-up Required

### 1) Child-style editor UX should move into the right-hand properties panel

The current child-style UI was implemented as a floating popup because the active editor state model is still block-scoped. Gordon clarified that the right-hand properties panel is supposed to be dynamic and should own this interaction rather than a detached popup.

Required follow-up:

1. Add explicit editor state for selected child element within a dynamic include block.
2. Render child-style controls through the right-hand properties panel instead of a popup.
3. Keep the panel dynamic rather than extending a fixed property list.

### 2) Current popup interaction is not complete

The popup implementation currently has interaction regressions:

1. Labels are not clickable because the generated controls do not yet use proper `for` / `id` associations.
2. Clicking popup controls can close the popup because the editor-wide deselect handler still treats popup clicks as outside-editor clicks.
3. The fields appear greyed-out / non-editable from the operator perspective because the popup is being torn down before inputs can retain focus.

This issue was diagnosed but intentionally left unresolved here because the session was being closed out to checkpoint and push the current state.

### 3) Heading block level selector is still not implemented

The editor runtime already contains partial `config.level` support in HTML export logic, but there is still no right-hand properties control for selecting heading level (`p`, `h1` … `h6`).

### 4) Global fragment-style behavior is incomplete

The controller/routes/template exposure for fragment style presets has begun, but the full workflow remains incomplete:

1. Right-hand-panel editing flow not built.
2. Full runtime/publish merge behavior not fully verified end-to-end.
3. UX/ownership model still needs to align with reusable fragment-level styling rather than page-instance-only edits.

## Files Touched In This Session Slice

- `CruinnCMS/config/routes.php`
- `CruinnCMS/src/Controllers/CruinnController.php`
- `CruinnCMS/templates/admin/editor.php`
- `public_html/css/editor.css`
- `public_html/js/editor.js`

## Commit Reference

- Testing push during session: `d5193bc` — `fix(editor,dynamic-include): fragment styling and canvas block insertion [v1.0.0-beta.15]`