<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Contracts\Services\ConfigInterface;
use App\Contracts\Services\ContainerInterface;
use App\Contracts\Services\ModuleInterface;
use App\Core\Application\Kernel;
use App\Contracts\Services\LoggerInterface;
use App\Core\Infrastructure\Config\ConfigLoader;
use App\Core\Infrastructure\DI\Container;
use App\Core\Infrastructure\Logging\LoggerFactory;

/**
 * WorkerRuntime — bootstraps the platform for async queue/worker processes.
 *
 * Workers share the same kernel/DI/config boot sequence as the web runtime
 * but are designed for long-running CLI processes consuming job queues.
 *
 * Boot sequence:
 *  1. Generate correlation ID for the worker lifecycle.
 *  2. Initialise DI container and config.
 *  3. Register core singletons.
 *  4. Boot the Kernel with registered modules.
 */
final class WorkerRuntime
{
    private string             $correlationId;
    private ContainerInterface $container;
    private ConfigInterface    $config;
    private Kernel             $kernel;

    private bool $booted = false;

    /**
     * @param list<ModuleInterface> $modules   Application modules to register.
     * @param string                $configDir Absolute path to config/ directory.
     * @param array<string,mixed>   $defaults  Base config defaults.
     */
    public function __construct(
        private readonly array  $modules   = [],
        private readonly string $configDir = '',
        private readonly array  $defaults  = [],
    ) {}

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->correlationId = $this->generateCorrelationId();

        $container = new Container();
        $config    = new ConfigLoader($this->defaults, $this->configDir);

        $container->instance(ConfigInterface::class, $config);
        $container->instance(ContainerInterface::class, $container);

        // Wire structured logger singleton for worker processes (INFRA-LOG-001).
        // Workers use the 'queue' channel so log records can be filtered by
        // component in aggregation tools.
        $loggerFactory = new LoggerFactory(
            defaultChannel: 'queue',
            minLevel: (string) ($config->get('log.level') ?? 'debug'),
        );
        $correlationId = $this->correlationId;
        $container->singleton(
            LoggerInterface::class,
            static fn () => $loggerFactory->makeDefault()->withCorrelationId($correlationId),
        );

        $this->container = $container;
        $this->config    = $config;

        $kernel = new Kernel($container, $config);
        $kernel->registerModules($this->modules);
        $kernel->boot();

        $this->kernel = $kernel;
        $this->booted = true;
    }

    // ------------------------------------------------------------------
    // Accessors
    // ------------------------------------------------------------------

    public function getCorrelationId(): string
    {
        $this->assertBooted();
        return $this->correlationId;
    }

    public function getContainer(): ContainerInterface
    {
        $this->assertBooted();
        return $this->container;
    }

    public function getConfig(): ConfigInterface
    {
        $this->assertBooted();
        return $this->config;
    }

    public function getKernel(): Kernel
    {
        $this->assertBooted();
        return $this->kernel;
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    private function generateCorrelationId(): string
    {
        return sprintf(
            'worker-%s-%s',
            date('Ymd-His'),
            bin2hex(random_bytes(8)),
        );
    }

    private function assertBooted(): void
    {
        if (!$this->booted) {
            throw new \LogicException('WorkerRuntime has not been booted yet.');
        }
    }
}
