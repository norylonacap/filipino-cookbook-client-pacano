<?php

/**
 * Example configuration file.
 *
 * Copy this file to "config.php" (same folder) and fill in your own local
 * values. config.php is listed in .gitignore and is never committed.
 */

return [
    'db_host'    => '127.0.0.1',
    'db_name'    => 'filipino_cookbook_api',
    'db_user'    => 'YOUR_DATABASE_USERNAME',
    'db_pass'    => 'YOUR_DATABASE_PASSWORD',
    'db_charset' => 'utf8mb4',

    // Token clients must send as: Authorization: Bearer <api_token>
    'api_token'  => 'YOUR_SECRET_API_TOKEN',
];
