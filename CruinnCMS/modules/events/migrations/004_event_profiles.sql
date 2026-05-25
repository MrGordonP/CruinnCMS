-- ============================================================
-- Events Module — Migration 004
-- ============================================================
-- Adds: event_profiles table
-- ============================================================

CREATE TABLE IF NOT EXISTS `event_profiles` (
    `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`                  VARCHAR(255) NOT NULL,
    `slug`                  VARCHAR(255) NOT NULL,
    `description`           TEXT NULL,
    `display_mode`          ENUM('list','detail','both') NOT NULL DEFAULT 'both',
    `events_per_page`       INT UNSIGNED NOT NULL DEFAULT 10,
    `default_filter`        ENUM('upcoming','past') NOT NULL DEFAULT 'upcoming',
    `show_return_to_list`   TINYINT(1) NOT NULL DEFAULT 1,
    `show_event_navigation` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_event_profiles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
