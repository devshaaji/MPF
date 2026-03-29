<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Contracts\Services\ConfigInterface;
use App\Contracts\Services\ContainerInterface;
use App\Contracts\Services\ModuleInterface;
use App\Core\Application\Kernel;
use App\Core\Infrastructure\Config\ConfigLoader;
use App\Core\Infrastructure\DI\Container;

/**
 * WebRuntime — bootstraps the platform for synchronous HTTP request handling.
 *
 * Boot sequence:
 *  1. Generate correlation ID.
 *  2. Initialise the DI container.
 *  3. Load and wire configuration.
 *  4. Register core singletons (config, container).
 *  5. Instantiate and boot the Kernel with registered modules.
 */
final class WebRuntime
{
    private string             $correlationId;
    private ContainerInterface $container;
    private ConfigInterface    $config;
    private Kernel             $kernel;

    private bool $booted = false;

    /**
     * @param list<ModuleInterface> $modules     Application modules to register.
     * @param string                $configDir   Absolute path to config/ directory.
     * @param array<string,mixed>   $defaults    Base config defaults.
     */
    public function __construct(
        private readonly array  $modules   = [],
        private readonly string $configDir = '',
        private readonly array  $defaults  = [],
    ) {}

    public function boot(): void
    {
        $this->correlationId = $this->generateCorrelationId();

        // Wire infrastructure.
        $container = new Container();
        $config    = new ConfigLoader($this->defaults, $this->configDir);

        // Register core services so modules can resolve them.
        $container->instance(ConfigInterface::class, $config);
        $container->instance(ContainerInterface::class, $container);

        $this->container = $container;
        $this->config    = $config;

        // Build and boot kernel.
        $kernel = new Kernel($container, $config);
        $kernel->registerModules($this->modules);
        $kernel->boot();

        $this->kernel = $kernel;
    }

    // ------------------------------------------------------------------
    // Accessors (available after boot())
    // ------------------------------------------------------------------

    public function getCorrelationId(): string
    {
        return $this->correlationId ?? throw new \LogicException('Runtime not booted yet.');
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container ?? throw new \LogicException('Runtime not booted yet.');
    }

    public function getConfig(): ConfigInterface
    {
        return $this->config ?? throw new \LogicException('Runtime not booted yet.');
    }

    public function getKernel(): Kernel
    {
        return $this->kernel ?? throw new \LogicException('Runtime not booted yet.');
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    private function generateCorrelationId(): string
    {
        return sprintf(
            'web-%s-%s',
            date('Ymd-His'),
            bin2hex(random_bytes(8)),
        );
    }
}
