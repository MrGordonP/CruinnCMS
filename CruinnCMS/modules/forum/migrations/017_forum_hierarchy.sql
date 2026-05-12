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

ALTER TABLE `forum_categories` DROP FOREIGN KEY IF EXISTS `fk_forum_categories_parent`;
ALTER TABLE `forum_categories` ADD CONSTRAINT `fk_forum_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `forum_categories` (`id`) ON DELETE CASCADE;
