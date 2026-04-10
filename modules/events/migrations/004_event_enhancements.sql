-- ══════════════════════════════════════════════════════════════════
-- Migration 004 — Event Enhancements (Phase 3)
-- ══════════════════════════════════════════════════════════════════
-- Adds cancellation tracking, confirmation tokens, cancelled_at
-- to event_registrations. Adds registration_open flag to events.

-- Add cancellation fields to event_registrations
ALTER TABLE `event_registrations`
    ADD COLUMN `status` ENUM('confirmed', 'cancelled', 'waitlisted') NOT NULL DEFAULT 'confirmed' AFTER `amount_paid`,
    ADD COLUMN `confirmation_token` VARCHAR(64) NULL AFTER `status`,
    ADD COLUMN `cancelled_at` DATETIME NULL AFTER `confirmation_token`,
    ADD COLUMN `cancel_reason` VARCHAR(255) NULL AFTER `cancelled_at`,
    ADD KEY `idx_registrations_status` (`status`),
    ADD KEY `idx_registrations_token` (`confirmation_token`);

-- Add registration_open boolean to events (defaults to 1 for published events)
ALTER TABLE `events`
    ADD COLUMN `registration_open` TINYINT(1) NOT NULL DEFAULT 1 AFTER `reg_deadline`;
