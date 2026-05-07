-- ============================================================
-- Events Module — Migration 003
-- ============================================================
-- Adds: related_article_id to events table
-- ============================================================

ALTER TABLE `events`
    ADD COLUMN IF NOT EXISTS `related_article_id` INT UNSIGNED NULL
    AFTER `external_form_url`;
