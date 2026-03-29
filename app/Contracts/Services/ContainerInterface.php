<?php

declare(strict_types=1);

namespace App\Contracts\Services;

/**
 * Contract for a PSR-11-compatible dependency injection container
 * used throughout the platform.
 */
interface ContainerInterface
{
    /**
     * Resolve a binding by its abstract identifier.
     *
     * @template T
     * @param  class-string<T>|string $abstract
     * @return T
     * @throws \RuntimeException when the binding cannot be resolved.
     */
    public function get(string $abstract): mixed;

    /**
     * Determine whether a binding is registered.
     */
    public function has(string $abstract): bool;

    /**
     * Bind an abstract to a concrete class name or factory callable.
     *
     * @param  class-string|string          $abstract
     * @param  class-string|callable|null   $concrete  Defaults to $abstract.
     * @param  bool                         $singleton Share a single instance.
     */
    public function bind(string $abstract, string|callable|null $concrete = null, bool $singleton = false): void;

    /**
     * Register a shared (singleton) binding.
     *
     * @param class-string|string        $abstract
     * @param class-string|callable|null $concrete
     */
    public function singleton(string $abstract, string|callable|null $concrete = null): void;

    /**
     * Register an already-constructed instance as a singleton.
     */
    public function instance(string $abstract, object $instance): void;
}
