-- ============================================================
-- Cruinn CMS — Migration 019: Subject-linked forum threads
--
-- Adds optional subject ownership so one discussion thread can be attached
-- to a subject and rendered beneath linked content items.
-- ============================================================

SET NAMES utf8mb4;

SET @forum_threads_has_subject_id := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'forum_threads'
      AND COLUMN_NAME = 'subject_id'
);

SET @forum_threads_add_subject_id_sql := IF(
    @forum_threads_has_subject_id = 0,
    'ALTER TABLE `forum_threads` ADD COLUMN `subject_id` INT UNSIGNED NULL AFTER `category_id`',
    'SELECT 1'
);
PREPARE forum_threads_add_subject_id_stmt FROM @forum_threads_add_subject_id_sql;
EXECUTE forum_threads_add_subject_id_stmt;
DEALLOCATE PREPARE forum_threads_add_subject_id_stmt;

SET @forum_threads_has_subject_idx := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'forum_threads'
      AND INDEX_NAME = 'idx_forum_threads_subject_id'
);

SET @forum_threads_add_subject_idx_sql := IF(
    @forum_threads_has_subject_idx = 0,
    'ALTER TABLE `forum_threads` ADD KEY `idx_forum_threads_subject_id` (`subject_id`)',
    'SELECT 1'
);
PREPARE forum_threads_add_subject_idx_stmt FROM @forum_threads_add_subject_idx_sql;
EXECUTE forum_threads_add_subject_idx_stmt;
DEALLOCATE PREPARE forum_threads_add_subject_idx_stmt;

SET @forum_threads_has_subject_unique := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'forum_threads'
      AND INDEX_NAME = 'uk_forum_threads_subject_id'
);

SET @forum_threads_add_subject_unique_sql := IF(
    @forum_threads_has_subject_unique = 0,
    'ALTER TABLE `forum_threads` ADD UNIQUE KEY `uk_forum_threads_subject_id` (`subject_id`)',
    'SELECT 1'
);
PREPARE forum_threads_add_subject_unique_stmt FROM @forum_threads_add_subject_unique_sql;
EXECUTE forum_threads_add_subject_unique_stmt;
DEALLOCATE PREPARE forum_threads_add_subject_unique_stmt;
