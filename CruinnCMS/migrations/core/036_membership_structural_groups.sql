-- Migration 036: Distinguish structural plan groups from shared-member subscriptions.

SET @plans_has_is_plan_group := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'membership_plans'
      AND COLUMN_NAME = 'is_plan_group'
);

SET @plans_add_is_plan_group_sql := IF(
    @plans_has_is_plan_group = 0,
    'ALTER TABLE `membership_plans` ADD COLUMN `is_plan_group` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_group`',
    'SELECT 1'
);
PREPARE plans_add_is_plan_group_stmt FROM @plans_add_is_plan_group_sql;
EXECUTE plans_add_is_plan_group_stmt;
DEALLOCATE PREPARE plans_add_is_plan_group_stmt;

-- Backfill structural groups from legacy heuristic:
-- top-level records that acted as grouping containers.
UPDATE `membership_plans` p
SET p.`is_plan_group` = 1
WHERE p.`is_group` = 1
  AND (p.`parent_plan_id` IS NULL OR p.`parent_plan_id` = 0)
  AND (
      p.`price` <= 0
      OR EXISTS (
          SELECT 1 FROM `membership_plans` c WHERE c.`parent_plan_id` = p.`id`
      )
  );

SET @plans_has_structural_idx := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'membership_plans'
      AND INDEX_NAME = 'idx_membership_plans_structural_group'
);

SET @plans_add_structural_idx_sql := IF(
    @plans_has_structural_idx = 0,
    'ALTER TABLE `membership_plans` ADD KEY `idx_membership_plans_structural_group` (`is_plan_group`)',
    'SELECT 1'
);
PREPARE plans_add_structural_idx_stmt FROM @plans_add_structural_idx_sql;
EXECUTE plans_add_structural_idx_stmt;
DEALLOCATE PREPARE plans_add_structural_idx_stmt;
