-- ============================================================
-- CruinnCMS — Platform Schema
-- Applied once at platform install by the /cms/install wizard.
--
-- Creates the two platform-level tables:
--   platform_settings  — key/value config for the CruinnCMS platform
--   instances          — registry of provisioned child instances
--
-- Instance-specific tables live in instance_core.sql and are applied
-- per-instance when provisioning through the platform dashboard.
-- Module tables live in modules/{slug}/schema.sql and are applied
-- when a module is activated on an instance.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- PLATFORM SETTINGS
-- ============================================================

CREATE TABLE IF NOT EXISTS `platform_settings` (
    `key`        VARCHAR(100) NOT NULL,
    `value`      TEXT         NULL,
    `group`      VARCHAR(50)  NOT NULL DEFAULT 'general',
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`),
    KEY `idx_platform_settings_group` (`group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- INSTANCE REGISTRY
-- ============================================================

CREATE TABLE IF NOT EXISTS `instances` (
    `id`          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    `slug`        VARCHAR(50)   NOT NULL UNIQUE,
    `name`        VARCHAR(100)  NOT NULL,
    `db_host`     VARCHAR(255)  NOT NULL DEFAULT 'localhost',
    `db_port`     SMALLINT UNSIGNED NOT NULL DEFAULT 3306,
    `db_name`     VARCHAR(100)  NOT NULL,
    `db_user`     VARCHAR(100)  NOT NULL,
    `db_password` TEXT          NOT NULL,
    `site_url`    VARCHAR(500)  NOT NULL,
    `status`      ENUM('provisioning','active','offline','error') NOT NULL DEFAULT 'provisioning',
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SEED DATA
-- ============================================================

INSERT IGNORE INTO `platform_settings` (`key`, `value`, `group`) VALUES
    ('platform.name',    'CruinnCMS', 'general'),
    ('platform.version', '1.0.0',     'general');

-- ============================================================
-- PLATFORM BLOCK EDITOR TABLES
-- Mirrors the instance block-editor tables so the platform
-- itself can be edited with the same editor as any instance.
-- ============================================================

CREATE TABLE IF NOT EXISTS `pages` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(255)  NOT NULL,
    `slug`        VARCHAR(255)  NOT NULL,
    `render_mode` ENUM('cruinn','html','file') NOT NULL DEFAULT 'cruinn',
    `render_file` VARCHAR(500)  NULL DEFAULT NULL,
    `body_html`   MEDIUMTEXT    NULL,
    `status`      ENUM('published','draft','archived') NOT NULL DEFAULT 'draft',
    `template`    VARCHAR(100)  NOT NULL DEFAULT 'none',
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_platform_pages_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cruinn_page_state` (
    `page_id`         INT UNSIGNED NOT NULL,
    `current_edit_seq` INT UNSIGNED NOT NULL DEFAULT 1,
    `max_edit_seq`    INT UNSIGNED NOT NULL DEFAULT 1,
    `last_edited_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`page_id`),
    CONSTRAINT `fk_platform_page_state_page` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cruinn_blocks` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `page_id`        INT UNSIGNED  NOT NULL,
    `block_id`       VARCHAR(36)   NOT NULL,
    `block_type`     VARCHAR(50)   NOT NULL,
    `inner_html`     MEDIUMTEXT    NULL,
    `css_props`      TEXT          NULL,
    `block_config`   TEXT          NULL,
    `sort_order`     INT           NOT NULL DEFAULT 0,
    `parent_block_id` VARCHAR(36)  NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_platform_blocks_block_id` (`block_id`),
    KEY `idx_platform_blocks_page` (`page_id`),
    CONSTRAINT `fk_platform_blocks_page` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cruinn_draft_blocks` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `page_id`        INT UNSIGNED  NOT NULL,
    `edit_seq`       INT UNSIGNED  NOT NULL DEFAULT 1,
    `block_id`       VARCHAR(36)   NOT NULL,
    `block_type`     VARCHAR(50)   NOT NULL,
    `inner_html`     MEDIUMTEXT    NULL,
    `css_props`      TEXT          NULL,
    `block_config`   TEXT          NULL,
    `sort_order`     INT           NOT NULL DEFAULT 0,
    `parent_block_id` VARCHAR(36)  NULL DEFAULT NULL,
    `is_active`      TINYINT(1)    NOT NULL DEFAULT 1,
    `is_deletion`    TINYINT(1)    NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_platform_draft_blocks_page_seq` (`page_id`, `edit_seq`, `is_active`),
    CONSTRAINT `fk_platform_draft_blocks_page` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
