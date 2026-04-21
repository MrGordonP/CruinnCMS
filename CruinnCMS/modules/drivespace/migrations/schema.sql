-- ============================================================
-- Drivespace Module — Full Schema
-- ============================================================

SET NAMES utf8mb4;

-- ── Folders ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `folders` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `parent_id`     INT UNSIGNED    NULL,
    `name`          VARCHAR(255)    NOT NULL,
    `slug`          VARCHAR(255)    NOT NULL,
    `description`   TEXT            NULL,
    `owner_id`      INT UNSIGNED    NOT NULL,
    `subject_id`    INT UNSIGNED    NULL,
    `visibility`    ENUM('private','role','members','public') NOT NULL DEFAULT 'private',
    `allowed_roles` JSON            NULL,
    `sort_order`    INT UNSIGNED    NOT NULL DEFAULT 0,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_folders_parent`     (`parent_id`),
    KEY `idx_folders_owner`      (`owner_id`),
    KEY `idx_folders_visibility` (`visibility`),
    CONSTRAINT `fk_folders_parent`  FOREIGN KEY (`parent_id`)  REFERENCES `folders`  (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_folders_owner`   FOREIGN KEY (`owner_id`)   REFERENCES `users`    (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_folders_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Files ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `files` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `folder_id`       INT UNSIGNED    NULL,
    `title`           VARCHAR(255)    NOT NULL,
    `description`     TEXT            NULL,
    `file_path`       VARCHAR(500)    NULL,
    `original_name`   VARCHAR(255)    NULL,
    `file_size`       INT UNSIGNED    NULL,
    `mime_type`       VARCHAR(100)    NULL,
    `file_ext`        VARCHAR(10)     NULL,
    `owner_id`        INT UNSIGNED    NOT NULL,
    `subject_id`      INT UNSIGNED    NULL,
    `status`          ENUM('draft','pending_review','approved','published','archived') NOT NULL DEFAULT 'draft',
    `version`         INT UNSIGNED    NOT NULL DEFAULT 1,
    `content_type`    ENUM('upload','composed') NOT NULL DEFAULT 'upload',
    `parsed_content`  LONGTEXT        NULL,
    `metadata`        JSON            NULL,
    `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_files_folder`  (`folder_id`),
    KEY `idx_files_owner`   (`owner_id`),
    KEY `idx_files_status`  (`status`),
    KEY `idx_files_subject` (`subject_id`),
    FULLTEXT KEY `idx_files_search` (`title`, `description`),
    CONSTRAINT `fk_files_folder`  FOREIGN KEY (`folder_id`)  REFERENCES `folders`  (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_files_owner`   FOREIGN KEY (`owner_id`)   REFERENCES `users`    (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_files_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── File Versions ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `file_versions` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `file_id`         INT UNSIGNED    NOT NULL,
    `version_num`     INT UNSIGNED    NOT NULL,
    `file_path`       VARCHAR(500)    NULL,
    `file_size`       INT UNSIGNED    NULL,
    `parsed_content`  LONGTEXT        NULL,
    `notes`           TEXT            NULL,
    `created_by`      INT UNSIGNED    NOT NULL,
    `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_file_versions_file` (`file_id`),
    CONSTRAINT `fk_file_versions_file` FOREIGN KEY (`file_id`)    REFERENCES `files` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_file_versions_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── File Shares ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `file_shares` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `resource_type` ENUM('file','folder') NOT NULL,
    `resource_id`   INT UNSIGNED    NOT NULL,
    `target_type`   ENUM('user','role') NOT NULL,
    `target_id`     INT UNSIGNED    NOT NULL,
    `permission`    ENUM('view','edit','manage') NOT NULL DEFAULT 'view',
    `shared_by`     INT UNSIGNED    NOT NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_file_shares` (`resource_type`, `resource_id`, `target_type`, `target_id`),
    KEY `idx_file_shares_resource` (`resource_type`, `resource_id`),
    KEY `idx_file_shares_target`   (`target_type`, `target_id`),
    CONSTRAINT `fk_file_shares_sharer` FOREIGN KEY (`shared_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── File Publications ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `file_publications` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `file_id`       INT UNSIGNED    NOT NULL,
    `target_type`   ENUM('article','event','mailing_list','social') NOT NULL,
    `target_id`     INT UNSIGNED    NULL,
    `published_by`  INT UNSIGNED    NOT NULL,
    `published_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `notes`         TEXT            NULL,
    PRIMARY KEY (`id`),
    KEY `idx_file_publications_file` (`file_id`),
    CONSTRAINT `fk_file_publications_file` FOREIGN KEY (`file_id`)     REFERENCES `files` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_file_publications_user` FOREIGN KEY (`published_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Seed default root folders ────────────────────────────────
INSERT INTO `folders` (`name`, `slug`, `description`, `owner_id`, `visibility`, `sort_order`) VALUES
    ('Council Documents', 'council-documents', 'Official council documents, minutes, and reports', 1, 'role', 1),
    ('Shared Resources',  'shared-resources',  'Resources available to all members',                1, 'members', 2),
    ('Public Files',      'public-files',       'Publicly accessible documents and downloads',       1, 'public', 3);

UPDATE `folders` SET `allowed_roles` = JSON_ARRAY(
    (SELECT `id` FROM `roles` WHERE `slug` = 'admin'   LIMIT 1),
    (SELECT `id` FROM `roles` WHERE `slug` = 'council' LIMIT 1)
) WHERE `slug` = 'council-documents';

-- ── User Quota Columns ───────────────────────────────────────
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `drivespace_quota_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 524288000 COMMENT '500 MB default quota',
    ADD COLUMN IF NOT EXISTS `drivespace_used_bytes`  BIGINT UNSIGNED NOT NULL DEFAULT 0;
