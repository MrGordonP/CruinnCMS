-- CruinnCMS — Documents Module Tables
-- Document library with versioning and approval workflow.
-- Safe to run on existing installs (CREATE TABLE IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS `documents` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(255)    NOT NULL,
    `description` TEXT            NULL,
    `category`    ENUM('minutes', 'reports', 'policies', 'correspondence', 'financial', 'other') NOT NULL DEFAULT 'other',
    `file_path`   VARCHAR(500)    NOT NULL,
    `file_size`   INT UNSIGNED    NOT NULL DEFAULT 0,
    `file_type`   VARCHAR(10)     NULL,
    `uploaded_by` INT UNSIGNED    NULL,
    `status`      ENUM('draft', 'submitted', 'approved', 'archived') NOT NULL DEFAULT 'draft',
    `approved_by` INT UNSIGNED    NULL,
    `approved_at` DATETIME        NULL,
    `version`     INT UNSIGNED    NOT NULL DEFAULT 1,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_documents_category` (`category`),
    KEY `idx_documents_status` (`status`),
    CONSTRAINT `fk_documents_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_documents_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `document_versions` (
    `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `document_id`  INT UNSIGNED    NOT NULL,
    `version_num`  INT UNSIGNED    NOT NULL,
    `file_path`    VARCHAR(500)    NOT NULL,
    `file_size`    INT UNSIGNED    NOT NULL DEFAULT 0,
    `uploaded_by`  INT UNSIGNED    NULL,
    `notes`        TEXT            NULL,
    `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_doc_versions_document` (`document_id`, `version_num`),
    CONSTRAINT `fk_doc_versions_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_doc_versions_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
