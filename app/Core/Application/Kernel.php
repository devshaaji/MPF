<?php

declare(strict_types=1);

namespace App\Core\Application;

use App\Contracts\Services\ConfigInterface;
use App\Contracts\Services\ContainerInterface;
use App\Contracts\Services\ModuleInterface;
use App\Contracts\Services\ServiceProviderInterface;

/**
 * Platform Kernel — orchestrates module registration, service provider
 * lifecycle, and module boot sequence.
 *
 * Responsibilities:
 *  1. Accept and validate registered modules.
 *  2. Drive register → boot lifecycle for every service provider.
 *  3. Expose the resolved DI container for runtime use.
 *
 * No module-specific business logic belongs here.
 */
final class Kernel
{
    /** @var array<string, ModuleInterface> Keyed by module name. */
    private array $modules = [];

    private bool $booted = false;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ConfigInterface    $config,
    ) {}

    // ------------------------------------------------------------------
    // Module registration
    // ------------------------------------------------------------------

    public function registerModule(ModuleInterface $module): void
    {
        if ($this->booted) {
            throw new \LogicException('Cannot register modules after the kernel has booted.');
        }

        $name = $module->getName();

        if (isset($this->modules[$name])) {
            throw new \LogicException(sprintf('Module "%s" is already registered.', $name));
        }

        $this->modules[$name] = $module;
    }

    /**
     * @param list<ModuleInterface> $modules
     */
    public function registerModules(array $modules): void
    {
        foreach ($modules as $module) {
            $this->registerModule($module);
        }
    }

    // ------------------------------------------------------------------
    // Boot lifecycle
    // ------------------------------------------------------------------

    /**
     * Run the full register → boot lifecycle.
     * Idempotent — subsequent calls are no-ops.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        // Phase 1: collect all service providers from all modules.
        $providers = $this->collectProviders();

        // Phase 2: register every provider into the container.
        foreach ($providers as $provider) {
            $provider->register($this->container);
        }

        // Phase 3: boot every provider (may use already-registered services).
        foreach ($providers as $provider) {
            $provider->boot($this->container);
        }

        // Phase 4: boot each module (lightweight, post-provider logic).
        foreach ($this->modules as $module) {
            $module->boot($this->container);
        }

        // Phase 5: register cross-module event subscriptions.
        // EventSubscriptions::register() must be called here so that all
        // module service providers (and their EventPublisherInterface binding)
        // are already resolved before subscriptions are wired.
        // Example boot-site wiring:
        //   $subscriptions = new \App\Bootstrap\EventSubscriptions(
        //       $this->container->get(\App\Contracts\Services\EventPublisherInterface::class)
        //   );
        //   $subscriptions->register();

        $this->booted = true;
    }

    // ------------------------------------------------------------------
    // Accessors
    // ------------------------------------------------------------------

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public function getConfig(): ConfigInterface
    {
        return $this->config;
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * @return array<string, ModuleInterface>
     */
    public function getModules(): array
    {
        return $this->modules;
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    /**
     * Instantiate all service providers declared by registered modules,
     * preserving module registration order.
     *
     * @return list<ServiceProviderInterface>
     */
    private function collectProviders(): array
    {
        $providers = [];

        foreach ($this->modules as $module) {
            foreach ($module->getServiceProviders() as $providerClass) {
                if (!is_a($providerClass, ServiceProviderInterface::class, true)) {
                    throw new \InvalidArgumentException(sprintf(
                        'Service provider "%s" declared by module "%s" must implement %s.',
                        $providerClass,
                        $module->getName(),
                        ServiceProviderInterface::class,
                    ));
                }

                $providers[] = new $providerClass();
            }
        }

        return $providers;
    }
}
