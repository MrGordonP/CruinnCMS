-- CruinnCMS — Organisation Migration 004: Officers / committee positions
--
-- Named positions within the organisation with optional user link.
-- Safe to run repeatedly (CREATE TABLE IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS `organisation_officers` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `position`    VARCHAR(150)    NOT NULL COMMENT 'e.g. President, Secretary, Treasurer',
    `user_id`     INT UNSIGNED    NULL     COMMENT 'Linked user account (optional)',
    `name`        VARCHAR(150)    NULL     COMMENT 'Free-text name if no user account',
    `email`       VARCHAR(255)    NULL,
    `bio`         TEXT            NULL,
    `sort_order`  INT UNSIGNED    NOT NULL DEFAULT 0,
    `active`      TINYINT(1)      NOT NULL DEFAULT 1,
    `term_start`  DATE            NULL,
    `term_end`    DATE            NULL,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_officers_sort` (`sort_order`, `position`),
    CONSTRAINT `fk_officers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
