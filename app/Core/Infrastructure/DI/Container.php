<?php

declare(strict_types=1);

namespace App\Core\Infrastructure\DI;

use App\Contracts\Services\ContainerInterface;

/**
 * Minimal reflection-based DI container.
 *
 * Supports:
 *  - Transient bindings   (bind)
 *  - Singleton bindings   (singleton)
 *  - Pre-built instances  (instance)
 *  - Auto-wiring via PHP's ReflectionClass
 *
 * Domain services must never call Container::get() directly (service
 * location anti-pattern); injection must be constructor-based only.
 */
final class Container implements ContainerInterface
{
    /** @var array<string, array{concrete: class-string|callable|null, singleton: bool}> */
    private array $bindings = [];

    /** @var array<string, object> Resolved singleton instances. */
    private array $instances = [];

    // ------------------------------------------------------------------
    // ContainerInterface implementation
    // ------------------------------------------------------------------

    public function get(string $abstract): mixed
    {
        // Return cached singleton instance.
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            $binding  = $this->bindings[$abstract];
            $concrete = $binding['concrete'] ?? $abstract;
            $resolved = $this->resolve($concrete);

            if ($binding['singleton']) {
                $this->instances[$abstract] = $resolved;
            }

            return $resolved;
        }

        // Attempt auto-wiring for unregistered class names.
        if (class_exists($abstract)) {
            return $this->build($abstract);
        }

        throw new \RuntimeException(sprintf(
            'No binding registered for "%s" and it cannot be auto-wired.',
            $abstract,
        ));
    }

    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract])
            || isset($this->instances[$abstract])
            || class_exists($abstract);
    }

    public function bind(string $abstract, string|callable|null $concrete = null, bool $singleton = false): void
    {
        $this->bindings[$abstract] = [
            'concrete'  => $concrete,
            'singleton' => $singleton,
        ];

        // Invalidate any previously cached singleton for this abstract.
        unset($this->instances[$abstract]);
    }

    public function singleton(string $abstract, string|callable|null $concrete = null): void
    {
        $this->bind($abstract, $concrete, singleton: true);
    }

    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
        // Remove any factory binding so instance() always wins.
        unset($this->bindings[$abstract]);
    }

    // ------------------------------------------------------------------
    // Internal resolution
    // ------------------------------------------------------------------

    /**
     * Resolve a concrete — either a callable factory or a class name.
     */
    private function resolve(string|callable $concrete): object
    {
        if (is_callable($concrete)) {
            $result = $concrete($this);
            if (!is_object($result)) {
                throw new \RuntimeException('Container factory must return an object.');
            }
            return $result;
        }

        return $this->build($concrete);
    }

    /**
     * Auto-wire a class by reflecting its constructor dependencies.
     *
     * @param class-string $class
     */
    private function build(string $class): object
    {
        try {
            $reflector = new \ReflectionClass($class);
        } catch (\ReflectionException $e) {
            throw new \RuntimeException(sprintf('Cannot reflect class "%s": %s', $class, $e->getMessage()), 0, $e);
        }

        if (!$reflector->isInstantiable()) {
            throw new \RuntimeException(sprintf('Class "%s" is not instantiable.', $class));
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return $reflector->newInstance();
        }

        $dependencies = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $dependencies[] = $this->get($type->getName());
            } elseif ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            } else {
                throw new \RuntimeException(sprintf(
                    'Cannot auto-wire parameter "$%s" of class "%s": no type hint and no default value.',
                    $parameter->getName(),
                    $class,
                ));
            }
        }

        return $reflector->newInstanceArgs($dependencies);
    }
}
