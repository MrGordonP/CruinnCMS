-- ============================================================
-- Migration 013 — Backfill template zone blocks for legacy instances
--
-- Pre-011 instances stored header/footer/sidebar as separate
-- pages_index rows with slugs like _header, _footer, _global_sidebar.
-- Migration 011 tagged these with canvas_type='zone' and zone_name.
--
-- This migration completes the wiring: for each global zone canvas,
-- each template that hasn't declared that zone gets:
--   1. A zone block inserted into its block tree at the correct
--      sort position (header-type at sort 0; all others at 32767).
--   2. The zone name added to page_templates.zones JSON.
--   3. The canvas ID added to page_templates.zone_canvases JSON.
--
-- Existing root blocks in the template are shifted up by 1 when a
-- header-type zone block is prepended, to preserve visual order.
--
-- Idempotent: INSERT uses WHERE NOT EXISTS; UPDATE uses JSON_CONTAINS_PATH.
-- ============================================================

DROP PROCEDURE IF EXISTS _cruinn_013;
DELIMITER //
CREATE PROCEDURE _cruinn_013()
BEGIN
    DECLARE v_done      INT DEFAULT 0;
    DECLARE v_zone_name VARCHAR(50);
    DECLARE v_canvas_id INT UNSIGNED;

    DECLARE zone_cur CURSOR FOR
        SELECT zone_name, id
        FROM pages_index
        WHERE canvas_type = 'zone'
          AND zone_name IS NOT NULL;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

    OPEN zone_cur;
    z_loop: LOOP
        FETCH zone_cur INTO v_zone_name, v_canvas_id;
        IF v_done THEN LEAVE z_loop; END IF;

        -- ── 1. Wire zone_canvases and zones into templates that lack this zone ──

        UPDATE page_templates
        SET
            zone_canvases = JSON_SET(
                COALESCE(zone_canvases, '{}'),
                CONCAT('$.', v_zone_name),
                v_canvas_id
            ),
            zones = IF(
                JSON_CONTAINS(COALESCE(zones, '["main"]'), JSON_QUOTE(v_zone_name)),
                zones,
                JSON_ARRAY_APPEND(COALESCE(zones, '["main"]'), '$', v_zone_name)
            )
        WHERE NOT JSON_CONTAINS_PATH(COALESCE(zone_canvases, '{}'), 'one', CONCAT('$.', v_zone_name));

        -- ── 2. Insert zone block into template block tree ──

        IF v_zone_name IN ('header', 'topbar', 'navbar', 'banner') THEN

            -- Shift all existing root template blocks up by 1 for templates
            -- that don't yet have a zone block for this zone name.
            -- Double-subquery wrapping avoids MySQL's "can't read and update same table" restriction.
            UPDATE pages p
            SET p.sort_order = p.sort_order + 1
            WHERE p.parent_block_id IS NULL
              AND p.template_id IS NOT NULL
              AND p.template_id NOT IN (
                  SELECT id FROM (
                      SELECT DISTINCT template_id AS id
                      FROM pages
                      WHERE block_type = 'zone'
                        AND template_id IS NOT NULL
                        AND JSON_UNQUOTE(JSON_EXTRACT(block_config, '$.zone_name')) = v_zone_name
                  ) AS already_wired
              );

            -- Insert the header zone block at sort_order 0
            INSERT INTO pages (block_id, template_id, block_type, block_config, sort_order, parent_block_id)
            SELECT
                CONCAT('zm', LPAD(pt.id, 5, '0'), LPAD(v_canvas_id, 5, '0')),
                pt.id,
                'zone',
                JSON_OBJECT('zone_name', v_zone_name),
                0,
                NULL
            FROM page_templates pt
            WHERE NOT EXISTS (
                SELECT 1 FROM pages p2
                WHERE p2.template_id = pt.id
                  AND p2.block_type = 'zone'
                  AND JSON_UNQUOTE(JSON_EXTRACT(p2.block_config, '$.zone_name')) = v_zone_name
            );

        ELSE

            -- Footer / sidebar / other zones: append at sort_order 32767
            INSERT INTO pages (block_id, template_id, block_type, block_config, sort_order, parent_block_id)
            SELECT
                CONCAT('zm', LPAD(pt.id, 5, '0'), LPAD(v_canvas_id, 5, '0')),
                pt.id,
                'zone',
                JSON_OBJECT('zone_name', v_zone_name),
                32767,
                NULL
            FROM page_templates pt
            WHERE NOT EXISTS (
                SELECT 1 FROM pages p2
                WHERE p2.template_id = pt.id
                  AND p2.block_type = 'zone'
                  AND JSON_UNQUOTE(JSON_EXTRACT(p2.block_config, '$.zone_name')) = v_zone_name
            );

        END IF;

    END LOOP z_loop;
    CLOSE zone_cur;
END //
DELIMITER ;

CALL _cruinn_013();
DROP PROCEDURE IF EXISTS _cruinn_013;
