-- ============================================================
-- Migration 002: Group Positions
-- Adds named positions per group (e.g. President, Secretary)
-- and a join table assigning users to positions within a group.
-- ============================================================

CREATE TABLE IF NOT EXISTS `group_positions` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `group_id`    INT UNSIGNED NOT NULL,
    `name`        VARCHAR(100) NOT NULL,
    `slug`        VARCHAR(100) NOT NULL,
    `sort_order`  SMALLINT     NOT NULL DEFAULT 0,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_group_position_slug` (`group_id`, `slug`),
    CONSTRAINT `fk_gp_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
    INDEX `idx_gp_group` (`group_id`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_group_positions` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED NOT NULL,
    `group_id`    INT UNSIGNED NOT NULL,
    `position_id` INT UNSIGNED NOT NULL,
    `assigned_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `assigned_by` INT UNSIGNED NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ugp` (`user_id`, `position_id`),
    CONSTRAINT `fk_ugp_user`     FOREIGN KEY (`user_id`)     REFERENCES `users`            (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ugp_group`    FOREIGN KEY (`group_id`)    REFERENCES `groups`           (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ugp_position` FOREIGN KEY (`position_id`) REFERENCES `group_positions`  (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ugp_by`       FOREIGN KEY (`assigned_by`) REFERENCES `users`            (`id`) ON DELETE SET NULL,
    INDEX `idx_ugp_group`    (`group_id`),
    INDEX `idx_ugp_position` (`position_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
