-- Migration 030: Split profile system page into separate account sub-pages
-- Converts the single /profile page (with account block type blocks) into
-- three system pages: /profile (hub), /profile/account, /profile/password.
-- The old block type slugs (account-details-form, account-password-form,
-- account-information) are replaced with php-include blocks pointing at the
-- new account partial templates.

-- ── 1. Rename existing profile page title to hub ─────────────
UPDATE `pages_index`
SET `title` = 'My Profile'
WHERE `slug` = 'profile';

-- ── 2. Remove old account block type blocks from profile page ─
DELETE p FROM `pages` p
INNER JOIN `pages_index` pi ON pi.id = p.page_id
WHERE pi.slug = 'profile'
  AND p.block_type IN ('account-details-form', 'account-password-form', 'account-information');

-- ── 3. Seed the hub php-include block on /profile ─────────────
INSERT IGNORE INTO `pages` (`block_id`, `page_id`, `block_type`, `block_config`, `sort_order`)
    SELECT 'sys-profile-hub-01', id, 'php-include', '{"template":"public/profile.php"}', 0
    FROM `pages_index` WHERE `slug` = 'profile';

-- ── 4. Seed /profile/account page ────────────────────────────
INSERT IGNORE INTO `pages_index` (`title`, `slug`, `status`, `template`, `page_zone`, `render_mode`, `editor_mode`)
VALUES ('Account Information', 'profile/account', 'published', 'default', 'main', 'block', 'structured');

INSERT IGNORE INTO `pages` (`block_id`, `page_id`, `block_type`, `block_config`, `sort_order`)
    SELECT 'sys-profile-account-01', id, 'php-include', '{"template":"public/account/information.php"}', 0
    FROM `pages_index` WHERE `slug` = 'profile/account';

INSERT IGNORE INTO `system_pages` (`system_key`, `page_id`)
    SELECT 'profile/account', id FROM `pages_index` WHERE `slug` = 'profile/account';

-- ── 5. Seed /profile/password page ───────────────────────────
INSERT IGNORE INTO `pages_index` (`title`, `slug`, `status`, `template`, `page_zone`, `render_mode`, `editor_mode`)
VALUES ('Change Password', 'profile/password', 'published', 'default', 'main', 'block', 'structured');

INSERT IGNORE INTO `pages` (`block_id`, `page_id`, `block_type`, `block_config`, `sort_order`)
    SELECT 'sys-profile-password-01', id, 'php-include', '{"template":"public/account/password-form.php"}', 0
    FROM `pages_index` WHERE `slug` = 'profile/password';

INSERT IGNORE INTO `system_pages` (`system_key`, `page_id`)
    SELECT 'profile/password', id FROM `pages_index` WHERE `slug` = 'profile/password';

-- ── 6. Record migration ───────────────────────────────────────
INSERT IGNORE INTO `module_migrations` (`module`, `filename`) VALUES ('core', '030_profile_pages_split.sql');
