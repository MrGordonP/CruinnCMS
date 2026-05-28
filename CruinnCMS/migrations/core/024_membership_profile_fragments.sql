-- ============================================================
-- Migration 024 - Membership fragments become module providers
--
-- Corrects earlier membership core-fragment keys by moving them into
-- membership module content providers.
-- ============================================================

UPDATE `pages`
SET `block_config` = JSON_OBJECT('source_type', 'module_content', 'provider_key', 'membership:member-dashboard-header')
WHERE `block_type` = 'dynamic-include'
  AND JSON_VALID(COALESCE(`block_config`, '{}'))
  AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(`block_config`, '{}'), '$.source_type')) = 'core_fragment'
  AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(`block_config`, '{}'), '$.core_fragment_key')) = 'member_dashboard_header';

UPDATE `pages`
SET `block_config` = JSON_OBJECT('source_type', 'module_content', 'provider_key', 'membership:member-details-form')
WHERE `block_type` = 'dynamic-include'
  AND JSON_VALID(COALESCE(`block_config`, '{}'))
  AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(`block_config`, '{}'), '$.source_type')) = 'core_fragment'
  AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(`block_config`, '{}'), '$.core_fragment_key')) = 'member_details_form';

UPDATE `pages`
SET `block_config` = JSON_OBJECT('source_type', 'module_content', 'provider_key', 'membership:member-notifications')
WHERE `block_type` = 'dynamic-include'
  AND JSON_VALID(COALESCE(`block_config`, '{}'))
  AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(`block_config`, '{}'), '$.source_type')) = 'core_fragment'
  AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(`block_config`, '{}'), '$.core_fragment_key')) = 'member_notifications';

UPDATE `pages`
SET `block_config` = JSON_OBJECT('source_type', 'module_content', 'provider_key', 'membership:member-upcoming-events')
WHERE `block_type` = 'dynamic-include'
  AND JSON_VALID(COALESCE(`block_config`, '{}'))
  AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(`block_config`, '{}'), '$.source_type')) = 'core_fragment'
  AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(`block_config`, '{}'), '$.core_fragment_key')) = 'member_upcoming_events';

UPDATE `pages`
SET `block_config` = JSON_OBJECT('source_type', 'module_content', 'provider_key', 'membership:member-membership-summary')
WHERE `block_type` = 'dynamic-include'
  AND JSON_VALID(COALESCE(`block_config`, '{}'))
  AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(`block_config`, '{}'), '$.source_type')) = 'core_fragment'
  AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(`block_config`, '{}'), '$.core_fragment_key')) = 'member_membership_summary';

UPDATE `pages`
SET `block_config` = JSON_OBJECT('source_type', 'module_content', 'provider_key', 'membership:member-admin-stats')
WHERE `block_type` = 'dynamic-include'
  AND JSON_VALID(COALESCE(`block_config`, '{}'))
  AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(`block_config`, '{}'), '$.source_type')) = 'core_fragment'
  AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(`block_config`, '{}'), '$.core_fragment_key')) = 'member_admin_stats';
