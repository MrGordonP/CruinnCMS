-- CruinnCMS — Mailbox Module Migration 001: Core tables
--
-- Stores the message index (headers only — bodies fetched live from IMAP),
-- thread graph, tags, tag assignments, and per-user read receipts.
-- Message bodies remain on the IMAP server as the permanent archive.
-- Safe to run repeatedly (CREATE TABLE IF NOT EXISTS).

-- ---------------------------------------------------------------------------
-- mailbox_messages
-- Header index synced from IMAP. One row per message per mailbox.
-- imap_uid is the IMAP UID — stable within a folder, resets if folder
-- is expunged. (mailbox_id, folder, imap_uid) is the natural unique key.
-- ---------------------------------------------------------------------------
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
    KEY `idx_thread` (`thread_id`),
    KEY `idx_message_id` (`message_id`(100)),
    KEY `idx_in_reply_to` (`in_reply_to`(100)),
    KEY `idx_sent_at` (`mailbox_id`, `folder`(100), `sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- mailbox_threads
-- Thread root record. thread_id on mailbox_messages points here.
-- Built from In-Reply-To / References chain; never from subject matching.
-- ---------------------------------------------------------------------------
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

-- ---------------------------------------------------------------------------
-- mailbox_reads
-- Per-user read receipts. One row = this user has opened this message.
-- Three-state derived:
--   0 rows for (mailbox_id, imap_uid)           → unread
--   Rows exist but < all current position holders → partially read
--   Rows for all current position holders         → fully read
-- Pruned on message delete.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mailbox_reads` (
    `mailbox_id`  INT UNSIGNED    NOT NULL,
    `folder`      VARCHAR(255)    NOT NULL,
    `imap_uid`    INT UNSIGNED    NOT NULL,
    `user_id`     INT UNSIGNED    NOT NULL,
    `read_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`mailbox_id`, `folder`(100), `imap_uid`, `user_id`),
    KEY `idx_reads_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- mailbox_tags
-- Instance-level tag definitions. Colour stored as hex e.g. #e74c3c.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mailbox_tags` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `label`      VARCHAR(100)  NOT NULL,
    `colour`     VARCHAR(7)    NOT NULL DEFAULT '#888888',
    `sort_order` INT UNSIGNED  NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tag_label` (`label`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- mailbox_tag_map
-- Many-to-many: messages ↔ tags.
-- Keyed by (mailbox_id, folder, imap_uid) — same natural key as mailbox_messages.
-- Does not FK to mailbox_messages so tags survive a re-sync gap.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mailbox_tag_map` (
    `tag_id`       INT UNSIGNED   NOT NULL,
    `mailbox_id`   INT UNSIGNED   NOT NULL,
    `folder`       VARCHAR(255)   NOT NULL,
    `imap_uid`     INT UNSIGNED   NOT NULL,
    `tagged_by`    INT UNSIGNED   NULL     COMMENT 'users.id',
    `tagged_at`    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`tag_id`, `mailbox_id`, `folder`(100), `imap_uid`),
    CONSTRAINT `fk_tagmap_tag` FOREIGN KEY (`tag_id`) REFERENCES `mailbox_tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
