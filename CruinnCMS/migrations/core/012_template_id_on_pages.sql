-- ============================================================
-- Migration 012 — Template block ownership via template_id
--
-- Previously, template layout blocks were stored in `pages` with
-- a page_id pointing to a proxy pages_index row (canvas_page_id
-- on page_templates). This creates unnecessary indirection.
--
-- This migration adds direct ownership:
--   pages.template_id  — FK to page_templates.id
--   pages_draft.template_id — same, for in-progress edits
--
-- pages.page_id is made nullable so template-owned blocks can set
-- page_id = NULL once migrated. Application logic enforces the
-- mutual exclusion (one of page_id / template_id must be set).
--
-- page_templates.canvas_page_id is now deprecated. It is left
-- intact for one release to allow rollback; a later migration
-- will drop it once all instances have been verified clean.
-- ============================================================

-- ── 1. Make pages.page_id nullable ───────────────────────────

ALTER TABLE `pages`
    DROP FOREIGN KEY `fk_pages_page_id`;

ALTER TABLE `pages`
    MODIFY COLUMN `page_id` INT UNSIGNED NULL DEFAULT NULL;

ALTER TABLE `pages`
    ADD CONSTRAINT `fk_pages_page_id`
        FOREIGN KEY (`page_id`) REFERENCES `pages_index` (`id`) ON DELETE CASCADE;

-- ── 2. Add template_id to pages ──────────────────────────────

ALTER TABLE `pages`
    ADD COLUMN `template_id` INT UNSIGNED NULL DEFAULT NULL
        COMMENT 'Set for template layout blocks; mutually exclusive with page_id'
        AFTER `page_id`,
    ADD INDEX `idx_pages_template` (`template_id`),
    ADD CONSTRAINT `fk_pages_template_id`
        FOREIGN KEY (`template_id`) REFERENCES `page_templates` (`id`) ON DELETE CASCADE;

-- ── 3. Make pages_draft.page_id nullable ─────────────────────

ALTER TABLE `pages_draft`
    DROP FOREIGN KEY `fk_pages_draft_page`;

ALTER TABLE `pages_draft`
    MODIFY COLUMN `page_id` INT UNSIGNED NULL DEFAULT NULL;

ALTER TABLE `pages_draft`
    ADD CONSTRAINT `fk_pages_draft_page`
        FOREIGN KEY (`page_id`) REFERENCES `pages_index` (`id`) ON DELETE CASCADE;

-- ── 4. Add template_id to pages_draft ────────────────────────

ALTER TABLE `pages_draft`
    ADD COLUMN `template_id` INT UNSIGNED NULL DEFAULT NULL
        COMMENT 'Set for template layout block drafts; mutually exclusive with page_id'
        AFTER `page_id`,
    ADD INDEX `idx_pages_draft_template` (`template_id`),
    ADD CONSTRAINT `fk_pages_draft_template_id`
        FOREIGN KEY (`template_id`) REFERENCES `page_templates` (`id`) ON DELETE CASCADE;

-- ── 5. Data migration — move canvas blocks to template ownership ──

-- For each template that has a canvas_page_id, re-point its published
-- blocks from page_id → template_id and clear page_id.
UPDATE `pages` pb
    JOIN `page_templates` pt ON pt.canvas_page_id = pb.page_id
SET pb.template_id = pt.id,
    pb.page_id     = NULL
WHERE pt.canvas_page_id IS NOT NULL;

-- Same for draft blocks.
UPDATE `pages_draft` pd
    JOIN `page_templates` pt ON pt.canvas_page_id = pd.page_id
SET pd.template_id = pt.id,
    pd.page_id     = NULL
WHERE pt.canvas_page_id IS NOT NULL;

-- ── 6. Record migration ───────────────────────────────────────
INSERT INTO `module_migrations` (`module`, `filename`)
VALUES ('core', '012_template_id_on_pages.sql')
ON DUPLICATE KEY UPDATE `filename` = VALUES(`filename`);
