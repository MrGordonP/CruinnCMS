-- ============================================================
-- Forms Module — Migration 002: Restore form subject linkage
-- ============================================================

SET NAMES utf8mb4;

SET @forms_has_subject_id := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'forms'
      AND COLUMN_NAME = 'subject_id'
);

SET @forms_add_subject_id_sql := IF(
    @forms_has_subject_id = 0,
    'ALTER TABLE `forms` ADD COLUMN `subject_id` INT UNSIGNED NULL AFTER `slug`',
    'SELECT 1'
);
PREPARE forms_add_subject_id_stmt FROM @forms_add_subject_id_sql;
EXECUTE forms_add_subject_id_stmt;
DEALLOCATE PREPARE forms_add_subject_id_stmt;

SET @forms_has_subject_idx := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'forms'
      AND INDEX_NAME = 'idx_forms_subject_id'
);

SET @forms_add_subject_idx_sql := IF(
    @forms_has_subject_idx = 0,
    'ALTER TABLE `forms` ADD KEY `idx_forms_subject_id` (`subject_id`)',
    'SELECT 1'
);
PREPARE forms_add_subject_idx_stmt FROM @forms_add_subject_idx_sql;
EXECUTE forms_add_subject_idx_stmt;
DEALLOCATE PREPARE forms_add_subject_idx_stmt;
