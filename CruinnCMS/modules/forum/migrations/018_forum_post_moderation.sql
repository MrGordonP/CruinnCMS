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
    ADD COLUMN `is_deleted`  TINYINT(1) NOT NULL DEFAULT 0 AFTER `body_html`,
    ADD COLUMN `deleted_at`  DATETIME NULL DEFAULT NULL AFTER `is_deleted`,
    ADD COLUMN `deleted_by`  INT UNSIGNED NULL DEFAULT NULL AFTER `deleted_at`,
    ADD COLUMN `edit_count`  SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER `deleted_by`,
    ADD COLUMN `edited_at`   DATETIME NULL DEFAULT NULL AFTER `edit_count`,
    ADD KEY `idx_forum_posts_deleted` (`is_deleted`),
    ADD CONSTRAINT `fk_forum_posts_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- Thread soft-delete
ALTER TABLE `forum_threads`
    ADD COLUMN `is_deleted`  TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_locked`,
    ADD COLUMN `deleted_at`  DATETIME NULL DEFAULT NULL AFTER `is_deleted`,
    ADD COLUMN `deleted_by`  INT UNSIGNED NULL DEFAULT NULL AFTER `deleted_at`,
    ADD KEY `idx_forum_threads_deleted` (`is_deleted`),
    ADD CONSTRAINT `fk_forum_threads_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

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
