-- ============================================================
-- Cruinn CMS — Migration 009: Forum Core
--
-- Native forum tables for public/member discussion.
-- Designed as provider-backed implementation (native provider first).
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `forum_categories` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`        VARCHAR(120) NOT NULL,
    `slug`         VARCHAR(120) NOT NULL,
    `description`  TEXT NULL,
    `access_role`  ENUM('public','member','council','admin') NOT NULL DEFAULT 'public',
    `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order`   INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_forum_categories_slug` (`slug`),
    KEY `idx_forum_categories_active_order` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `forum_threads` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id`       INT UNSIGNED NOT NULL,
    `user_id`           INT UNSIGNED NOT NULL,
    `title`             VARCHAR(255) NOT NULL,
    `slug`              VARCHAR(255) NOT NULL,
    `is_pinned`         TINYINT(1) NOT NULL DEFAULT 0,
    `is_locked`         TINYINT(1) NOT NULL DEFAULT 0,
    `reply_count`       INT UNSIGNED NOT NULL DEFAULT 0,
    `last_post_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_post_user_id` INT UNSIGNED NULL,
    `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_forum_threads_category` (`category_id`),
    KEY `idx_forum_threads_last_post` (`last_post_at`),
    KEY `idx_forum_threads_pinned` (`is_pinned`),
    CONSTRAINT `fk_forum_threads_category` FOREIGN KEY (`category_id`) REFERENCES `forum_categories` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_forum_threads_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_forum_threads_last_user` FOREIGN KEY (`last_post_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `forum_posts` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `thread_id`   INT UNSIGNED NOT NULL,
    `user_id`     INT UNSIGNED NOT NULL,
    `body_html`   MEDIUMTEXT NOT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_forum_posts_thread_created` (`thread_id`, `created_at`),
    CONSTRAINT `fk_forum_posts_thread` FOREIGN KEY (`thread_id`) REFERENCES `forum_threads` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_forum_posts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `forum_categories` (`title`, `slug`, `description`, `access_role`, `is_active`, `sort_order`)
SELECT * FROM (
    SELECT
        'General Discussion' AS `title`,
        'general' AS `slug`,
        'General community discussion.' AS `description`,
        'public' AS `access_role`,
        1 AS `is_active`,
        1 AS `sort_order`
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `forum_categories` WHERE `slug` = 'general') LIMIT 1;

INSERT INTO `forum_categories` (`title`, `slug`, `description`, `access_role`, `is_active`, `sort_order`)
SELECT * FROM (
    SELECT
        'Fieldtrips and Events' AS `title`,
        'fieldtrips-events' AS `slug`,
        'Trip reports, fieldwork planning, and event follow-up.' AS `description`,
        'member' AS `access_role`,
        1 AS `is_active`,
        2 AS `sort_order`
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `forum_categories` WHERE `slug` = 'fieldtrips-events') LIMIT 1;

INSERT INTO `forum_categories` (`title`, `slug`, `description`, `access_role`, `is_active`, `sort_order`)
SELECT * FROM (
    SELECT
        'Council and Operations (Forum)' AS `title`,
        'council-ops-forum' AS `slug`,
        'Council-only operational discussion in the forum module.' AS `description`,
        'council' AS `access_role`,
        1 AS `is_active`,
        3 AS `sort_order`
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `forum_categories` WHERE `slug` = 'council-ops-forum') LIMIT 1;

SET FOREIGN_KEY_CHECKS = 1;
