-- ============================================================
-- Migration 017 — System Pages
--
-- Creates pages_index entries for engine-level routes (login,
-- register, profile, forgot-password, reset-password,
-- verify-email-sent) so they participate in the block/template
-- system and inherit site chrome (header, footer zones).
--
-- Most system pages get a single php-include block pointing to the
-- existing PHP template partial. The profile page is seeded with
-- dedicated core/system account blocks so users can compose account
-- layouts directly in Cruinn.
--
-- Note: system_pages table registration is handled by migration
-- 019 which runs after this one. If applying to a schema that
-- already includes system_pages (instance_core.sql post-019),
-- 019 will backfill the mappings.
-- ============================================================

INSERT IGNORE INTO `pages_index` (`title`, `slug`, `status`, `template`, `page_zone`, `render_mode`, `editor_mode`) VALUES
    ('Login',              'login',             'published', 'default', 'main', 'block', 'structured'),
    ('Create Account',     'register',          'published', 'default', 'main', 'block', 'structured'),
    ('My Profile',         'profile',           'published', 'default', 'main', 'block', 'structured'),
    ('Forgot Password',    'forgot-password',   'published', 'default', 'main', 'block', 'structured'),
    ('Reset Password',     'reset-password',    'published', 'default', 'main', 'block', 'structured'),
    ('Email Verification', 'verify-email-sent', 'published', 'default', 'main', 'block', 'structured');

-- php-include block for each system page (INSERT IGNORE skips if block_id already exists)
INSERT IGNORE INTO `pages` (`block_id`, `page_id`, `block_type`, `block_config`, `sort_order`)
    SELECT 'sys-login-01',        id, 'php-include', '{"template":"public/login.php"}',             0 FROM `pages_index` WHERE `slug` = 'login';
INSERT IGNORE INTO `pages` (`block_id`, `page_id`, `block_type`, `block_config`, `sort_order`)
    SELECT 'sys-register-01',     id, 'php-include', '{"template":"public/register.php"}',          0 FROM `pages_index` WHERE `slug` = 'register';
INSERT IGNORE INTO `pages` (`block_id`, `page_id`, `block_type`, `block_config`, `sort_order`)
    SELECT 'sys-profile-details-01', id, 'account-details-form', NULL, 0 FROM `pages_index` WHERE `slug` = 'profile';
INSERT IGNORE INTO `pages` (`block_id`, `page_id`, `block_type`, `block_config`, `sort_order`)
    SELECT 'sys-profile-password-01', id, 'account-password-form', NULL, 1 FROM `pages_index` WHERE `slug` = 'profile';
INSERT IGNORE INTO `pages` (`block_id`, `page_id`, `block_type`, `block_config`, `sort_order`)
    SELECT 'sys-profile-info-01', id, 'account-information', NULL, 2 FROM `pages_index` WHERE `slug` = 'profile';
INSERT IGNORE INTO `pages` (`block_id`, `page_id`, `block_type`, `block_config`, `sort_order`)
    SELECT 'sys-forgot-pw-01',    id, 'php-include', '{"template":"public/forgot-password.php"}',   0 FROM `pages_index` WHERE `slug` = 'forgot-password';
INSERT IGNORE INTO `pages` (`block_id`, `page_id`, `block_type`, `block_config`, `sort_order`)
    SELECT 'sys-reset-pw-01',     id, 'php-include', '{"template":"public/reset-password.php"}',    0 FROM `pages_index` WHERE `slug` = 'reset-password';
INSERT IGNORE INTO `pages` (`block_id`, `page_id`, `block_type`, `block_config`, `sort_order`)
    SELECT 'sys-verify-email-01', id, 'php-include', '{"template":"public/verify-email-sent.php"}', 0 FROM `pages_index` WHERE `slug` = 'verify-email-sent';
