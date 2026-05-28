-- ============================================================
-- Migration 022 — Profile System Page Core Blocks
--
-- Moves the default profile system page from a single legacy
-- php-include block (public/profile.php) to dedicated core/system
-- account blocks that users can compose in the editor.
--
-- Existing customisations are preserved:
-- - We only rewrite the legacy seeded block id (sys-profile-01)
--   when it is still the original php-include profile block.
-- - New account blocks are added with INSERT IGNORE.
-- - If a legacy sys-profile-01 block still exists, we do not also
--   insert sys-profile-details-01, avoiding duplicate details forms.
-- ============================================================

-- Convert legacy seeded profile include into account-details-form
UPDATE `pages` p
JOIN `system_pages` sp ON sp.page_id = p.page_id AND sp.system_key = 'profile'
SET p.block_type = 'account-details-form',
    p.block_config = NULL,
    p.sort_order = 0
WHERE p.block_id = 'sys-profile-01'
  AND p.block_type = 'php-include'
  AND p.block_config LIKE '%"public/profile.php"%';

-- Ensure default profile account block set exists
INSERT IGNORE INTO `pages` (`block_id`, `page_id`, `block_type`, `block_config`, `sort_order`)
SELECT 'sys-profile-details-01', sp.page_id, 'account-details-form', NULL, 0
FROM `system_pages` sp
WHERE sp.system_key = 'profile'
  AND NOT EXISTS (
      SELECT 1
      FROM `pages` p0
      WHERE p0.page_id = sp.page_id
        AND p0.block_id = 'sys-profile-01'
  );

INSERT IGNORE INTO `pages` (`block_id`, `page_id`, `block_type`, `block_config`, `sort_order`)
SELECT 'sys-profile-password-01', sp.page_id, 'account-password-form', NULL, 1
FROM `system_pages` sp
WHERE sp.system_key = 'profile';

INSERT IGNORE INTO `pages` (`block_id`, `page_id`, `block_type`, `block_config`, `sort_order`)
SELECT 'sys-profile-info-01', sp.page_id, 'account-information', NULL, 2
FROM `system_pages` sp
WHERE sp.system_key = 'profile';
