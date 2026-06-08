-- Migration 034: Add subject linkage to membership plans.

SET @plans_has_subject_id := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'membership_plans'
      AND COLUMN_NAME = 'subject_id'
);

SET @plans_add_subject_sql := IF(
    @plans_has_subject_id = 0,
    'ALTER TABLE `membership_plans` ADD COLUMN `subject_id` INT UNSIGNED NULL AFTER `parent_plan_id`',
    'SELECT 1'
);
PREPARE plans_add_subject_stmt FROM @plans_add_subject_sql;
EXECUTE plans_add_subject_stmt;
DEALLOCATE PREPARE plans_add_subject_stmt;

SET @plans_has_subject_idx := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'membership_plans'
      AND INDEX_NAME = 'idx_membership_plans_subject'
);

SET @plans_add_subject_idx_sql := IF(
    @plans_has_subject_idx = 0,
    'ALTER TABLE `membership_plans` ADD KEY `idx_membership_plans_subject` (`subject_id`)',
    'SELECT 1'
);
PREPARE plans_add_subject_idx_stmt FROM @plans_add_subject_idx_sql;
EXECUTE plans_add_subject_idx_stmt;
DEALLOCATE PREPARE plans_add_subject_idx_stmt;

SET @plans_has_subject_fk := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'membership_plans'
      AND CONSTRAINT_NAME = 'fk_membership_plans_subject'
);

SET @plans_add_subject_fk_sql := IF(
    @plans_has_subject_fk = 0,
    'ALTER TABLE `membership_plans` ADD CONSTRAINT `fk_membership_plans_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE plans_add_subject_fk_stmt FROM @plans_add_subject_fk_sql;
EXECUTE plans_add_subject_fk_stmt;
DEALLOCATE PREPARE plans_add_subject_fk_stmt;
