-- CruinnCMS — Mailbox Module Migration 003: Dedicated mailboxes table
--
-- Decouples email account credentials from organisation_officers.
-- Each email account becomes a standalone row in `mailboxes`.
-- Access is controlled by `mailbox_access` — either a direct user grant
-- or a position-based grant (resolves to whoever currently holds the position).
--
-- Data migration: existing officer IMAP credentials are copied into `mailboxes`
-- using the same primary key so that existing mailbox_messages rows stay valid.
-- IMAP columns on organisation_officers are left in place (ignored by new code).
-- Safe to run repeatedly (CREATE IF NOT EXISTS + INSERT guarded by NOT EXISTS).

-- ── 1. Mailboxes credential store ────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `mailboxes` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `label`           VARCHAR(100)  NOT NULL                  COMMENT 'Human display name e.g. "President"',
    `email`           VARCHAR(255)  NOT NULL DEFAULT '',
    `imap_host`       VARCHAR(255)  NULL,
    `imap_port`       SMALLINT      NOT NULL DEFAULT 993,
    `imap_encryption` ENUM('ssl','tls','none') NOT NULL DEFAULT 'ssl',
    `imap_user`       VARCHAR(255)  NULL,
    `imap_pass_enc`   TEXT          NULL                      COMMENT 'AES-256-CBC encrypted password',
    `smtp_host`       VARCHAR(255)  NULL,
    `smtp_port`       SMALLINT      NOT NULL DEFAULT 587,
    `smtp_encryption` ENUM('tls','ssl','none') NOT NULL DEFAULT 'tls',
    `smtp_user`       VARCHAR(255)  NULL,
    `smtp_pass_enc`   TEXT          NULL                      COMMENT 'AES-256-CBC encrypted password',
    `imap_last_uid`   JSON          NULL                      COMMENT 'JSON map folder→last synced UID',
    `enabled`         TINYINT(1)    NOT NULL DEFAULT 0,
    `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. Migrate existing officer credentials into mailboxes ────────────────────
-- Only runs when mailboxes is empty (safe to re-run once populated).

INSERT INTO `mailboxes`
    (`id`, `label`, `email`,
     `imap_host`, `imap_port`, `imap_encryption`, `imap_user`, `imap_pass_enc`,
     `smtp_host`, `smtp_port`, `smtp_encryption`, `smtp_user`, `smtp_pass_enc`,
     `imap_last_uid`, `enabled`, `created_at`)
SELECT
    o.`id`,
    o.`position`,
    COALESCE(o.`email`, ''),
    o.`imap_host`,
    o.`imap_port`,
    o.`imap_encryption`,
    o.`imap_user`,
    o.`imap_pass_enc`,
    o.`smtp_host`,
    o.`smtp_port`,
    o.`smtp_encryption`,
    o.`smtp_user`,
    o.`smtp_pass_enc`,
    o.`imap_last_uid`,
    o.`imap_enabled`,
    NOW()
FROM `organisation_officers` o
WHERE o.`imap_host` IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `mailboxes` LIMIT 1);

-- ── 3. Access control table ───────────────────────────────────────────────────
-- One row = one grant. Either user_id OR officer_position_id is set, not both.
-- A position-based grant gives access to whoever currently holds that position.

CREATE TABLE IF NOT EXISTS `mailbox_access` (
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `mailbox_id`          INT UNSIGNED NOT NULL,
    `user_id`             INT UNSIGNED NULL     COMMENT 'Direct grant to a specific user',
    `officer_position_id` INT UNSIGNED NULL     COMMENT 'Grant via officer position (resolves to current holder)',
    `granted_by`          INT UNSIGNED NULL     COMMENT 'users.id of admin who created this grant',
    `granted_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_access_user`     (`mailbox_id`, `user_id`),
    UNIQUE KEY `uq_access_position` (`mailbox_id`, `officer_position_id`),
    KEY `idx_access_mailbox`        (`mailbox_id`),
    KEY `idx_access_user`           (`user_id`),
    KEY `idx_access_position`       (`officer_position_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. Migrate officer → access links ────────────────────────────────────────
-- For every migrated mailbox, grant access to the officer position that owned it.
-- Only runs when mailbox_access is empty.

INSERT IGNORE INTO `mailbox_access` (`mailbox_id`, `officer_position_id`, `granted_at`)
SELECT `id`, `id`, NOW()
FROM `organisation_officers`
WHERE `imap_host` IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `mailbox_access` LIMIT 1);
