# 2026-05-28 CHECKPOINT — Include Interior Editability + Legacy Profile Bridge

Date: 2026-05-28
Branch: main
Status: checkpoint committed for testing

## Scope

This checkpoint captures two related fixes:

1. Include/module fragment interior elements not being editable in the active editor.
2. Existing instances still on legacy `public/members/profile.php` include path not reflecting the new membership fragment stack.

## Why

- The include element inspector only accepted class-bearing nodes, so classless interior elements could not be selected or styled.
- Existing profile pages still on legacy include config were bypassing module fragment composition.

## Changes

### 1) Editor include interior editability

- `dynamic-include` renderer now annotates all rendered interior elements in edit mode with `data-phpi-el`.
- `php-include` renderer aligned to same behavior for consistency.
- Editor include inspector now supports classless element selection by falling back to `[data-phpi-el="N"]` selectors when classes are absent.

Files:

- `CruinnCMS/src/BlockTypes/dynamic-source/definition.php`
- `CruinnCMS/src/BlockTypes/php-include/definition.php`
- `public_html/js/editor.js`

### 2) Legacy profile bridge migration

- Added migration to convert legacy profile include blocks (`public/members/profile.php`) on system profile pages into membership module-content fragments and seed default membership stack where needed.

File:

- `CruinnCMS/migrations/core/026_members_profile_legacy_to_module_fragments.sql`

## Validation

- Diagnostics: no errors in changed files.
- PHP lint: no syntax errors in changed PHP files.

## Commit Reference

- Pending commit in this session (to be pushed for testing).
