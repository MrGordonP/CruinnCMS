-- ============================================================
-- Migration 019 — System Pages Table
--
-- Introduces the system_pages registry table that maps stable
-- engine-owned keys (login, register, profile, etc.) to
-- pages_index rows.  Engine controllers resolve system pages
-- by system_key rather than by public slug, so renaming a page
-- slug never breaks engine routing.
--
-- Also removes the dependency on the legacy _zoneName slug
-- convention for zone canvas resolution (migration 011 already
-- introduced the canonical canvas_type='zone' path; the slug
-- fallback was kept only for pre-seed instances and is now
-- dropped from the engine runtime).
-- ============================================================

CREATE TABLE IF NOT EXISTS `system_pages` (
    `system_key` VARCHAR(64)   NOT NULL,
    `page_id`    INT UNSIGNED  NOT NULL,
    PRIMARY KEY (`system_key`),
    UNIQUE KEY `uq_system_pages_page_id` (`page_id`),
    CONSTRAINT `fk_system_pages_page` FOREIGN KEY (`page_id`) REFERENCES `pages_index` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill mappings for all known engine system pages.
-- Uses INSERT IGNORE so it is safe to re-run and safe on instances
-- that already have system_pages seeded via instance_core.sql.
-- Slug lookup here is a one-time bootstrap — once the row exists the
-- engine uses system_key exclusively.
INSERT IGNORE INTO `system_pages` (`system_key`, `page_id`)
    SELECT 'login',             id FROM `pages_index` WHERE `slug` = 'login'             LIMIT 1;
INSERT IGNORE INTO `system_pages` (`system_key`, `page_id`)
    SELECT 'register',          id FROM `pages_index` WHERE `slug` = 'register'          LIMIT 1;
INSERT IGNORE INTO `system_pages` (`system_key`, `page_id`)
    SELECT 'profile',           id FROM `pages_index` WHERE `slug` = 'profile'           LIMIT 1;
INSERT IGNORE INTO `system_pages` (`system_key`, `page_id`)
    SELECT 'forgot-password',   id FROM `pages_index` WHERE `slug` = 'forgot-password'   LIMIT 1;
INSERT IGNORE INTO `system_pages` (`system_key`, `page_id`)
    SELECT 'reset-password',    id FROM `pages_index` WHERE `slug` = 'reset-password'    LIMIT 1;
INSERT IGNORE INTO `system_pages` (`system_key`, `page_id`)
    SELECT 'verify-email-sent', id FROM `pages_index` WHERE `slug` = 'verify-email-sent' LIMIT 1;
