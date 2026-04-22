-- Migration 004: Add activity_log table
-- Apply to: instance DB

CREATE TABLE IF NOT EXISTS `activity_log` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED    NULL,
    `action`      VARCHAR(50)     NOT NULL,
    `entity_type` VARCHAR(50)     NOT NULL,
    `entity_id`   INT UNSIGNED    NULL,
    `details`     TEXT            NULL,
    `ip_address`  VARCHAR(45)     NULL,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_activity_user`   (`user_id`),
    KEY `idx_activity_entity` (`entity_type`, `entity_id`),
    KEY `idx_activity_date`   (`created_at`),
    CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
