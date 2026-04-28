-- ============================================================
-- Drivespace Migration 002: Per-user quota tracking
--
-- Adds quota_bytes (limit) and quota_used_bytes (counter) to users.
-- quota_used_bytes is a denormalised counter maintained by the
-- FileManagerController on upload and delete.
-- ============================================================

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `drivespace_quota_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 524288000
        COMMENT 'Storage quota in bytes (default 500 MB)'
        AFTER `last_login`,
    ADD COLUMN IF NOT EXISTS `drivespace_used_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Bytes currently in use — kept in sync by FileManagerController'
        AFTER `drivespace_quota_bytes`;
