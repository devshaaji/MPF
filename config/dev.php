<?php

declare(strict_types=1);

/**
 * Development environment configuration.
 *
 * Merged on top of defaults.php when APP_ENV=dev.
 * Process environment variables still take precedence over these values.
 */

return [
    'app' => [
        'env'   => 'dev',
        'debug' => true,
    ],

    'http' => [
        'host'   => 'localhost',
        'port'   => 8080,
        'scheme' => 'http',
    ],

    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'name'     => 'mpf_dev',
        'username' => '',   // Override via APP__DB__USERNAME env var (e.g. export APP__DB__USERNAME=mpf)
        'password' => '',   // Override via APP__DB__PASSWORD env var (e.g. export APP__DB__PASSWORD=secret)
    ],

    'cache' => [
        'driver' => 'array',
    ],

    'queue' => [
        'driver' => 'sync',
    ],

    'log' => [
        'level'   => 'debug',
        'channel' => 'stderr',
    ],
];
