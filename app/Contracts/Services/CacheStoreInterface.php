<?php

declare(strict_types=1);

namespace App\Contracts\Services;

/**
 * CacheStoreInterface — contract for key/value cache operations.
 *
 * Implementations must guarantee:
 *   - TTL-based expiration.
 *   - Stampede-safe remember() via a check → lock → fill → release pattern.
 */
interface CacheStoreInterface
{
    /**
     * Retrieve a cached value by key.
     * Returns null on cache miss or expired entry.
     */
    public function get(string $key): mixed;

    /**
     * Store a value under the given key with a TTL in seconds.
     * Pass 0 to use the store's configured default TTL.
     */
    public function set(string $key, mixed $value, int $ttl = 0): void;

    /**
     * Remove a single entry from the cache.
     */
    public function delete(string $key): void;

    /**
     * Retrieve a cached value, or compute and store it if absent.
     *
     * The $callback is called only on a cache miss; its return value is
     * stored and returned.  Implementations must use locking to prevent
     * stampede (multiple concurrent calls computing the same value).
     */
    public function remember(string $key, int $ttl, callable $callback): mixed;

    /**
     * Alias for delete() — semantic sugar for intent-expressive code.
     */
    public function forget(string $key): void;

    /**
     * Purge all entries from the cache store.
     */
    public function flush(): void;
}
