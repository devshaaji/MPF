<?php

declare(strict_types=1);

namespace App\Contracts\Services;

/**
 * Contract for a module's service registration unit.
 *
 * Each ServiceProvider is responsible for registering its own services,
 * repositories, and infrastructure bindings into the DI container.
 * Providers must not call other modules' providers directly.
 */
interface ServiceProviderInterface
{
    /**
     * Register bindings into the container.
     * At this stage, other providers may not yet be registered.
     */
    public function register(ContainerInterface $container): void;

    /**
     * Bootstrap services after ALL providers have been registered.
     * Side-effects (e.g. event listeners, cache warmup) belong here.
     */
    public function boot(ContainerInterface $container): void;

    /**
     * Return the list of abstract identifiers this provider registers.
     * Used by the Kernel for dependency-order validation.
     *
     * @return list<class-string|string>
     */
    public function provides(): array;
}
