<?php
/**
 * CruinnCMS — Local Configuration Example
 *
 * This file is written automatically by the /cms/install wizard.
 * Do not edit manually unless you know what you are doing.
 *
 * config.local.php overrides the defaults in config.php.
 * It is gitignored — never commit the real version.
 *
 * To bootstrap manually (e.g. after cloning), copy this file to
 * config.local.php and fill in your local values, then visit /cms/install.
 */

return [
    'db' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'name'     => 'cruinn_platform',
        'user'     => 'root',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],
    'site' => ['debug' => false],
    'trusted_proxy' => '127.0.0.1,::1',
    'mail' => [
        'host'       => 'localhost',
        'port'       => 587,
        'username'   => '',
        'password'   => '',
        'encryption' => 'tls',
        'from_email' => 'noreply@example.com',
        'from_name'  => 'CruinnCMS',
    ],
];
