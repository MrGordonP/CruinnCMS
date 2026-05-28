# 2026-05-28 CHECKPOINT — Membership Module Boundary Correction

Date: 2026-05-28
Branch: main
Status: checkpoint committed for testing

## Scope

This checkpoint captures the follow-up refactor that moved membership profile fragments out of engine `core_fragment` handling and into module-owned `module_content` providers.

## Why

Membership functionality belongs to the membership module boundary, not system/core fragment space. The previous slice temporarily modeled membership profile fragments as core fragments, which violated engine/module ownership separation.

## Changes

1. Editor fragment picker boundary correction
- Removed membership from the core fragment staged picker.
- Files:
  - `CruinnCMS/templates/admin/editor.php`
  - `public_html/js/editor.js`

2. Membership module content providers introduced
- Added provider registration for membership profile fragments.
- Added provider controller that prepares reusable member context.
- Added module-content templates for:
  - dashboard header
  - details form
  - notifications
  - upcoming events
  - membership summary
  - admin stats
- Files:
  - `CruinnCMS/modules/membership/module.php`
  - `CruinnCMS/modules/membership/src/Controllers/MembershipContentController.php`
  - `CruinnCMS/modules/membership/templates/public/membership/module-content/*.php`

3. Engine core fragment cleanup
- Removed membership-specific core fragment render branches from dynamic include core fragment handling.
- File:
  - `CruinnCMS/src/BlockTypes/dynamic-source/definition.php`

4. Migration correction
- Reworked migration 024 to convert mistaken membership `core_fragment` keys into `module_content` provider keys (`membership:*`).
- File:
  - `CruinnCMS/migrations/core/024_membership_profile_fragments.sql`

## Validation

- Diagnostics check: no reported errors in touched PHP/JS/SQL files.
- PHP lint: no syntax errors across all modified/new PHP files.

## Commit Reference

- Pending commit in this session (to be pushed for testing).
