-- ============================================================
-- Migration 012 — Template block ownership via template_id
--
-- Previously, template layout blocks were stored in `pages` with
-- a page_id pointing to a proxy pages_index row (canvas_page_id
-- on page_templates). This creates unnecessary indirection.
--
-- This migration adds direct ownership:
--   pages.template_id  — FK to page_templates.id
--   pages_draft.template_id — same, for in-progress edits
--
-- pages.page_id is made nullable so template-owned blocks can set
-- page_id = NULL once migrated. Application logic enforces the
-- mutual exclusion (one of page_id / template_id must be set).
--
-- page_templates.canvas_page_id is now deprecated. It is left
-- intact for one release to allow rollback; a later migration
-- will drop it once all instances have been verified clean.
--
-- Idempotent: all changes are conditional.
-- ============================================================

DROP PROCEDURE IF EXISTS _cruinn_012;
DELIMITER //
CREATE PROCEDURE _cruinn_012()
BEGIN
    DECLARE v_fk_name   VARCHAR(128) DEFAULT NULL;
    DECLARE v_is_null   VARCHAR(3);

    -- ── 1. Make pages.page_id nullable ───────────────────────

    -- Find and drop the FK on pages.page_id (name may vary per instance)
    SELECT CONSTRAINT_NAME INTO v_fk_name
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pages'
      AND COLUMN_NAME = 'page_id'
      AND REFERENCED_TABLE_NAME = 'pages_index'
    LIMIT 1;

    IF v_fk_name IS NOT NULL THEN
        SET @drop_fk_sql = CONCAT('ALTER TABLE `pages` DROP FOREIGN KEY `', v_fk_name, '`');
        PREPARE _stmt FROM @drop_fk_sql;
        EXECUTE _stmt;
        DEALLOCATE PREPARE _stmt;
    END IF;

    -- Make page_id nullable only if it currently is NOT NULL
    SELECT IS_NULLABLE INTO v_is_null
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pages' AND COLUMN_NAME = 'page_id';

    IF v_is_null = 'NO' THEN
        ALTER TABLE `pages`
            MODIFY COLUMN `page_id` INT UNSIGNED NULL DEFAULT NULL;
    END IF;

    -- Re-add FK with canonical name (drop first in case it already exists)
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'pages' AND CONSTRAINT_NAME = 'fk_pages_page_id'
    ) THEN
        ALTER TABLE `pages` DROP FOREIGN KEY `fk_pages_page_id`;
    END IF;

    -- Null out any orphaned page_id values (no matching pages_index row) before re-adding FK
    UPDATE `pages` p
        LEFT JOIN `pages_index` pi ON pi.id = p.page_id
    SET p.page_id = NULL
    WHERE p.page_id IS NOT NULL AND pi.id IS NULL;

    ALTER TABLE `pages`
        ADD CONSTRAINT `fk_pages_page_id`
            FOREIGN KEY (`page_id`) REFERENCES `pages_index` (`id`) ON DELETE CASCADE;

    -- ── 2. Add template_id to pages ──────────────────────────

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pages' AND COLUMN_NAME = 'template_id'
    ) THEN
        ALTER TABLE `pages`
            ADD COLUMN `template_id` INT UNSIGNED NULL DEFAULT NULL
                COMMENT 'Set for template layout blocks; mutually exclusive with page_id'
                AFTER `page_id`;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pages' AND INDEX_NAME = 'idx_pages_template'
    ) THEN
        ALTER TABLE `pages` ADD INDEX `idx_pages_template` (`template_id`);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'pages' AND CONSTRAINT_NAME = 'fk_pages_template_id'
    ) THEN
        ALTER TABLE `pages`
            ADD CONSTRAINT `fk_pages_template_id`
                FOREIGN KEY (`template_id`) REFERENCES `page_templates` (`id`) ON DELETE CASCADE;
    END IF;

    -- ── 3. Make pages_draft.page_id nullable ─────────────────

    SET v_fk_name = NULL;
    SELECT CONSTRAINT_NAME INTO v_fk_name
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pages_draft'
      AND COLUMN_NAME = 'page_id'
      AND REFERENCED_TABLE_NAME = 'pages_index'
    LIMIT 1;

    IF v_fk_name IS NOT NULL THEN
        SET @drop_fk_sql = CONCAT('ALTER TABLE `pages_draft` DROP FOREIGN KEY `', v_fk_name, '`');
        PREPARE _stmt FROM @drop_fk_sql;
        EXECUTE _stmt;
        DEALLOCATE PREPARE _stmt;
    END IF;

    SELECT IS_NULLABLE INTO v_is_null
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pages_draft' AND COLUMN_NAME = 'page_id';

    IF v_is_null = 'NO' THEN
        ALTER TABLE `pages_draft`
            MODIFY COLUMN `page_id` INT UNSIGNED NULL DEFAULT NULL;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'pages_draft' AND CONSTRAINT_NAME = 'fk_pages_draft_page'
    ) THEN
        ALTER TABLE `pages_draft` DROP FOREIGN KEY `fk_pages_draft_page`;
    END IF;

    -- Null out any orphaned page_id values in pages_draft before re-adding FK
    UPDATE `pages_draft` pd
        LEFT JOIN `pages_index` pi ON pi.id = pd.page_id
    SET pd.page_id = NULL
    WHERE pd.page_id IS NOT NULL AND pi.id IS NULL;

    ALTER TABLE `pages_draft`
        ADD CONSTRAINT `fk_pages_draft_page`
            FOREIGN KEY (`page_id`) REFERENCES `pages_index` (`id`) ON DELETE CASCADE;

    -- ── 4. Add template_id to pages_draft ────────────────────

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pages_draft' AND COLUMN_NAME = 'template_id'
    ) THEN
        ALTER TABLE `pages_draft`
            ADD COLUMN `template_id` INT UNSIGNED NULL DEFAULT NULL
                COMMENT 'Set for template layout block drafts; mutually exclusive with page_id'
                AFTER `page_id`;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pages_draft' AND INDEX_NAME = 'idx_pages_draft_template'
    ) THEN
        ALTER TABLE `pages_draft` ADD INDEX `idx_pages_draft_template` (`template_id`);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'pages_draft' AND CONSTRAINT_NAME = 'fk_pages_draft_template_id'
    ) THEN
        ALTER TABLE `pages_draft`
            ADD CONSTRAINT `fk_pages_draft_template_id`
                FOREIGN KEY (`template_id`) REFERENCES `page_templates` (`id`) ON DELETE CASCADE;
    END IF;

END //
DELIMITER ;
CALL _cruinn_012();
DROP PROCEDURE IF EXISTS _cruinn_012;

-- ── 5. Data migration — move canvas blocks to template ownership ──

-- For each template that has a canvas_page_id, re-point its published
-- blocks from page_id → template_id and clear page_id.
UPDATE `pages` pb
    JOIN `page_templates` pt ON pt.canvas_page_id = pb.page_id
SET pb.template_id = pt.id,
    pb.page_id     = NULL
WHERE pt.canvas_page_id IS NOT NULL;

-- Same for draft blocks.
UPDATE `pages_draft` pd
    JOIN `page_templates` pt ON pt.canvas_page_id = pd.page_id
SET pd.template_id = pt.id,
    pd.page_id     = NULL
WHERE pt.canvas_page_id IS NOT NULL;


-- ── 6. Record migration ───────────────────────────────────────
INSERT INTO `module_migrations` (`module`, `filename`)
VALUES ('core', '012_template_id_on_pages.sql')
ON DUPLICATE KEY UPDATE `filename` = VALUES(`filename`);
