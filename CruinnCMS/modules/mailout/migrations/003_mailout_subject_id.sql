-- ============================================================
-- Mailout Module — Migration 003: Restore subject linkage
-- ============================================================

SET NAMES utf8mb4;

SET @lists_has_subject_id := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'mailing_lists'
      AND COLUMN_NAME = 'subject_id'
);

SET @lists_add_subject_sql := IF(
    @lists_has_subject_id = 0,
    'ALTER TABLE `mailing_lists` ADD COLUMN `subject_id` INT UNSIGNED NULL AFTER `is_active`',
    'SELECT 1'
);
PREPARE lists_add_subject_stmt FROM @lists_add_subject_sql;
EXECUTE lists_add_subject_stmt;
DEALLOCATE PREPARE lists_add_subject_stmt;

SET @lists_has_subject_idx := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'mailing_lists'
      AND INDEX_NAME = 'idx_mailing_lists_subject'
);

SET @lists_add_subject_idx_sql := IF(
    @lists_has_subject_idx = 0,
    'ALTER TABLE `mailing_lists` ADD KEY `idx_mailing_lists_subject` (`subject_id`)',
    'SELECT 1'
);
PREPARE lists_add_subject_idx_stmt FROM @lists_add_subject_idx_sql;
EXECUTE lists_add_subject_idx_stmt;
DEALLOCATE PREPARE lists_add_subject_idx_stmt;

SET @lists_has_subject_fk := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'mailing_lists'
      AND CONSTRAINT_NAME = 'fk_mailing_lists_subject'
);

SET @lists_add_subject_fk_sql := IF(
    @lists_has_subject_fk = 0,
    'ALTER TABLE `mailing_lists` ADD CONSTRAINT `fk_mailing_lists_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE lists_add_subject_fk_stmt FROM @lists_add_subject_fk_sql;
EXECUTE lists_add_subject_fk_stmt;
DEALLOCATE PREPARE lists_add_subject_fk_stmt;

SET @broadcast_has_subject_id := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'email_broadcasts'
      AND COLUMN_NAME = 'subject_id'
);

SET @broadcast_add_subject_sql := IF(
    @broadcast_has_subject_id = 0,
    'ALTER TABLE `email_broadcasts` ADD COLUMN `subject_id` INT UNSIGNED NULL AFTER `list_id`',
    'SELECT 1'
);
PREPARE broadcast_add_subject_stmt FROM @broadcast_add_subject_sql;
EXECUTE broadcast_add_subject_stmt;
DEALLOCATE PREPARE broadcast_add_subject_stmt;

SET @broadcast_has_subject_idx := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'email_broadcasts'
      AND INDEX_NAME = 'idx_broadcast_subject'
);

SET @broadcast_add_subject_idx_sql := IF(
    @broadcast_has_subject_idx = 0,
    'ALTER TABLE `email_broadcasts` ADD KEY `idx_broadcast_subject` (`subject_id`)',
    'SELECT 1'
);
PREPARE broadcast_add_subject_idx_stmt FROM @broadcast_add_subject_idx_sql;
EXECUTE broadcast_add_subject_idx_stmt;
DEALLOCATE PREPARE broadcast_add_subject_idx_stmt;

SET @broadcast_has_subject_fk := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'email_broadcasts'
      AND CONSTRAINT_NAME = 'fk_broadcast_subject'
);

SET @broadcast_add_subject_fk_sql := IF(
    @broadcast_has_subject_fk = 0,
    'ALTER TABLE `email_broadcasts` ADD CONSTRAINT `fk_broadcast_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE broadcast_add_subject_fk_stmt FROM @broadcast_add_subject_fk_sql;
EXECUTE broadcast_add_subject_fk_stmt;
DEALLOCATE PREPARE broadcast_add_subject_fk_stmt;
