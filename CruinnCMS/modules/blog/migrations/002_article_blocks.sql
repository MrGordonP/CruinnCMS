-- ============================================================
-- Blog/Articles Module — Article Blocks + Subject
-- ============================================================
-- Creates: article_blocks
-- Adds:    subject_id to articles
-- ============================================================

-- Block storage for individual articles.
-- Mirrors cruinn_blocks but keyed to article_id rather than page_id,
-- keeping article content isolated from the page block pool.
CREATE TABLE IF NOT EXISTS `article_blocks` (
    `block_id`        VARCHAR(20)       NOT NULL,
    `article_id`      INT UNSIGNED      NOT NULL,
    `block_type`      VARCHAR(40)       NOT NULL,
    `inner_html`      MEDIUMTEXT        NULL,
    `css_props`       JSON              NULL,
    `block_config`    JSON              NULL,
    `sort_order`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `parent_block_id` VARCHAR(20)       NULL,
    `created_at`      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME          NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`block_id`),
    KEY `idx_article` (`article_id`, `parent_block_id`, `sort_order`),
    CONSTRAINT `fk_ab_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Subject taxonomy for categorising articles.
ALTER TABLE `articles`
    ADD COLUMN IF NOT EXISTS `subject_id` INT UNSIGNED NULL DEFAULT NULL AFTER `id`,
    ADD KEY IF NOT EXISTS `idx_articles_subject` (`subject_id`);
