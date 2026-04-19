-- ============================================================
-- Forms Migration 002: Payment Options & Submission Payment Fields
-- ============================================================

-- ── 1. Payment options per form (price tiers) ────────────────

CREATE TABLE IF NOT EXISTS `form_payment_options` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `form_id`     INT UNSIGNED NOT NULL,
    `label`       VARCHAR(200) NOT NULL COMMENT 'e.g. Full Member, Student Member',
    `amount`      DECIMAL(8,2) NOT NULL,
    `currency`    CHAR(3) NOT NULL DEFAULT 'EUR',
    `sort_order`  INT UNSIGNED NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_fpo_form` FOREIGN KEY (`form_id`) REFERENCES `forms`(`id`) ON DELETE CASCADE,
    INDEX `idx_fpo_form_order` (`form_id`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. Payment columns on form_submissions ───────────────────

ALTER TABLE `form_submissions`
    ADD COLUMN `payment_option_id` INT UNSIGNED NULL
        COMMENT 'Selected price tier at time of submission'
        AFTER `reviewer_notes`,
    ADD COLUMN `payment_method` ENUM('bank_transfer','cash','cheque','stripe') NULL
        AFTER `payment_option_id`,
    ADD COLUMN `payment_status` ENUM('not_required','pending','verified','rejected') NOT NULL DEFAULT 'not_required'
        AFTER `payment_method`,
    ADD COLUMN `payment_verified_by` INT UNSIGNED NULL
        AFTER `payment_status`,
    ADD COLUMN `payment_verified_at` DATETIME NULL
        AFTER `payment_verified_by`,
    ADD COLUMN `payment_notes` TEXT NULL
        AFTER `payment_verified_at`,
    ADD CONSTRAINT `fk_submissions_payment_option`
        FOREIGN KEY (`payment_option_id`) REFERENCES `form_payment_options`(`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_submissions_payment_verifier`
        FOREIGN KEY (`payment_verified_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;
