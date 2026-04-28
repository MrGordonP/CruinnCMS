-- ============================================================
-- Core Migration 002: Content Sets
--
-- Introduces the dynamic data layer: named content sets
-- (user-defined field schemas) and their row data.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP PROCEDURE IF EXISTS _cruinn_migrate_002;
DELIMITER ;;
CREATE PROCEDURE _cruinn_migrate_002()
BEGIN
    -- content_sets
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'content_sets'
    ) THEN
        CREATE TABLE `content_sets` (
            `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
            `name`        VARCHAR(100)  NOT NULL,
            `slug`        VARCHAR(100)  NOT NULL,
            `description` VARCHAR(320)  NULL DEFAULT '',
            `fields`      JSON          NOT NULL COMMENT 'Array of {name, label, type: text|richtext|image|url|date}',
            `created_by`  INT UNSIGNED  NULL,
            `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_content_sets_slug` (`slug`),
            CONSTRAINT `fk_content_sets_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    END IF;

    -- content_set_rows
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'content_set_rows'
    ) THEN
        CREATE TABLE `content_set_rows` (
            `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
            `set_id`     INT UNSIGNED  NOT NULL,
            `data`       JSON          NOT NULL COMMENT 'Key/value map matching the set field names',
            `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_content_set_rows_set` (`set_id`, `sort_order`),
            CONSTRAINT `fk_content_set_rows_set` FOREIGN KEY (`set_id`) REFERENCES `content_sets` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    END IF;
END;;
DELIMITER ;
CALL _cruinn_migrate_002();
DROP PROCEDURE IF EXISTS _cruinn_migrate_002;

SET FOREIGN_KEY_CHECKS = 1;
