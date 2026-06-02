-- CruinnCMS — Add context linkage columns to discussions
-- Allows discussions to be scoped to a subject (or future context types).
-- Safe to run on existing installs (information_schema guards on each step).

-- Step 1: Add context_type column if absent
SET @col1 := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'discussions'
      AND COLUMN_NAME  = 'context_type'
);
SET @sql1 := IF(@col1 = 0,
    'ALTER TABLE `discussions` ADD COLUMN `context_type` VARCHAR(50) NULL DEFAULT NULL AFTER `category`',
    'SELECT 1'
);
PREPARE s1 FROM @sql1; EXECUTE s1; DEALLOCATE PREPARE s1;

-- Step 2: Add context_id column if absent
SET @col2 := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'discussions'
      AND COLUMN_NAME  = 'context_id'
);
SET @sql2 := IF(@col2 = 0,
    'ALTER TABLE `discussions` ADD COLUMN `context_id` INT UNSIGNED NULL DEFAULT NULL AFTER `context_type`',
    'SELECT 1'
);
PREPARE s2 FROM @sql2; EXECUTE s2; DEALLOCATE PREPARE s2;

-- Step 3: Add index on (context_type, context_id) if absent
SET @idx1 := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'discussions'
      AND INDEX_NAME   = 'idx_discussions_context'
);
SET @sql3 := IF(@idx1 = 0,
    'ALTER TABLE `discussions` ADD INDEX `idx_discussions_context` (`context_type`, `context_id`)',
    'SELECT 1'
);
PREPARE s3 FROM @sql3; EXECUTE s3; DEALLOCATE PREPARE s3;
