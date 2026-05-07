-- ============================================================
-- Events Module — Migration 002
-- ============================================================
-- Adds: external_form_url to events table
-- ============================================================

ALTER TABLE `events`
    ADD COLUMN `external_form_url` VARCHAR(500) NULL
    AFTER `registration_open`;
