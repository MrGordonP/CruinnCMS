-- ============================================================
-- Migration 009 — Membership Schema Restructure
-- ============================================================
-- Drops the empty legacy membership_subscriptions and
-- membership_payments tables and rebuilds the full membership
-- schema as designed. Preserves existing members rows, strips
-- redundant columns, and adds the new supporting tables.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── Drop empty legacy tables ──────────────────────────────────

DROP TABLE IF EXISTS `membership_payments`;
DROP TABLE IF EXISTS `membership_subscriptions`;

-- ── Strip redundant columns from members ──────────────────────
-- Status is derived from subscriptions.
-- Plan, year, joined/lapsed dates belong on subscriptions.
-- Phone belongs on member_addresses.
-- Notes move to member_admin.

ALTER TABLE `members`
    DROP FOREIGN KEY IF EXISTS `fk_members_plan`;

ALTER TABLE `members`
    DROP COLUMN IF EXISTS `phone`,
    DROP COLUMN IF EXISTS `status`,
    DROP COLUMN IF EXISTS `membership_year`,
    DROP COLUMN IF EXISTS `plan_id`,
    DROP COLUMN IF EXISTS `joined_at`,
    DROP COLUMN IF EXISTS `lapsed_at`,
    DROP COLUMN IF EXISTS `notes`;

-- Add unique constraint on email (required for idempotent SQL imports)
-- Any existing duplicate emails will need manual resolution before this runs.
ALTER TABLE `members`
    ADD UNIQUE KEY IF NOT EXISTS `uk_members_email` (`email`);

-- ── Add phone to member_addresses ─────────────────────────────

ALTER TABLE `member_addresses`
    ADD COLUMN IF NOT EXISTS `phone` VARCHAR(50) NULL AFTER `country`;

-- ── member_admin — admin-only notes per member ────────────────

CREATE TABLE IF NOT EXISTS `member_admin` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `member_id`  INT UNSIGNED NOT NULL,
    `notes`      TEXT         NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_member_admin_member` (`member_id`),
    CONSTRAINT `fk_member_admin_member` FOREIGN KEY (`member_id`)
        REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── membership_plans additions ────────────────────────────────

ALTER TABLE `membership_plans`
    ADD COLUMN IF NOT EXISTS `is_group`    TINYINT(1)      NOT NULL DEFAULT 0    AFTER `is_active`,
    ADD COLUMN IF NOT EXISTS `max_members` TINYINT UNSIGNED NULL                 AFTER `is_group`;

-- ── payments — generic transaction log (payments module) ──────
-- subscription_id is set after membership_subscriptions is created.

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

-- ── membership_subscriptions — redesigned ─────────────────────

CREATE TABLE IF NOT EXISTS `membership_subscriptions` (
    `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `member_id`           INT UNSIGNED  NOT NULL,
    `plan_id`             INT UNSIGNED  NULL,
    `subject_id`          INT UNSIGNED  NULL,
    `period_start`        DATE          NOT NULL,
    `period_end`          DATE          NOT NULL,
    `member_type`         ENUM('new','renewal') NOT NULL DEFAULT 'new',
    `geologist_level`     ENUM('amateur','professional','academic','student','other') NULL,
    `institution`         VARCHAR(200)  NULL,
    `position`            VARCHAR(200)  NULL,
    `student_level`       ENUM('secondary','undergraduate','postgraduate','other') NULL,
    `amount`              DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `currency`            CHAR(3)       NOT NULL DEFAULT 'EUR',
    `payment_method`      ENUM('online','bank_transfer','cash','waived') NOT NULL DEFAULT 'bank_transfer',
    `transaction_id`      VARCHAR(120)  NULL,
    `payment_id`          INT UNSIGNED  NULL,
    `verification_status` ENUM('unverified','verified','disputed','waived') NOT NULL DEFAULT 'unverified',
    `verified_by`         INT UNSIGNED  NULL,
    `verified_at`         DATETIME      NULL,
    `notes`               TEXT          NULL,
    `created_at`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_membership_subscriptions_member` (`member_id`),
    INDEX `idx_membership_subscriptions_period` (`period_start`, `period_end`),
    INDEX `idx_membership_subscriptions_plan`   (`plan_id`),
    INDEX `idx_membership_subscriptions_subject` (`subject_id`),
    INDEX `idx_membership_subscriptions_tx`     (`transaction_id`),
    CONSTRAINT `fk_membership_subscriptions_member`
        FOREIGN KEY (`member_id`)  REFERENCES `members`(`id`)             ON DELETE CASCADE,
    CONSTRAINT `fk_membership_subscriptions_plan`
        FOREIGN KEY (`plan_id`)    REFERENCES `membership_plans`(`id`)    ON DELETE SET NULL,
    CONSTRAINT `fk_membership_subscriptions_subject`
        FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`)            ON DELETE SET NULL,
    CONSTRAINT `fk_membership_subscriptions_payment`
        FOREIGN KEY (`payment_id`) REFERENCES `payments`(`id`)            ON DELETE SET NULL,
    CONSTRAINT `fk_membership_subscriptions_verifier`
        FOREIGN KEY (`verified_by`) REFERENCES `users`(`id`)              ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Back-link payments → subscriptions ───────────────────────
-- Added after membership_subscriptions exists.

ALTER TABLE `payments`
    ADD CONSTRAINT `fk_payments_subscription`
        FOREIGN KEY (`subscription_id`) REFERENCES `membership_subscriptions`(`id`) ON DELETE SET NULL;

ALTER TABLE `payments`
    ADD CONSTRAINT `fk_payments_subject`
        FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE SET NULL;

-- ── subscription_members — junction with approval state ───────

CREATE TABLE IF NOT EXISTS `subscription_members` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `subscription_id` INT UNSIGNED NOT NULL,
    `member_id`       INT UNSIGNED NOT NULL,
    `role`            ENUM('primary','secondary') NOT NULL DEFAULT 'primary',
    `status`          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
    `requested_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `approved_by`     INT UNSIGNED NULL,
    `approved_at`     DATETIME     NULL,
    `notes`           TEXT         NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_subscription_members` (`subscription_id`, `member_id`),
    INDEX `idx_subscription_members_member` (`member_id`),
    CONSTRAINT `fk_subscription_members_subscription`
        FOREIGN KEY (`subscription_id`) REFERENCES `membership_subscriptions`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_subscription_members_member`
        FOREIGN KEY (`member_id`)       REFERENCES `members`(`id`)                  ON DELETE CASCADE,
    CONSTRAINT `fk_subscription_members_approver`
        FOREIGN KEY (`approved_by`)     REFERENCES `users`(`id`)                    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
