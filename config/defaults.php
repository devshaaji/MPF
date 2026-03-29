<?php

declare(strict_types=1);

/**
 * Default configuration values.
 *
 * These are the lowest-precedence layer and provide safe fallbacks for every
 * key the platform uses.  Environment-specific files and process environment
 * variables always override these values.
 *
 * Keys use dot-notation; nested arrays are also supported and will be
 * flattened by ConfigLoader automatically.
 */

return [
    'app' => [
        'name'     => 'Village Community Digital Platform',
        'env'      => 'dev',
        'debug'    => false,
        'timezone' => 'UTC',
        'locale'   => 'en',
    ],

    'http' => [
        'host'   => 'localhost',
        'port'   => 8080,
        'scheme' => 'http',
    ],

    'db' => [
        'driver'    => 'mysql',
        'host'      => '127.0.0.1',
        'port'      => 3306,
        'name'      => 'mpf',
        'charset'   => 'utf8mb4',
        'user'      => '',      // primary key consumed by ConnectionFactory
        'username'  => '',      // legacy alias — prefer db.user
        'password'  => '',
    ],

    'cache' => [
        'driver'      => 'array',
        'ttl'         => 3600,
        'ttl_default' => 3600,  // consumed by CacheStore (INFRA-CACHE-001)
    ],

    'queue' => [
        'driver'      => 'sync',
        'retry_limit' => 3,
    ],

    'log' => [
        'level'   => 'debug',
        'channel' => 'stderr',
    ],

    'jwt' => [
        'algorithm' => 'HS256',
        'ttl'       => 3600,
        'secret'    => '',
    ],
];
