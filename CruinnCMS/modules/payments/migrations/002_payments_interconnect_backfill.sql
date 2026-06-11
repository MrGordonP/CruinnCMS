-- ============================================================
-- Payments Module — Migration 002: Interconnect backfill
-- ============================================================
-- Last edit: 2026-06-11 14:05 UTC.
--
-- Purpose:
-- 1) Ensure legacy instances have the canonical payments columns/indexes.
-- 2) Ensure reconciliation columns exist on payment_transactions.
-- 3) Backfill canonical aliases from legacy names where possible.
--
-- Safe to re-run:
-- - Uses INFORMATION_SCHEMA guards before ALTER/INDEX operations.
-- - Uses CREATE TABLE IF NOT EXISTS for missing tables.

SET NAMES utf8mb4;

-- Canonical supporting table in case a legacy instance never had it.
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

-- Canonical raw transactions table in case a legacy instance never had it.
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

-- payments.source_type
SET @payments_has_source_type := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payments'
      AND COLUMN_NAME = 'source_type'
);
SET @payments_add_source_type_sql := IF(
    @payments_has_source_type = 0,
    'ALTER TABLE `payments` ADD COLUMN `source_type` VARCHAR(60) NULL AFTER `subject_id`',
    'DO 0'
);
PREPARE payments_add_source_type_stmt FROM @payments_add_source_type_sql;
EXECUTE payments_add_source_type_stmt;
DEALLOCATE PREPARE payments_add_source_type_stmt;

-- payments.source_id
SET @payments_has_source_id := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payments'
      AND COLUMN_NAME = 'source_id'
);
SET @payments_add_source_id_sql := IF(
    @payments_has_source_id = 0,
    'ALTER TABLE `payments` ADD COLUMN `source_id` INT UNSIGNED NULL AFTER `source_type`',
    'DO 0'
);
PREPARE payments_add_source_id_stmt FROM @payments_add_source_id_sql;
EXECUTE payments_add_source_id_stmt;
DEALLOCATE PREPARE payments_add_source_id_stmt;

-- payments index: idx_payments_source
SET @payments_has_source_idx := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payments'
      AND INDEX_NAME = 'idx_payments_source'
);
SET @payments_add_source_idx_sql := IF(
    @payments_has_source_idx = 0,
    'ALTER TABLE `payments` ADD KEY `idx_payments_source` (`source_type`, `source_id`)',
    'DO 0'
);
PREPARE payments_add_source_idx_stmt FROM @payments_add_source_idx_sql;
EXECUTE payments_add_source_idx_stmt;
DEALLOCATE PREPARE payments_add_source_idx_stmt;

-- payment_transactions.payment_id
SET @pt_has_payment_id := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payment_transactions'
      AND COLUMN_NAME = 'payment_id'
);
SET @pt_add_payment_id_sql := IF(
    @pt_has_payment_id = 0,
    'ALTER TABLE `payment_transactions` ADD COLUMN `payment_id` INT UNSIGNED NULL AFTER `id`',
    'DO 0'
);
PREPARE pt_add_payment_id_stmt FROM @pt_add_payment_id_sql;
EXECUTE pt_add_payment_id_stmt;
DEALLOCATE PREPARE pt_add_payment_id_stmt;

-- payment_transactions.batch_id
SET @pt_has_batch_id := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payment_transactions'
      AND COLUMN_NAME = 'batch_id'
);
SET @pt_add_batch_id_sql := IF(
    @pt_has_batch_id = 0,
    'ALTER TABLE `payment_transactions` ADD COLUMN `batch_id` INT UNSIGNED NULL AFTER `payment_id`',
    'DO 0'
);
PREPARE pt_add_batch_id_stmt FROM @pt_add_batch_id_sql;
EXECUTE pt_add_batch_id_stmt;
DEALLOCATE PREPARE pt_add_batch_id_stmt;

-- payment_transactions.source
SET @pt_has_source := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payment_transactions'
      AND COLUMN_NAME = 'source'
);
SET @pt_add_source_sql := IF(
    @pt_has_source = 0,
    'ALTER TABLE `payment_transactions` ADD COLUMN `source` VARCHAR(40) NOT NULL DEFAULT ''manual'' AFTER `batch_id`',
    'DO 0'
);
PREPARE pt_add_source_stmt FROM @pt_add_source_sql;
EXECUTE pt_add_source_stmt;
DEALLOCATE PREPARE pt_add_source_stmt;

-- payment_transactions.external_transaction_id
SET @pt_has_external_tx := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payment_transactions'
      AND COLUMN_NAME = 'external_transaction_id'
);
SET @pt_add_external_tx_sql := IF(
    @pt_has_external_tx = 0,
    'ALTER TABLE `payment_transactions` ADD COLUMN `external_transaction_id` VARCHAR(191) NULL AFTER `source`',
    'DO 0'
);
PREPARE pt_add_external_tx_stmt FROM @pt_add_external_tx_sql;
EXECUTE pt_add_external_tx_stmt;
DEALLOCATE PREPARE pt_add_external_tx_stmt;

-- payment_transactions.gateway
SET @pt_has_gateway := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payment_transactions'
      AND COLUMN_NAME = 'gateway'
);
SET @pt_add_gateway_sql := IF(
    @pt_has_gateway = 0,
    'ALTER TABLE `payment_transactions` ADD COLUMN `gateway` VARCHAR(60) NULL AFTER `external_transaction_id`',
    'DO 0'
);
PREPARE pt_add_gateway_stmt FROM @pt_add_gateway_sql;
EXECUTE pt_add_gateway_stmt;
DEALLOCATE PREPARE pt_add_gateway_stmt;

-- payment_transactions.direction
SET @pt_has_direction := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payment_transactions'
      AND COLUMN_NAME = 'direction'
);
SET @pt_add_direction_sql := IF(
    @pt_has_direction = 0,
    'ALTER TABLE `payment_transactions` ADD COLUMN `direction` ENUM(''credit'',''debit'') NOT NULL DEFAULT ''credit'' AFTER `gateway`',
    'DO 0'
);
PREPARE pt_add_direction_stmt FROM @pt_add_direction_sql;
EXECUTE pt_add_direction_stmt;
DEALLOCATE PREPARE pt_add_direction_stmt;

-- payment_transactions.amount
SET @pt_has_amount := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payment_transactions'
      AND COLUMN_NAME = 'amount'
);
SET @pt_add_amount_sql := IF(
    @pt_has_amount = 0,
    'ALTER TABLE `payment_transactions` ADD COLUMN `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `direction`',
    'DO 0'
);
PREPARE pt_add_amount_stmt FROM @pt_add_amount_sql;
EXECUTE pt_add_amount_stmt;
DEALLOCATE PREPARE pt_add_amount_stmt;

-- payment_transactions.currency
SET @pt_has_currency := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payment_transactions'
      AND COLUMN_NAME = 'currency'
);
SET @pt_add_currency_sql := IF(
    @pt_has_currency = 0,
    'ALTER TABLE `payment_transactions` ADD COLUMN `currency` CHAR(3) NOT NULL DEFAULT ''EUR'' AFTER `amount`',
    'DO 0'
);
PREPARE pt_add_currency_stmt FROM @pt_add_currency_sql;
EXECUTE pt_add_currency_stmt;
DEALLOCATE PREPARE pt_add_currency_stmt;

-- payment_transactions.transacted_at
SET @pt_has_transacted_at := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payment_transactions'
      AND COLUMN_NAME = 'transacted_at'
);
SET @pt_add_transacted_at_sql := IF(
    @pt_has_transacted_at = 0,
    'ALTER TABLE `payment_transactions` ADD COLUMN `transacted_at` DATETIME NULL AFTER `currency`',
    'DO 0'
);
PREPARE pt_add_transacted_at_stmt FROM @pt_add_transacted_at_sql;
EXECUTE pt_add_transacted_at_stmt;
DEALLOCATE PREPARE pt_add_transacted_at_stmt;

-- payment_transactions.description
SET @pt_has_description := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payment_transactions'
      AND COLUMN_NAME = 'description'
);
SET @pt_add_description_sql := IF(
    @pt_has_description = 0,
    'ALTER TABLE `payment_transactions` ADD COLUMN `description` VARCHAR(255) NULL AFTER `transacted_at`',
    'DO 0'
);
PREPARE pt_add_description_stmt FROM @pt_add_description_sql;
EXECUTE pt_add_description_stmt;
DEALLOCATE PREPARE pt_add_description_stmt;

-- payment_transactions.reference
SET @pt_has_reference := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payment_transactions'
      AND COLUMN_NAME = 'reference'
);
SET @pt_add_reference_sql := IF(
    @pt_has_reference = 0,
    'ALTER TABLE `payment_transactions` ADD COLUMN `reference` VARCHAR(191) NULL AFTER `description`',
    'DO 0'
);
PREPARE pt_add_reference_stmt FROM @pt_add_reference_sql;
EXECUTE pt_add_reference_stmt;
DEALLOCATE PREPARE pt_add_reference_stmt;

-- payment_transactions.counterparty
SET @pt_has_counterparty := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payment_transactions'
      AND COLUMN_NAME = 'counterparty'
);
SET @pt_add_counterparty_sql := IF(
    @pt_has_counterparty = 0,
    'ALTER TABLE `payment_transactions` ADD COLUMN `counterparty` VARCHAR(191) NULL AFTER `reference`',
    'DO 0'
);
PREPARE pt_add_counterparty_stmt FROM @pt_add_counterparty_sql;
EXECUTE pt_add_counterparty_stmt;
DEALLOCATE PREPARE pt_add_counterparty_stmt;

-- payment_transactions.reconciled_status
SET @pt_has_reconciled_status := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payment_transactions'
      AND COLUMN_NAME = 'reconciled_status'
);
SET @pt_add_reconciled_status_sql := IF(
    @pt_has_reconciled_status = 0,
    'ALTER TABLE `payment_transactions` ADD COLUMN `reconciled_status` ENUM(''unmatched'',''matched'',''ignored'') NOT NULL DEFAULT ''unmatched'' AFTER `counterparty`',
    'DO 0'
);
PREPARE pt_add_reconciled_status_stmt FROM @pt_add_reconciled_status_sql;
EXECUTE pt_add_reconciled_status_stmt;
DEALLOCATE PREPARE pt_add_reconciled_status_stmt;

-- payment_transactions.reconciled_by_user_id
SET @pt_has_reconciled_by_user_id := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payment_transactions'
      AND COLUMN_NAME = 'reconciled_by_user_id'
);
SET @pt_add_reconciled_by_user_id_sql := IF(
    @pt_has_reconciled_by_user_id = 0,
    'ALTER TABLE `payment_transactions` ADD COLUMN `reconciled_by_user_id` INT UNSIGNED NULL AFTER `reconciled_status`',
    'DO 0'
);
PREPARE pt_add_reconciled_by_user_id_stmt FROM @pt_add_reconciled_by_user_id_sql;
EXECUTE pt_add_reconciled_by_user_id_stmt;
DEALLOCATE PREPARE pt_add_reconciled_by_user_id_stmt;

-- payment_transactions.reconciled_at
SET @pt_has_reconciled_at := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payment_transactions'
      AND COLUMN_NAME = 'reconciled_at'
);
SET @pt_add_reconciled_at_sql := IF(
    @pt_has_reconciled_at = 0,
    'ALTER TABLE `payment_transactions` ADD COLUMN `reconciled_at` DATETIME NULL AFTER `reconciled_by_user_id`',
    'DO 0'
);
PREPARE pt_add_reconciled_at_stmt FROM @pt_add_reconciled_at_sql;
EXECUTE pt_add_reconciled_at_stmt;
DEALLOCATE PREPARE pt_add_reconciled_at_stmt;

-- payment_transactions.reconciliation_notes
SET @pt_has_reconciliation_notes := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payment_transactions'
      AND COLUMN_NAME = 'reconciliation_notes'
);
SET @pt_add_reconciliation_notes_sql := IF(
    @pt_has_reconciliation_notes = 0,
    'ALTER TABLE `payment_transactions` ADD COLUMN `reconciliation_notes` TEXT NULL AFTER `reconciled_at`',
    'DO 0'
);
PREPARE pt_add_reconciliation_notes_stmt FROM @pt_add_reconciliation_notes_sql;
EXECUTE pt_add_reconciliation_notes_stmt;
DEALLOCATE PREPARE pt_add_reconciliation_notes_stmt;

-- payment_transactions.raw_payload
SET @pt_has_raw_payload := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payment_transactions'
      AND COLUMN_NAME = 'raw_payload'
);
SET @pt_add_raw_payload_sql := IF(
    @pt_has_raw_payload = 0,
    'ALTER TABLE `payment_transactions` ADD COLUMN `raw_payload` JSON NULL AFTER `reconciliation_notes`',
    'DO 0'
);
PREPARE pt_add_raw_payload_stmt FROM @pt_add_raw_payload_sql;
EXECUTE pt_add_raw_payload_stmt;
DEALLOCATE PREPARE pt_add_raw_payload_stmt;

-- payment_transactions.created_at
SET @pt_has_created_at := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payment_transactions'
      AND COLUMN_NAME = 'created_at'
);
SET @pt_add_created_at_sql := IF(
    @pt_has_created_at = 0,
    'ALTER TABLE `payment_transactions` ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `raw_payload`',
    'DO 0'
);
PREPARE pt_add_created_at_stmt FROM @pt_add_created_at_sql;
EXECUTE pt_add_created_at_stmt;
DEALLOCATE PREPARE pt_add_created_at_stmt;

-- Backfill canonical source from legacy source_type.
SET @pt_has_source_now := (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'payment_transactions'
            AND COLUMN_NAME = 'source'
);
SET @pt_has_legacy_source_type := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payment_transactions'
      AND COLUMN_NAME = 'source_type'
);
SET @pt_backfill_source_sql := IF(
    @pt_has_source_now = 1 AND @pt_has_legacy_source_type = 1,
    'UPDATE `payment_transactions` SET `source` = `source_type` WHERE (`source` IS NULL OR TRIM(`source`) = '''') AND `source_type` IS NOT NULL',
    'DO 0'
);
PREPARE pt_backfill_source_stmt FROM @pt_backfill_source_sql;
EXECUTE pt_backfill_source_stmt;
DEALLOCATE PREPARE pt_backfill_source_stmt;

-- Backfill canonical external_transaction_id from legacy gateway_ref.
SET @pt_has_external_tx_now := (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'payment_transactions'
            AND COLUMN_NAME = 'external_transaction_id'
);
SET @pt_has_legacy_gateway_ref := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payment_transactions'
      AND COLUMN_NAME = 'gateway_ref'
);
SET @pt_backfill_external_sql := IF(
    @pt_has_external_tx_now = 1 AND @pt_has_legacy_gateway_ref = 1,
    'UPDATE `payment_transactions` SET `external_transaction_id` = `gateway_ref` WHERE `external_transaction_id` IS NULL AND `gateway_ref` IS NOT NULL',
    'DO 0'
);
PREPARE pt_backfill_external_stmt FROM @pt_backfill_external_sql;
EXECUTE pt_backfill_external_stmt;
DEALLOCATE PREPARE pt_backfill_external_stmt;

-- Backfill transacted_at from created_at if missing.
SET @pt_has_transacted_at_now := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payment_transactions'
      AND COLUMN_NAME = 'transacted_at'
);
SET @pt_has_created_at_now := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payment_transactions'
      AND COLUMN_NAME = 'created_at'
);
SET @pt_backfill_transacted_sql := IF(
    @pt_has_transacted_at_now = 1 AND @pt_has_created_at_now = 1,
    'UPDATE `payment_transactions` SET `transacted_at` = `created_at` WHERE `transacted_at` IS NULL',
    'DO 0'
);
PREPARE pt_backfill_transacted_stmt FROM @pt_backfill_transacted_sql;
EXECUTE pt_backfill_transacted_stmt;
DEALLOCATE PREPARE pt_backfill_transacted_stmt;

-- payment_transactions index: idx_payment_transactions_payment
SET @pt_has_idx_payment := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payment_transactions'
      AND INDEX_NAME = 'idx_payment_transactions_payment'
);
SET @pt_add_idx_payment_sql := IF(
    @pt_has_idx_payment = 0,
    'ALTER TABLE `payment_transactions` ADD KEY `idx_payment_transactions_payment` (`payment_id`)',
    'DO 0'
);
PREPARE pt_add_idx_payment_stmt FROM @pt_add_idx_payment_sql;
EXECUTE pt_add_idx_payment_stmt;
DEALLOCATE PREPARE pt_add_idx_payment_stmt;

-- payment_transactions index: idx_payment_transactions_batch
SET @pt_has_idx_batch := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payment_transactions'
      AND INDEX_NAME = 'idx_payment_transactions_batch'
);
SET @pt_add_idx_batch_sql := IF(
    @pt_has_idx_batch = 0,
    'ALTER TABLE `payment_transactions` ADD KEY `idx_payment_transactions_batch` (`batch_id`)',
    'DO 0'
);
PREPARE pt_add_idx_batch_stmt FROM @pt_add_idx_batch_sql;
EXECUTE pt_add_idx_batch_stmt;
DEALLOCATE PREPARE pt_add_idx_batch_stmt;

-- payment_transactions index: idx_payment_transactions_source
SET @pt_has_idx_source := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payment_transactions'
      AND INDEX_NAME = 'idx_payment_transactions_source'
);
SET @pt_add_idx_source_sql := IF(
    @pt_has_idx_source = 0,
    'ALTER TABLE `payment_transactions` ADD KEY `idx_payment_transactions_source` (`source`)',
    'DO 0'
);
PREPARE pt_add_idx_source_stmt FROM @pt_add_idx_source_sql;
EXECUTE pt_add_idx_source_stmt;
DEALLOCATE PREPARE pt_add_idx_source_stmt;

-- payment_transactions index: idx_payment_transactions_external
SET @pt_has_idx_external := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payment_transactions'
      AND INDEX_NAME = 'idx_payment_transactions_external'
);
SET @pt_add_idx_external_sql := IF(
    @pt_has_idx_external = 0,
    'ALTER TABLE `payment_transactions` ADD KEY `idx_payment_transactions_external` (`external_transaction_id`)',
    'DO 0'
);
PREPARE pt_add_idx_external_stmt FROM @pt_add_idx_external_sql;
EXECUTE pt_add_idx_external_stmt;
DEALLOCATE PREPARE pt_add_idx_external_stmt;

-- payment_transactions index: idx_payment_transactions_reconciled
SET @pt_has_idx_reconciled := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payment_transactions'
      AND INDEX_NAME = 'idx_payment_transactions_reconciled'
);
SET @pt_add_idx_reconciled_sql := IF(
    @pt_has_idx_reconciled = 0,
    'ALTER TABLE `payment_transactions` ADD KEY `idx_payment_transactions_reconciled` (`reconciled_status`)',
    'DO 0'
);
PREPARE pt_add_idx_reconciled_stmt FROM @pt_add_idx_reconciled_sql;
EXECUTE pt_add_idx_reconciled_stmt;
DEALLOCATE PREPARE pt_add_idx_reconciled_stmt;

-- payment_transactions index: idx_payment_transactions_transacted_at
SET @pt_has_idx_transacted := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payment_transactions'
      AND INDEX_NAME = 'idx_payment_transactions_transacted_at'
);
SET @pt_add_idx_transacted_sql := IF(
    @pt_has_idx_transacted = 0,
    'ALTER TABLE `payment_transactions` ADD KEY `idx_payment_transactions_transacted_at` (`transacted_at`)',
    'DO 0'
);
PREPARE pt_add_idx_transacted_stmt FROM @pt_add_idx_transacted_sql;
EXECUTE pt_add_idx_transacted_stmt;
DEALLOCATE PREPARE pt_add_idx_transacted_stmt;
