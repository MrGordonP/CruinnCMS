-- Migration 028: Replace per-module subject_id columns with a single subject_content bridge table.
--
-- Previously each module stored a single INT subject_id FK. This prevented an item from
-- belonging to more than one subject grouping. The bridge table makes subject association
-- polymorphic and many-to-many. Module tables no longer reference subjects directly.
--
-- item_type values: 'article', 'event', 'file', 'folder' (extensible by future modules)
-- item_id: the module table's own row PK (no FK enforced — polymorphic by design)

-- ── Step 1: Create the bridge table ──────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `subject_content` (
    `subject_id`  INT UNSIGNED  NOT NULL COMMENT 'FK → subjects.id',
    `item_type`   VARCHAR(50)   NOT NULL COMMENT 'Module item type: article, event, file, folder, …',
    `item_id`     INT UNSIGNED  NOT NULL COMMENT 'Row PK in the owning module table',
    PRIMARY KEY (`subject_id`, `item_type`, `item_id`),
    KEY `idx_sc_item` (`item_type`, `item_id`),
    CONSTRAINT `fk_sc_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Step 2: Migrate existing data (articles) ─────────────────────────────────
-- Guard: only if articles.subject_id column exists and has non-NULL rows.

SET @has_articles_subject := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'articles' AND COLUMN_NAME = 'subject_id'
);

SET @migrate_articles_sql := IF(
    @has_articles_subject > 0,
    'INSERT IGNORE INTO `subject_content` (subject_id, item_type, item_id)
     SELECT subject_id, ''article'', id FROM `articles` WHERE subject_id IS NOT NULL',
    'SELECT 1'
);
PREPARE migrate_articles_stmt FROM @migrate_articles_sql;
EXECUTE migrate_articles_stmt;
DEALLOCATE PREPARE migrate_articles_stmt;

-- ── Step 3: Migrate existing data (events) ───────────────────────────────────

SET @has_events_subject := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'subject_id'
);

SET @migrate_events_sql := IF(
    @has_events_subject > 0,
    'INSERT IGNORE INTO `subject_content` (subject_id, item_type, item_id)
     SELECT subject_id, ''event'', id FROM `events` WHERE subject_id IS NOT NULL',
    'SELECT 1'
);
PREPARE migrate_events_stmt FROM @migrate_events_sql;
EXECUTE migrate_events_stmt;
DEALLOCATE PREPARE migrate_events_stmt;

-- ── Step 4: Migrate existing data (files) ────────────────────────────────────

SET @has_files_subject := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND COLUMN_NAME = 'subject_id'
);

SET @migrate_files_sql := IF(
    @has_files_subject > 0,
    'INSERT IGNORE INTO `subject_content` (subject_id, item_type, item_id)
     SELECT subject_id, ''file'', id FROM `files` WHERE subject_id IS NOT NULL',
    'SELECT 1'
);
PREPARE migrate_files_stmt FROM @migrate_files_sql;
EXECUTE migrate_files_stmt;
DEALLOCATE PREPARE migrate_files_stmt;

-- ── Step 5: Migrate existing data (folders) ──────────────────────────────────

SET @has_folders_subject := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'folders' AND COLUMN_NAME = 'subject_id'
);

SET @migrate_folders_sql := IF(
    @has_folders_subject > 0,
    'INSERT IGNORE INTO `subject_content` (subject_id, item_type, item_id)
     SELECT subject_id, ''folder'', id FROM `folders` WHERE subject_id IS NOT NULL',
    'SELECT 1'
);
PREPARE migrate_folders_stmt FROM @migrate_folders_sql;
EXECUTE migrate_folders_stmt;
DEALLOCATE PREPARE migrate_folders_stmt;

-- ── Step 6: Drop subject_id from articles ────────────────────────────────────

SET @drop_articles_idx_sql := IF(
    @has_articles_subject > 0,
    'ALTER TABLE `articles` DROP KEY IF EXISTS `idx_articles_subject`',
    'SELECT 1'
);
PREPARE drop_articles_idx_stmt FROM @drop_articles_idx_sql;
EXECUTE drop_articles_idx_stmt;
DEALLOCATE PREPARE drop_articles_idx_stmt;

SET @drop_articles_col_sql := IF(
    @has_articles_subject > 0,
    'ALTER TABLE `articles` DROP COLUMN `subject_id`',
    'SELECT 1'
);
PREPARE drop_articles_col_stmt FROM @drop_articles_col_sql;
EXECUTE drop_articles_col_stmt;
DEALLOCATE PREPARE drop_articles_col_stmt;

-- ── Step 7: Drop subject_id from events ──────────────────────────────────────

SET @drop_events_idx_sql := IF(
    @has_events_subject > 0,
    'ALTER TABLE `events` DROP KEY IF EXISTS `idx_events_subject_id`',
    'SELECT 1'
);
PREPARE drop_events_idx_stmt FROM @drop_events_idx_sql;
EXECUTE drop_events_idx_stmt;
DEALLOCATE PREPARE drop_events_idx_stmt;

SET @drop_events_col_sql := IF(
    @has_events_subject > 0,
    'ALTER TABLE `events` DROP COLUMN `subject_id`',
    'SELECT 1'
);
PREPARE drop_events_col_stmt FROM @drop_events_col_sql;
EXECUTE drop_events_col_stmt;
DEALLOCATE PREPARE drop_events_col_stmt;

-- ── Step 8: Drop subject_id from files (has named FK) ────────────────────────

SET @has_files_fk := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files'
      AND CONSTRAINT_NAME = 'fk_files_subject' AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @drop_files_fk_sql := IF(@has_files_fk > 0, 'ALTER TABLE `files` DROP FOREIGN KEY `fk_files_subject`', 'SELECT 1');
PREPARE drop_files_fk_stmt FROM @drop_files_fk_sql;
EXECUTE drop_files_fk_stmt;
DEALLOCATE PREPARE drop_files_fk_stmt;

SET @drop_files_idx_sql := IF(
    @has_files_subject > 0,
    'ALTER TABLE `files` DROP KEY IF EXISTS `idx_files_subject`',
    'SELECT 1'
);
PREPARE drop_files_idx_stmt FROM @drop_files_idx_sql;
EXECUTE drop_files_idx_stmt;
DEALLOCATE PREPARE drop_files_idx_stmt;

SET @drop_files_col_sql := IF(
    @has_files_subject > 0,
    'ALTER TABLE `files` DROP COLUMN `subject_id`',
    'SELECT 1'
);
PREPARE drop_files_col_stmt FROM @drop_files_col_sql;
EXECUTE drop_files_col_stmt;
DEALLOCATE PREPARE drop_files_col_stmt;

-- ── Step 9: Drop subject_id from folders (has named FK) ──────────────────────

SET @has_folders_fk := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'folders'
      AND CONSTRAINT_NAME = 'fk_folders_subject' AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @drop_folders_fk_sql := IF(@has_folders_fk > 0, 'ALTER TABLE `folders` DROP FOREIGN KEY `fk_folders_subject`', 'SELECT 1');
PREPARE drop_folders_fk_stmt FROM @drop_folders_fk_sql;
EXECUTE drop_folders_fk_stmt;
DEALLOCATE PREPARE drop_folders_fk_stmt;

SET @drop_folders_col_sql := IF(
    @has_folders_subject > 0,
    'ALTER TABLE `folders` DROP COLUMN `subject_id`',
    'SELECT 1'
);
PREPARE drop_folders_col_stmt FROM @drop_folders_col_sql;
EXECUTE drop_folders_col_stmt;
DEALLOCATE PREPARE drop_folders_col_stmt;
