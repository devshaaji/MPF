<?php

declare(strict_types=1);

namespace App\Contracts\Services;

/**
 * Typed read-only configuration accessor.
 *
 * Resolution precedence (lowest → highest):
 *   defaults < env file < process environment variables
 */
interface ConfigInterface
{
    /**
     * Retrieve a value by dot-notation key.
     * Returns $default when the key is absent.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Retrieve a string value; throws \UnexpectedValueException when the
     * resolved value is not a string (or castable to one).
     */
    public function getString(string $key, ?string $default = null): ?string;

    /**
     * Retrieve an integer value.
     */
    public function getInt(string $key, ?int $default = null): ?int;

    /**
     * Retrieve a boolean value.
     * Truthy strings ("true", "1", "yes") are coerced to true.
     */
    public function getBool(string $key, ?bool $default = null): ?bool;

    /**
     * Retrieve an array value.
     */
    public function getArray(string $key, ?array $default = null): ?array;

    /**
     * Assert a key exists; throws \RuntimeException when absent.
     */
    public function require(string $key): mixed;

    /**
     * Return the active environment label (dev | staging | prod).
     */
    public function getEnvironment(): string;

    /**
     * Return all resolved configuration as a flat associative array.
     * Useful for debug/logging; must NOT expose secrets.
     *
     * @return array<string, mixed>
     */
    public function all(): array;
}
