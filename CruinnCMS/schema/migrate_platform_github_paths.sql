-- ============================================================
-- CruinnCMS — Platform Migration: GitHub Path Maps
--
-- Adds the platform_github_path_maps table to an existing platform DB
-- that was provisioned before this table was added to platform.sql.
--
-- Safe to apply on a fresh install (uses CREATE TABLE IF NOT EXISTS /
-- INSERT IGNORE) but is not needed if platform.sql was applied after
-- this table was added.
--
-- Apply manually via the platform DB browser query runner, or via
-- mysql CLI:
--   mysql -u <user> -p <platform_db> < schema/migrate_platform_github_paths.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS `platform_github_path_maps` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `local_prefix` VARCHAR(255)  NOT NULL,
    `repo_prefix`  VARCHAR(255)  NOT NULL,
    `sort_order`   SMALLINT      NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_github_path_maps_local` (`local_prefix`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `platform_github_path_maps` (`local_prefix`, `repo_prefix`, `sort_order`) VALUES
    ('src/',        'src/',        80),
    ('templates/',  'templates/',  70),
    ('schema/',     'schema/',     60),
    ('migrations/', 'migrations/', 50),
    ('modules/',    'modules/',    40),
    ('config/',     'config/',     30),
    ('public/',     'public_html/', 20);

-- Migration tracking table (used by /cms/migrations runner)
CREATE TABLE IF NOT EXISTS `platform_migrations` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `filename`   VARCHAR(255) NOT NULL,
    `applied_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_platform_migrations_filename` (`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mark this file as already applied so the runner doesn't re-run it
INSERT IGNORE INTO `platform_migrations` (`filename`) VALUES ('migrate_platform_github_paths.sql');
