-- CruinnCMS — Organisation Migration 005: Meetings
--
-- Scheduled and past meetings with agenda and minutes document links.
-- Safe to run repeatedly (CREATE TABLE IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS `organisation_meetings` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `title`         VARCHAR(255)    NOT NULL,
    `meeting_type`  ENUM('agm','egm','committee','working_group','other') NOT NULL DEFAULT 'committee',
    `meeting_date`  DATE            NOT NULL,
    `start_time`    TIME            NULL,
    `location`      VARCHAR(255)    NULL,
    `description`   TEXT            NULL,
    `agenda_doc_id` INT UNSIGNED    NULL COMMENT 'FK to documents.id for agenda',
    `minutes_doc_id` INT UNSIGNED   NULL COMMENT 'FK to documents.id for approved minutes',
    `status`        ENUM('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
    `created_by`    INT UNSIGNED    NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_meetings_date` (`meeting_date`, `status`),
    CONSTRAINT `fk_meetings_created_by`   FOREIGN KEY (`created_by`)     REFERENCES `users`     (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_meetings_agenda_doc`   FOREIGN KEY (`agenda_doc_id`)  REFERENCES `documents` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_meetings_minutes_doc`  FOREIGN KEY (`minutes_doc_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
