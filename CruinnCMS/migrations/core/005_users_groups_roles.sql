-- ============================================================
-- Core Migration 005: Clean users, groups, and roles tables
--
-- users:  add forenames, surname, email_verified, verified_at
--         drop role ENUM, role_id FK, updated_at
-- groups: add level, is_system
--         drop role_id FK, group_type, updated_at
-- roles:  drop updated_at
--
-- Each step is guarded.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── users: add new columns ────────────────────────────────────
DROP PROCEDURE IF EXISTS _cruinn_migrate_005;
DELIMITER ;;
CREATE PROCEDURE _cruinn_migrate_005()
BEGIN
    -- forenames
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'forenames'
    ) THEN
        ALTER TABLE `users` ADD COLUMN `forenames` VARCHAR(100) NOT NULL DEFAULT '' AFTER `password_hash`;
    END IF;

    -- surname
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'surname'
    ) THEN
        ALTER TABLE `users` ADD COLUMN `surname` VARCHAR(100) NOT NULL DEFAULT '' AFTER `forenames`;
    END IF;

    -- email_verified
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'email_verified'
    ) THEN
        -- Treat existing active users as verified so they can still log in
        ALTER TABLE `users` ADD COLUMN `email_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `active`;
        UPDATE `users` SET `email_verified` = 1 WHERE `active` = 1;
    END IF;

    -- verified_at
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'verified_at'
    ) THEN
        ALTER TABLE `users` ADD COLUMN `verified_at` DATETIME NULL AFTER `email_verified`;
    END IF;

    -- ── users: drop role_id FK and column ─────────────────────
    IF EXISTS (
        SELECT 1 FROM information_schema.table_constraints
        WHERE table_schema = DATABASE() AND table_name = 'users' AND constraint_name = 'fk_users_role'
    ) THEN
        ALTER TABLE `users` DROP FOREIGN KEY `fk_users_role`;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'role_id'
    ) THEN
        ALTER TABLE `users` DROP COLUMN `role_id`;
    END IF;

    -- ── users: drop legacy role ENUM ──────────────────────────
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'role'
    ) THEN
        ALTER TABLE `users` DROP COLUMN `role`;
    END IF;

    -- ── users: drop updated_at ────────────────────────────────
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'updated_at'
    ) THEN
        ALTER TABLE `users` DROP COLUMN `updated_at`;
    END IF;

    -- ── groups: drop role_id FK and column ────────────────────
    IF EXISTS (
        SELECT 1 FROM information_schema.table_constraints
        WHERE table_schema = DATABASE() AND table_name = 'groups' AND constraint_name = 'fk_groups_role'
    ) THEN
        ALTER TABLE `groups` DROP FOREIGN KEY `fk_groups_role`;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'groups' AND column_name = 'role_id'
    ) THEN
        ALTER TABLE `groups` DROP COLUMN `role_id`;
    END IF;

    -- ── groups: drop group_type ───────────────────────────────
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'groups' AND column_name = 'group_type'
    ) THEN
        ALTER TABLE `groups` DROP COLUMN `group_type`;
    END IF;

    -- ── groups: drop updated_at ───────────────────────────────
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'groups' AND column_name = 'updated_at'
    ) THEN
        ALTER TABLE `groups` DROP COLUMN `updated_at`;
    END IF;

    -- ── groups: add level ─────────────────────────────────────
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'groups' AND column_name = 'level'
    ) THEN
        ALTER TABLE `groups` ADD COLUMN `level` INT UNSIGNED NOT NULL DEFAULT 0
            COMMENT 'Content access level; higher = more access' AFTER `description`;
        ALTER TABLE `groups` ADD INDEX `idx_groups_level` (`level`);
    END IF;

    -- ── groups: add is_system ─────────────────────────────────
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'groups' AND column_name = 'is_system'
    ) THEN
        ALTER TABLE `groups` ADD COLUMN `is_system` TINYINT(1) NOT NULL DEFAULT 0
            COMMENT 'System groups cannot be deleted' AFTER `level`;
    END IF;

    -- ── roles: drop updated_at ────────────────────────────────
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'roles' AND column_name = 'updated_at'
    ) THEN
        ALTER TABLE `roles` DROP COLUMN `updated_at`;
    END IF;

END;;
DELIMITER ;

CALL _cruinn_migrate_005();
DROP PROCEDURE IF EXISTS _cruinn_migrate_005;

SET FOREIGN_KEY_CHECKS = 1;
