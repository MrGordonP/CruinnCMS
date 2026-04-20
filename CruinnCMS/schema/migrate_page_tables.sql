-- ============================================================
-- Migration: Rename page/block tables to clean schema
--
-- pages         (meta index)  → pages_index
-- cruinn_blocks (published)   → pages
-- cruinn_draft_blocks         → pages_draft  (strips is_active, is_deletion, prev_id)
-- cruinn_page_state           → DROP
--
-- Run once on the IGA instance DB.
-- Safe to re-run: each step is guarded.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Rename existing pages meta table
ALTER TABLE `pages` RENAME TO `pages_index`;

-- Update the slug unique key name (cosmetic, not required but cleaner)
ALTER TABLE `pages_index`
    DROP KEY `uk_pages_slug`,
    ADD UNIQUE KEY `uk_pages_index_slug` (`slug`),
    DROP KEY `idx_pages_status`,
    ADD KEY `idx_pages_index_status` (`status`),
    DROP FOREIGN KEY `fk_pages_created_by`,
    ADD CONSTRAINT `fk_pages_index_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CHANGE `render_mode` `render_mode` ENUM('block','html','file') NOT NULL DEFAULT 'block';

-- Update foreign keys pointing to old pages.id
ALTER TABLE `page_templates`
    DROP FOREIGN KEY `fk_tpl_canvas_page`,
    ADD CONSTRAINT `fk_tpl_canvas_page` FOREIGN KEY (`canvas_page_id`) REFERENCES `pages_index` (`id`) ON DELETE SET NULL;

ALTER TABLE `menu_items`
    DROP FOREIGN KEY `fk_menu_items_page`,
    ADD CONSTRAINT `fk_menu_items_page` FOREIGN KEY (`page_id`) REFERENCES `pages_index` (`id`) ON DELETE SET NULL;

-- 2. Rename cruinn_blocks → pages
ALTER TABLE `cruinn_blocks` RENAME TO `pages`;

ALTER TABLE `pages`
    DROP FOREIGN KEY `fk_cb_page`,
    DROP KEY `idx_page`,
    ADD KEY `idx_pages_page` (`page_id`, `parent_block_id`, `sort_order`),
    ADD CONSTRAINT `fk_pages_page_id` FOREIGN KEY (`page_id`) REFERENCES `pages_index` (`id`) ON DELETE CASCADE;

-- 3. Create pages_draft from cruinn_draft_blocks (stripped columns)
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

-- Carry over active draft rows only (is_active=1, is_deletion=0)
INSERT INTO `pages_draft` (`page_id`, `edit_seq`, `block_id`, `block_type`, `inner_html`, `css_props`, `block_config`, `sort_order`, `parent_block_id`, `created_at`)
SELECT `page_id`, `edit_seq`, `block_id`, `block_type`, `inner_html`, `css_props`, `block_config`, `sort_order`, `parent_block_id`, `created_at`
FROM `cruinn_draft_blocks`
WHERE `is_active` = 1 AND `is_deletion` = 0;

-- 4. Drop old tables
DROP TABLE IF EXISTS `cruinn_draft_blocks`;
DROP TABLE IF EXISTS `cruinn_page_state`;

-- 5. Rename render_mode value 'cruinn' → 'block' in existing rows
UPDATE `pages_index` SET `render_mode` = 'block' WHERE `render_mode` = 'cruinn';

SET FOREIGN_KEY_CHECKS = 1;
