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
-- Mirrors the instance block-editor schema (pages_index / pages /
-- pages_draft) so the platform itself can be edited with the same
-- editor as any instance.
--
-- Differences from instance_core.sql:
--   - pages_index.created_by has no FK (no users table in platform DB)
--   - render_mode includes 'block' (canonical value used by the editor)
-- ============================================================

CREATE TABLE IF NOT EXISTS `pages_index` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `title`            VARCHAR(255)  NOT NULL,
    `slug`             VARCHAR(255)  NOT NULL,
    `status`           ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    `template`         VARCHAR(50)   NOT NULL DEFAULT 'default',
    `editor_mode`      ENUM('structured','freeform') NOT NULL DEFAULT 'structured',
    `meta_description` VARCHAR(320)  NULL DEFAULT '',
    `render_mode`      ENUM('block','html','file') NOT NULL DEFAULT 'block',
    `body_html`        MEDIUMTEXT    NULL DEFAULT NULL,
    `render_file`      VARCHAR(500)  NULL DEFAULT NULL,
    `created_by`       INT UNSIGNED  NULL,
    `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_platform_pages_index_slug` (`slug`),
    KEY `idx_platform_pages_index_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Published page blocks — canonical published state
CREATE TABLE IF NOT EXISTS `pages` (
    `block_id`        VARCHAR(20)       NOT NULL,
    `page_id`         INT UNSIGNED      NOT NULL,
    `block_type`      VARCHAR(40)       NOT NULL,
    `inner_html`      MEDIUMTEXT        NULL,
    `css_props`       JSON              NULL,
    `block_config`    JSON              NULL,
    `sort_order`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `parent_block_id` VARCHAR(20)       NULL,
    `created_at`      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME          NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`block_id`),
    KEY `idx_platform_pages_page` (`page_id`, `parent_block_id`, `sort_order`),
    CONSTRAINT `fk_platform_pages_page_id` FOREIGN KEY (`page_id`) REFERENCES `pages_index` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Editor draft history — MAX(edit_seq) per page_id = current working state
CREATE TABLE IF NOT EXISTS `pages_draft` (
    `id`              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `page_id`         INT UNSIGNED      NOT NULL,
    `edit_seq`        INT UNSIGNED      NOT NULL,
    `block_id`        VARCHAR(20)       NOT NULL,
    `block_type`      VARCHAR(40)       NOT NULL,
    `inner_html`      MEDIUMTEXT        NULL,
    `css_props`       JSON              NULL,
    `block_config`    JSON              NULL,
    `sort_order`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `parent_block_id` VARCHAR(20)       NULL,
    `created_at`      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_platform_pages_draft_page_seq` (`page_id`, `edit_seq`),
    KEY `idx_platform_pages_draft_block`    (`page_id`, `block_id`),
    CONSTRAINT `fk_platform_pages_draft_page` FOREIGN KEY (`page_id`) REFERENCES `pages_index` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
