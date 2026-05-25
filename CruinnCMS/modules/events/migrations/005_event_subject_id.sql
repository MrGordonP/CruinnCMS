-- ============================================================
-- Events Module — Migration 005: Subject linkage
--
-- Allows events to participate in subject-owned flows such as linked
-- forum discussions and cross-content relationships.
-- ============================================================

SET NAMES utf8mb4;

SET @events_has_subject_id := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'events'
      AND COLUMN_NAME = 'subject_id'
);

SET @events_add_subject_id_sql := IF(
    @events_has_subject_id = 0,
    'ALTER TABLE `events` ADD COLUMN `subject_id` INT UNSIGNED NULL AFTER `slug`',
    'SELECT 1'
);
PREPARE events_add_subject_id_stmt FROM @events_add_subject_id_sql;
EXECUTE events_add_subject_id_stmt;
DEALLOCATE PREPARE events_add_subject_id_stmt;

SET @events_has_subject_idx := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
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
