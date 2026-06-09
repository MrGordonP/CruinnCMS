-- Migration 006 (blog): Add subject_id filter column to blog_profiles.

ALTER TABLE `blog_profiles`
    ADD COLUMN IF NOT EXISTS `subject_id` INT UNSIGNED NULL DEFAULT NULL AFTER `description`;
