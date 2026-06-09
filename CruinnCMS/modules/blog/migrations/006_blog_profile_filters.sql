-- Migration 006 (blog): Add subject_id filter column to blog_profiles.
-- Idempotent — guarded by information_schema check.

SET @blog_profiles_has_subject_id := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'blog_profiles'
      AND COLUMN_NAME  = 'subject_id'
);

SET @blog_profiles_add_subject_sql := IF(
    @blog_profiles_has_subject_id = 0,
    'ALTER TABLE `blog_profiles` ADD COLUMN `subject_id` INT UNSIGNED NULL DEFAULT NULL AFTER `description`',
    'SELECT 1'
);

PREPARE blog_profiles_add_subject_stmt FROM @blog_profiles_add_subject_sql;
EXECUTE blog_profiles_add_subject_stmt;
DEALLOCATE PREPARE blog_profiles_add_subject_stmt;
