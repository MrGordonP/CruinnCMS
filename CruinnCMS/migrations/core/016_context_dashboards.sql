-- Migration 016: Add context_dashboards table for widget dashboard canvases
-- Stage 3 of Role & Capability Refactor (v1.0.0-beta.9)
--
-- Changes:
-- - Add context_dashboards table
-- - Enable role/position/user-specific dashboard assignment
--
-- Context:
-- Widget dashboards are pages_index entries with canvas_type='widget-dashboard'
-- built in the block editor using module-widget blocks and layout blocks.
-- This table maps contexts (role/position/user) to their dashboard pages.
-- Resolution order: user → position → role → default admin role dashboard.

CREATE TABLE IF NOT EXISTS `context_dashboards` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `context_type` ENUM('role','position','user')      NOT NULL COMMENT 'Dashboard assigned to role, position, or user',
    `context_id`   INT UNSIGNED                        NOT NULL COMMENT 'Role ID, position ID, or user ID',
    `page_id`      INT UNSIGNED                        NOT NULL COMMENT 'Dashboard canvas page (canvas_type=widget-dashboard)',
    `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP  NOT NULL,
    `created_by`   INT UNSIGNED                        DEFAULT NULL COMMENT 'User who assigned dashboard',
    UNIQUE KEY `uq_context` (`context_type`, `context_id`),
    INDEX `idx_dashboard_page` (`page_id`),
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
