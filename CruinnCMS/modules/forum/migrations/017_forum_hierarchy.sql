-- ============================================================
-- Cruinn CMS — Migration 017: Forum Category Hierarchy
--
-- Adds parent_id to forum_categories so categories can nest,
-- mirroring the phpBB forum/sub-forum structure.
-- ============================================================

SET NAMES utf8mb4;

ALTER TABLE `forum_categories`
    ADD COLUMN IF NOT EXISTS `parent_id` INT UNSIGNED NULL DEFAULT NULL AFTER `id`;

ALTER TABLE `forum_categories` ADD KEY IF NOT EXISTS `idx_forum_categories_parent` (`parent_id`);

SET @fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'forum_categories' AND CONSTRAINT_NAME = 'fk_forum_categories_parent');
SET @sql = IF(@fk = 0, 'ALTER TABLE `forum_categories` ADD CONSTRAINT `fk_forum_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `forum_categories` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
