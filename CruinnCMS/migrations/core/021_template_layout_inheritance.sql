-- Migration 021: add explicit layout inheritance for page templates.
-- canvas_page_id remains the page template editor anchor.
-- layout_page_id points to the standalone template layout canvas.

SET @has_layout_page_id := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'page_templates'
      AND COLUMN_NAME = 'layout_page_id'
);

SET @add_layout_page_id_sql := IF(
    @has_layout_page_id = 0,
    'ALTER TABLE `page_templates` ADD COLUMN `layout_page_id` INT UNSIGNED NULL DEFAULT NULL AFTER `canvas_page_id`',
    'SELECT 1'
);
PREPARE stmt FROM @add_layout_page_id_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_layout_fk := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND CONSTRAINT_NAME = 'fk_tpl_layout_page'
);

SET @add_layout_fk_sql := IF(
    @has_layout_fk = 0,
    'ALTER TABLE `page_templates` ADD CONSTRAINT `fk_tpl_layout_page` FOREIGN KEY (`layout_page_id`) REFERENCES `pages_index` (`id`) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @add_layout_fk_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
