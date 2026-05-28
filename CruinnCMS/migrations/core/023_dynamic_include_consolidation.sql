-- ============================================================
-- Migration 023 - Consolidate dynamic blocks into dynamic-include
--
-- Converts legacy dynamic block types to the single dynamic-include type.
-- Existing config is preserved and source_type/core_fragment_key are added.
-- ============================================================

-- php-include -> dynamic-include (php_include source)
UPDATE `pages`
SET `block_type` = 'dynamic-include',
    `block_config` = CASE
        WHEN `block_config` IS NULL OR TRIM(`block_config`) = '' THEN JSON_OBJECT('source_type', 'php_include')
        WHEN JSON_VALID(`block_config`) THEN JSON_SET(CAST(`block_config` AS JSON), '$.source_type', 'php_include')
        ELSE JSON_OBJECT('source_type', 'php_include')
    END
WHERE `block_type` = 'php-include';

-- module-widget -> dynamic-include (module_widget source)
UPDATE `pages`
SET `block_type` = 'dynamic-include',
    `block_config` = CASE
        WHEN `block_config` IS NULL OR TRIM(`block_config`) = '' THEN JSON_OBJECT('source_type', 'module_widget')
        WHEN JSON_VALID(`block_config`) THEN JSON_SET(CAST(`block_config` AS JSON), '$.source_type', 'module_widget')
        ELSE JSON_OBJECT('source_type', 'module_widget')
    END
WHERE `block_type` = 'module-widget';

-- module-content -> dynamic-include (module_content source)
UPDATE `pages`
SET `block_type` = 'dynamic-include',
    `block_config` = CASE
        WHEN `block_config` IS NULL OR TRIM(`block_config`) = '' THEN JSON_OBJECT('source_type', 'module_content')
        WHEN JSON_VALID(`block_config`) THEN JSON_SET(CAST(`block_config` AS JSON), '$.source_type', 'module_content')
        ELSE JSON_OBJECT('source_type', 'module_content')
    END
WHERE `block_type` = 'module-content';

-- account-details-form -> dynamic-include core fragment
UPDATE `pages`
SET `block_type` = 'dynamic-include',
    `block_config` = JSON_OBJECT('source_type', 'core_fragment', 'core_fragment_key', 'account_details_form')
WHERE `block_type` = 'account-details-form';

-- account-password-form -> dynamic-include core fragment
UPDATE `pages`
SET `block_type` = 'dynamic-include',
    `block_config` = JSON_OBJECT('source_type', 'core_fragment', 'core_fragment_key', 'account_password_form')
WHERE `block_type` = 'account-password-form';

-- account-information -> dynamic-include core fragment
UPDATE `pages`
SET `block_type` = 'dynamic-include',
    `block_config` = JSON_OBJECT('source_type', 'core_fragment', 'core_fragment_key', 'account_information')
WHERE `block_type` = 'account-information';
