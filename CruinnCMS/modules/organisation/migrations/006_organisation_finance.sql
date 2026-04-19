-- CruinnCMS — Organisation Migration 006: Finance tracking
--
-- Budget periods, transaction categories, and ledger entries.
-- Reads membership and form payment records as read-only sources.
-- Safe to run repeatedly (CREATE TABLE IF NOT EXISTS; INSERT IGNORE).

-- Budget periods (e.g. financial year)
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

-- Transaction categories
CREATE TABLE IF NOT EXISTS `finance_categories` (
    `id`         INT UNSIGNED                   NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(100)                   NOT NULL,
    `type`       ENUM('income','expense')        NOT NULL,
    `sort_order` INT UNSIGNED                   NOT NULL DEFAULT 0,
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

-- Ledger entries (manual + auto-ingested from other modules)
CREATE TABLE IF NOT EXISTS `finance_entries` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `period_id`   INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
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
