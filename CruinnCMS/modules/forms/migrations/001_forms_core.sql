-- ============================================================
-- Migration 020: Forms & Data Acquisition System
-- ============================================================
-- Generic form builder with configurable fields, submissions,
-- approval workflows, and file attachments.
-- Includes a seeded Membership Application form replicating
-- the fields previously captured by the external Google Form.
-- ============================================================

-- ── 1. Forms table ────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `forms` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(200) NOT NULL,
    `slug`        VARCHAR(200) NOT NULL,
    `description` TEXT NULL,
    `form_type`   ENUM('general','membership_application','survey','feedback') NOT NULL DEFAULT 'general',
    `status`      ENUM('draft','published','closed') NOT NULL DEFAULT 'draft',
    `settings`    JSON NULL COMMENT '{"require_login","require_approval","notification_email","success_message","redirect_url","max_submissions"}',
    `created_by`  INT UNSIGNED NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_forms_slug` (`slug`),
    CONSTRAINT `fk_forms_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. Form fields table ──────────────────────────────────────

CREATE TABLE IF NOT EXISTS `form_fields` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `form_id`     INT UNSIGNED NOT NULL,
    `field_type`  ENUM('text','email','number','textarea','select','radio','checkbox','checkbox_group','date','file','heading','paragraph','hidden') NOT NULL DEFAULT 'text',
    `label`       TEXT NOT NULL,
    `name`        VARCHAR(100) NOT NULL,
    `placeholder` VARCHAR(255) NULL,
    `help_text`   VARCHAR(500) NULL,
    `options`     JSON NULL COMMENT 'For select/radio/checkbox_group: [{"value":"v","label":"l"},...]',
    `validation`  JSON NULL COMMENT '{"required":true,"min":0,"max":100,"pattern":"","min_length":0,"max_length":0}',
    `sort_order`  INT UNSIGNED NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_form_fields_form` FOREIGN KEY (`form_id`) REFERENCES `forms`(`id`) ON DELETE CASCADE,
    INDEX `idx_form_fields_order` (`form_id`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. Form submissions table ─────────────────────────────────

CREATE TABLE IF NOT EXISTS `form_submissions` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `form_id`         INT UNSIGNED NOT NULL,
    `user_id`         INT UNSIGNED NULL,
    `data`            JSON NOT NULL COMMENT 'Field name → value pairs',
    `status`          ENUM('pending','approved','rejected','processed') NOT NULL DEFAULT 'pending',
    `reviewer_id`     INT UNSIGNED NULL,
    `reviewed_at`     DATETIME NULL,
    `reviewer_notes`  TEXT NULL,
    `ip_address`      VARCHAR(45) NULL,
    `submitted_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_submissions_form`     FOREIGN KEY (`form_id`)     REFERENCES `forms`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_submissions_user`     FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_submissions_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_submissions_form_status` (`form_id`, `status`),
    INDEX `idx_submissions_date` (`submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. Submission file attachments ────────────────────────────

CREATE TABLE IF NOT EXISTS `form_submission_files` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `submission_id`  INT UNSIGNED NOT NULL,
    `field_name`     VARCHAR(100) NOT NULL,
    `file_path`      VARCHAR(500) NOT NULL,
    `original_name`  VARCHAR(255) NOT NULL,
    `mime_type`      VARCHAR(100) NULL,
    `file_size`      INT UNSIGNED NOT NULL DEFAULT 0,
    `uploaded_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_subfiles_submission` FOREIGN KEY (`submission_id`) REFERENCES `form_submissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════════════════════════════
-- 5. Seed: Example Contact Form
-- ══════════════════════════════════════════════════════════════
-- A minimal contact form seeded as a starting point.
-- Replace or extend this with your organisation's own forms.
-- ══════════════════════════════════════════════════════════════

INSERT INTO `forms` (`title`, `slug`, `description`, `form_type`, `status`, `settings`) VALUES
(
    'Contact Us',
    'contact',
    'General contact form.',
    'general',
    'published',
    JSON_OBJECT(
        'require_login', false,
        'require_approval', false,
        'notification_email', 'info@example.com',
        'success_message', 'Thank you for your message. We will be in touch shortly.',
        'redirect_url', '',
        'max_submissions', 0
    )
);

SET @contact_form = (SELECT id FROM forms WHERE slug = 'contact');

INSERT INTO `form_fields` (`form_id`, `field_type`, `label`, `name`, `placeholder`, `help_text`, `options`, `validation`, `sort_order`) VALUES
(@contact_form, 'text',     'Name',          'name',    'Your name',          NULL, NULL, '{"required":true}', 1),
(@contact_form, 'email',    'Email Address', 'email',   'your@email.com',     NULL, NULL, '{"required":true}', 2),
(@contact_form, 'text',     'Subject',       'subject', NULL,                 NULL, NULL, '{"required":true}', 3),
(@contact_form, 'textarea', 'Message',       'message', 'Your message…',      NULL, NULL, '{"required":true}', 4);
