-- ── Migration 034: Broadcast target type ─────────────────────────────────────
--
-- Adds a target_type column to email_broadcasts so that a broadcast can be
-- directed at either a named mailing list (subscribers with portal accounts)
-- or directly at all active members in the members table (email address only,
-- no portal account required — for use when importing legacy member lists).
--
-- 'list'        — targets mailing_list_subscriptions (existing behaviour)
-- 'all_members' — targets members WHERE status IN ('active','honorary')
--                 AND email IS NOT NULL, excluding known unsubscribes

ALTER TABLE `email_broadcasts`
    ADD COLUMN `target_type` ENUM('list', 'all_members') NOT NULL DEFAULT 'list'
    AFTER `list_id`;
