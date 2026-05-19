# 2026-05-19 OAuth Login Regression Resolution Checkpoint

**Base commit:** 85f4af0  
**Date:** 2026-05-19  
**Status:** Resolved

## Issue

After migrating system page routing to use `system_pages`, the login page rendered through the block pipeline (header/footer present) but OAuth buttons disappeared.

## Root Cause

The login `php-include` block config contained a persisted key:

- `oauth_providers: ""`

In live rendering, `php-include` merged variables in this order:

1. `Template::globals()`
2. render context (`$context`)
3. block config vars (`$vars`)

Because block config vars were merged last, `oauth_providers: ""` from `block_config` overrode the real providers array from controller context.

## Fix Applied

### Engine hardening

Updated merge order in `src/BlockTypes/php-include/definition.php` so context wins over block config:

- Old: `array_merge(Template::globals(), $context, $vars)`
- New: `array_merge(Template::globals(), $vars, $context)`

This prevents stale or editor-persisted config keys from clobbering runtime data.

### Live instance cleanup

Removed stale `oauth_providers` key from login block config on the live DB.

## Verification

- OAuth buttons reappeared on `/login`.
- Reproduced regression by recreating login block in editor.
- With corrected merge order and cleanup, OAuth rendering is stable.

## Notes

- System page mapping itself was correct (`system_pages.login -> pages_index.id 43`).
- `public/login.php` template path was correct.
- Failure mode was variable precedence, not routing or template lookup.
