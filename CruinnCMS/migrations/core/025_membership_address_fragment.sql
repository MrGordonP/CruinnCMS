-- ============================================================
-- Migration 025 - Split member address into its own module fragment
--
-- Keeps existing default profile compositions intact after the
-- member details provider is reduced to the members table only.
-- ============================================================

UPDATE `pages` p
JOIN `system_pages` sp ON sp.page_id = p.page_id AND sp.system_key = 'profile'
SET p.sort_order = p.sort_order + 1
WHERE p.block_type = 'dynamic-include'
  AND JSON_VALID(COALESCE(p.block_config, '{}'))
  AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(p.block_config, '{}'), '$.source_type')) = 'module_content'
  AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(p.block_config, '{}'), '$.provider_key')) IN (
      'membership:member-notifications',
      'membership:member-upcoming-events',
      'membership:member-membership-summary',
      'membership:member-admin-stats'
  )
  AND p.sort_order >= 2
  AND EXISTS (
      SELECT 1
      FROM `pages` p2
      WHERE p2.page_id = sp.page_id
        AND p2.block_type = 'dynamic-include'
        AND JSON_VALID(COALESCE(p2.block_config, '{}'))
        AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(p2.block_config, '{}'), '$.source_type')) = 'module_content'
        AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(p2.block_config, '{}'), '$.provider_key')) = 'membership:member-details-form'
  )
  AND NOT EXISTS (
      SELECT 1
      FROM `pages` p3
      WHERE p3.page_id = sp.page_id
        AND p3.block_type = 'dynamic-include'
        AND JSON_VALID(COALESCE(p3.block_config, '{}'))
        AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(p3.block_config, '{}'), '$.source_type')) = 'module_content'
        AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(p3.block_config, '{}'), '$.provider_key')) = 'membership:member-address-form'
  );

INSERT IGNORE INTO `pages` (`block_id`, `page_id`, `block_type`, `block_config`, `sort_order`)
SELECT 'sys-profile-member-address-01', sp.page_id, 'dynamic-include',
       JSON_OBJECT('source_type', 'module_content', 'provider_key', 'membership:member-address-form'),
       2
FROM `system_pages` sp
WHERE sp.system_key = 'profile'
  AND EXISTS (
      SELECT 1
      FROM `pages` p
      WHERE p.page_id = sp.page_id
        AND p.block_type = 'dynamic-include'
        AND JSON_VALID(COALESCE(p.block_config, '{}'))
        AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(p.block_config, '{}'), '$.source_type')) = 'module_content'
        AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(p.block_config, '{}'), '$.provider_key')) = 'membership:member-details-form'
  )
  AND NOT EXISTS (
      SELECT 1
      FROM `pages` p
      WHERE p.page_id = sp.page_id
        AND p.block_type = 'dynamic-include'
        AND JSON_VALID(COALESCE(p.block_config, '{}'))
        AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(p.block_config, '{}'), '$.source_type')) = 'module_content'
        AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(p.block_config, '{}'), '$.provider_key')) = 'membership:member-address-form'
  );
