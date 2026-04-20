-- ============================================================
-- CruinnCMS — OAuth Migration 002: Email Verification Columns
--
-- Adds email verification token columns to the users table.
-- Used for manual registration (OAuth users are auto-verified
-- as the provider has already confirmed email ownership).
-- ============================================================

SET NAMES utf8mb4;

ALTER TABLE `users`
    ADD COLUMN `email_verify_token`  VARCHAR(64)  NULL DEFAULT NULL AFTER `password_hash`,
    ADD COLUMN `email_verify_expiry` DATETIME     NULL DEFAULT NULL AFTER `email_verify_token`;
