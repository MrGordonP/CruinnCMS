-- ============================================================
-- Organisation Module — Migration 003: Restore group subject linkage
-- ============================================================

SET NAMES utf8mb4;

SET @groups_has_subject_id := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'groups'
      AND COLUMN_NAME = 'subject_id'
);

SET @groups_add_subject_id_sql := IF(
    @groups_has_subject_id = 0,
    'ALTER TABLE `groups` ADD COLUMN `subject_id` INT UNSIGNED NULL AFTER `description`',
    'SELECT 1'
);
PREPARE groups_add_subject_id_stmt FROM @groups_add_subject_id_sql;
EXECUTE groups_add_subject_id_stmt;
DEALLOCATE PREPARE groups_add_subject_id_stmt;

SET @groups_has_subject_idx := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'groups'
      AND INDEX_NAME = 'idx_groups_subject'
);

SET @groups_add_subject_idx_sql := IF(
    @groups_has_subject_idx = 0,
    'ALTER TABLE `groups` ADD KEY `idx_groups_subject` (`subject_id`)',
    'SELECT 1'
);
PREPARE groups_add_subject_idx_stmt FROM @groups_add_subject_idx_sql;
EXECUTE groups_add_subject_idx_stmt;
DEALLOCATE PREPARE groups_add_subject_idx_stmt;

SET @groups_has_subject_fk := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'groups'
      AND CONSTRAINT_NAME = 'fk_groups_subject'
);

SET @groups_add_subject_fk_sql := IF(
    @groups_has_subject_fk = 0,
    'ALTER TABLE `groups` ADD CONSTRAINT `fk_groups_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE groups_add_subject_fk_stmt FROM @groups_add_subject_fk_sql;
EXECUTE groups_add_subject_fk_stmt;
DEALLOCATE PREPARE groups_add_subject_fk_stmt;
