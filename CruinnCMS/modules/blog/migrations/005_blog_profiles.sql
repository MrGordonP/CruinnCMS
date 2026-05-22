CREATE TABLE IF NOT EXISTS `blog_profiles` (
    `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`                  VARCHAR(150) NOT NULL,
    `slug`                  VARCHAR(150) NOT NULL,
    `description`           TEXT DEFAULT NULL,
    `display_mode`          ENUM('list','post','both') NOT NULL DEFAULT 'both',
    `posts_per_page`        SMALLINT UNSIGNED NOT NULL DEFAULT 10,
    `show_return_to_list`   TINYINT(1) NOT NULL DEFAULT 1,
    `show_post_navigation`  TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_blog_profiles_slug` (`slug`),
    KEY `idx_blog_profiles_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
