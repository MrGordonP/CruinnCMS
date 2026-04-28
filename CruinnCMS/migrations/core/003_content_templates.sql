-- ============================================================
-- Migration 003: Content templates + blog template settings
-- ============================================================

-- Add template_type to page_templates
ALTER TABLE `page_templates`
    ADD COLUMN `template_type` ENUM('page', 'content') NOT NULL DEFAULT 'page'
    AFTER `canvas_page_id`;

-- Blog module template settings
INSERT IGNORE INTO `settings` (`key`, `value`, `group`) VALUES
    ('blog.single_post_template', '', 'blog'),
    ('blog.post_list_template',   '', 'blog');
