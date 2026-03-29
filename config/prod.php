<?php

declare(strict_types=1);

/**
 * Production environment configuration.
 *
 * Merged on top of defaults.php when APP_ENV=prod.
 * ALL sensitive values (passwords, secrets, hostnames) MUST be supplied via
 * process environment variables — never committed to source control.
 */

return [
    'app' => [
        'env'   => 'prod',
        'debug' => false,
    ],

    'http' => [
        'scheme' => 'https',
        'port'   => 443,
    ],

    'db' => [
        'host'     => '',   // Required: APP__DB__HOST
        'name'     => 'mpf',
        'username' => '',   // Required: APP__DB__USERNAME
        'password' => '',   // Required: APP__DB__PASSWORD
    ],

    'cache' => [
        'driver' => 'redis',
        'ttl'    => 3600,
    ],

    'queue' => [
        'driver'      => 'redis',
        'retry_limit' => 5,
    ],

    'log' => [
        'level'   => 'warning',
        'channel' => 'stdout',
    ],

    'jwt' => [
        'secret' => '',     // Required: APP__JWT__SECRET
    ],
];
