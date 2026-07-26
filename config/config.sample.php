<?php
// FILE: /config/config.sample.php
// -------------------------------------------------------------------
// Installer copies this to /config/config.php and fills the values.
// /config/config.php is NEVER overwritten by the GitHub auto-updater.
// -------------------------------------------------------------------

return [

    'app' => [
        'name'     => 'AK Cloud',
        'env'      => 'production',   // production | local
        'debug'    => false,          // always false in production
        'url'      => 'https://panel.akdwk.in',
        'timezone' => 'Asia/Kolkata',
        'locale'   => 'en',           // en | gu | hi
        'key'      => '',             // AES-256 key (installer generates)
    ],

    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'akcloud',
        'user'    => 'akcloud',
        'pass'    => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],

    'aapanel' => [
        'url'        => 'http://127.0.0.1:35435',
        'verify_ssl' => false,
        'timeout'    => 30,
        'connect_timeout' => 10,
        'retries'    => 3,
    ],

    'whatsapp' => [
        'api_url' => 'https://bulk.akdwk.in/api.php',
        // api_key + session_id are stored ENCRYPTED in `settings`, not here.
    ],

    'paths' => [
        'php_bin'  => '/www/server/php/82/bin/php',
        'storage'  => __DIR__ . '/../storage',
        'uploads'  => __DIR__ . '/../public/uploads',
        'backups'  => __DIR__ . '/../storage/backups',
    ],

    'session' => [
        'name'     => 'akc_session',
        'lifetime' => 7200,           // seconds
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ],

    'security' => [
        'max_login_attempts' => 5,
        'lockout_minutes'    => 15,
        'admin_ip_whitelist' => [],   // empty = allow all
    ],
];
