-- Migration 031: Restore canonical subject_id columns and backfill from subject_content.
--
-- This reverses the subject_id column drops introduced in migration 028 and
-- repopulates subject_id from the bridge table where available.

-- ---------------------------------------------------------------------------
-- Articles
-- ---------------------------------------------------------------------------

SET @articles_has_subject_id := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'articles'
      AND COLUMN_NAME = 'subject_id'
);

SET @articles_add_subject_sql := IF(
    @articles_has_subject_id = 0,
    'ALTER TABLE `articles` ADD COLUMN `subject_id` INT UNSIGNED NULL AFTER `author_id`',
    'SELECT 1'
);
PREPARE articles_add_subject_stmt FROM @articles_add_subject_sql;
EXECUTE articles_add_subject_stmt;
DEALLOCATE PREPARE articles_add_subject_stmt;

SET @articles_has_subject_idx := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'articles'
      AND INDEX_NAME = 'idx_articles_subject'
);

SET @articles_add_subject_idx_sql := IF(
    @articles_has_subject_idx = 0,
    'ALTER TABLE `articles` ADD KEY `idx_articles_subject` (`subject_id`)',
    'SELECT 1'
);
PREPARE articles_add_subject_idx_stmt FROM @articles_add_subject_idx_sql;
EXECUTE articles_add_subject_idx_stmt;
DEALLOCATE PREPARE articles_add_subject_idx_stmt;

UPDATE `articles` a
LEFT JOIN (
    SELECT item_id, MIN(subject_id) AS subject_id
    FROM `subject_content`
    WHERE item_type = 'article'
    GROUP BY item_id
) sc ON sc.item_id = a.id
SET a.subject_id = sc.subject_id
WHERE a.subject_id IS NULL
  AND sc.subject_id IS NOT NULL;

-- ---------------------------------------------------------------------------
-- Events
-- ---------------------------------------------------------------------------

SET @events_has_subject_id := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'events'
      AND COLUMN_NAME = 'subject_id'
);

SET @events_add_subject_sql := IF(
    @events_has_subject_id = 0,
    'ALTER TABLE `events` ADD COLUMN `subject_id` INT UNSIGNED NULL AFTER `slug`',
    'SELECT 1'
);
PREPARE events_add_subject_stmt FROM @events_add_subject_sql;
EXECUTE events_add_subject_stmt;
DEALLOCATE PREPARE events_add_subject_stmt;

SET @events_has_subject_idx := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'events'
      AND INDEX_NAME = 'idx_events_subject_id'
);

SET @events_add_subject_idx_sql := IF(
    @events_has_subject_idx = 0,
    'ALTER TABLE `events` ADD KEY `idx_events_subject_id` (`subject_id`)',
    'SELECT 1'
);
PREPARE events_add_subject_idx_stmt FROM @events_add_subject_idx_sql;
EXECUTE events_add_subject_idx_stmt;
DEALLOCATE PREPARE events_add_subject_idx_stmt;

UPDATE `events` e
LEFT JOIN (
    SELECT item_id, MIN(subject_id) AS subject_id
    FROM `subject_content`
    WHERE item_type = 'event'
    GROUP BY item_id
) sc ON sc.item_id = e.id
SET e.subject_id = sc.subject_id
WHERE e.subject_id IS NULL
  AND sc.subject_id IS NOT NULL;

-- ---------------------------------------------------------------------------
-- Files
-- ---------------------------------------------------------------------------

SET @files_has_subject_id := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'files'
      AND COLUMN_NAME = 'subject_id'
);

SET @files_add_subject_sql := IF(
    @files_has_subject_id = 0,
    'ALTER TABLE `files` ADD COLUMN `subject_id` INT UNSIGNED NULL AFTER `folder_id`',
    'SELECT 1'
);
PREPARE files_add_subject_stmt FROM @files_add_subject_sql;
EXECUTE files_add_subject_stmt;
DEALLOCATE PREPARE files_add_subject_stmt;

SET @files_has_subject_idx := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'files'
      AND INDEX_NAME = 'idx_files_subject'
);

SET @files_add_subject_idx_sql := IF(
    @files_has_subject_idx = 0,
    'ALTER TABLE `files` ADD KEY `idx_files_subject` (`subject_id`)',
    'SELECT 1'
);
PREPARE files_add_subject_idx_stmt FROM @files_add_subject_idx_sql;
EXECUTE files_add_subject_idx_stmt;
DEALLOCATE PREPARE files_add_subject_idx_stmt;

UPDATE `files` f
LEFT JOIN (
    SELECT item_id, MIN(subject_id) AS subject_id
    FROM `subject_content`
    WHERE item_type = 'file'
    GROUP BY item_id
) sc ON sc.item_id = f.id
SET f.subject_id = sc.subject_id
WHERE f.subject_id IS NULL
  AND sc.subject_id IS NOT NULL;

-- ---------------------------------------------------------------------------
-- Folders
-- ---------------------------------------------------------------------------

SET @folders_has_subject_id := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'folders'
      AND COLUMN_NAME = 'subject_id'
);

SET @folders_add_subject_sql := IF(
    @folders_has_subject_id = 0,
    'ALTER TABLE `folders` ADD COLUMN `subject_id` INT UNSIGNED NULL AFTER `slug`',
    'SELECT 1'
);
PREPARE folders_add_subject_stmt FROM @folders_add_subject_sql;
EXECUTE folders_add_subject_stmt;
DEALLOCATE PREPARE folders_add_subject_stmt;

SET @folders_has_subject_idx := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'folders'
      AND INDEX_NAME = 'idx_folders_subject'
);

SET @folders_add_subject_idx_sql := IF(
    @folders_has_subject_idx = 0,
    'ALTER TABLE `folders` ADD KEY `idx_folders_subject` (`subject_id`)',
    'SELECT 1'
);
PREPARE folders_add_subject_idx_stmt FROM @folders_add_subject_idx_sql;
EXECUTE folders_add_subject_idx_stmt;
DEALLOCATE PREPARE folders_add_subject_idx_stmt;

UPDATE `folders` f
LEFT JOIN (
    SELECT item_id, MIN(subject_id) AS subject_id
    FROM `subject_content`
    WHERE item_type = 'folder'
    GROUP BY item_id
) sc ON sc.item_id = f.id
SET f.subject_id = sc.subject_id
WHERE f.subject_id IS NULL
  AND sc.subject_id IS NOT NULL;
