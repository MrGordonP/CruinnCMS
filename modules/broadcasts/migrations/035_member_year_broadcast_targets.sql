-- ─────────────────────────────────────────────────────────────────────────
-- Migration 035 — Member year + flexible broadcast targeting
--
-- 1. Add membership_year to members (tracks the year a member was last active,
--    set during CSV import or manual edit). Allows targeting "lapsed 2024 members".
--
-- 2. Add target_config JSON to email_broadcasts for per-type filter parameters.
--    e.g. {"member_status":["active","lapsed"],"membership_year":2024}
--
-- 3. Migrate any existing all_members rows → members with equivalent config.
--
-- 4. Expand target_type ENUM: (list | members | portal_users).
--    'all_members' is removed — migrate data first, then alter.
-- ─────────────────────────────────────────────────────────────────────────

-- 1. Add membership_year to members
ALTER TABLE `members`
    ADD COLUMN `membership_year` SMALLINT UNSIGNED NULL
        COMMENT 'Year membership was last paid / active (e.g. 2025)'
    AFTER `status`;

-- 2. Add target_config to email_broadcasts
ALTER TABLE `email_broadcasts`
    ADD COLUMN `target_config` JSON NULL
        COMMENT 'Filter params for members/portal_users targets: {"member_status":["active"],"membership_year":2025}'
    AFTER `target_type`;

-- 3. Migrate existing all_members rows to the new members type
UPDATE `email_broadcasts`
SET
    `target_type`   = 'members',
    `target_config` = '{"member_status":["active","honorary"]}'
WHERE `target_type` = 'all_members';

-- 4. Expand the ENUM (must happen after data migration to avoid empty-string fallback)
ALTER TABLE `email_broadcasts`
    MODIFY COLUMN `target_type`
        ENUM('list', 'members', 'portal_users') NOT NULL DEFAULT 'list';
