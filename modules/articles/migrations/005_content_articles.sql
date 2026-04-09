-- ============================================================
-- Migration 005: Content & Articles Phase
-- ============================================================
-- 1. Rename page_blocks → content_blocks + make polymorphic
-- 2. Add featured_image to articles
-- ============================================================

-- ── 1. Rename page_blocks to content_blocks ───────────────────

-- Drop existing FK and index first
ALTER TABLE page_blocks DROP FOREIGN KEY fk_blocks_page;
ALTER TABLE page_blocks DROP INDEX idx_blocks_page_order;

-- Rename the table
RENAME TABLE page_blocks TO content_blocks;

-- Add parent_type column (page or article)
ALTER TABLE content_blocks
    ADD COLUMN parent_type ENUM('page','article') NOT NULL DEFAULT 'page' AFTER id;

-- Rename page_id to parent_id
ALTER TABLE content_blocks CHANGE COLUMN page_id parent_id INT UNSIGNED NOT NULL;

-- Add new composite index
CREATE INDEX idx_blocks_parent_order ON content_blocks (parent_type, parent_id, sort_order);

-- ── 2. Add featured_image to articles ─────────────────────────

ALTER TABLE articles
    ADD COLUMN featured_image VARCHAR(255) NULL AFTER excerpt;
