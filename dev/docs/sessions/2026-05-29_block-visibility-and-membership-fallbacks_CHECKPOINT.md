# 2026-05-29 CHECKPOINT — Block Visibility Controls + Membership Fallback Rendering

Date: 2026-05-29
Branch: main
Status: checkpoint prepared for testing

## Scope

This checkpoint captures two targeted fixes completed in this session:

1. Membership module-content fragments now render safe fallback output instead of being interpreted as missing content providers.
2. Block-level visibility controls were added to the editor and enforced at live render time.

## Why

Two regressions/gaps were present:

- Membership profile fragments (`member-address-form`, `member-notifications`) could render an empty string for valid states, which the module-content renderer treated as "not found".
- Instance admins needed block-level control over who can see dynamic/custom content based on authentication and access levels, with secure server-side omission from output.

## Changes

### 1) Membership fragments: always return non-empty output for valid empty states

Updated templates:

- `CruinnCMS/modules/membership/templates/public/membership/module-content/member-address-form.php`
- `CruinnCMS/modules/membership/templates/public/membership/module-content/member-notifications.php`

What changed:

- Address fragment now renders explicit fallback cards for:
  - not logged in
  - logged in but no membership record yet
- Notifications fragment now renders a "No notifications." empty state instead of returning no markup.

Result:

- Module content keys resolve correctly in these states and no longer surface as missing providers.

### 2) Block-level visibility controls in editor

Updated files:

- `CruinnCMS/templates/admin/editor.php`
- `public_html/js/editor.js`

What changed:

- Added a new Visibility accordion in the block properties panel:
  - Show to: Everyone / Logged-in users only / Logged-out visitors only
  - Minimum role (for logged-in visibility)
  - Minimum group level (for logged-in visibility)
- Added editor runtime bindings to read/write block config keys:
  - `_visibility`
  - `_min_role`
  - `_min_group`

### 3) Server-side render gating (secure omission)

Updated file:

- `CruinnCMS/src/Services/CruinnRenderService.php`

What changed:

- Added a visibility gate in `renderTree()` before block output.
- Live render now skips blocks (and their subtree) when visibility conditions fail.
- Gate checks are server-side only and produce no placeholder/comment markers.
- Editor mode remains unrestricted (all blocks remain editable).

Result:

- Hidden blocks do not appear in frontend HTML output/source when access checks fail.

## Validation

- PHP lint passed:
  - `CruinnCMS/src/Services/CruinnRenderService.php`
  - `CruinnCMS/templates/admin/editor.php`
  - membership fragment templates updated in this session

## Notes

- This checkpoint intentionally focuses on role/group/auth visibility methods now implemented in block config and render gating.
- Position-based block visibility can be layered into the same mechanism in a follow-up slice if needed.
