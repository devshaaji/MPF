<?php

declare(strict_types=1);

namespace App\Core\Infrastructure\Config;

use App\Contracts\Services\ConfigInterface;

/**
 * Supported runtime environments.
 */
enum Environment: string
{
    case Dev     = 'dev';
    case Staging = 'staging';
    case Prod    = 'prod';

    public static function fromString(string $value): self
    {
        return self::from(strtolower(trim($value)));
    }
}

/**
 * ConfigLoader — merges configuration with the following precedence
 * (lowest → highest):
 *
 *   1. Hard-coded defaults (passed at construction)
 *   2. Environment config file  (config/{env}.php)
 *   3. Process environment variables (getenv / $_ENV)
 *
 * All values are flattened to dot-notation keys internally.
 */
final class ConfigLoader implements ConfigInterface
{
    /** @var array<string, mixed> Fully merged, flat dot-notation map. */
    private readonly array $resolved;

    private readonly Environment $environment;

    /**
     * @param array<string, mixed> $defaults      Base defaults (dot-notation or nested).
     * @param string               $configDir     Absolute path to the config/ directory.
     * @param string|null          $envOverride   Force a specific environment label (testing).
     */
    public function __construct(
        array   $defaults   = [],
        string  $configDir  = '',
        ?string $envOverride = null,
    ) {
        $envLabel = $envOverride
            ?? (getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? Environment::Dev->value));

        $this->environment = Environment::fromString((string) $envLabel);

        // Layer 1: defaults.
        $merged = $this->flatten($defaults);

        // Layer 2: environment config file.
        $file = rtrim($configDir, '/') . '/' . $this->environment->value . '.php';
        if ($configDir !== '' && is_file($file)) {
            /** @var array<string, mixed> $fileConfig */
            $fileConfig = require $file;
            $merged = array_merge($merged, $this->flatten($fileConfig));
        }

        // Layer 3: process environment variables (uppercase → lowercase dot keys).
        $merged = array_merge($merged, $this->fromProcessEnv());

        $this->resolved = $merged;
    }

    // ------------------------------------------------------------------
    // ConfigInterface implementation
    // ------------------------------------------------------------------

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->resolved[$key] ?? $default;
    }

    public function getString(string $key, ?string $default = null): ?string
    {
        $value = $this->get($key);
        if ($value === null) {
            return $default;
        }
        if (!is_scalar($value)) {
            throw new \UnexpectedValueException(sprintf(
                'Config key "%s" cannot be cast to string.',
                $key,
            ));
        }
        return (string) $value;
    }

    public function getInt(string $key, ?int $default = null): ?int
    {
        $value = $this->get($key);
        if ($value === null) {
            return $default;
        }
        if (!is_numeric($value)) {
            throw new \UnexpectedValueException(sprintf(
                'Config key "%s" cannot be cast to int.',
                $key,
            ));
        }
        return (int) $value;
    }

    public function getBool(string $key, ?bool $default = null): ?bool
    {
        $value = $this->get($key);
        if ($value === null) {
            return $default;
        }
        if (is_bool($value)) {
            return $value;
        }
        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['true', '1', 'yes', 'on'], true);
    }

    public function getArray(string $key, ?array $default = null): ?array
    {
        $value = $this->get($key);
        if ($value === null) {
            return $default;
        }
        if (!is_array($value)) {
            throw new \UnexpectedValueException(sprintf(
                'Config key "%s" is not an array.',
                $key,
            ));
        }
        return $value;
    }

    public function require(string $key): mixed
    {
        if (!array_key_exists($key, $this->resolved)) {
            throw new \RuntimeException(sprintf(
                'Required configuration key "%s" is missing.',
                $key,
            ));
        }
        return $this->resolved[$key];
    }

    public function getEnvironment(): string
    {
        return $this->environment->value;
    }

    public function all(): array
    {
        return $this->resolved;
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    /**
     * Recursively flatten a nested array to dot-notation keys.
     *
     * @param  array<string, mixed> $array
     * @return array<string, mixed>
     */
    private function flatten(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $fullKey = $prefix !== '' ? $prefix . '.' . $key : (string) $key;

            if (is_array($value) && !array_is_list($value)) {
                $result = array_merge($result, $this->flatten($value, $fullKey));
            } else {
                $result[$fullKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Read relevant process env vars and map them to dot-notation keys.
     * Convention: APP__DB__HOST → app.db.host (double underscore = dot).
     *
     * @return array<string, mixed>
     */
    private function fromProcessEnv(): array
    {
        $result = [];

        foreach (array_merge($_ENV, getenv() ?: []) as $key => $value) {
            // Only map keys that contain our namespace delimiter.
            if (!str_contains((string) $key, '__')) {
                continue;
            }
            $dotKey = strtolower(str_replace('__', '.', (string) $key));
            $result[$dotKey] = $value;
        }

        return $result;
    }
}
