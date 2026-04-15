-- ============================================================
-- Membership Module Core Schema
-- ============================================================
-- Generic, organisation-agnostic membership model.
-- No seed data.
-- ============================================================

CREATE TABLE IF NOT EXISTS `membership_plans` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug`           VARCHAR(80)  NOT NULL,
    `name`           VARCHAR(160) NOT NULL,
    `description`    TEXT NULL,
    `billing_period` ENUM('annual','monthly','quarterly','lifetime','custom') NOT NULL DEFAULT 'annual',
    `price`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `currency`       CHAR(3) NOT NULL DEFAULT 'EUR',
    `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_membership_plans_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `members` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`           INT UNSIGNED NULL,
    `membership_number` VARCHAR(60) NULL,
    `forenames`         VARCHAR(120) NOT NULL,
    `surnames`          VARCHAR(120) NOT NULL,
    `email`             VARCHAR(255) NOT NULL,
    `phone`             VARCHAR(50) NULL,
    `organisation`      VARCHAR(180) NULL,
    `status`            ENUM('applicant','active','lapsed','suspended','resigned','archived') NOT NULL DEFAULT 'applicant',
    `plan_id`           INT UNSIGNED NULL,
    `joined_at`         DATETIME NULL,
    `lapsed_at`         DATETIME NULL,
    `notes`             TEXT NULL,
    `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_members_membership_number` (`membership_number`),
    INDEX `idx_members_email` (`email`),
    INDEX `idx_members_status` (`status`),
    INDEX `idx_members_user` (`user_id`),
    CONSTRAINT `fk_members_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_members_plan` FOREIGN KEY (`plan_id`) REFERENCES `membership_plans`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `member_addresses` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `member_id`   INT UNSIGNED NOT NULL,
    `line_1`      VARCHAR(200) NULL,
    `line_2`      VARCHAR(200) NULL,
    `city`        VARCHAR(120) NULL,
    `county`      VARCHAR(120) NULL,
    `postcode`    VARCHAR(40) NULL,
    `country`     VARCHAR(120) NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_member_addresses_member` (`member_id`),
    CONSTRAINT `fk_member_addresses_member` FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `membership_subscriptions` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `member_id`        INT UNSIGNED NOT NULL,
    `plan_id`          INT UNSIGNED NULL,
    `period_label`     VARCHAR(40) NOT NULL,
    `period_start`     DATE NOT NULL,
    `period_end`       DATE NOT NULL,
    `amount`           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `currency`         CHAR(3) NOT NULL DEFAULT 'EUR',
    `status`           ENUM('pending','paid','overdue','waived','refunded','cancelled') NOT NULL DEFAULT 'pending',
    `due_date`         DATE NULL,
    `paid_at`          DATETIME NULL,
    `payment_reference` VARCHAR(120) NULL,
    `notes`            TEXT NULL,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_membership_subscriptions_member` (`member_id`),
    INDEX `idx_membership_subscriptions_status` (`status`),
    INDEX `idx_membership_subscriptions_period` (`period_start`, `period_end`),
    CONSTRAINT `fk_membership_subscriptions_member` FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_membership_subscriptions_plan` FOREIGN KEY (`plan_id`) REFERENCES `membership_plans`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `membership_payments` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `subscription_id`  INT UNSIGNED NULL,
    `member_id`        INT UNSIGNED NOT NULL,
    `amount`           DECIMAL(10,2) NOT NULL,
    `currency`         CHAR(3) NOT NULL DEFAULT 'EUR',
    `method`           VARCHAR(40) NULL,
    `reference`        VARCHAR(120) NULL,
    `status`           ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'completed',
    `paid_at`          DATETIME NOT NULL,
    `notes`            TEXT NULL,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_membership_payments_member` (`member_id`),
    INDEX `idx_membership_payments_subscription` (`subscription_id`),
    CONSTRAINT `fk_membership_payments_member` FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_membership_payments_subscription` FOREIGN KEY (`subscription_id`) REFERENCES `membership_subscriptions`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
