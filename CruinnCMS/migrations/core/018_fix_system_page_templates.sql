-- ============================================================
-- Migration 018 — Fix system page php-include template paths
--
-- Migration 017 seeded sys-* blocks without the .php extension,
-- causing the php-include renderer to silently return empty
-- (realpath() fails on extensionless paths).
-- ============================================================

UPDATE `pages` SET `block_config` = REPLACE(`block_config`, '"public/login"',             '"public/login.php"')             WHERE `block_id` = 'sys-login-01';
UPDATE `pages` SET `block_config` = REPLACE(`block_config`, '"public/register"',          '"public/register.php"')          WHERE `block_id` = 'sys-register-01';
UPDATE `pages` SET `block_config` = REPLACE(`block_config`, '"public/profile"',           '"public/profile.php"')           WHERE `block_id` = 'sys-profile-01';
UPDATE `pages` SET `block_config` = REPLACE(`block_config`, '"public/forgot-password"',   '"public/forgot-password.php"')   WHERE `block_id` = 'sys-forgot-pw-01';
UPDATE `pages` SET `block_config` = REPLACE(`block_config`, '"public/reset-password"',    '"public/reset-password.php"')    WHERE `block_id` = 'sys-reset-pw-01';
UPDATE `pages` SET `block_config` = REPLACE(`block_config`, '"public/verify-email-sent"', '"public/verify-email-sent.php"') WHERE `block_id` = 'sys-verify-email-01';
