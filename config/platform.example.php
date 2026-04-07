<?php
/**
 * CMS Platform Credentials — Example / Template
 *
 * Copy this file to config/platform.php and customise.
 * config/platform.php is gitignored (contains credentials).
 *
 * Generate a password hash:
 *   php -r "echo password_hash('your-password', PASSWORD_BCRYPT, ['cost' => 12]);"
 */

return [
    'username'      => 'platform',
    'password_hash' => '$2y$12$REPLACE_WITH_REAL_HASH',

    'multi_instance' => false,

    'platform_db' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'name'     => 'cms_platform',
        'user'     => 'cms_platform',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],
];
