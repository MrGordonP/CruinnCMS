-- ============================================================
-- IGA Portal — Migration 017: Forum Category Hierarchy
--
-- Adds parent_id to forum_categories so categories can nest,
-- mirroring the phpBB forum/sub-forum structure.
-- ============================================================

SET NAMES utf8mb4;

ALTER TABLE `forum_categories`
    ADD COLUMN `parent_id` INT UNSIGNED NULL DEFAULT NULL AFTER `id`,
    ADD KEY `idx_forum_categories_parent` (`parent_id`),
    ADD CONSTRAINT `fk_forum_categories_parent`
        FOREIGN KEY (`parent_id`) REFERENCES `forum_categories` (`id`)
        ON DELETE CASCADE;
