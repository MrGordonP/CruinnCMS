-- Organisation Module — Groups & User Groups
-- Safe to run on existing installs (CREATE TABLE IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS `groups` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug`        VARCHAR(50)  NOT NULL,
    `name`        VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) DEFAULT '',
    `group_type`  ENUM('committee','working_group','interest','custom') NOT NULL DEFAULT 'custom',
    `role_id`     INT UNSIGNED NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_groups_slug` (`slug`),
    CONSTRAINT `fk_groups_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_groups` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED NOT NULL,
    `group_id`    INT UNSIGNED NOT NULL,
    `assigned_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `assigned_by` INT UNSIGNED NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_group` (`user_id`, `group_id`),
    CONSTRAINT `fk_ug_user`        FOREIGN KEY (`user_id`)     REFERENCES `users`  (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ug_group`       FOREIGN KEY (`group_id`)    REFERENCES `groups` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ug_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users`  (`id`) ON DELETE SET NULL,
    INDEX `idx_ug_group` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
