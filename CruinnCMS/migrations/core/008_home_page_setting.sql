-- Migration 008: add site.home_page_id setting
-- Designates which page is served at /

INSERT INTO `settings` (`key`, `value`, `group`)
VALUES ('site.home_page_id', '', 'site')
ON DUPLICATE KEY UPDATE `key` = VALUES(`key`);
