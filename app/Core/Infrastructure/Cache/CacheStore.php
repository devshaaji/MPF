<?php

declare(strict_types=1);

namespace App\Core\Infrastructure\Cache;

use App\Contracts\Services\CacheStoreInterface;

/**
 * CacheStore — in-process array cache with TTL tracking and stampede prevention.
 *
 * Stampede prevention in remember():
 *   1. Check: return hit immediately.
 *   2. Lock: set a "filling" sentinel in $locks so concurrent callers skip.
 *   3. Fill: call $callback, store result.
 *   4. Release: clear lock.
 *
 * NOTE: This implementation is single-process. For multi-process stampede
 * prevention use a distributed lock (Redis SETNX / APCu) in a future adapter.
 */
final class CacheStore implements CacheStoreInterface
{
    /**
     * @var array<string, array{value: mixed, expires_at: float}>
     */
    private array $store = [];

    /**
     * Active fill-locks for stampede prevention.
     * @var array<string, true>
     */
    private array $locks = [];

    public function __construct(private readonly int $defaultTtl = 3600) {}

    // ------------------------------------------------------------------
    // CacheStoreInterface
    // ------------------------------------------------------------------

    public function get(string $key): mixed
    {
        if (!isset($this->store[$key])) {
            return null;
        }

        $entry = $this->store[$key];

        if (microtime(true) > $entry['expires_at']) {
            unset($this->store[$key]);
            return null;
        }

        return $entry['value'];
    }

    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        $effectiveTtl = $ttl > 0 ? $ttl : $this->defaultTtl;

        $this->store[$key] = [
            'value'      => $value,
            'expires_at' => microtime(true) + $effectiveTtl,
        ];
    }

    public function delete(string $key): void
    {
        unset($this->store[$key]);
    }

    public function forget(string $key): void
    {
        $this->delete($key);
    }

    public function flush(): void
    {
        $this->store = [];
        $this->locks = [];
    }

    /**
     * Check → lock → fill → release pattern.
     *
     * If a lock is already held (another in-flight call in the same process),
     * the second caller returns null rather than computing a second value.
     * In a real multi-process environment replace with a distributed lock.
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        // 1. Check cache hit.
        $cached = $this->get($key);
        if ($cached !== null) {
            return $cached;
        }

        // 2. Check for active fill-lock (stampede guard).
        if (isset($this->locks[$key])) {
            // Another call is already filling; return stale null for now.
            return null;
        }

        // 3. Acquire lock.
        $this->locks[$key] = true;

        try {
            // 4. Re-check after acquiring lock (double-checked locking).
            $cached = $this->get($key);
            if ($cached !== null) {
                return $cached;
            }

            // 5. Fill.
            $value = $callback();
            $this->set($key, $value, $ttl);

            return $value;
        } finally {
            // 6. Release lock.
            unset($this->locks[$key]);
        }
    }
}
