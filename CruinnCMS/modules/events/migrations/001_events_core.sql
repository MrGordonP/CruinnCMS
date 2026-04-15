-- ============================================================
-- Events Module — Core Schema
-- ============================================================
-- Creates: events, event_registrations
-- ============================================================

CREATE TABLE IF NOT EXISTS `events` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`            VARCHAR(255) NOT NULL,
    `slug`             VARCHAR(255) NOT NULL,
    `description`      TEXT NULL,
    `location`         VARCHAR(255) NULL,
    `location_url`     VARCHAR(500) NULL,
    `start_date`       DATE NOT NULL,
    `start_time`       TIME NULL,
    `end_date`         DATE NULL,
    `end_time`         TIME NULL,
    `is_all_day`       TINYINT(1) NOT NULL DEFAULT 0,
    `capacity`         INT UNSIGNED NULL COMMENT 'NULL = unlimited',
    `price`            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `currency`         CHAR(3) NOT NULL DEFAULT 'EUR',
    `reg_required`     TINYINT(1) NOT NULL DEFAULT 0,
    `reg_deadline`     DATETIME NULL,
    `registration_open` TINYINT(1) NOT NULL DEFAULT 1,
    `status`           ENUM('draft','published','cancelled','completed') NOT NULL DEFAULT 'draft',
    `featured_image`   VARCHAR(500) NULL,
    `created_by`       INT UNSIGNED NULL,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_events_slug` (`slug`),
    INDEX `idx_events_start` (`start_date`),
    INDEX `idx_events_status` (`status`),
    CONSTRAINT `fk_events_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `event_registrations` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_id`           INT UNSIGNED NOT NULL,
    `user_id`            INT UNSIGNED NULL,
    `name`               VARCHAR(200) NOT NULL,
    `email`              VARCHAR(255) NOT NULL,
    `phone`              VARCHAR(50) NULL,
    `attendees`          TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `notes`              TEXT NULL,
    `amount_paid`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `status`             ENUM('confirmed','cancelled','waitlisted') NOT NULL DEFAULT 'confirmed',
    `confirmation_token` VARCHAR(64) NULL,
    `cancelled_at`       DATETIME NULL,
    `cancel_reason`      VARCHAR(255) NULL,
    `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_registrations_event` (`event_id`),
    INDEX `idx_registrations_user` (`user_id`),
    INDEX `idx_registrations_email` (`email`),
    INDEX `idx_registrations_status` (`status`),
    INDEX `idx_registrations_token` (`confirmation_token`),
    CONSTRAINT `fk_registrations_event` FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_registrations_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
