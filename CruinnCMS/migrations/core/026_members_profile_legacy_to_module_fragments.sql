-- ============================================================
-- Migration 026 - Convert legacy members/profile include to
-- module-content membership fragments
--
-- This catches instances still using the legacy include block
-- (public/members/profile.php) so newer module fragments are visible.
-- ============================================================

-- Convert legacy include block on profile system page to membership header
UPDATE `pages` p
JOIN `system_pages` sp ON sp.page_id = p.page_id AND sp.system_key = 'profile'
SET p.block_type = 'dynamic-include',
    p.block_config = JSON_OBJECT('source_type', 'module_content', 'provider_key', 'membership:member-dashboard-header'),
    p.sort_order = 0
WHERE (
        p.block_type = 'dynamic-include'
        AND JSON_VALID(COALESCE(p.block_config, '{}'))
        AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(p.block_config, '{}'), '$.source_type')) = 'php_include'
        AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(p.block_config, '{}'), '$.template')) = 'public/members/profile.php'
    )
   OR (
        p.block_type = 'php-include'
        AND p.block_config LIKE '%"public/members/profile.php"%'
    );

-- Ensure the default membership fragment stack exists after conversion
INSERT IGNORE INTO `pages` (`block_id`, `page_id`, `block_type`, `block_config`, `sort_order`)
SELECT 'sys-profile-member-details-01', sp.page_id, 'dynamic-include',
       JSON_OBJECT('source_type', 'module_content', 'provider_key', 'membership:member-details-form'),
       1
FROM `system_pages` sp
WHERE sp.system_key = 'profile'
  AND EXISTS (
      SELECT 1
      FROM `pages` p
      WHERE p.page_id = sp.page_id
        AND p.block_type = 'dynamic-include'
        AND JSON_VALID(COALESCE(p.block_config, '{}'))
        AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(p.block_config, '{}'), '$.source_type')) = 'module_content'
        AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(p.block_config, '{}'), '$.provider_key')) = 'membership:member-dashboard-header'
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
        AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(p.block_config, '{}'), '$.provider_key')) = 'membership:member-dashboard-header'
  );

INSERT IGNORE INTO `pages` (`block_id`, `page_id`, `block_type`, `block_config`, `sort_order`)
SELECT 'sys-profile-member-notifications-01', sp.page_id, 'dynamic-include',
       JSON_OBJECT('source_type', 'module_content', 'provider_key', 'membership:member-notifications'),
       3
FROM `system_pages` sp
WHERE sp.system_key = 'profile'
  AND EXISTS (
      SELECT 1
      FROM `pages` p
      WHERE p.page_id = sp.page_id
        AND p.block_type = 'dynamic-include'
        AND JSON_VALID(COALESCE(p.block_config, '{}'))
        AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(p.block_config, '{}'), '$.source_type')) = 'module_content'
        AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(p.block_config, '{}'), '$.provider_key')) = 'membership:member-dashboard-header'
  );

INSERT IGNORE INTO `pages` (`block_id`, `page_id`, `block_type`, `block_config`, `sort_order`)
SELECT 'sys-profile-member-upcoming-events-01', sp.page_id, 'dynamic-include',
       JSON_OBJECT('source_type', 'module_content', 'provider_key', 'membership:member-upcoming-events'),
       4
FROM `system_pages` sp
WHERE sp.system_key = 'profile'
  AND EXISTS (
      SELECT 1
      FROM `pages` p
      WHERE p.page_id = sp.page_id
        AND p.block_type = 'dynamic-include'
        AND JSON_VALID(COALESCE(p.block_config, '{}'))
        AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(p.block_config, '{}'), '$.source_type')) = 'module_content'
        AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(p.block_config, '{}'), '$.provider_key')) = 'membership:member-dashboard-header'
  );

INSERT IGNORE INTO `pages` (`block_id`, `page_id`, `block_type`, `block_config`, `sort_order`)
SELECT 'sys-profile-member-membership-summary-01', sp.page_id, 'dynamic-include',
       JSON_OBJECT('source_type', 'module_content', 'provider_key', 'membership:member-membership-summary'),
       5
FROM `system_pages` sp
WHERE sp.system_key = 'profile'
  AND EXISTS (
      SELECT 1
      FROM `pages` p
      WHERE p.page_id = sp.page_id
        AND p.block_type = 'dynamic-include'
        AND JSON_VALID(COALESCE(p.block_config, '{}'))
        AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(p.block_config, '{}'), '$.source_type')) = 'module_content'
        AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(p.block_config, '{}'), '$.provider_key')) = 'membership:member-dashboard-header'
  );

INSERT IGNORE INTO `pages` (`block_id`, `page_id`, `block_type`, `block_config`, `sort_order`)
SELECT 'sys-profile-member-admin-stats-01', sp.page_id, 'dynamic-include',
       JSON_OBJECT('source_type', 'module_content', 'provider_key', 'membership:member-admin-stats'),
       6
FROM `system_pages` sp
WHERE sp.system_key = 'profile'
  AND EXISTS (
      SELECT 1
      FROM `pages` p
      WHERE p.page_id = sp.page_id
        AND p.block_type = 'dynamic-include'
        AND JSON_VALID(COALESCE(p.block_config, '{}'))
        AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(p.block_config, '{}'), '$.source_type')) = 'module_content'
        AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(p.block_config, '{}'), '$.provider_key')) = 'membership:member-dashboard-header'
  );
