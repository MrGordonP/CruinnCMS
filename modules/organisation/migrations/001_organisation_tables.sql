-- CruinnCMS — Organisation Module Tables
-- Discussion threads and inbox. Document tables live in the documents module.
-- Safe to run on existing installs (CREATE TABLE IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS `discussions` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(255)    NOT NULL,
    `category`    VARCHAR(50)     NULL,
    `created_by`  INT UNSIGNED    NULL,
    `pinned`      TINYINT(1)      NOT NULL DEFAULT 0,
    `locked`      TINYINT(1)      NOT NULL DEFAULT 0,
    `post_count`  INT UNSIGNED    NOT NULL DEFAULT 0,
    `last_post_at` DATETIME       NULL,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_discussions_pinned` (`pinned`, `last_post_at`),
    CONSTRAINT `fk_discussions_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `discussion_posts` (
    `id`             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `discussion_id`  INT UNSIGNED    NOT NULL,
    `author_id`      INT UNSIGNED    NULL,
    `body`           TEXT            NOT NULL,
    `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME        NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_posts_discussion` (`discussion_id`, `created_at`),
    CONSTRAINT `fk_posts_discussion` FOREIGN KEY (`discussion_id`) REFERENCES `discussions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_posts_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
