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
--
-- Idempotent: all ALTER TABLE statements are conditional.
-- ============================================================

DROP PROCEDURE IF EXISTS _cruinn_011;
DELIMITER //
CREATE PROCEDURE _cruinn_011()
BEGIN
    -- pages_index: canvas_type
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pages_index' AND COLUMN_NAME = 'canvas_type'
    ) THEN
        ALTER TABLE `pages_index`
            ADD COLUMN `canvas_type` ENUM('content','zone','template-shell','typography') NOT NULL DEFAULT 'content' AFTER `render_file`;
    END IF;

    -- pages_index: zone_name
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pages_index' AND COLUMN_NAME = 'zone_name'
    ) THEN
        ALTER TABLE `pages_index`
            ADD COLUMN `zone_name` VARCHAR(50) NULL AFTER `canvas_type`;
    END IF;

    -- pages_index: zone_overrides
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pages_index' AND COLUMN_NAME = 'zone_overrides'
    ) THEN
        ALTER TABLE `pages_index`
            ADD COLUMN `zone_overrides` JSON NULL AFTER `zone_name`;
    END IF;

    -- pages_index: idx_pi_canvas_type
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pages_index' AND INDEX_NAME = 'idx_pi_canvas_type'
    ) THEN
        ALTER TABLE `pages_index` ADD INDEX `idx_pi_canvas_type` (`canvas_type`);
    END IF;

    -- pages_index: idx_pi_zone_name
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pages_index' AND INDEX_NAME = 'idx_pi_zone_name'
    ) THEN
        ALTER TABLE `pages_index` ADD INDEX `idx_pi_zone_name` (`zone_name`);
    END IF;

    -- page_templates: zone_canvases
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'page_templates' AND COLUMN_NAME = 'zone_canvases'
    ) THEN
        ALTER TABLE `page_templates`
            ADD COLUMN `zone_canvases` JSON NULL AFTER `canvas_page_id`;
    END IF;
END //
DELIMITER ;
CALL _cruinn_011();
DROP PROCEDURE IF EXISTS _cruinn_011;

-- ── Backfill canvas_type for existing convention-named rows ──

UPDATE `pages_index` SET `canvas_type` = 'zone',         `zone_name` = 'header'  WHERE `slug` = '_header'            AND `canvas_type` = 'content';
UPDATE `pages_index` SET `canvas_type` = 'zone',         `zone_name` = 'footer'  WHERE `slug` = '_footer'            AND `canvas_type` = 'content';
UPDATE `pages_index` SET `canvas_type` = 'zone',         `zone_name` = 'sidebar' WHERE `slug` = '_global_sidebar'    AND `canvas_type` = 'content';
UPDATE `pages_index` SET `canvas_type` = 'zone',         `zone_name` = 'header'  WHERE `slug` = '_tpl__global_header' AND `canvas_type` = 'content';
UPDATE `pages_index` SET `canvas_type` = 'typography'                             WHERE `slug` = '_typography'         AND `canvas_type` = 'content';
UPDATE `pages_index`
    SET `canvas_type` = 'template-shell'
    WHERE `slug` LIKE '_tpl_%'
      AND `slug` NOT IN ('_tpl__global_header')
      AND `canvas_type` = 'content';
