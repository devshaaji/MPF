<?php

declare(strict_types=1);

/**
 * HTTP entry point — Village Community Digital Platform.
 *
 * Responsibilities:
 *  - Resolve the project root path.
 *  - Load the Composer autoloader.
 *  - Boot the WebRuntime with an empty module array (modules are added here
 *    as the platform grows).
 *  - Dispatch the current HTTP request through the application.
 *
 * No business logic belongs here.  All bootstrapping is delegated to
 * WebRuntime; all routing/dispatching to the Application layer.
 */

// ---------------------------------------------------------------------------
// Paths
// ---------------------------------------------------------------------------

$rootDir   = dirname(__DIR__);
$configDir = $rootDir . '/config';

// ---------------------------------------------------------------------------
// Autoloader
// ---------------------------------------------------------------------------

$autoloader = $rootDir . '/vendor/autoload.php';

if (!file_exists($autoloader)) {
    http_response_code(503);
    echo 'Dependencies not installed. Run: composer install';
    exit(1);
}

require_once $autoloader;

// ---------------------------------------------------------------------------
// Runtime boot
// ---------------------------------------------------------------------------

use App\Bootstrap\WebRuntime;

$runtime = new WebRuntime(
    modules:   [],          // Register App\Modules\*\Module instances here.
    configDir: $configDir,
    defaults:  require $rootDir . '/config/defaults.php',
);

$runtime->boot();

// ---------------------------------------------------------------------------
// HTTP Dispatch (stub — replace with router integration)
// ---------------------------------------------------------------------------

// The correlation ID is available for injection into request context /
// response headers for distributed tracing.
header('X-Correlation-ID: ' . $runtime->getCorrelationId());

// TODO: Route the request through the application's HTTP handler.
//   $kernel   = $runtime->getKernel();
//   $router   = $runtime->getContainer()->get(RouterInterface::class);
//   $response = $router->dispatch(ServerRequest::fromGlobals());
//   $response->send();
