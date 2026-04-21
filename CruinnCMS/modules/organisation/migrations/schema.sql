-- ============================================================
-- Organisation Module — Full Schema
-- ============================================================

SET NAMES utf8mb4;

-- ── Discussions ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `discussions` (
    `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `title`        VARCHAR(255)    NOT NULL,
    `category`     VARCHAR(50)     NULL,
    `created_by`   INT UNSIGNED    NULL,
    `pinned`       TINYINT(1)      NOT NULL DEFAULT 0,
    `locked`       TINYINT(1)      NOT NULL DEFAULT 0,
    `post_count`   INT UNSIGNED    NOT NULL DEFAULT 0,
    `last_post_at` DATETIME        NULL,
    `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_discussions_pinned` (`pinned`, `last_post_at`),
    CONSTRAINT `fk_discussions_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `discussion_posts` (
    `id`             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `discussion_id`  INT UNSIGNED    NOT NULL,
    `author_id`      INT UNSIGNED    NULL,
    `body`           TEXT            NOT NULL,
    `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME        NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_posts_discussion` (`discussion_id`, `created_at`),
    CONSTRAINT `fk_posts_discussion` FOREIGN KEY (`discussion_id`) REFERENCES `discussions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_posts_author`     FOREIGN KEY (`author_id`)     REFERENCES `users`       (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Groups ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `groups` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug`        VARCHAR(50)  NOT NULL,
    `name`        VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) DEFAULT '',
    `group_type`  ENUM('committee','working_group','interest','custom') NOT NULL DEFAULT 'custom',
    `role_id`     INT UNSIGNED NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_groups_slug` (`slug`),
    CONSTRAINT `fk_groups_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_groups` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED NOT NULL,
    `group_id`    INT UNSIGNED NOT NULL,
    `assigned_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `assigned_by` INT UNSIGNED NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_group` (`user_id`, `group_id`),
    INDEX `idx_ug_group` (`group_id`),
    CONSTRAINT `fk_ug_user`        FOREIGN KEY (`user_id`)     REFERENCES `users`  (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ug_group`       FOREIGN KEY (`group_id`)    REFERENCES `groups` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ug_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users`  (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Organisation Profile ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `organisation_profile` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`            VARCHAR(255)    NOT NULL DEFAULT '',
    `short_name`      VARCHAR(50)     NULL     COMMENT 'Abbreviation / acronym',
    `tagline`         VARCHAR(255)    NULL,
    `founded_year`    YEAR            NULL,
    `registration_no` VARCHAR(100)    NULL     COMMENT 'Company / charity registration number',
    `address`         TEXT            NULL,
    `email`           VARCHAR(255)    NULL,
    `phone`           VARCHAR(50)     NULL,
    `website`         VARCHAR(255)    NULL,
    `bio`             TEXT            NULL     COMMENT 'Public-facing organisation description',
    `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `organisation_profile` (`id`, `name`) VALUES (1, '');

-- ── Officers ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `organisation_officers` (
    `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `position`   VARCHAR(150)    NOT NULL COMMENT 'e.g. President, Secretary, Treasurer',
    `user_id`    INT UNSIGNED    NULL     COMMENT 'Linked user account (optional)',
    `name`       VARCHAR(150)    NULL     COMMENT 'Free-text name if no user account',
    `email`      VARCHAR(255)    NULL,
    `bio`        TEXT            NULL,
    `sort_order` INT UNSIGNED    NOT NULL DEFAULT 0,
    `active`     TINYINT(1)      NOT NULL DEFAULT 1,
    `term_start` DATE            NULL,
    `term_end`   DATE            NULL,
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_officers_sort` (`sort_order`, `position`),
    CONSTRAINT `fk_officers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Meetings ─────────────────────────────────────────────────
-- Note: agenda_doc_id and minutes_doc_id are nullable INT columns linking
-- to documents.id. FK constraints are intentionally omitted so the
-- meetings table does not depend on the documents module being installed.
CREATE TABLE IF NOT EXISTS `organisation_meetings` (
    `id`             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `title`          VARCHAR(255)    NOT NULL,
    `meeting_type`   ENUM('agm','egm','committee','working_group','other') NOT NULL DEFAULT 'committee',
    `meeting_date`   DATE            NOT NULL,
    `start_time`     TIME            NULL,
    `location`       VARCHAR(255)    NULL,
    `description`    TEXT            NULL,
    `agenda_doc_id`  INT UNSIGNED    NULL COMMENT 'documents.id for agenda (no FK — documents module optional)',
    `minutes_doc_id` INT UNSIGNED    NULL COMMENT 'documents.id for approved minutes (no FK — documents module optional)',
    `status`         ENUM('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
    `created_by`     INT UNSIGNED    NULL,
    `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_meetings_date` (`meeting_date`, `status`),
    CONSTRAINT `fk_meetings_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Finance ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `finance_periods` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(100) NOT NULL COMMENT 'e.g. 2025-2026',
    `starts_on`  DATE         NOT NULL,
    `ends_on`    DATE         NOT NULL,
    `is_current` TINYINT(1)   NOT NULL DEFAULT 0,
    `notes`      TEXT         NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `finance_categories` (
    `id`         INT UNSIGNED                    NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(100)                    NOT NULL,
    `type`       ENUM('income','expense')         NOT NULL,
    `sort_order` INT UNSIGNED                    NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `finance_categories` (`name`, `type`, `sort_order`) VALUES
    ('Membership Fees',    'income',  10),
    ('Event Income',       'income',  20),
    ('Donations',          'income',  30),
    ('Other Income',       'income',  99),
    ('Operating Expenses', 'expense', 10),
    ('Event Expenses',     'expense', 20),
    ('Venue Hire',         'expense', 30),
    ('Equipment',          'expense', 40),
    ('Communications',     'expense', 50),
    ('Other Expense',      'expense', 99);

CREATE TABLE IF NOT EXISTS `finance_entries` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `period_id`   INT UNSIGNED  NOT NULL,
    `category_id` INT UNSIGNED  NOT NULL,
    `type`        ENUM('income','expense') NOT NULL,
    `amount`      DECIMAL(10,2) NOT NULL,
    `currency`    CHAR(3)       NOT NULL DEFAULT 'EUR',
    `description` VARCHAR(500)  NOT NULL,
    `reference`   VARCHAR(100)  NULL COMMENT 'Cheque no., receipt ref, etc.',
    `entry_date`  DATE          NOT NULL,
    `source_type` ENUM('manual','membership_payment','form_payment','event_payment') NOT NULL DEFAULT 'manual',
    `source_id`   INT UNSIGNED  NULL COMMENT 'ID in source table',
    `recorded_by` INT UNSIGNED  NULL,
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_finance_period`   (`period_id`),
    KEY `idx_finance_category` (`category_id`),
    KEY `idx_finance_date`     (`entry_date`),
    KEY `idx_finance_source`   (`source_type`, `source_id`),
    CONSTRAINT `fk_finance_period`   FOREIGN KEY (`period_id`)   REFERENCES `finance_periods`    (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_finance_category` FOREIGN KEY (`category_id`) REFERENCES `finance_categories` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_finance_recorder` FOREIGN KEY (`recorded_by`) REFERENCES `users`              (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
