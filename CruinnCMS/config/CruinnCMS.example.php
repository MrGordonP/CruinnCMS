<?php
/**
 * CruinnCMS Platform Config — Example / Template
 *
 * This file is generated automatically by the /cms/install wizard.
 * It is gitignored (config/CruinnCMS.php) — never commit the real version.
 *
 * This example shows the structure written by the wizard so you can
 * reconstruct it manually if needed (e.g. after migrating to a new server).
 *
 * To generate a password hash manually:
 *   php -r "echo password_hash('your-password', PASSWORD_BCRYPT, ['cost' => 12]);"
 */

return [
    // Set to true by the install wizard. If false or missing, every request
    // is redirected to /cms/install.
    'initialized'   => true,

    // Platform admin credentials (independent of any instance database)
    'username'      => 'platform',
    'password_hash' => '$2y$12$REPLACE_WITH_REAL_HASH',

    // Platform database connection — separate from any instance DB
    'db' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'name'     => 'cruinn_platform',
        'user'     => 'cruinn',
        'password' => 'your-password',
        'charset'  => 'utf8mb4',
    ],
];
