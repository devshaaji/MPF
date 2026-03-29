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
 * SchedulerRuntime — bootstraps the platform for CLI scheduled task execution.
 *
 * The scheduler runtime mirrors the worker boot sequence but is designed
 * for short-lived invocations (cron, task runner).  Each invocation gets
 * its own correlation ID so scheduled tasks are individually traceable.
 *
 * Boot sequence:
 *  1. Generate correlation ID for this scheduler invocation.
 *  2. Initialise DI container and config.
 *  3. Register core singletons.
 *  4. Boot the Kernel with registered modules.
 */
final class SchedulerRuntime
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
            'scheduler-%s-%s',
            date('Ymd-His'),
            bin2hex(random_bytes(8)),
        );
    }

    private function assertBooted(): void
    {
        if (!$this->booted) {
            throw new \LogicException('SchedulerRuntime has not been booted yet.');
        }
    }
}
