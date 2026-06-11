-- ============================================================
-- Payments Module — Canonical Payments Schema
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `payments` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `subscription_id` INT UNSIGNED  NULL,
    `subject_id`      INT UNSIGNED  NULL,
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
    INDEX `idx_payments_subject`      (`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
