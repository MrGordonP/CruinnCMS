-- Migration 015: Deleted accounts holding table
--
-- When a member deletes their account, all their data is moved into
-- a single JSON blob in this holding table. The live tables are then
-- anonymised/purged as before. The held data is retained for 30 days
-- in case of accidental deletion or disputes, then exported and purged.
--
-- No archive tables — membership statistics are handled elsewhere.

DROP TABLE IF EXISTS `membership_archive`;
DROP TABLE IF EXISTS `event_attendance_archive`;

CREATE TABLE IF NOT EXISTS `deleted_accounts` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `original_user_id` INT UNSIGNED   NOT NULL COMMENT 'The user ID before deletion',
    `account_data`    JSON            NOT NULL COMMENT 'Full snapshot of all user data at time of deletion',
    `deleted_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at`      DATETIME        NOT NULL COMMENT 'Date after which data should be purged (deleted_at + 30 days)',
    `purged_at`       DATETIME        NULL     COMMENT 'When the record was actually exported and removed',
    PRIMARY KEY (`id`),
    INDEX `idx_da_expires` (`expires_at`),
    INDEX `idx_da_original_user` (`original_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
