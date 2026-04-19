-- Add subscription_mode to mailing_lists
ALTER TABLE `mailing_lists`
    ADD COLUMN IF NOT EXISTS `subscription_mode`
        ENUM('open','request') NOT NULL DEFAULT 'open'
        AFTER `is_public`;

-- Add 'pending' status for request-based subscriptions
ALTER TABLE `mailing_list_subscriptions`
    MODIFY COLUMN `status`
        ENUM('active','unsubscribed','bounced','pending') NOT NULL DEFAULT 'active';
