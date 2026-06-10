-- ============================================================
-- Notifications Module — Full Schema
-- ============================================================

SET NAMES utf8mb4;

-- In-app notification inbox per user
CREATE TABLE IF NOT EXISTS `notifications` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED  NOT NULL,
    `category`   VARCHAR(60)   NOT NULL DEFAULT 'general',
    `title`      VARCHAR(255)  NOT NULL,
    `body`       TEXT          NULL,
    `url`        VARCHAR(500)  NULL,
    `subject_id` INT UNSIGNED  NULL,
    `read_at`    DATETIME      NULL,
    `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notifications_user`     (`user_id`),
    KEY `idx_notifications_unread`   (`user_id`, `read_at`),
    KEY `idx_notifications_category` (`category`),
    CONSTRAINT `fk_notifications_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE,
    CONSTRAINT `fk_notifications_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-user notification delivery preferences by category
CREATE TABLE IF NOT EXISTS `notification_preferences` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED NOT NULL,
    `category`        VARCHAR(60)  NOT NULL,
    `in_app`          TINYINT(1)   NOT NULL DEFAULT 1,
    `email_frequency` ENUM('immediate','daily','weekly','off') NOT NULL DEFAULT 'daily',
    `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_notif_pref_user_cat` (`user_id`, `category`),
    CONSTRAINT `fk_notif_pref_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
