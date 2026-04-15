-- ============================================================
-- Membership Module — Enhancement: Membership Year Tracking
-- ============================================================
-- Adds membership_year to members for tracking annual cycles
-- ============================================================

ALTER TABLE `members`
    ADD COLUMN `membership_year` SMALLINT UNSIGNED NULL
        COMMENT 'Year membership was last paid / active (e.g. 2025)'
    AFTER `status`;
