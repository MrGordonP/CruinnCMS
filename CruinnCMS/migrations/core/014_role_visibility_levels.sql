-- Migration 014: Convert role visibility from slugs to numeric levels
-- Stage 1 of Role & Capability Refactor (v1.0.0-beta.9)
--
-- Changes:
-- - menu_items.min_role: VARCHAR(20) → SMALLINT UNSIGNED
-- - Convert slug values to numeric levels (public=0, member=10, editor=20, council=50, admin=100)
--
-- Context:
-- The engine now uses Auth::roleLevel() (integer) instead of Auth::role() (slug).
-- This migration ensures menu visibility checks work with numeric levels.

-- Step 1: Add a temporary column to hold numeric values
ALTER TABLE `menu_items` ADD COLUMN `min_role_level` SMALLINT UNSIGNED NULL DEFAULT NULL AFTER `min_role`;

-- Step 2: Convert existing slug values to numeric levels
UPDATE `menu_items` SET `min_role_level` = 0   WHERE `min_role` = 'public';
UPDATE `menu_items` SET `min_role_level` = 10  WHERE `min_role` = 'member';
UPDATE `menu_items` SET `min_role_level` = 20  WHERE `min_role` = 'editor';
UPDATE `menu_items` SET `min_role_level` = 50  WHERE `min_role` = 'council';
UPDATE `menu_items` SET `min_role_level` = 100 WHERE `min_role` = 'admin';

-- Step 3: Drop the old VARCHAR column
ALTER TABLE `menu_items` DROP COLUMN `min_role`;

-- Step 4: Rename the new column to min_role
ALTER TABLE `menu_items` CHANGE COLUMN `min_role_level` `min_role` SMALLINT UNSIGNED NULL DEFAULT NULL;
