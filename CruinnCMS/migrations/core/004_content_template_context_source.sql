-- Migration: add context_source to page_templates
-- context_source format: 'content_set:{slug}' or a built-in identifier like 'blog.post' / 'blog.list'

ALTER TABLE `page_templates`
    ADD COLUMN `context_source` VARCHAR(100) NULL DEFAULT NULL
        COMMENT 'Data source for content templates, e.g. content_set:members or blog.post'
        AFTER `template_type`;
