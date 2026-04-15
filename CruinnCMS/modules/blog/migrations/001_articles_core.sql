-- ============================================================
-- Blog/Articles Module — Core Schema
-- ============================================================
-- Creates: articles
-- Uses cruinn_blocks for article body content (via parent_type/parent_id)
-- ============================================================

CREATE TABLE IF NOT EXISTS `articles` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`          VARCHAR(255) NOT NULL,
    `slug`           VARCHAR(255) NOT NULL,
    `excerpt`        TEXT NULL,
    `featured_image` VARCHAR(500) NULL,
    `author_id`      INT UNSIGNED NULL,
    `status`         ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    `published_at`   DATETIME NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_articles_slug` (`slug`),
    INDEX `idx_articles_status` (`status`),
    INDEX `idx_articles_published` (`published_at`),
    CONSTRAINT `fk_articles_author` FOREIGN KEY (`author_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Articles use cruinn_blocks with parent_type='article' and parent_id=article.id
-- No separate content_blocks table needed — blocks are stored in cruinn_blocks
