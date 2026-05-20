-- ============================================================
-- Migration 020 — Zone blocks own their canvas page references
--
-- Fixes the dual-control antipattern where zone existence and
-- zone content assignment were split across two controls:
--   1. page_templates.zones (checkbox array)
--   2. page_templates.zone_canvases (dropdown mapping)
--
-- After this migration:
--   - Zone blocks carry their canvas_page_id in block_config
--   - zone_canvases becomes read-only fallback (deprecated)
--   - zones array becomes derived data (also deprecated)
--
-- Single source of truth: zone block presence = zone exists,
-- block_config.canvas_page_id = which canvas to render.
--
-- Idempotent: UPDATE uses WHERE clause to only update blocks
-- that don't already have canvas_page_id set.
-- ============================================================

-- Migrate zone_canvases data into zone block configs
UPDATE pages p
JOIN page_templates pt ON p.template_id = pt.id
SET p.block_config = JSON_SET(
    COALESCE(p.block_config, '{}'),
    '$.canvas_page_id',
    CAST(JSON_UNQUOTE(JSON_EXTRACT(
        pt.zone_canvases,
        CONCAT('$.', JSON_UNQUOTE(JSON_EXTRACT(p.block_config, '$.zone_name')))
    )) AS UNSIGNED)
)
WHERE p.block_type = 'zone'
  AND p.template_id IS NOT NULL
  AND pt.zone_canvases IS NOT NULL
  AND JSON_EXTRACT(p.block_config, '$.zone_name') IS NOT NULL
  AND JSON_CONTAINS_PATH(
      pt.zone_canvases,
      'one',
      CONCAT('$.', JSON_UNQUOTE(JSON_EXTRACT(p.block_config, '$.zone_name')))
  )
  AND NOT JSON_CONTAINS_PATH(p.block_config, 'one', '$.canvas_page_id');
