-- ============================================================
-- Module Template Migration
-- ============================================================
-- Keep migrations idempotent where possible.
-- Use IF NOT EXISTS guards for first-run compatibility.
-- Avoid seed data in platform modules.
-- ============================================================

CREATE TABLE IF NOT EXISTS `example_items` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`      VARCHAR(160) NOT NULL,
    `slug`       VARCHAR(160) NOT NULL,
    `status`     ENUM('draft','published') NOT NULL DEFAULT 'draft',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_example_items_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
