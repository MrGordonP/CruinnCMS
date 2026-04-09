-- ============================================================
-- IGA Portal — Migration 014: GDPR Consent & Data Management
--
-- Tracks user consent records and data request history.
-- All GDPR features are toggleable via config('gdpr.enabled').
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── Consent Records ──────────────────────────────────────────
-- Stores an immutable audit log of consent given/withdrawn.
-- Each row is a point-in-time record — never updated, only inserted.

CREATE TABLE IF NOT EXISTS `gdpr_consents` (
    `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `user_id`      INT UNSIGNED    NULL     COMMENT 'Null for anonymous cookie consent',
    `consent_type` VARCHAR(50)     NOT NULL COMMENT 'cookies, privacy_policy, data_processing, marketing',
    `granted`      TINYINT(1)      NOT NULL COMMENT '1 = consent given, 0 = withdrawn',
    `ip_address`   VARCHAR(45)     NULL     COMMENT 'IP at time of consent (for audit)',
    `user_agent`   VARCHAR(500)    NULL     COMMENT 'Browser UA at time of consent',
    `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_consent_user` (`user_id`),
    KEY `idx_consent_type` (`consent_type`, `created_at`),
    CONSTRAINT `fk_consent_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Data Requests (SAR / Deletion) ───────────────────────────
-- Tracks Subject Access Requests and Right-to-Erasure requests.

CREATE TABLE IF NOT EXISTS `gdpr_data_requests` (
    `id`             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `user_id`        INT UNSIGNED    NOT NULL,
    `request_type`   ENUM('export', 'deletion') NOT NULL,
    `status`         ENUM('pending', 'processing', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    `requested_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `processed_at`   DATETIME        NULL,
    `processed_by`   INT UNSIGNED    NULL     COMMENT 'Admin who processed the request',
    `notes`          TEXT            NULL,
    PRIMARY KEY (`id`),
    KEY `idx_request_user` (`user_id`),
    KEY `idx_request_status` (`status`),
    CONSTRAINT `fk_request_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_request_processor` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
