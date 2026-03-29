<?php

declare(strict_types=1);

namespace App\Core\Infrastructure\Database;

use App\Contracts\Services\DatabaseInterface;

/**
 * TransactionManager — wraps a PDO connection in a DatabaseInterface boundary.
 *
 * Provides:
 *  - Nested-transaction safety via a depth counter (savepoints not required
 *    for phase-2 scope; outer transaction wins).
 *  - Exception-safe: every thrown exception should trigger rollback in the
 *    calling service's catch block.
 *
 * Usage:
 *   $db->beginTransaction();
 *   try {
 *       $db->execute('INSERT …', […]);
 *       $db->commit();
 *   } catch (\Throwable $e) {
 *       $db->rollback();
 *       throw $e;
 *   }
 */
final class TransactionManager implements DatabaseInterface
{
    private int $depth = 0;

    public function __construct(private readonly ConnectionFactory $factory) {}

    // ------------------------------------------------------------------
    // Transaction control
    // ------------------------------------------------------------------

    public function beginTransaction(): void
    {
        if ($this->depth === 0) {
            $this->factory->getConnection()->beginTransaction();
        }

        $this->depth++;
    }

    public function commit(): void
    {
        if ($this->depth <= 0) {
            throw new \LogicException('Cannot commit: no active transaction.');
        }

        $this->depth--;

        if ($this->depth === 0) {
            $this->factory->getConnection()->commit();
        }
    }

    public function rollback(): void
    {
        if ($this->depth <= 0) {
            return; // Idempotent — safe to call in finally blocks.
        }

        $this->depth = 0;
        $this->factory->getConnection()->rollBack();
    }

    // ------------------------------------------------------------------
    // Statement execution
    // ------------------------------------------------------------------

    public function execute(string $query, array $params = []): mixed
    {
        $stmt = $this->factory->getConnection()->prepare($query);
        $stmt->execute($params);

        // Return last insert ID when available, otherwise row count.
        $lastId = $this->factory->getConnection()->lastInsertId();

        return ($lastId !== false && $lastId !== '0') ? $lastId : $stmt->rowCount();
    }

    public function query(string $query, array $params = []): array
    {
        $stmt = $this->factory->getConnection()->prepare($query);
        $stmt->execute($params);

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $rows;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Wrap a callable in a transaction, rolling back on exception.
     *
     * @template T
     * @param  callable(): T $callback
     * @return T
     */
    public function transactional(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback();
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }
}
