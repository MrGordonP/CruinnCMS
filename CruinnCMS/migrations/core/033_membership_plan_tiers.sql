-- Migration 033: Add tier grouping support to membership plans.

SET @plans_has_parent := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'membership_plans'
      AND COLUMN_NAME = 'parent_plan_id'
);

SET @plans_add_parent_sql := IF(
    @plans_has_parent = 0,
    'ALTER TABLE `membership_plans` ADD COLUMN `parent_plan_id` INT UNSIGNED NULL AFTER `max_members`',
    'SELECT 1'
);
PREPARE plans_add_parent_stmt FROM @plans_add_parent_sql;
EXECUTE plans_add_parent_stmt;
DEALLOCATE PREPARE plans_add_parent_stmt;

SET @plans_has_parent_idx := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'membership_plans'
      AND INDEX_NAME = 'idx_membership_plans_parent'
);

SET @plans_add_parent_idx_sql := IF(
    @plans_has_parent_idx = 0,
    'ALTER TABLE `membership_plans` ADD KEY `idx_membership_plans_parent` (`parent_plan_id`)',
    'SELECT 1'
);
PREPARE plans_add_parent_idx_stmt FROM @plans_add_parent_idx_sql;
EXECUTE plans_add_parent_idx_stmt;
DEALLOCATE PREPARE plans_add_parent_idx_stmt;

SET @plans_has_parent_fk := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'membership_plans'
      AND CONSTRAINT_NAME = 'fk_membership_plans_parent'
);

SET @plans_add_parent_fk_sql := IF(
    @plans_has_parent_fk = 0,
    'ALTER TABLE `membership_plans` ADD CONSTRAINT `fk_membership_plans_parent` FOREIGN KEY (`parent_plan_id`) REFERENCES `membership_plans` (`id`) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE plans_add_parent_fk_stmt FROM @plans_add_parent_fk_sql;
EXECUTE plans_add_parent_fk_stmt;
DEALLOCATE PREPARE plans_add_parent_fk_stmt;
