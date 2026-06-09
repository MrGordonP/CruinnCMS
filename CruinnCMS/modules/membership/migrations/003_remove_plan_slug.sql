-- ============================================================
-- Membership Module — Migration 003
-- Remove slug column from membership_plans
-- ============================================================

SET NAMES utf8mb4;

ALTER TABLE `membership_plans`
    DROP INDEX IF EXISTS `uk_membership_plans_slug`,
    DROP COLUMN IF EXISTS `slug`;
