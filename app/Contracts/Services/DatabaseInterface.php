<?php

declare(strict_types=1);

namespace App\Contracts\Services;

/**
 * DatabaseInterface — DBAL contract for query execution and transaction control.
 *
 * All module repositories must depend on this interface; they must never
 * import concrete PDO or framework-specific DB classes directly.
 */
interface DatabaseInterface
{
    /**
     * Begin a new database transaction.
     */
    public function beginTransaction(): void;

    /**
     * Commit the current transaction.
     */
    public function commit(): void;

    /**
     * Roll back the current transaction.
     */
    public function rollback(): void;

    /**
     * Execute a write statement (INSERT / UPDATE / DELETE / DDL).
     *
     * Returns the last insert ID as string when the driver supports it,
     * or the affected row count as int, or null for DDL.
     *
     * @param array<int|string, mixed> $params Positional or named bind values.
     */
    public function execute(string $query, array $params = []): mixed;

    /**
     * Execute a SELECT and return all matching rows as associative arrays.
     *
     * @param  array<int|string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function query(string $query, array $params = []): array;
}
