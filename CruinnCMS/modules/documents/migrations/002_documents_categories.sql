-- CruinnCMS — Documents Migration 002: Standing categories table
--
-- Replaces the hardcoded ENUM `category` with a FK reference to a
-- `document_categories` table, allowing admins to manage categories
-- without schema changes.
--
-- The old `category` ENUM column is left intact so existing data and
-- any code still referencing it does not break. category_id is the
-- canonical reference going forward.
--
-- Safe to run repeatedly (IF NOT EXISTS / IF EXISTS guards throughout).

-- 1. Categories table
CREATE TABLE IF NOT EXISTS `document_categories` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100)    NOT NULL,
    `slug`        VARCHAR(100)    NOT NULL,
    `description` TEXT            NULL,
    `sort_order`  INT UNSIGNED    NOT NULL DEFAULT 0,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_doc_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Seed default categories (match the existing ENUM values)
INSERT IGNORE INTO `document_categories` (`name`, `slug`, `sort_order`) VALUES
    ('Minutes',          'minutes',          10),
    ('Reports',          'reports',          20),
    ('Policies',         'policies',         30),
    ('Correspondence',   'correspondence',   40),
    ('Financial',        'financial',        50),
    ('Other',            'other',            60);

-- 3. Add category_id FK to documents (nullable — rows with no match stay NULL)
ALTER TABLE `documents`
    ADD COLUMN IF NOT EXISTS `category_id` INT UNSIGNED NULL
        COMMENT 'FK to document_categories; replaces legacy category ENUM'
        AFTER `category`;

-- 4. Populate category_id from existing ENUM values (idempotent — only fills NULLs)
UPDATE `documents` d
INNER JOIN `document_categories` c ON c.slug = d.category
SET d.category_id = c.id
WHERE d.category_id IS NULL;

-- 5. Add FK constraint if it doesn't exist yet
SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME         = 'documents'
      AND CONSTRAINT_NAME    = 'fk_documents_category_id'
      AND CONSTRAINT_TYPE    = 'FOREIGN KEY'
);
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE `documents` ADD CONSTRAINT `fk_documents_category_id`
     FOREIGN KEY (`category_id`) REFERENCES `document_categories` (`id`) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
