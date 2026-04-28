-- Migration 003: Add query type support to content_sets
-- Apply once per instance database.

ALTER TABLE `content_sets`
    ADD COLUMN `type` ENUM('manual', 'query') NOT NULL DEFAULT 'manual' AFTER `slug`,
    ADD COLUMN `query_config` JSON NULL AFTER `fields`;
