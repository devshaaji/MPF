<?php

declare(strict_types=1);

/**
 * Staging environment configuration.
 *
 * Merged on top of defaults.php when APP_ENV=staging.
 * Credentials and hostnames should be provided via process environment
 * variables (APP__DB__HOST, APP__DB__PASSWORD, etc.) — never hard-coded here.
 */

return [
    'app' => [
        'env'   => 'staging',
        'debug' => false,
    ],

    'http' => [
        'scheme' => 'https',
        'port'   => 443,
    ],

    'db' => [
        'host'     => '',   // Override via APP__DB__HOST env var.
        'name'     => 'mpf_staging',
        'username' => '',   // Override via APP__DB__USERNAME env var.
        'password' => '',   // Override via APP__DB__PASSWORD env var.
    ],

    'cache' => [
        'driver' => 'redis',
        'ttl'    => 600,
    ],

    'queue' => [
        'driver'      => 'redis',
        'retry_limit' => 5,
    ],

    'log' => [
        'level'   => 'info',
        'channel' => 'stdout',
    ],
];
