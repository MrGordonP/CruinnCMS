# 2026-05-28 Editor / Include / Collapse Follow-up CHECKPOINT

Date: 2026-05-28
Branch: main
Status: pushed after checkpoint update

## Scope

This checkpoint captures the full beta.15 follow-up session that finished the editor-side include inspector migration, membership dynamic-include follow-up, autosave recovery work, right-panel context cleanup, heading-level controls, and the first usable always-on collapse / heading-bar collapse path.

## Commits included in this session

- 7173d72 fix(editor): target include child styles from right panel [v1.0.0-beta.15]
- 3a5476f fix(editor,include): recover CSRF autosave and cascade include text styles [v1.0.0-beta.15]
- 19a1098 fix(editor,include): move typography preset UI to template markup [v1.0.0-beta.15]
- bbf4b73 fix(editor,include): tighten inspector context visibility and target controls [v1.0.0-beta.15]
- 410cb06 feat(editor): add heading level controls and hide stale include UI [v1.0.0-beta.15]
- 1e785fe fix(editor,collapse): bridge collapsed toggle to live responsive collapse [v1.0.0-beta.15]
- 56a9a93 fix(editor,collapse): separate collapsed from responsive UI and add live collapsed toggle [v1.0.0-beta.15]
- b6bb0e9 fix(collapse): show always-collapse toggle bars on live pages [v1.0.0-beta.15]
- e91fbe6 feat(collapse): add heading-bar collapse style with bottom-border icon [v1.0.0-beta.15]
- 06032cb fix(editor,collapse): expose collapse style for always-on collapsed blocks [v1.0.0-beta.15]

## What changed

### 1) Dynamic include / php-include child styling now lives in the right-hand panel

- Removed the old popup-owned editing model for include descendants.
- Child element targeting now uses right-panel state with selectable style targets.
- Include inspector visibility is re-synced per block selection so include controls do not leak into regular blocks.
- Typography preset controls were moved into static template markup instead of runtime-injected HTML.

### 2) Include descendant editing became practical rather than partial

- Edit-mode renderers now annotate descendant elements so inner content can be selected and styled.
- Include child styles support element, tag, and class targeting.
- Text-facing child styles now cascade through descendant text nodes in both editor preview and live render output.

### 3) Membership include / module-content follow-up was stabilized

- Membership profile rendering was kept on the module-content boundary rather than incorrectly treating membership fragments as system/core fragments.
- Legacy membership/profile surfaces were bridged so missing runtime variables no longer hard-fail include previews.
- Membership profile fragments were decomposed into module-owned templates and migration bridges for existing data.

### 4) Editor save/publish behavior was hardened around CSRF expiry

- Autosave now refreshes the CSRF token and retries once before save is locked.
- Failure paths now surface a clearer editor-side save error rather than silently looping on 403 responses.

### 5) Right-panel context awareness improved

- Include-only accordions stay hidden when no include context exists.
- Regular heading blocks now have an explicit heading-level selector (H1-H6) in the properties panel.
- Heading level changes also update the live canvas tag so preview and saved config stay aligned.

### 6) Collapse behavior was split into two distinct concepts

- `Collapsed` now means always-on collapse, independent of Responsive UI.
- `Responsive UI` remains breakpoint-owned collapse and no longer hijacks always-on collapse state.
- Live always-on collapsed blocks now get their own toggle behavior and grouped toggle-bar visibility.

### 7) Heading-bar collapse presentation was added

- Collapse presentation now supports `hamburger` or `heading` style.
- Heading style uses the first heading element inside the block as its label, falling back to the existing generic block label when no heading exists.
- The heading-style toggle icon sits on the bottom border, matching the requested presentation direction.
- The collapse-style selector is now available for both Responsive UI collapse and always-on `Collapsed` blocks.

## Files touched across this session

- CruinnCMS/modules/membership/module.php
- CruinnCMS/modules/membership/src/Controllers/MembershipContentController.php
- CruinnCMS/modules/membership/templates/public/membership/module-content/*
- CruinnCMS/migrations/core/024_membership_profile_fragments.sql
- CruinnCMS/migrations/core/025_membership_address_fragment.sql
- CruinnCMS/migrations/core/026_members_profile_legacy_to_module_fragments.sql
- CruinnCMS/src/BlockTypes/php-include/definition.php
- CruinnCMS/src/BlockTypes/dynamic-source/definition.php
- CruinnCMS/src/Services/CruinnRenderService.php
- CruinnCMS/templates/admin/editor.php
- CruinnCMS/templates/public/members/profile.php
- public_html/css/style.css
- public_html/js/editor.js
- public_html/js/main.js

## Validation

- JS diagnostics reported no errors in touched editor/runtime files after each slice.
- PHP lint passed on touched PHP files during the session, including:
  - `CruinnCMS/templates/admin/editor.php`
  - `CruinnCMS/src/Services/CruinnRenderService.php`
  - Membership-related PHP files touched earlier in the session
- Each user-requested test slice was committed and pushed incrementally for live verification.

## Notes

- Collapse UX is improved but not considered fully polished; the heading-bar path was accepted as close enough for now.
- This checkpoint intentionally covers the entire 2026-05-28 follow-up session rather than only the final collapse sub-slice, because the work was one continuous editor/runtime stabilization thread.