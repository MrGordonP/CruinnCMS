-- ============================================================
-- Documents Module — Full Schema
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `documents` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `title`         VARCHAR(255)    NOT NULL,
    `description`   TEXT            NULL,
    `file_path`     VARCHAR(500)    NOT NULL,
    `file_name`     VARCHAR(255)    NOT NULL,
    `mime_type`     VARCHAR(100)    NULL,
    `file_size`     INT UNSIGNED    NULL,
    `category_id`   INT UNSIGNED    NULL,
    `uploaded_by`   INT UNSIGNED    NULL,
    `is_public`     TINYINT(1)      NOT NULL DEFAULT 0,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_documents_category` (`category_id`),
    KEY `idx_documents_public` (`is_public`),
    CONSTRAINT `fk_documents_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `document_versions` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `document_id` INT UNSIGNED    NOT NULL,
    `version`     SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `file_path`   VARCHAR(500)    NOT NULL,
    `file_name`   VARCHAR(255)    NOT NULL,
    `file_size`   INT UNSIGNED    NULL,
    `uploaded_by` INT UNSIGNED    NULL,
    `notes`       TEXT            NULL,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_doc_versions_document` (`document_id`, `version`),
    CONSTRAINT `fk_doc_versions_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_doc_versions_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Document Categories ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `document_categories` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `slug`        VARCHAR(80)     NOT NULL,
    `name`        VARCHAR(100)    NOT NULL,
    `description` TEXT            NULL,
    `sort_order`  INT UNSIGNED    NOT NULL DEFAULT 0,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_doc_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `document_categories` (`slug`, `name`, `sort_order`) VALUES
    ('general',   'General',   10),
    ('minutes',   'Minutes',   20),
    ('agendas',   'Agendas',   30),
    ('policies',  'Policies',  40),
    ('reports',   'Reports',   50);

ALTER TABLE `documents`
    ADD COLUMN IF NOT EXISTS `category_id` INT UNSIGNED NULL AFTER `description`;

SET @fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND CONSTRAINT_NAME = 'fk_documents_category');
SET @sql = IF(@fk = 0, 'ALTER TABLE `documents` ADD CONSTRAINT `fk_documents_category` FOREIGN KEY (`category_id`) REFERENCES `document_categories` (`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
