-- ============================================================
-- Migration 011 — Explicit canvas types and zone-canvas mapping
--
-- Replaces the underscore slug naming convention for zone and
-- template-shell canvases with explicit typed columns.
--
-- pages_index:
--   canvas_type  — declares what this canvas is for
--   zone_name    — populated when canvas_type = 'zone'
--   zone_overrides — page-level zone canvas overrides (JSON map)
--
-- page_templates:
--   zone_canvases — template-level zone canvas assignments (JSON map)
--                   e.g. {"header": 12, "footer": 17, "sidebar": 34}
--
-- Resolution order in buildZone():
--   1. page.zone_overrides.$zone   (most specific)
--   2. template.zone_canvases.$zone
--   3. pages_index WHERE canvas_type='zone' AND zone_name=? (global)
--   4. Legacy: pages_index WHERE slug='_'.$zone (backward compat)
-- ============================================================

ALTER TABLE `pages_index`
    ADD COLUMN `canvas_type`    ENUM('content','zone','template-shell','typography') NOT NULL DEFAULT 'content' AFTER `render_file`,
    ADD COLUMN `zone_name`      VARCHAR(50) NULL AFTER `canvas_type`,
    ADD COLUMN `zone_overrides` JSON        NULL AFTER `zone_name`,
    ADD INDEX  `idx_pi_canvas_type` (`canvas_type`),
    ADD INDEX  `idx_pi_zone_name`   (`zone_name`);

ALTER TABLE `page_templates`
    ADD COLUMN `zone_canvases` JSON NULL AFTER `canvas_page_id`;

-- ── Backfill canvas_type for existing convention-named rows ──

-- Global zone canvases (the _header, _footer, _global_* pattern)
UPDATE `pages_index` SET `canvas_type` = 'zone', `zone_name` = 'header'  WHERE `slug` = '_header';
UPDATE `pages_index` SET `canvas_type` = 'zone', `zone_name` = 'footer'  WHERE `slug` = '_footer';
UPDATE `pages_index` SET `canvas_type` = 'zone', `zone_name` = 'sidebar' WHERE `slug` = '_global_sidebar';
UPDATE `pages_index` SET `canvas_type` = 'zone', `zone_name` = 'header'  WHERE `slug` = '_tpl__global_header';

-- Typography canvas
UPDATE `pages_index` SET `canvas_type` = 'typography' WHERE `slug` = '_typography';

-- Template shell canvases (slug starts _tpl_ but not already handled above)
UPDATE `pages_index`
    SET `canvas_type` = 'template-shell'
    WHERE `slug` LIKE '_tpl_%'
      AND `slug` NOT IN ('_tpl__global_header');
