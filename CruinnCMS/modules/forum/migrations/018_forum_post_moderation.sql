-- ============================================================
-- Cruinn CMS — Migration 018: Forum Post Moderation
--
-- Adds soft-delete, edit tracking to forum_posts.
-- Adds forum_post_reports for user report-a-post functionality.
-- Adds is_deleted soft-delete to forum_threads.
-- ============================================================

SET NAMES utf8mb4;

-- Post edit tracking and soft-delete
ALTER TABLE `forum_posts`
    ADD COLUMN IF NOT EXISTS `is_deleted`  TINYINT(1) NOT NULL DEFAULT 0 AFTER `body_html`,
    ADD COLUMN IF NOT EXISTS `deleted_at`  DATETIME NULL DEFAULT NULL AFTER `is_deleted`,
    ADD COLUMN IF NOT EXISTS `deleted_by`  INT UNSIGNED NULL DEFAULT NULL AFTER `deleted_at`,
    ADD COLUMN IF NOT EXISTS `edit_count`  SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER `deleted_by`,
    ADD COLUMN IF NOT EXISTS `edited_at`   DATETIME NULL DEFAULT NULL AFTER `edit_count`;

ALTER TABLE `forum_posts` ADD KEY IF NOT EXISTS `idx_forum_posts_deleted` (`is_deleted`);

SET @fk3 = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'forum_posts' AND CONSTRAINT_NAME = 'fk_forum_posts_deleted_by');
SET @sql3 = IF(@fk3 = 0, 'ALTER TABLE `forum_posts` ADD CONSTRAINT `fk_forum_posts_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt3 FROM @sql3; EXECUTE stmt3; DEALLOCATE PREPARE stmt3;

-- Thread soft-delete
ALTER TABLE `forum_threads`
    ADD COLUMN IF NOT EXISTS `is_deleted`  TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_locked`,
    ADD COLUMN IF NOT EXISTS `deleted_at`  DATETIME NULL DEFAULT NULL AFTER `is_deleted`,
    ADD COLUMN IF NOT EXISTS `deleted_by`  INT UNSIGNED NULL DEFAULT NULL AFTER `deleted_at`;

ALTER TABLE `forum_threads` ADD KEY IF NOT EXISTS `idx_forum_threads_deleted` (`is_deleted`);

SET @fk4 = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'forum_threads' AND CONSTRAINT_NAME = 'fk_forum_threads_deleted_by');
SET @sql4 = IF(@fk4 = 0, 'ALTER TABLE `forum_threads` ADD CONSTRAINT `fk_forum_threads_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt4 FROM @sql4; EXECUTE stmt4; DEALLOCATE PREPARE stmt4;

-- Post reports
CREATE TABLE IF NOT EXISTS `forum_post_reports` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `post_id`     INT UNSIGNED NOT NULL,
    `reporter_id` INT UNSIGNED NOT NULL,
    `reason`      VARCHAR(255) NOT NULL,
    `body`        TEXT NULL,
    `status`      ENUM('open','reviewed','dismissed') NOT NULL DEFAULT 'open',
    `reviewed_by` INT UNSIGNED NULL DEFAULT NULL,
    `reviewed_at` DATETIME NULL DEFAULT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_forum_reports_post` (`post_id`),
    KEY `idx_forum_reports_status` (`status`),
    CONSTRAINT `fk_forum_reports_post`     FOREIGN KEY (`post_id`)     REFERENCES `forum_posts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_forum_reports_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_forum_reports_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
