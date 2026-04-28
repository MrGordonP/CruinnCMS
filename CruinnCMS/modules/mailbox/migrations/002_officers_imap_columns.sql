-- CruinnCMS — Mailbox Module Migration 002: IMAP/SMTP columns on organisation_officers
--
-- Adds mailbox credentials to the existing organisation_officers table.
-- Passwords are stored AES-256 encrypted at rest using the instance secret.
-- imap_last_uid tracks the highest UID synced per-folder (folder→uid map
-- serialised as JSON, e.g. {"INBOX":1042,"Sent":88}).
-- Safe to run repeatedly (ALTER TABLE with IF NOT EXISTS checks via
-- SHOW COLUMNS pattern — or simply run once via module manager).

ALTER TABLE `organisation_officers`
    ADD COLUMN IF NOT EXISTS `imap_host`       VARCHAR(255)  NULL     COMMENT 'IMAP hostname e.g. mail.geology.ie' AFTER `email`,
    ADD COLUMN IF NOT EXISTS `imap_port`       SMALLINT      NOT NULL DEFAULT 993 AFTER `imap_host`,
    ADD COLUMN IF NOT EXISTS `imap_encryption` ENUM('ssl','tls','none') NOT NULL DEFAULT 'ssl' AFTER `imap_port`,
    ADD COLUMN IF NOT EXISTS `imap_user`       VARCHAR(255)  NULL     COMMENT 'IMAP username (usually the email address)' AFTER `imap_encryption`,
    ADD COLUMN IF NOT EXISTS `imap_pass_enc`   TEXT          NULL     COMMENT 'AES-256 encrypted IMAP password' AFTER `imap_user`,
    ADD COLUMN IF NOT EXISTS `smtp_host`       VARCHAR(255)  NULL     COMMENT 'SMTP hostname' AFTER `imap_pass_enc`,
    ADD COLUMN IF NOT EXISTS `smtp_port`       SMALLINT      NOT NULL DEFAULT 587 AFTER `smtp_host`,
    ADD COLUMN IF NOT EXISTS `smtp_encryption` ENUM('tls','ssl','none') NOT NULL DEFAULT 'tls' AFTER `smtp_port`,
    ADD COLUMN IF NOT EXISTS `smtp_user`       VARCHAR(255)  NULL     AFTER `smtp_encryption`,
    ADD COLUMN IF NOT EXISTS `smtp_pass_enc`   TEXT          NULL     COMMENT 'AES-256 encrypted SMTP password' AFTER `smtp_user`,
    ADD COLUMN IF NOT EXISTS `imap_last_uid`   JSON          NULL     COMMENT 'JSON map of folder→last synced UID' AFTER `smtp_pass_enc`,
    ADD COLUMN IF NOT EXISTS `imap_enabled`    TINYINT(1)    NOT NULL DEFAULT 0 COMMENT '1 = mailbox active in Cruinn' AFTER `imap_last_uid`;
