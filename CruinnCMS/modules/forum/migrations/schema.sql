-- ============================================================
-- Forum Module — Full Schema
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `forum_categories` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `parent_id`   INT UNSIGNED    NULL     DEFAULT NULL,
    `name`        VARCHAR(100)    NOT NULL,
    `slug`        VARCHAR(100)    NOT NULL,
    `description` TEXT            NULL,
    `sort_order`  INT UNSIGNED    NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_forum_categories_slug` (`slug`),
    KEY `idx_forum_categories_parent` (`parent_id`),
    CONSTRAINT `fk_forum_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `forum_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `forum_threads` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `category_id` INT UNSIGNED    NOT NULL,
    `author_id`   INT UNSIGNED    NULL,
    `title`       VARCHAR(255)    NOT NULL,
    `is_pinned`   TINYINT(1)      NOT NULL DEFAULT 0,
    `is_locked`   TINYINT(1)      NOT NULL DEFAULT 0,
    `is_deleted`  TINYINT(1)      NOT NULL DEFAULT 0,
    `deleted_at`  DATETIME        NULL,
    `deleted_by`  INT UNSIGNED    NULL,
    `reply_count` INT UNSIGNED    NOT NULL DEFAULT 0,
    `view_count`  INT UNSIGNED    NOT NULL DEFAULT 0,
    `last_post_at` DATETIME       NULL,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_forum_threads_cat`     (`category_id`, `is_pinned`, `last_post_at`),
    KEY `idx_forum_threads_deleted` (`is_deleted`),
    CONSTRAINT `fk_forum_threads_cat`        FOREIGN KEY (`category_id`) REFERENCES `forum_categories` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_forum_threads_author`     FOREIGN KEY (`author_id`)   REFERENCES `users`            (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_forum_threads_deleted_by` FOREIGN KEY (`deleted_by`)  REFERENCES `users`            (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `forum_posts` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `thread_id`   INT UNSIGNED    NOT NULL,
    `author_id`   INT UNSIGNED    NULL,
    `body`        MEDIUMTEXT      NOT NULL,
    `body_html`   MEDIUMTEXT      NULL,
    `is_deleted`  TINYINT(1)      NOT NULL DEFAULT 0,
    `deleted_at`  DATETIME        NULL,
    `deleted_by`  INT UNSIGNED    NULL,
    `edit_count`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `edited_at`   DATETIME        NULL,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_forum_posts_thread`  (`thread_id`, `created_at`),
    KEY `idx_forum_posts_deleted` (`is_deleted`),
    CONSTRAINT `fk_forum_posts_thread`     FOREIGN KEY (`thread_id`)  REFERENCES `forum_threads`   (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_forum_posts_author`     FOREIGN KEY (`author_id`)  REFERENCES `users`           (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_forum_posts_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users`           (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `forum_post_reports` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `post_id`     INT UNSIGNED    NOT NULL,
    `reporter_id` INT UNSIGNED    NOT NULL,
    `reason`      VARCHAR(255)    NOT NULL,
    `body`        TEXT            NULL,
    `status`      ENUM('open','reviewed','dismissed') NOT NULL DEFAULT 'open',
    `reviewed_by` INT UNSIGNED    NULL,
    `reviewed_at` DATETIME        NULL,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_forum_reports_post`   (`post_id`),
    KEY `idx_forum_reports_status` (`status`),
    CONSTRAINT `fk_forum_reports_post`     FOREIGN KEY (`post_id`)     REFERENCES `forum_posts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_forum_reports_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users`       (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_forum_reports_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users`       (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Seed categories ──────────────────────────────────────────
INSERT IGNORE INTO `forum_categories` (`title`, `slug`, `description`, `sort_order`) VALUES
    ('General Discussion', 'general',       'Open discussion for all members.',           1),
    ('Announcements',      'announcements', 'Official announcements from the committee.', 2),
    ('Help & Support',     'help',          'Ask questions and get help from members.',   3);

SET FOREIGN_KEY_CHECKS = 1;
