-- ============================================================
-- Migration 010 — Remove structural seeds from instance schema
--
-- Removes the hardcoded page_templates, system zone pages_index
-- rows, and associated block data that violate the engine's
-- no-structural-assumptions principle (see EDITOR_ZONE_REFACTOR.md).
--
-- Structural defaults (header/footer canvases, default templates)
-- now belong in theme seed files applied explicitly on theme
-- activation, not at install time.
--
-- Adds the editor.zone_suggestions setting (used by editor.js
-- to populate the zone name autocomplete list).
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ── Remove seeded header/footer blocks ───────────────────────
DELETE FROM `pages` WHERE `block_id` IN (
    'seed_hdr_section', 'seed_hdr_title', 'seed_hdr_main', 'seed_hdr_topbar',
    'seed_ftr_section', 'seed_ftr_copy', 'seed_ftr_nav'
);

-- ── Remove system zone canvas pages ──────────────────────────
DELETE FROM `pages_index` WHERE `slug` IN (
    '_header', '_footer', '_tpl__global_header', '_tpl_default', '_typography'
);

-- ── Remove default page templates ────────────────────────────
DELETE FROM `page_templates` WHERE `slug` IN (
    'default', 'full-width', 'landing', 'blank', 'sidebar-right', 'sidebar-left'
) AND `is_system` = 1;

SET FOREIGN_KEY_CHECKS = 1;

-- ── Add editor zone suggestions setting ──────────────────────
INSERT INTO `settings` (`key`, `value`, `group`)
VALUES ('editor.zone_suggestions', 'main,header,footer,sidebar', 'editor')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
