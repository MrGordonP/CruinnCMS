-- ============================================================
-- Mailout Module — Full Schema
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `mailing_lists` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug`              VARCHAR(80)  NOT NULL,
    `name`              VARCHAR(160) NOT NULL,
    `description`       TEXT         NULL,
    `is_public`         TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1 = visible in subscription prefs',
    `subscription_mode` ENUM('open','request') NOT NULL DEFAULT 'open',
    `is_active`         TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_mailing_lists_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mailing_list_subscriptions` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `list_id`           INT UNSIGNED NOT NULL,
    `user_id`           INT UNSIGNED NULL,
    `email`             VARCHAR(255) NOT NULL,
    `name`              VARCHAR(200) NULL,
    `unsubscribe_token` CHAR(64)     NOT NULL,
    `status`            ENUM('active','unsubscribed','bounced','pending') NOT NULL DEFAULT 'active',
    `subscribed_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `unsubscribed_at`   DATETIME     NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_subscription_list_email` (`list_id`, `email`),
    INDEX `idx_subscription_user`  (`user_id`),
    INDEX `idx_subscription_token` (`unsubscribe_token`),
    CONSTRAINT `fk_subscription_list` FOREIGN KEY (`list_id`) REFERENCES `mailing_lists`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_subscription_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)         ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_broadcasts` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `list_id`          INT UNSIGNED NULL COMMENT 'NULL = ad-hoc, not tied to a list',
    `target_type`      ENUM('list','members','portal_users') NOT NULL DEFAULT 'list',
    `target_config`    JSON         NULL COMMENT 'Filter params: {"member_status":["active"],"membership_year":2025}',
    `subject`          VARCHAR(255) NOT NULL,
    `body_html`        MEDIUMTEXT   NOT NULL,
    `body_text`        TEXT         NOT NULL DEFAULT '',
    `status`           ENUM('draft','queued','sending','sent','failed') NOT NULL DEFAULT 'draft',
    `recipient_count`  INT UNSIGNED NOT NULL DEFAULT 0,
    `sent_count`       INT UNSIGNED NOT NULL DEFAULT 0,
    `created_by`       INT UNSIGNED NULL,
    `scheduled_at`     DATETIME     NULL,
    `started_at`       DATETIME     NULL,
    `completed_at`     DATETIME     NULL,
    `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_broadcast_status` (`status`),
    CONSTRAINT `fk_broadcast_list`       FOREIGN KEY (`list_id`)    REFERENCES `mailing_lists`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_broadcast_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)         ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_queue` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `broadcast_id`      INT UNSIGNED NOT NULL,
    `recipient_email`   VARCHAR(255) NOT NULL,
    `recipient_name`    VARCHAR(100) NOT NULL DEFAULT '',
    `unsubscribe_token` CHAR(64)     NULL COMMENT 'From mailing_list_subscriptions',
    `status`            ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
    `attempts`          TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `last_error`        TEXT         NULL,
    `next_retry_at`     DATETIME     NULL,
    `processed_at`      DATETIME     NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_queue_broadcast`    (`broadcast_id`),
    INDEX `idx_queue_status_retry` (`status`, `next_retry_at`),
    INDEX `idx_queue_email`        (`recipient_email`),
    CONSTRAINT `fk_queue_broadcast` FOREIGN KEY (`broadcast_id`) REFERENCES `email_broadcasts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_unsubscribes` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email`      VARCHAR(255) NOT NULL,
    `reason`     ENUM('unsubscribe','bounce','complaint','manual') NOT NULL DEFAULT 'unsubscribe',
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_email_unsubscribes_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
