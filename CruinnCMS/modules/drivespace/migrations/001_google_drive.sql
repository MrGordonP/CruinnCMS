-- ============================================================
-- Drivespace Module — Google Drive integration settings
-- ============================================================

INSERT IGNORE INTO `settings` (`key`, `value`, `group`) VALUES
    ('gdrive.service_account_json', NULL,      'drivespace'),
    ('gdrive.root_folder_id',       NULL,      'drivespace'),
    ('gdrive.shared_drive_id',      NULL,      'drivespace'),
    ('gdrive.access_token',         NULL,      'drivespace'),
    ('gdrive.token_expires_at',     '0',       'drivespace'),
    ('gdrive.write_role',           'council', 'drivespace');
