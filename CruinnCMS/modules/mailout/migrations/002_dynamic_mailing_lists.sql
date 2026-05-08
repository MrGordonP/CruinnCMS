-- ============================================================
-- Mailout Module — Migration 002: Dynamic Mailing Lists
--
-- Adds support for auto-populating mailing lists based on
-- database queries (e.g., "all 2026 members").
-- ============================================================

SET NAMES utf8mb4;

-- Add fields for dynamic list configuration
ALTER TABLE `mailing_lists`
    ADD COLUMN IF NOT EXISTS `is_dynamic` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = auto-populated from query' AFTER `is_active`,
    ADD COLUMN IF NOT EXISTS `source_table` VARCHAR(50) NULL COMMENT 'members, users, groups, etc.' AFTER `is_dynamic`,
    ADD COLUMN IF NOT EXISTS `source_criteria` JSON NULL COMMENT 'Filter criteria: {"status":"active","year":2026}' AFTER `source_table`,
    ADD COLUMN IF NOT EXISTS `last_synced_at` DATETIME NULL COMMENT 'Last auto-population run' AFTER `source_criteria`;

-- Add subscription_mode if not exists (for request-based subscriptions)
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'mailing_lists'
    AND COLUMN_NAME = 'subscription_mode');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `mailing_lists` ADD COLUMN `subscription_mode` ENUM(''open'',''request'') NOT NULL DEFAULT ''open'' AFTER `is_active`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add 'pending' status to subscriptions if not already there
ALTER TABLE `mailing_list_subscriptions`
    MODIFY COLUMN `status` ENUM('active','unsubscribed','bounced','pending') NOT NULL DEFAULT 'active';
