-- ============================================================
-- Payments Module — Core Schema (stub)
-- ============================================================
-- payment_transactions records every payment attempt associated
-- with any source object (e.g. a form submission).
-- Gateway integration (Stripe etc.) is implemented separately.
-- ============================================================

CREATE TABLE IF NOT EXISTS `payment_transactions` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `source_type`       VARCHAR(50) NOT NULL COMMENT 'e.g. form_submission',
    `source_id`         INT UNSIGNED NOT NULL COMMENT 'FK to the source record',
    `amount`            DECIMAL(10,2) NOT NULL,
    `currency`          CHAR(3) NOT NULL DEFAULT 'EUR',
    `payment_method`    ENUM('bank_transfer','cash','cheque','stripe') NOT NULL,
    `gateway`           VARCHAR(50) NULL COMMENT 'stripe, paypal, etc.',
    `gateway_ref`       VARCHAR(255) NULL COMMENT 'Gateway transaction ID',
    `status`            ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
    `verified_by`       INT UNSIGNED NULL,
    `verified_at`       DATETIME NULL,
    `notes`             TEXT NULL,
    `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_pt_source` (`source_type`, `source_id`),
    INDEX `idx_pt_status` (`status`),
    CONSTRAINT `fk_pt_verifier` FOREIGN KEY (`verified_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
