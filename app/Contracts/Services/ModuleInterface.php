<?php

declare(strict_types=1);

namespace App\Contracts\Services;

/**
 * Contract every module must implement to be registered with the Kernel.
 * @see ContainerInterface
 *
 * Modules declare their identity, service providers, and event bindings
 * without the Kernel knowing any module-specific logic.
 */
interface ModuleInterface
{
    /**
     * Canonical machine-readable name, e.g. "users", "marketplace".
     */
    public function getName(): string;

    /**
     * Ordered list of fully-qualified ServiceProviderInterface class names
     * that this module wishes to register into the DI container.
     *
     * @return list<class-string<ServiceProviderInterface>>
     */
    public function getServiceProviders(): array;

    /**
     * Optional boot logic executed after all service providers are registered.
     * Must not depend on other modules being fully booted.
     */
    public function boot(ContainerInterface $container): void;
}
