-- Migration 032: Restore subject_id on payments and membership subscriptions.

SET @payments_has_subject_id := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payments'
      AND COLUMN_NAME = 'subject_id'
);

SET @payments_add_subject_sql := IF(
    @payments_has_subject_id = 0,
    'ALTER TABLE `payments` ADD COLUMN `subject_id` INT UNSIGNED NULL AFTER `subscription_id`',
    'SELECT 1'
);
PREPARE payments_add_subject_stmt FROM @payments_add_subject_sql;
EXECUTE payments_add_subject_stmt;
DEALLOCATE PREPARE payments_add_subject_stmt;

SET @payments_has_subject_idx := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payments'
      AND INDEX_NAME = 'idx_payments_subject'
);

SET @payments_add_subject_idx_sql := IF(
    @payments_has_subject_idx = 0,
    'ALTER TABLE `payments` ADD KEY `idx_payments_subject` (`subject_id`)',
    'SELECT 1'
);
PREPARE payments_add_subject_idx_stmt FROM @payments_add_subject_idx_sql;
EXECUTE payments_add_subject_idx_stmt;
DEALLOCATE PREPARE payments_add_subject_idx_stmt;

SET @payments_has_subject_fk := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payments'
      AND CONSTRAINT_NAME = 'fk_payments_subject'
);

SET @payments_add_subject_fk_sql := IF(
    @payments_has_subject_fk = 0,
    'ALTER TABLE `payments` ADD CONSTRAINT `fk_payments_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE payments_add_subject_fk_stmt FROM @payments_add_subject_fk_sql;
EXECUTE payments_add_subject_fk_stmt;
DEALLOCATE PREPARE payments_add_subject_fk_stmt;

SET @subs_has_subject_id := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'membership_subscriptions'
      AND COLUMN_NAME = 'subject_id'
);

SET @subs_add_subject_sql := IF(
    @subs_has_subject_id = 0,
    'ALTER TABLE `membership_subscriptions` ADD COLUMN `subject_id` INT UNSIGNED NULL AFTER `plan_id`',
    'SELECT 1'
);
PREPARE subs_add_subject_stmt FROM @subs_add_subject_sql;
EXECUTE subs_add_subject_stmt;
DEALLOCATE PREPARE subs_add_subject_stmt;

SET @subs_has_subject_idx := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'membership_subscriptions'
      AND INDEX_NAME = 'idx_membership_subscriptions_subject'
);

SET @subs_add_subject_idx_sql := IF(
    @subs_has_subject_idx = 0,
    'ALTER TABLE `membership_subscriptions` ADD KEY `idx_membership_subscriptions_subject` (`subject_id`)',
    'SELECT 1'
);
PREPARE subs_add_subject_idx_stmt FROM @subs_add_subject_idx_sql;
EXECUTE subs_add_subject_idx_stmt;
DEALLOCATE PREPARE subs_add_subject_idx_stmt;

SET @subs_has_subject_fk := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'membership_subscriptions'
      AND CONSTRAINT_NAME = 'fk_membership_subscriptions_subject'
);

SET @subs_add_subject_fk_sql := IF(
    @subs_has_subject_fk = 0,
    'ALTER TABLE `membership_subscriptions` ADD CONSTRAINT `fk_membership_subscriptions_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE subs_add_subject_fk_stmt FROM @subs_add_subject_fk_sql;
EXECUTE subs_add_subject_fk_stmt;
DEALLOCATE PREPARE subs_add_subject_fk_stmt;
