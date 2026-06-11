-- ============================================================
-- Payments Module — Canonical Payments Schema
-- ============================================================
-- Last edit: 2026-06-11 13:04 UTC.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `payments` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `subscription_id` INT UNSIGNED  NULL,
    `subject_id`      INT UNSIGNED  NULL,
    `source_type`     VARCHAR(60)   NULL,
    `source_id`       INT UNSIGNED  NULL,
    `transaction_id`  VARCHAR(120)  NOT NULL,
    `gateway`         VARCHAR(60)   NULL,
    `amount`          DECIMAL(10,2) NOT NULL,
    `currency`        CHAR(3)       NOT NULL DEFAULT 'EUR',
    `status`          ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'completed',
    `paid_at`         DATETIME      NOT NULL,
    `notes`           TEXT          NULL,
    `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_payments_transaction`  (`transaction_id`),
    INDEX `idx_payments_subscription` (`subscription_id`),
    INDEX `idx_payments_subject`      (`subject_id`),
    INDEX `idx_payments_source`       (`source_type`, `source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payment_import_batches` (
    `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `source`               VARCHAR(40)  NOT NULL COMMENT 'bank_csv, stripe_webhook, manual',
    `filename`             VARCHAR(255) NULL,
    `checksum`             VARCHAR(128) NULL,
    `status`               ENUM('processing','completed','failed') NOT NULL DEFAULT 'processing',
    `row_count`            INT UNSIGNED NOT NULL DEFAULT 0,
    `matched_count`        INT UNSIGNED NOT NULL DEFAULT 0,
    `unmatched_count`      INT UNSIGNED NOT NULL DEFAULT 0,
    `error_message`        TEXT NULL,
    `imported_by_user_id`  INT UNSIGNED NULL,
    `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at`         DATETIME NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_payment_import_batches_source` (`source`),
    INDEX `idx_payment_import_batches_status` (`status`),
    INDEX `idx_payment_import_batches_user` (`imported_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payment_transactions` (
    `id`                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `payment_id`              INT UNSIGNED NULL,
    `batch_id`                INT UNSIGNED NULL,
    `source`                  VARCHAR(40)  NOT NULL COMMENT 'bank_csv, stripe_webhook, manual',
    `external_transaction_id` VARCHAR(191) NULL,
    `gateway`                 VARCHAR(60)  NULL,
    `direction`               ENUM('credit','debit') NOT NULL DEFAULT 'credit',
    `amount`                  DECIMAL(10,2) NOT NULL,
    `currency`                CHAR(3) NOT NULL DEFAULT 'EUR',
    `transacted_at`           DATETIME NOT NULL,
    `description`             VARCHAR(255) NULL,
    `reference`               VARCHAR(191) NULL,
    `counterparty`            VARCHAR(191) NULL,
    `reconciled_status`       ENUM('unmatched','matched','ignored') NOT NULL DEFAULT 'unmatched',
    `reconciled_by_user_id`   INT UNSIGNED NULL,
    `reconciled_at`           DATETIME NULL,
    `reconciliation_notes`    TEXT NULL,
    `raw_payload`             JSON NULL,
    `created_at`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_payment_transactions_payment` (`payment_id`),
    INDEX `idx_payment_transactions_batch` (`batch_id`),
    INDEX `idx_payment_transactions_source` (`source`),
    INDEX `idx_payment_transactions_external` (`external_transaction_id`),
    INDEX `idx_payment_transactions_reconciled` (`reconciled_status`),
    INDEX `idx_payment_transactions_transacted_at` (`transacted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
