-- Migration 007: Page target zone
-- Adds per-page target template zone selection so page content can be injected
-- into a template zone other than 'main'.

ALTER TABLE `pages_index`
    ADD COLUMN IF NOT EXISTS `page_zone` VARCHAR(50) NOT NULL DEFAULT 'main' AFTER `template`;
