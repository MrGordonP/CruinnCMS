-- CruinnCMS — Organisation Migration 003: Organisation profile
--
-- Single-row table holding core organisation identity information.
-- Safe to run repeatedly (CREATE TABLE IF NOT EXISTS; INSERT IGNORE).

CREATE TABLE IF NOT EXISTS `organisation_profile` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`            VARCHAR(255)    NOT NULL DEFAULT '',
    `short_name`      VARCHAR(50)     NULL     COMMENT 'Abbreviation / acronym',
    `tagline`         VARCHAR(255)    NULL,
    `founded_year`    YEAR            NULL,
    `registration_no` VARCHAR(100)    NULL     COMMENT 'Company / charity registration number',
    `address`         TEXT            NULL,
    `email`           VARCHAR(255)    NULL,
    `phone`           VARCHAR(50)     NULL,
    `website`         VARCHAR(255)    NULL,
    `bio`             TEXT            NULL     COMMENT 'Public-facing organisation description',
    `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed one empty row so the edit form always has a record to UPDATE
INSERT IGNORE INTO `organisation_profile` (`id`, `name`) VALUES (1, '');
