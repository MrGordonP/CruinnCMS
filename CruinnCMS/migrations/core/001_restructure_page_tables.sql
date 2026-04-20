-- ============================================================
-- Core Migration 001: Rename page/block tables to clean schema
--
-- pages             (meta index)  → pages_index
-- cruinn_blocks     (published)   → pages
-- cruinn_draft_blocks             → pages_draft
-- cruinn_page_state               → DROP
--
-- Each step is guarded: will not fail if already applied.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── Step 1: Rename pages → pages_index ───────────────────────
-- Guard: only run if pages_index does not already exist AND pages does
DROP PROCEDURE IF EXISTS _cruinn_migrate_001;
DELIMITER ;;
CREATE PROCEDURE _cruinn_migrate_001()
BEGIN
    -- Step 1: pages → pages_index
    IF EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'pages'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'pages_index'
    ) THEN
        ALTER TABLE `pages` RENAME TO `pages_index`;

        ALTER TABLE `pages_index`
            DROP KEY IF EXISTS `uk_pages_slug`,
            ADD UNIQUE KEY `uk_pages_index_slug` (`slug`);

        -- Drop and re-add status index
        IF EXISTS (
            SELECT 1 FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = 'pages_index'
              AND index_name = 'idx_pages_status'
        ) THEN
            ALTER TABLE `pages_index` DROP KEY `idx_pages_status`;
        END IF;
        ALTER TABLE `pages_index` ADD KEY `idx_pages_index_status` (`status`);

        -- Drop old FK, add new
        IF EXISTS (
            SELECT 1 FROM information_schema.referential_constraints
            WHERE constraint_schema = DATABASE() AND constraint_name = 'fk_pages_created_by'
        ) THEN
            ALTER TABLE `pages_index` DROP FOREIGN KEY `fk_pages_created_by`;
        END IF;
        ALTER TABLE `pages_index`
            ADD CONSTRAINT `fk_pages_index_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

        -- Normalise render_mode enum value cruinn → block
        -- Step 1: expand enum to include both old and new value
        ALTER TABLE `pages_index`
            CHANGE `render_mode` `render_mode` ENUM('cruinn','block','html','file') NOT NULL DEFAULT 'block';
        -- Step 2: migrate data
        UPDATE `pages_index` SET `render_mode` = 'block' WHERE `render_mode` = 'cruinn';
        -- Step 3: remove old value now that no rows reference it
        ALTER TABLE `pages_index`
            CHANGE `render_mode` `render_mode` ENUM('block','html','file') NOT NULL DEFAULT 'block';

        -- Update FKs on dependent tables
        IF EXISTS (
            SELECT 1 FROM information_schema.referential_constraints
            WHERE constraint_schema = DATABASE() AND constraint_name = 'fk_tpl_canvas_page'
        ) THEN
            ALTER TABLE `page_templates` DROP FOREIGN KEY `fk_tpl_canvas_page`;
        END IF;
        ALTER TABLE `page_templates`
            ADD CONSTRAINT `fk_tpl_canvas_page` FOREIGN KEY (`canvas_page_id`) REFERENCES `pages_index` (`id`) ON DELETE SET NULL;

        IF EXISTS (
            SELECT 1 FROM information_schema.referential_constraints
            WHERE constraint_schema = DATABASE() AND constraint_name = 'fk_menu_items_page'
        ) THEN
            ALTER TABLE `menu_items` DROP FOREIGN KEY `fk_menu_items_page`;
        END IF;
        ALTER TABLE `menu_items`
            ADD CONSTRAINT `fk_menu_items_page` FOREIGN KEY (`page_id`) REFERENCES `pages_index` (`id`) ON DELETE SET NULL;
    END IF;

    -- Step 2: cruinn_blocks → pages
    IF EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'cruinn_blocks'
    ) THEN
        ALTER TABLE `cruinn_blocks` RENAME TO `pages`;

        IF EXISTS (
            SELECT 1 FROM information_schema.referential_constraints
            WHERE constraint_schema = DATABASE() AND constraint_name = 'fk_cb_page'
        ) THEN
            ALTER TABLE `pages` DROP FOREIGN KEY `fk_cb_page`;
        END IF;
        IF EXISTS (
            SELECT 1 FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = 'pages' AND index_name = 'idx_page'
        ) THEN
            ALTER TABLE `pages` DROP KEY `idx_page`;
        END IF;
        ALTER TABLE `pages`
            ADD KEY `idx_pages_page` (`page_id`, `parent_block_id`, `sort_order`),
            ADD CONSTRAINT `fk_pages_page_id` FOREIGN KEY (`page_id`) REFERENCES `pages_index` (`id`) ON DELETE CASCADE;
    END IF;

    -- Step 3: cruinn_draft_blocks → pages_draft
    IF EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'cruinn_draft_blocks'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'pages_draft'
    ) THEN
        CREATE TABLE `pages_draft` (
            `id`              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
            `page_id`         INT UNSIGNED      NOT NULL,
            `edit_seq`        INT UNSIGNED      NOT NULL,
            `block_id`        VARCHAR(20)       NOT NULL,
            `block_type`      VARCHAR(40)       NOT NULL,
            `inner_html`      MEDIUMTEXT        NULL,
            `css_props`       JSON              NULL,
            `block_config`    JSON              NULL,
            `sort_order`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `parent_block_id` VARCHAR(20)       NULL,
            `created_at`      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_pages_draft_page_seq` (`page_id`, `edit_seq`),
            KEY `idx_pages_draft_block`    (`page_id`, `block_id`),
            CONSTRAINT `fk_pages_draft_page` FOREIGN KEY (`page_id`) REFERENCES `pages_index` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        INSERT INTO `pages_draft`
            (`page_id`, `edit_seq`, `block_id`, `block_type`, `inner_html`, `css_props`, `block_config`, `sort_order`, `parent_block_id`, `created_at`)
        SELECT `page_id`, `edit_seq`, `block_id`, `block_type`, `inner_html`, `css_props`, `block_config`, `sort_order`, `parent_block_id`, `created_at`
        FROM `cruinn_draft_blocks`
        WHERE `is_active` = 1 AND `is_deletion` = 0;

        DROP TABLE `cruinn_draft_blocks`;
    END IF;

    -- Step 4: drop cruinn_page_state
    IF EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'cruinn_page_state'
    ) THEN
        DROP TABLE `cruinn_page_state`;
    END IF;
END;;
DELIMITER ;

CALL _cruinn_migrate_001();
DROP PROCEDURE IF EXISTS _cruinn_migrate_001;

SET FOREIGN_KEY_CHECKS = 1;
