-- Migration 004: add sort_order to mailboxes table
ALTER TABLE `mailboxes`
    ADD COLUMN `sort_order` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `enabled`;
