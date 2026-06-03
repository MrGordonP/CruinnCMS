-- Migration 030: Block type config table
-- Adds per-instance block type activation tracking.
-- Mirrors the module_config model: discovered (on disk, not yet activated),
-- active (installed for this instance), offline (disabled).

CREATE TABLE IF NOT EXISTS `block_type_config` (
    `slug`       VARCHAR(64)  NOT NULL,
    `status`     ENUM('discovered','active','offline') NOT NULL DEFAULT 'discovered',
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
