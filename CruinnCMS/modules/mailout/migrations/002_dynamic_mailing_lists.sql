-- ============================================================
-- Mailout Module — Migration 002: Dynamic Mailing Lists
--
-- Adds support for auto-populating mailing lists based on
-- database queries (e.g., "all 2026 members").
-- ============================================================

SET NAMES utf8mb4;

-- Add fields for dynamic list configuration
-- Note: Errors for duplicate columns (1060) are caught and ignored by migration runner
ALTER TABLE `mailing_lists`
    ADD COLUMN `is_dynamic` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = auto-populated from query' AFTER `is_active`;

ALTER TABLE `mailing_lists`
    ADD COLUMN `source_table` VARCHAR(50) NULL COMMENT 'members, users, groups, etc.' AFTER `is_dynamic`;

ALTER TABLE `mailing_lists`
    ADD COLUMN `source_criteria` JSON NULL COMMENT 'Filter criteria: {"status":"active","year":2026}' AFTER `source_table`;

ALTER TABLE `mailing_lists`
    ADD COLUMN `last_synced_at` DATETIME NULL COMMENT 'Last auto-population run' AFTER `source_criteria`;
