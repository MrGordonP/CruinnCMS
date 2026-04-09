-- ── Migration 032: Email broadcast queue ───────────────────────────────────────
--
-- email_broadcasts  — admin-composed messages targeting a mailing list
-- email_queue       — per-recipient delivery rows; drained by cron
-- email_unsubscribes— global bounce/unsubscribe suppression list

CREATE TABLE IF NOT EXISTS `email_broadcasts` (
    `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `list_id`      INT UNSIGNED    NULL COMMENT 'NULL = ad-hoc, not tied to a list',
    `subject`      VARCHAR(255)    NOT NULL,
    `body_html`    MEDIUMTEXT      NOT NULL,
    `body_text`    TEXT            NOT NULL DEFAULT '',
    `status`       ENUM('draft','queued','sending','sent','failed')
                                   NOT NULL DEFAULT 'draft',
    `recipient_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `sent_count`   INT UNSIGNED    NOT NULL DEFAULT 0,
    `created_by`   INT UNSIGNED    NULL,
    `scheduled_at` DATETIME        NULL,
    `started_at`   DATETIME        NULL,
    `completed_at` DATETIME        NULL,
    `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_broadcast_list`       FOREIGN KEY (`list_id`)     REFERENCES `mailing_lists` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_broadcast_created_by` FOREIGN KEY (`created_by`)  REFERENCES `users` (`id`)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_queue` (
    `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `broadcast_id`     INT UNSIGNED    NOT NULL,
    `recipient_email`  VARCHAR(255)    NOT NULL,
    `recipient_name`   VARCHAR(100)    NOT NULL DEFAULT '',
    `unsubscribe_token` CHAR(64)       NULL COMMENT 'From mailing_list_subscriptions',
    `status`           ENUM('pending','sent','failed','skipped')
                                       NOT NULL DEFAULT 'pending',
    `attempts`         TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `last_error`       TEXT            NULL,
    `next_retry_at`    DATETIME        NULL,
    `processed_at`     DATETIME        NULL,
    PRIMARY KEY (`id`),
    KEY `idx_broadcast` (`broadcast_id`),
    KEY `idx_status_retry` (`status`, `next_retry_at`),
    KEY `idx_email` (`recipient_email`),
    CONSTRAINT `fk_queue_broadcast` FOREIGN KEY (`broadcast_id`)
        REFERENCES `email_broadcasts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
