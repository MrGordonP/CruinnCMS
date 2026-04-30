-- ============================================================
-- Mailbox Module — Full Schema
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `mailbox_messages` (
    `id`              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `mailbox_id`      INT UNSIGNED     NOT NULL COMMENT 'organisation_officers.id',
    `folder`          VARCHAR(255)     NOT NULL COMMENT 'IMAP folder name e.g. INBOX, Sent',
    `imap_uid`        INT UNSIGNED     NOT NULL,
    `message_id`      VARCHAR(255)     NULL     COMMENT 'RFC 2822 Message-ID header',
    `in_reply_to`     VARCHAR(255)     NULL     COMMENT 'RFC 2822 In-Reply-To header',
    `thread_id`       BIGINT UNSIGNED  NULL     COMMENT 'FK mailbox_threads.id',
    `subject`         VARCHAR(500)     NULL,
    `from_address`    VARCHAR(255)     NULL,
    `from_name`       VARCHAR(255)     NULL,
    `to_address`      TEXT             NULL     COMMENT 'Raw To: header value',
    `cc_address`      TEXT             NULL,
    `sent_at`         DATETIME         NULL     COMMENT 'Date header parsed to UTC',
    `synced_at`       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `has_attachments` TINYINT(1)       NOT NULL DEFAULT 0,
    `imap_flags`      VARCHAR(255)     NULL     COMMENT 'Space-separated IMAP flags e.g. \\Seen \\Answered',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_message` (`mailbox_id`, `folder`(100), `imap_uid`),
    KEY `idx_thread`      (`thread_id`),
    KEY `idx_message_id`  (`message_id`(100)),
    KEY `idx_in_reply_to` (`in_reply_to`(100)),
    KEY `idx_sent_at`     (`mailbox_id`, `folder`(100), `sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mailbox_threads` (
    `id`              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `mailbox_id`      INT UNSIGNED     NOT NULL,
    `root_message_id` VARCHAR(255)     NULL     COMMENT 'message_id of the first message in thread',
    `subject`         VARCHAR(500)     NULL,
    `last_message_at` DATETIME         NULL,
    `message_count`   INT UNSIGNED     NOT NULL DEFAULT 1,
    `created_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_thread_mailbox` (`mailbox_id`, `last_message_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mailbox_reads` (
    `mailbox_id` INT UNSIGNED    NOT NULL,
    `folder`     VARCHAR(255)    NOT NULL,
    `imap_uid`   INT UNSIGNED    NOT NULL,
    `user_id`    INT UNSIGNED    NOT NULL,
    `read_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`mailbox_id`, `folder`(100), `imap_uid`, `user_id`),
    KEY `idx_reads_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mailbox_tags` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `label`      VARCHAR(100)  NOT NULL,
    `colour`     VARCHAR(7)    NOT NULL DEFAULT '#888888',
    `sort_order` INT UNSIGNED  NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tag_label` (`label`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mailbox_tag_map` (
    `tag_id`     INT UNSIGNED   NOT NULL,
    `mailbox_id` INT UNSIGNED   NOT NULL,
    `folder`     VARCHAR(255)   NOT NULL,
    `imap_uid`   INT UNSIGNED   NOT NULL,
    `tagged_by`  INT UNSIGNED   NULL COMMENT 'users.id',
    `tagged_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`tag_id`, `mailbox_id`, `folder`(100), `imap_uid`),
    CONSTRAINT `fk_tagmap_tag` FOREIGN KEY (`tag_id`) REFERENCES `mailbox_tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── IMAP/SMTP columns on organisation_officers ───────────────
ALTER TABLE `organisation_officers`
    ADD COLUMN IF NOT EXISTS `imap_host`       VARCHAR(255)                        NULL     COMMENT 'IMAP hostname'    AFTER `email`,
    ADD COLUMN IF NOT EXISTS `imap_port`       SMALLINT                            NOT NULL DEFAULT 993               AFTER `imap_host`,
    ADD COLUMN IF NOT EXISTS `imap_encryption` ENUM('ssl','tls','none')            NOT NULL DEFAULT 'ssl'             AFTER `imap_port`,
    ADD COLUMN IF NOT EXISTS `imap_user`       VARCHAR(255)                        NULL     COMMENT 'IMAP username'    AFTER `imap_encryption`,
    ADD COLUMN IF NOT EXISTS `imap_pass_enc`   TEXT                                NULL     COMMENT 'AES-256 encrypted IMAP password' AFTER `imap_user`,
    ADD COLUMN IF NOT EXISTS `smtp_host`       VARCHAR(255)                        NULL     COMMENT 'SMTP hostname'    AFTER `imap_pass_enc`,
    ADD COLUMN IF NOT EXISTS `smtp_port`       SMALLINT                            NOT NULL DEFAULT 587               AFTER `smtp_host`,
    ADD COLUMN IF NOT EXISTS `smtp_encryption` ENUM('tls','ssl','none')            NOT NULL DEFAULT 'tls'             AFTER `smtp_port`,
    ADD COLUMN IF NOT EXISTS `smtp_user`       VARCHAR(255)                        NULL                               AFTER `smtp_encryption`,
    ADD COLUMN IF NOT EXISTS `smtp_pass_enc`   TEXT                                NULL     COMMENT 'AES-256 encrypted SMTP password' AFTER `smtp_user`,
    ADD COLUMN IF NOT EXISTS `imap_last_uid`   JSON                                NULL     COMMENT 'JSON map of folder→last synced UID' AFTER `smtp_pass_enc`,
    ADD COLUMN IF NOT EXISTS `imap_enabled`    TINYINT(1)                          NOT NULL DEFAULT 0 COMMENT '1 = mailbox active in Cruinn' AFTER `imap_last_uid`;

-- ── Dedicated mailboxes store ─────────────────────────────────
CREATE TABLE IF NOT EXISTS `mailboxes` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `label`           VARCHAR(100)  NOT NULL                  COMMENT 'Human display name',
    `email`           VARCHAR(255)  NOT NULL DEFAULT '',
    `imap_host`       VARCHAR(255)  NULL,
    `imap_port`       SMALLINT      NOT NULL DEFAULT 993,
    `imap_encryption` ENUM('ssl','tls','none') NOT NULL DEFAULT 'ssl',
    `imap_user`       VARCHAR(255)  NULL,
    `imap_pass_enc`   TEXT          NULL,
    `smtp_host`       VARCHAR(255)  NULL,
    `smtp_port`       SMALLINT      NOT NULL DEFAULT 587,
    `smtp_encryption` ENUM('tls','ssl','none') NOT NULL DEFAULT 'tls',
    `smtp_user`       VARCHAR(255)  NULL,
    `smtp_pass_enc`   TEXT          NULL,
    `imap_last_uid`   JSON          NULL,
    `enabled`         TINYINT(1)    NOT NULL DEFAULT 0,
    `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Per-mailbox access control ────────────────────────────────
CREATE TABLE IF NOT EXISTS `mailbox_access` (
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `mailbox_id`          INT UNSIGNED NOT NULL,
    `user_id`             INT UNSIGNED NULL,
    `officer_position_id` INT UNSIGNED NULL,
    `granted_by`          INT UNSIGNED NULL,
    `granted_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_access_user`     (`mailbox_id`, `user_id`),
    UNIQUE KEY `uq_access_position` (`mailbox_id`, `officer_position_id`),
    KEY `idx_access_mailbox`        (`mailbox_id`),
    KEY `idx_access_user`           (`user_id`),
    KEY `idx_access_position`       (`officer_position_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
