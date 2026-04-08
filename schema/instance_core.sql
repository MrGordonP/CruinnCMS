-- ============================================================
-- CruinnCMS — Instance Core Schema
-- Applied once per instance at provisioning time.
--
-- This builds out a fresh instance database: users, roles,
-- permissions, pages, menus, blocks, settings, and all
-- core CruinnCMS tables.
--
-- Module tables live in modules/{slug}/schema.sql and are
-- applied separately when a module is activated on the instance.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- USERS & AUTHENTICATION
-- ============================================================

CREATE TABLE `users` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `email`         VARCHAR(255)    NOT NULL,
    `password_hash` VARCHAR(255)    NOT NULL,
    `display_name`  VARCHAR(100)    NOT NULL,
    `role`          ENUM('admin','council','member','public') NOT NULL DEFAULT 'member',
    `role_id`       INT UNSIGNED    NULL DEFAULT NULL,
    `active`        TINYINT(1)      NOT NULL DEFAULT 1,
    `last_login`    DATETIME        NULL,
    `failed_logins` INT UNSIGNED    NOT NULL DEFAULT 0,
    `locked_until`  DATETIME        NULL DEFAULT NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ROLES & PERMISSIONS
-- ============================================================

CREATE TABLE `roles` (
    `id`               INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    `slug`             VARCHAR(50)   NOT NULL UNIQUE,
    `name`             VARCHAR(100)  NOT NULL,
    `description`      VARCHAR(255)  DEFAULT '',
    `level`            INT UNSIGNED  NOT NULL DEFAULT 0 COMMENT 'Higher = more privilege',
    `is_system`        TINYINT(1)    NOT NULL DEFAULT 0 COMMENT 'System roles cannot be deleted',
    `colour`           VARCHAR(7)    DEFAULT '#6c757d',
    `default_redirect` VARCHAR(100)  DEFAULT '/',
    `created_at`       DATETIME      DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_roles_level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FK from users to roles (added after roles table exists)
ALTER TABLE `users`
    ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL;

CREATE TABLE `permissions` (
    `id`          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    `slug`        VARCHAR(80)   NOT NULL UNIQUE,
    `name`        VARCHAR(120)  NOT NULL,
    `category`    VARCHAR(40)   NOT NULL COMMENT 'Grouping for UI display',
    `description` VARCHAR(255)  DEFAULT '',
    INDEX `idx_permissions_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `role_permissions` (
    `role_id`       INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    FOREIGN KEY (`role_id`)       REFERENCES `roles`       (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Multi-role assignment junction
CREATE TABLE `user_roles` (
    `user_id`     INT UNSIGNED NOT NULL,
    `role_id`     INT UNSIGNED NOT NULL,
    `assigned_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `assigned_by` INT UNSIGNED NULL,
    PRIMARY KEY (`user_id`, `role_id`),
    FOREIGN KEY (`user_id`)     REFERENCES `users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`role_id`)     REFERENCES `roles` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `role_nav_items` (
    `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `role_id`             INT UNSIGNED  NOT NULL,
    `parent_id`           INT UNSIGNED  DEFAULT NULL,
    `label`               VARCHAR(100)  NOT NULL,
    `url`                 VARCHAR(255)  NOT NULL DEFAULT '#',
    `icon`                VARCHAR(50)   DEFAULT NULL,
    `permission_required` VARCHAR(100)  DEFAULT NULL,
    `sort_order`          INT UNSIGNED  NOT NULL DEFAULT 0,
    `is_visible`          TINYINT(1)    NOT NULL DEFAULT 1,
    `css_class`           VARCHAR(100)  DEFAULT NULL,
    `opens_new_tab`       TINYINT(1)    NOT NULL DEFAULT 0,
    `created_at`          TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`role_id`)   REFERENCES `roles`          (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`parent_id`) REFERENCES `role_nav_items` (`id`) ON DELETE CASCADE,
    INDEX `idx_role_nav_role` (`role_id`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- GROUPS
-- ============================================================

CREATE TABLE `groups` (
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

CREATE TABLE `user_groups` (
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

-- ============================================================
-- DASHBOARD WIDGETS
-- ============================================================

CREATE TABLE `dashboard_widgets` (
    `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `slug`             VARCHAR(60)  NOT NULL UNIQUE,
    `name`             VARCHAR(120) NOT NULL,
    `description`      VARCHAR(255) DEFAULT '',
    `category`         VARCHAR(40)  NOT NULL,
    `template_path`    VARCHAR(120) NOT NULL,
    `data_provider`    VARCHAR(120) NOT NULL,
    `default_settings` JSON         DEFAULT NULL,
    `is_active`        TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`       DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_widgets_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `role_dashboard_widgets` (
    `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `role_id`           INT UNSIGNED NOT NULL,
    `widget_id`         INT UNSIGNED NOT NULL,
    `sort_order`        INT UNSIGNED NOT NULL DEFAULT 0,
    `grid_width`        ENUM('full','half') NOT NULL DEFAULT 'full',
    `settings_override` JSON         DEFAULT NULL,
    `is_visible`        TINYINT(1)   NOT NULL DEFAULT 1,
    UNIQUE KEY `uk_role_widget` (`role_id`, `widget_id`),
    FOREIGN KEY (`role_id`)   REFERENCES `roles`             (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`widget_id`) REFERENCES `dashboard_widgets` (`id`) ON DELETE CASCADE,
    INDEX `idx_rdw_role_order` (`role_id`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ACTIVITY LOG & SETTINGS
-- ============================================================

CREATE TABLE `activity_log` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED    NULL,
    `action`      VARCHAR(50)     NOT NULL,
    `entity_type` VARCHAR(50)     NOT NULL,
    `entity_id`   INT UNSIGNED    NULL,
    `details`     TEXT            NULL,
    `ip_address`  VARCHAR(45)     NULL,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_activity_user`   (`user_id`),
    KEY `idx_activity_entity` (`entity_type`, `entity_id`),
    KEY `idx_activity_date`   (`created_at`),
    CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `settings` (
    `key`        VARCHAR(100) NOT NULL,
    `value`      TEXT         NULL,
    `group`      VARCHAR(50)  NOT NULL DEFAULT 'general',
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`),
    KEY `idx_settings_group` (`group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DELETED ACCOUNTS (GDPR retention)
-- ============================================================

CREATE TABLE `deleted_accounts` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `original_user_id` INT UNSIGNED NOT NULL,
    `account_data`     JSON         NOT NULL,
    `deleted_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at`       DATETIME     NOT NULL,
    `purged_at`        DATETIME     NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_da_expires`       (`expires_at`),
    INDEX `idx_da_original_user` (`original_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SUBJECTS (site structure / content organisation)
-- ============================================================

CREATE TABLE `subjects` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `parent_id`   INT UNSIGNED NULL,
    `code`        VARCHAR(50)  NOT NULL,
    `title`       VARCHAR(255) NOT NULL,
    `slug`        VARCHAR(255) NOT NULL,
    `type`        ENUM('series','event','news','campaign','project','general') NOT NULL DEFAULT 'general',
    `status`      ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
    `description` TEXT         NULL,
    `starts_at`   DATETIME     NULL,
    `ends_at`     DATETIME     NULL,
    `metadata`    JSON         NULL,
    `created_by`  INT UNSIGNED NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_subjects_code` (`code`),
    UNIQUE KEY `uk_subjects_slug` (`slug`),
    KEY `idx_subjects_parent` (`parent_id`),
    KEY `idx_subjects_type`   (`type`),
    KEY `idx_subjects_status` (`status`),
    CONSTRAINT `fk_subjects_parent`     FOREIGN KEY (`parent_id`)  REFERENCES `subjects` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_subjects_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`    (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PAGES
-- ============================================================

CREATE TABLE `pages` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `title`            VARCHAR(255)  NOT NULL,
    `slug`             VARCHAR(255)  NOT NULL,
    `status`           ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    `template`         VARCHAR(50)   NOT NULL DEFAULT 'default',
    `editor_mode`      ENUM('structured','freeform') NOT NULL DEFAULT 'structured',
    `meta_description` VARCHAR(320)  NULL DEFAULT '',
    `render_mode`      ENUM('cruinn','html','file') NOT NULL DEFAULT 'cruinn',
    `body_html`        MEDIUMTEXT    NULL DEFAULT NULL,
    `render_file`      VARCHAR(500)  NULL DEFAULT NULL COMMENT 'Relative path to static HTML file',
    `created_by`       INT UNSIGNED  NULL,
    `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_pages_slug` (`slug`),
    KEY `idx_pages_status` (`status`),
    CONSTRAINT `fk_pages_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PAGE TEMPLATES
-- ============================================================

CREATE TABLE `page_templates` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `canvas_page_id` INT UNSIGNED NULL DEFAULT NULL,
    `slug`           VARCHAR(50)  NOT NULL,
    `name`           VARCHAR(100) NOT NULL,
    `description`    VARCHAR(255) DEFAULT '',
    `zones`          JSON         NOT NULL COMMENT '["main"] or ["main","sidebar"] etc.',
    `css_class`      VARCHAR(100) DEFAULT '',
    `settings`       JSON         NULL,
    `is_system`      TINYINT(1)   NOT NULL DEFAULT 0,
    `sort_order`     INT UNSIGNED NOT NULL DEFAULT 0,
    `editor_mode`    ENUM('structured','freeform') NOT NULL DEFAULT 'structured',
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME     NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_page_templates_slug` (`slug`),
    CONSTRAINT `fk_tpl_canvas_page` FOREIGN KEY (`canvas_page_id`) REFERENCES `pages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MENUS
-- ============================================================

CREATE TABLE `menus` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`          VARCHAR(100) NOT NULL,
    `location`      VARCHAR(50)  NOT NULL UNIQUE,
    `description`   VARCHAR(255) NULL,
    `block_page_id` INT UNSIGNED NULL DEFAULT NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `menu_items` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `menu_id`      INT UNSIGNED NOT NULL,
    `parent_id`    INT UNSIGNED NULL,
    `label`        VARCHAR(100) NOT NULL,
    `link_type`    ENUM('url','page','subject','route') NOT NULL DEFAULT 'url',
    `url`          VARCHAR(500) NULL,
    `page_id`      INT UNSIGNED NULL,
    `subject_id`   INT UNSIGNED NULL,
    `route`        VARCHAR(100) NULL,
    `sort_order`   INT UNSIGNED NOT NULL DEFAULT 0,
    `css_class`    VARCHAR(100) NULL,
    `open_new_tab` TINYINT(1)   NOT NULL DEFAULT 0,
    `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
    `visibility`   ENUM('always','logged_in','logged_out') NOT NULL DEFAULT 'always',
    `min_role`     VARCHAR(20)  NULL DEFAULT NULL,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_menu_items_menu`    FOREIGN KEY (`menu_id`)    REFERENCES `menus`      (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_menu_items_parent`  FOREIGN KEY (`parent_id`)  REFERENCES `menu_items` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_menu_items_page`    FOREIGN KEY (`page_id`)    REFERENCES `pages`      (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_menu_items_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects`   (`id`) ON DELETE SET NULL,
    INDEX `idx_menu_items_menu_order` (`menu_id`, `sort_order`),
    INDEX `idx_menu_items_parent`     (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CRUINN BLOCK EDITOR
-- ============================================================

-- Published blocks — the public site reads ONLY this table
CREATE TABLE `cruinn_blocks` (
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
    KEY `idx_page` (`page_id`, `parent_block_id`, `sort_order`),
    CONSTRAINT `fk_cb_page` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Edit history / draft blocks — one row per block per edit action
CREATE TABLE `cruinn_draft_blocks` (
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
    `is_active`       TINYINT(1)        NOT NULL DEFAULT 1,
    `is_deletion`     TINYINT(1)        NOT NULL DEFAULT 0,
    `prev_id`         INT UNSIGNED      NULL,
    `created_at`      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_page_seq`    (`page_id`, `edit_seq`),
    KEY `idx_page_active` (`page_id`, `block_id`, `is_active`),
    CONSTRAINT `fk_draftblocks_page` FOREIGN KEY (`page_id`)  REFERENCES `pages`               (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_draftblocks_prev` FOREIGN KEY (`prev_id`)  REFERENCES `cruinn_draft_blocks` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Undo cursor — one row per page with an active draft session
CREATE TABLE `cruinn_page_state` (
    `page_id`          INT UNSIGNED NOT NULL,
    `current_edit_seq` INT UNSIGNED NOT NULL DEFAULT 0,
    `max_edit_seq`     INT UNSIGNED NOT NULL DEFAULT 0,
    `last_edited_at`   DATETIME     NULL,
    PRIMARY KEY (`page_id`),
    CONSTRAINT `fk_cps_page` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Named block library (saved/reusable block structures)
CREATE TABLE `named_blocks` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`          VARCHAR(120) NOT NULL,
    `slug`          VARCHAR(120) NOT NULL UNIQUE,
    `description`   TEXT         NULL,
    `root_type`     VARCHAR(40)  NOT NULL DEFAULT 'section',
    `tree_snapshot` JSON         NOT NULL,
    `thumbnail_url` VARCHAR(500) NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- MODULE SYSTEM
-- ============================================================

CREATE TABLE `module_config` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `slug`       VARCHAR(64)  NOT NULL,
    `status`     ENUM('discovered','active','offline') NOT NULL DEFAULT 'discovered',
    `settings`   JSON         NOT NULL DEFAULT (JSON_OBJECT()),
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration tracking (keyed by module + filename)
CREATE TABLE `module_migrations` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `module`     VARCHAR(64)  NOT NULL COMMENT '''core'' or a module slug',
    `filename`   VARCHAR(255) NOT NULL,
    `applied_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_module_file` (`module`, `filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SEED DATA
-- ============================================================

-- ── System Roles ─────────────────────────────────────────────
INSERT INTO `roles` (`slug`, `name`, `description`, `level`, `is_system`, `colour`, `default_redirect`) VALUES
    ('admin',  'Administrator', 'Full site administration',              100, 1, '#dc3545', '/users/profile'),
    ('member', 'Member',        'Authenticated user with standard access', 20, 1, '#198754', '/users/profile'),
    ('public', 'Public',        'Basic account with no special access',     0, 1, '#6c757d', '/');

-- ── Core Permissions ─────────────────────────────────────────
INSERT INTO `permissions` (`slug`, `name`, `category`, `description`) VALUES
    ('pages.view',          'View Pages',           'Content', 'View pages list in admin'),
    ('pages.manage',        'Manage Pages',         'Content', 'Create, edit, and delete pages'),
    ('menus.view',          'View Menus',           'Content', 'View menu configurations'),
    ('menus.manage',        'Manage Menus',         'Content', 'Create and edit navigation menus'),
    ('subjects.view',       'View Subjects',        'Content', 'View site structure'),
    ('subjects.manage',     'Manage Subjects',      'Content', 'Create and manage site structure'),
    ('media.upload',        'Upload Media',         'Content', 'Upload files and images'),
    ('users.view',          'View Users',           'System',  'View user accounts'),
    ('users.manage',        'Manage Users',         'System',  'Create, edit, and delete users'),
    ('roles.manage',        'Manage Roles',         'System',  'Configure roles and permissions'),
    ('dashboard.configure', 'Configure Dashboards', 'System',  'Configure dashboard widgets per role'),
    ('settings.manage',     'Manage Settings',      'System',  'Change site-wide settings'),
    ('activity.view',       'View Activity Log',    'System',  'View the system activity log');

-- Admin gets all permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
    SELECT (SELECT `id` FROM `roles` WHERE `slug` = 'admin'), `id` FROM `permissions`;

-- ── Admin Dashboard Widgets ───────────────────────────────────
INSERT INTO `dashboard_widgets` (`slug`, `name`, `description`, `category`, `template_path`, `data_provider`, `default_settings`) VALUES
    ('stats-overview',  'Stats Overview',  'Key counts: pages, users',      'stats',  'admin/widgets/stats-overview',  'Cruinn\\Services\\DashboardService::statsOverviewData',  NULL),
    ('recent-activity', 'Recent Activity', 'Latest actions from audit log', 'system', 'admin/widgets/recent-activity', 'Cruinn\\Services\\DashboardService::recentActivityData', '{"limit":20}');

INSERT INTO `role_dashboard_widgets` (`role_id`, `widget_id`, `sort_order`, `grid_width`, `is_visible`)
SELECT r.`id`, w.`id`,
    CASE w.`slug` WHEN 'stats-overview' THEN 1 WHEN 'recent-activity' THEN 2 END,
    'full', 1
FROM `roles` r, `dashboard_widgets` w
WHERE r.`slug` = 'admin' AND w.`slug` IN ('stats-overview', 'recent-activity');

-- ── Admin Sidebar Navigation ──────────────────────────────────
SET @admin = (SELECT `id` FROM `roles` WHERE `slug` = 'admin');

INSERT INTO `role_nav_items` (`role_id`, `parent_id`, `label`, `url`, `sort_order`) VALUES
    (@admin, NULL, 'Dashboard', '/admin', 0);

INSERT INTO `role_nav_items` (`role_id`, `parent_id`, `label`, `url`, `sort_order`) VALUES (@admin, NULL, 'Website', '#', 10);
SET @website = LAST_INSERT_ID();
INSERT INTO `role_nav_items` (`role_id`, `parent_id`, `label`, `url`, `sort_order`) VALUES
    (@admin, @website, 'Pages',    '/admin/pages',    0),
    (@admin, @website, 'Menus',    '/admin/menus',    1),
    (@admin, @website, 'Subjects', '/admin/subjects', 2);

INSERT INTO `role_nav_items` (`role_id`, `parent_id`, `label`, `url`, `sort_order`) VALUES (@admin, NULL, 'Site Builder', '/admin/site-builder', 20);

INSERT INTO `role_nav_items` (`role_id`, `parent_id`, `label`, `url`, `sort_order`) VALUES (@admin, NULL, 'Accounts', '#', 30);
SET @accounts = LAST_INSERT_ID();
INSERT INTO `role_nav_items` (`role_id`, `parent_id`, `label`, `url`, `sort_order`) VALUES
    (@admin, @accounts, 'Users',  '/admin/users',  0),
    (@admin, @accounts, 'Roles',  '/admin/roles',  1),
    (@admin, @accounts, 'Groups', '/admin/groups', 2);

INSERT INTO `role_nav_items` (`role_id`, `parent_id`, `label`, `url`, `sort_order`) VALUES
    (@admin, NULL, 'Settings', '/admin/settings', 40);

-- ── Default Menus ────────────────────────────────────────────
INSERT INTO `menus` (`name`, `location`, `description`) VALUES
    ('Main Navigation', 'main',   'Primary site navigation'),
    ('Footer',          'footer', 'Footer navigation'),
    ('Utility Bar',     'topbar', 'Slim utility bar: login/logout/admin');

SET @topbar = (SELECT `id` FROM `menus` WHERE `location` = 'topbar');
INSERT INTO `menu_items` (`menu_id`, `label`, `link_type`, `route`, `sort_order`, `visibility`) VALUES
    (@topbar, 'Login',  'route', '/login',  1, 'logged_out'),
    (@topbar, 'Logout', 'route', '/logout', 2, 'logged_in');

-- ── Default Page Templates ────────────────────────────────────
INSERT INTO `page_templates` (`slug`, `name`, `description`, `zones`, `css_class`, `is_system`, `sort_order`, `settings`) VALUES
    ('default',       'Default',       'Standard reading-width page',       '["main"]',           'layout-default',       1, 1, '{"show_title":true,"show_header":true,"show_footer":true,"show_breadcrumbs":false,"content_width":"default","title_align":"left"}'),
    ('full-width',    'Full Width',     'Full container width',              '["main"]',           'layout-full-width',    1, 2, '{"show_title":true,"show_header":true,"show_footer":true,"show_breadcrumbs":false,"content_width":"full","title_align":"left"}'),
    ('landing',       'Landing Page',  'Full-width hero, no title heading',  '["main"]',           'layout-landing',       1, 3, '{"show_title":false,"show_header":true,"show_footer":true,"show_breadcrumbs":false,"content_width":"full","title_align":"center"}'),
    ('blank',         'Blank',         'Raw block output, no chrome',        '["main"]',           'layout-blank',         1, 4, '{"show_title":false,"show_header":false,"show_footer":false,"show_breadcrumbs":false,"content_width":"full","title_align":"left"}'),
    ('sidebar-right', 'Sidebar Right', 'Content with right sidebar',         '["main","sidebar"]', 'layout-sidebar-right', 1, 5, '{"show_title":true,"show_header":true,"show_footer":true,"show_breadcrumbs":false,"content_width":"default","title_align":"left"}'),
    ('sidebar-left',  'Sidebar Left',  'Content with left sidebar',          '["sidebar","main"]', 'layout-sidebar-left',  1, 6, '{"show_title":true,"show_header":true,"show_footer":true,"show_breadcrumbs":false,"content_width":"default","title_align":"left"}');

-- ── System Zone Pages ─────────────────────────────────────────
-- Slug prefix '_' marks system pages (excluded from page lists)
INSERT INTO `pages` (`title`, `slug`, `status`, `template`, `editor_mode`, `render_mode`) VALUES
    ('Site Header',            '_header',             'published', 'none', 'freeform',   'cruinn'),
    ('Site Footer',            '_footer',             'published', 'none', 'freeform',   'cruinn'),
    ('Global Header Template', '_tpl__global_header', 'published', 'none', 'freeform',   'cruinn'),
    ('Default Template',       '_tpl_default',        'published', 'none', 'structured', 'cruinn'),
    ('Typography & Styles',    '_typography',         'published', 'none', 'freeform',   'cruinn');

-- ── Settings ─────────────────────────────────────────────────
INSERT INTO `settings` (`key`, `value`, `group`) VALUES
    ('site.name',                '', 'site'),
    ('site.tagline',             '', 'site'),
    ('site.url',                 '', 'site'),
    ('site.timezone',            '', 'site'),
    ('site.logo',                '', 'site'),
    ('site.banner',              '', 'site'),
    ('maintenance_mode',         '0', 'site'),
    ('mail.host',                '', 'mail'),
    ('mail.port',                '', 'mail'),
    ('mail.username',            '', 'mail'),
    ('mail.password',            '', 'mail'),
    ('mail.encryption',          '', 'mail'),
    ('mail.from_email',          '', 'mail'),
    ('mail.from_name',           '', 'mail'),
    ('session.lifetime',         '', 'auth'),
    ('session.name',             '', 'auth'),
    ('auth.password_min_length', '8', 'auth'),
    ('auth.max_login_attempts',  '5', 'auth'),
    ('auth.lockout_duration',    '900', 'auth'),
    ('auth.reset_token_expiry',  '3600', 'auth'),
    ('uploads.max_size',         '', 'security'),
    ('uploads.allowed',          '', 'security'),
    ('uploads.image_types',      '', 'security')
ON DUPLICATE KEY UPDATE `key` = VALUES(`key`);

-- ── Mark this schema as applied ──────────────────────────────
INSERT INTO `module_migrations` (`module`, `filename`) VALUES ('core', 'instance_core.sql');
