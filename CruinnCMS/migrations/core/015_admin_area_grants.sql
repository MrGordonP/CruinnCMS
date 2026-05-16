-- Migration 015: Add admin_area_grants table for sub-admin access control
-- Stage 2 of Role & Capability Refactor (v1.0.0-beta.9)
--
-- Changes:
-- - Add admin_area_grants table
-- - Allow non-admin roles and org positions to access specific admin sections
--
-- Context:
-- Enables granular access control for admin areas (blog, forum, mailout, etc.)
-- without granting full admin privileges. Admin role (level >= 100) always has
-- access to all areas. Non-admin roles/positions require explicit grants.

CREATE TABLE IF NOT EXISTS `admin_area_grants` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `area_slug`    VARCHAR(60)                         NOT NULL COMMENT 'Admin area identifier (blog, forum, mailout, etc.)',
    `context_type` ENUM('role','position')             NOT NULL COMMENT 'Grant to role or org position',
    `context_id`   INT UNSIGNED                        NOT NULL COMMENT 'Role ID or position ID',
    `granted_at`   DATETIME DEFAULT CURRENT_TIMESTAMP  NOT NULL,
    `granted_by`   INT UNSIGNED                        DEFAULT NULL COMMENT 'User who granted access',
    UNIQUE KEY `uq_grant` (`area_slug`, `context_type`, `context_id`),
    INDEX `idx_grants_context` (`context_type`, `context_id`),
    FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
