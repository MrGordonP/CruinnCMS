# Zone Canvas Dual Control Fix — 20 May 2026

**Problem:** The template zone architecture has a dual-control antipattern at the data model layer, identical to the UI antipattern reverted in commit `73fea87`.

**Current architecture:**
1. `page_templates.zones` — JSON array `["main", "header", "sidebar"]` — checkbox in UI
2. `page_templates.zone_canvases` — JSON map `{"header": 12, "sidebar": 34}` — dropdown assignment
3. Migration 013 creates zone blocks in template tree based on #1 and #2

**Antipattern:** To add a sidebar zone, you must:
1. Check "sidebar" in the zones array
2. Separately assign a canvas page ID via zone_canvases mapping

This is dual control. The UI version was already reverted today — the data model version needs the same fix.

---

## Solution: Zone Blocks Own Their Canvas Reference

Zone blocks in the template tree should be the single source of truth:
- `block_type = 'zone'`
- `block_config.zone_name` — declares which zone this is
- `block_config.canvas_page_id` — (optional) references which canvas page to render here

When editing a template, you add/remove zone blocks directly in the block tree. No checkboxes, no separate assignment dropdowns.

---

## Migration Path

### 1. Add canvas_page_id to existing zone blocks

Migrate data from `page_templates.zone_canvases` into each zone block's `block_config`:

```sql
UPDATE pages p
JOIN page_templates pt ON p.template_id = pt.id
SET p.block_config = JSON_SET(
    COALESCE(p.block_config, '{}'),
    '$.canvas_page_id',
    JSON_EXTRACT(pt.zone_canvases, CONCAT('$.', JSON_UNQUOTE(JSON_EXTRACT(p.block_config, '$.zone_name'))))
)
WHERE p.block_type = 'zone'
  AND p.template_id IS NOT NULL
  AND pt.zone_canvases IS NOT NULL
  AND JSON_CONTAINS_PATH(pt.zone_canvases, 'one', CONCAT('$.', JSON_UNQUOTE(JSON_EXTRACT(p.block_config, '$.zone_name'))));
```

### 2. Update CruinnRenderService

Replace `resolveZoneCanvasId()` 3-level lookup (page overrides → template zone_canvases → global) with inline zone block resolution:

When rendering a zone block:
1. Check zone block's `block_config.canvas_page_id` directly
2. Fallback to page-level `zone_overrides` (page-specific override remains valid)
3. Fallback to global zone canvas (`canvas_type='zone' AND zone_name=?`)

Remove the template-level `zone_canvases` lookup entirely.

### 3. Update SiteBuilderController

Remove `builderSaveZoneCanvases()` and `builderEnsureCanvas()` methods — they operate on the zone_canvases JSON.

When user clicks "Edit Header" on a template:
1. Find the zone block in the template tree with `zone_name='header'`
2. If it has `canvas_page_id`, redirect to that canvas's editor
3. If not, create a new canvas and update the zone block's `block_config.canvas_page_id`

### 4. Derive zones array (optional, read-only)

`page_templates.zones` can become a read-only derived column computed from the template's zone blocks:

```sql
SELECT JSON_ARRAYAGG(JSON_UNQUOTE(JSON_EXTRACT(block_config, '$.zone_name')))
FROM pages
WHERE template_id = ? AND block_type = 'zone' AND parent_block_id IS NULL
```

Or remove it entirely if nothing depends on it.

### 5. Deprecate/remove zone_canvases

Mark `page_templates.zone_canvases` as deprecated. Remove in next major version after confirming all instances migrated.

---

## Benefits

✅ **Single source of truth** — zone blocks own their identity and content reference
✅ **No dual control** — presence of zone block = zone exists, config contains canvas reference
✅ **Consistent with module-content pattern** — blocks declare what they mount, not via external mapping
✅ **Simpler mental model** — editing template layout directly manipulates zone blocks
✅ **Future-proof** — supports per-template zone customization without schema changes

---

## Files to Change

| File | Change |
|---|---|
| `migrations/core/020_zone_block_canvas_references.sql` | Migrate zone_canvases data into zone blocks |
| `CruinnRenderService.php` | Update zone rendering to read canvas_page_id from zone blocks |
| `SiteBuilderController.php` | Update template zone editing to work with zone blocks directly |
| `AdminPageController.php` (editor) | Zone block property panel displays canvas assignment |
| `instance_core.sql` | Annotate zone_canvases as deprecated |

---

## Implementation Order

1. Write migration 020 to backfill canvas_page_id into zone blocks
2. Update CruinnRenderService zone resolution logic
3. Update SiteBuilderController zone editing flow
4. Test thoroughly with IGAPortal instance
5. Mark zone_canvases as deprecated (keep for backward compat during beta)
6. Update this checkpoint doc with results
7. Commit, tag v1.0.0-beta.14

---

**Status:** Plan documented, ready for Gordon's approval before implementation
