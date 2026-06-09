-- Migration 035: Add promotion modifiers to membership plans.

SET @plans_has_promo_type := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'membership_plans'
      AND COLUMN_NAME = 'promo_type'
);

SET @plans_add_promo_type_sql := IF(
    @plans_has_promo_type = 0,
    'ALTER TABLE `membership_plans` ADD COLUMN `promo_type` VARCHAR(16) NULL AFTER `subject_id`',
    'SELECT 1'
);
PREPARE plans_add_promo_type_stmt FROM @plans_add_promo_type_sql;
EXECUTE plans_add_promo_type_stmt;
DEALLOCATE PREPARE plans_add_promo_type_stmt;

SET @plans_has_promo_value := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'membership_plans'
      AND COLUMN_NAME = 'promo_value'
);

SET @plans_add_promo_value_sql := IF(
    @plans_has_promo_value = 0,
    'ALTER TABLE `membership_plans` ADD COLUMN `promo_value` DECIMAL(10,2) NULL AFTER `promo_type`',
    'SELECT 1'
);
PREPARE plans_add_promo_value_stmt FROM @plans_add_promo_value_sql;
EXECUTE plans_add_promo_value_stmt;
DEALLOCATE PREPARE plans_add_promo_value_stmt;

SET @plans_has_promo_starts := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'membership_plans'
      AND COLUMN_NAME = 'promo_starts_at'
);

SET @plans_add_promo_starts_sql := IF(
    @plans_has_promo_starts = 0,
    'ALTER TABLE `membership_plans` ADD COLUMN `promo_starts_at` DATETIME NULL AFTER `promo_value`',
    'SELECT 1'
);
PREPARE plans_add_promo_starts_stmt FROM @plans_add_promo_starts_sql;
EXECUTE plans_add_promo_starts_stmt;
DEALLOCATE PREPARE plans_add_promo_starts_stmt;

SET @plans_has_promo_ends := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'membership_plans'
      AND COLUMN_NAME = 'promo_ends_at'
);

SET @plans_add_promo_ends_sql := IF(
    @plans_has_promo_ends = 0,
    'ALTER TABLE `membership_plans` ADD COLUMN `promo_ends_at` DATETIME NULL AFTER `promo_starts_at`',
    'SELECT 1'
);
PREPARE plans_add_promo_ends_stmt FROM @plans_add_promo_ends_sql;
EXECUTE plans_add_promo_ends_stmt;
DEALLOCATE PREPARE plans_add_promo_ends_stmt;
