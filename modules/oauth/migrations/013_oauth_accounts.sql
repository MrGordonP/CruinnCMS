-- ============================================================
-- IGA Portal — Migration 013: OAuth / Social Login Accounts
--
-- Links external OAuth provider identities to local user accounts.
-- A single user can have multiple OAuth providers linked.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `user_oauth_accounts` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED    NOT NULL,
    `provider`        VARCHAR(30)     NOT NULL COMMENT 'google, facebook, twitter',
    `provider_uid`    VARCHAR(255)    NOT NULL COMMENT 'Unique ID from the OAuth provider',
    `email`           VARCHAR(255)    NULL     COMMENT 'Email returned by provider (informational)',
    `display_name`    VARCHAR(255)    NULL     COMMENT 'Name returned by provider',
    `avatar_url`      VARCHAR(500)    NULL     COMMENT 'Profile picture URL from provider',
    `access_token`    TEXT            NULL     COMMENT 'Encrypted access token (if needed for API calls)',
    `refresh_token`   TEXT            NULL     COMMENT 'Encrypted refresh token',
    `token_expires`   DATETIME        NULL     COMMENT 'When the access token expires',
    `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_oauth_provider_uid` (`provider`, `provider_uid`),
    KEY `idx_oauth_user` (`user_id`),
    CONSTRAINT `fk_oauth_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Allow OAuth-only users who may not have a password set
ALTER TABLE `users` MODIFY `password_hash` VARCHAR(255) NULL;

SET FOREIGN_KEY_CHECKS = 1;
