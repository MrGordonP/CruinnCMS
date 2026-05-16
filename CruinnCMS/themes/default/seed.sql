-- ============================================================
-- CruinnCMS — Default Theme Seed
-- Theme: default
--
-- Provides the structural defaults for a standard site:
--   • Zone canvas pages: header, footer, typography
--   • Default page templates (6 layouts)
--   • Template canvas pages for header + default templates
--   • Seed blocks for header and footer canvases
--
-- This file is applied via Admin → Theme → Apply Seed.
-- It is fully idempotent — safe to run on a fresh install
-- or an existing instance (INSERT IGNORE throughout).
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ── Zone canvas pages ─────────────────────────────────────────
-- canvas_type and zone_name columns exist after migration 011.
-- If they do not exist yet, the INSERT still works (columns ignored).

INSERT IGNORE INTO `pages_index`
    (`title`, `slug`, `status`, `template`, `editor_mode`, `render_mode`, `canvas_type`, `zone_name`, `created_at`, `updated_at`)
VALUES
    ('Site Header',            '_header',             'published', 'none', 'freeform',   'block', 'zone',           'header', NOW(), NOW()),
    ('Site Footer',            '_footer',             'published', 'none', 'freeform',   'block', 'zone',           'footer', NOW(), NOW()),
    ('Global Header Template', '_tpl__global_header', 'published', 'none', 'freeform',   'block', 'template-shell', NULL,     NOW(), NOW()),
    ('Default Template',       '_tpl_default',        'published', 'none', 'structured', 'block', 'template-shell', NULL,     NOW(), NOW()),
    ('Typography & Styles',    '_typography',         'published', 'none', 'freeform',   'block', 'typography',     NULL,     NOW(), NOW());

-- ── Default page templates ────────────────────────────────────

INSERT IGNORE INTO `page_templates`
    (`slug`, `name`, `description`, `zones`, `css_class`, `is_system`, `sort_order`, `settings`)
VALUES
    ('default',       'Default',                   'Standard reading-width page',              '["header","main","footer"]',           'layout-default',       1, 1, '{"show_title":true,"show_header":true,"show_footer":true,"show_breadcrumbs":false,"content_width":"default","title_align":"left"}'),
    ('full-width',    'Full Width',                'Full container width',                     '["header","main","footer"]',           'layout-full-width',    1, 2, '{"show_title":true,"show_header":true,"show_footer":true,"show_breadcrumbs":false,"content_width":"full","title_align":"left"}'),
    ('landing',       'Landing Page',              'Full-width hero, no title heading',        '["header","main","footer"]',           'layout-landing',       1, 3, '{"show_title":false,"show_header":true,"show_footer":true,"show_breadcrumbs":false,"content_width":"full","title_align":"center"}'),
    ('blank',         'Blank',                     'Raw block output, no chrome',              '["main"]',                             'layout-blank',         1, 4, '{"show_title":false,"show_header":false,"show_footer":false,"show_breadcrumbs":false,"content_width":"full","title_align":"left"}'),
    ('sidebar-right', 'Page + Sidebar (Right)',    'Content with right-side zone panel',       '["header","main","sidebar","footer"]', 'layout-sidebar-right', 1, 5, '{"show_title":true,"show_header":true,"show_footer":true,"show_breadcrumbs":false,"content_width":"default","title_align":"left"}'),
    ('sidebar-left',  'Page + Sidebar (Left)',     'Content with left-side zone panel',        '["header","sidebar","main","footer"]', 'layout-sidebar-left',  1, 6, '{"show_title":true,"show_header":true,"show_footer":true,"show_breadcrumbs":false,"content_width":"default","title_align":"left"}');

-- ── Patch existing template zone arrays on live instances ─────
-- Adds header/footer to templates that were seeded before this correction.
-- Only updates rows that still have the old zone-less arrays.

UPDATE `page_templates` SET `zones` = '["header","main","footer"]'
WHERE `slug` = 'default'      AND `zones` IN ('["main"]', '["header","main"]', '["main","footer"]');

UPDATE `page_templates` SET `zones` = '["header","main","footer"]'
WHERE `slug` = 'full-width'   AND `zones` IN ('["main"]', '["header","main"]', '["main","footer"]');

UPDATE `page_templates` SET `zones` = '["header","main","footer"]'
WHERE `slug` = 'landing'      AND `zones` IN ('["main"]', '["header","main"]', '["main","footer"]');

UPDATE `page_templates`
SET `zones` = '["header","main","sidebar","footer"]',
    `name`  = 'Page + Sidebar (Right)',
    `description` = 'Content with right-side zone panel'
WHERE `slug` = 'sidebar-right' AND `zones` IN ('["main","sidebar"]', '["header","main","sidebar"]', '["main","sidebar","footer"]');

UPDATE `page_templates`
SET `zones` = '["header","sidebar","main","footer"]',
    `name`  = 'Page + Sidebar (Left)',
    `description` = 'Content with left-side zone panel'
WHERE `slug` = 'sidebar-left'  AND `zones` IN ('["sidebar","main"]', '["header","sidebar","main"]', '["sidebar","main","footer"]');

-- ── Link template canvas pages to their templates ─────────────
-- Only update where canvas_page_id is not already set (don't clobber
-- customised instances that have already pointed these templates elsewhere).

UPDATE `page_templates` t
    JOIN `pages_index` p ON p.slug = '_tpl__global_header'
SET t.`canvas_page_id` = p.`id`
WHERE t.`slug` = '_global_header'
  AND (t.`canvas_page_id` IS NULL OR t.`canvas_page_id` = 0);

UPDATE `page_templates` t
    JOIN `pages_index` p ON p.slug = '_tpl_default'
SET t.`canvas_page_id` = p.`id`
WHERE t.`slug` = 'default'
  AND (t.`canvas_page_id` IS NULL OR t.`canvas_page_id` = 0);

-- ── Seed header blocks ────────────────────────────────────────
-- Uses INSERT IGNORE so re-runs are safe.
-- block_ids are fixed strings so they are idempotent.

SET @hdr_page    = (SELECT `id` FROM `pages_index` WHERE `slug` = '_header'  LIMIT 1);
SET @menu_main   = (SELECT `id` FROM `menus`       WHERE `location` = 'main'   LIMIT 1);
SET @menu_topbar = (SELECT `id` FROM `menus`       WHERE `location` = 'topbar' LIMIT 1);

INSERT IGNORE INTO `pages`
    (`block_id`, `page_id`, `block_type`, `inner_html`, `css_props`, `block_config`, `sort_order`, `parent_block_id`)
VALUES
    ('seed_hdr_section', @hdr_page, 'section',    NULL, '{"_class":"site-header"}', '{}',                                         0, NULL),
    ('seed_hdr_title',   @hdr_page, 'site-title', NULL, '{}',                       '{}',                                         0, 'seed_hdr_section'),
    ('seed_hdr_main',    @hdr_page, 'nav-menu',   NULL, '{"_class":"site-nav-bar"}', JSON_OBJECT('menu_id', CAST(@menu_main   AS CHAR)), 1, 'seed_hdr_section'),
    ('seed_hdr_topbar',  @hdr_page, 'nav-menu',   NULL, '{"_class":"utility-bar"}',  JSON_OBJECT('menu_id', CAST(@menu_topbar AS CHAR)), 2, 'seed_hdr_section');

-- ── Seed footer blocks ────────────────────────────────────────

SET @ftr_page    = (SELECT `id` FROM `pages_index` WHERE `slug` = '_footer' LIMIT 1);
SET @menu_footer = (SELECT `id` FROM `menus`       WHERE `location` = 'footer' LIMIT 1);

INSERT IGNORE INTO `pages`
    (`block_id`, `page_id`, `block_type`, `inner_html`, `css_props`, `block_config`, `sort_order`, `parent_block_id`)
VALUES
    ('seed_ftr_section', @ftr_page, 'section', NULL, '{"_class":"site-footer"}', '{}',                                           0, NULL),
    ('seed_ftr_copy',    @ftr_page, 'text',    NULL, '{}',                       '{}',                                           0, 'seed_ftr_section'),
    ('seed_ftr_nav',     @ftr_page, 'nav-menu',NULL, '{"_class":"footer-nav"}',  JSON_OBJECT('menu_id', CAST(@menu_footer AS CHAR)), 1, 'seed_ftr_section');

-- ── Instance-specific roles ───────────────────────────────────
-- System roles (admin, public) are seeded in instance_core.sql.
-- Instance-specific roles (council, member) are added here
-- via the theme seed so they can be customised per instance.
-- Stage 1 of Role & Capability Refactor (v1.0.0-beta.9).

INSERT IGNORE INTO `roles` (`slug`, `name`, `description`, `level`, `is_system`, `colour`, `default_redirect`) VALUES
    ('council', 'Council', 'Council member with elevated access',      50, 0, '#fd7e14', '/profile'),
    ('member',  'Member',  'Registered member with standard access', 10, 0, '#198754', '/profile');

SET FOREIGN_KEY_CHECKS = 1;
