-- ============================================================
-- Notifications Module — Migration 002: Hub event log
-- ============================================================
-- Last edit: 2026-06-11 16:00 UTC.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `notification_hub_events` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `source_module`      VARCHAR(80)  NOT NULL,
    `source_event`       VARCHAR(120) NOT NULL,
    `category`           VARCHAR(60)  NOT NULL DEFAULT 'general',
    `title`              VARCHAR(255) NOT NULL,
    `body`               TEXT NULL,
    `url`                VARCHAR(500) NULL,
    `subject_id`         INT UNSIGNED NULL,
    `actor_user_id`      INT UNSIGNED NULL,
    `recipient_count`    INT UNSIGNED NOT NULL DEFAULT 0,
    `recipient_user_ids` JSON NULL,
    `dedupe_key`         VARCHAR(191) NULL,
    `metadata`           JSON NULL,
    `status`             ENUM('queued','delivered','skipped','failed') NOT NULL DEFAULT 'queued',
    `delivered_count`    INT UNSIGNED NOT NULL DEFAULT 0,
    `error_message`      TEXT NULL,
    `processed_at`       DATETIME NULL,
    `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notif_hub_status` (`status`),
    KEY `idx_notif_hub_source` (`source_module`, `source_event`),
    KEY `idx_notif_hub_category` (`category`),
    KEY `idx_notif_hub_created` (`created_at`),
    KEY `idx_notif_hub_dedupe` (`dedupe_key`),
    CONSTRAINT `fk_notif_hub_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_notif_hub_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
