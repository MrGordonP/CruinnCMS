# 2026-05-28 CHECKPOINT — Include Right-Panel Child Targeting Fix

Date: 2026-05-28
Branch: main
Status: checkpoint committed for testing

## Scope

This checkpoint fixes right-hand properties panel behavior for include/module fragment interior editing.

## Problem

Even after include interior nodes became selectable, right-panel style controls still wrote to the include wrapper block. That caused style changes (font size, etc.) to behave globally across include content instead of targeting the selected interior node.

## Changes

- Updated editor property read/write flow so when an include child element is selected:
  - style reads come from the selected child node's computed styles
  - style writes are persisted under `block_config.childStyles` for the active include-child selector
- Added CSS property key normalization for child-style writes so camelCase properties from panel controls are stored as CSS-safe kebab-case keys.
- Prevented viewport override read path from polluting selected-child include style editing.

File:

- `public_html/js/editor.js`

## Validation

- Diagnostics check: no errors in `public_html/js/editor.js`.

## Commit Reference

- Pending commit in this session (to be pushed for testing).
