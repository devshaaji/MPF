<?php

declare(strict_types=1);

namespace App\Core\Infrastructure\Database;

use App\Contracts\Services\ConfigInterface;

/**
 * ConnectionFactory — creates and manages a PDO connection from config.
 *
 * Config keys consumed (all under the "db" namespace):
 *   db.driver   — PDO driver prefix (default: mysql)
 *   db.host     — database host
 *   db.port     — database port
 *   db.name     — database/schema name
 *   db.user     — login user
 *   db.password — login password
 *   db.charset  — connection charset (default: utf8mb4)
 *
 * The returned PDO instance uses:
 *   - ERRMODE_EXCEPTION for all errors
 *   - FETCH_ASSOC as default fetch mode
 *   - Emulated prepares disabled (native prepared statements)
 */
final class ConnectionFactory
{
    private ?\PDO $connection = null;

    public function __construct(private readonly ConfigInterface $config) {}

    /**
     * Return a shared (lazy-created) PDO connection.
     * Call this once per request; do not call per query.
     */
    public function getConnection(): \PDO
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        $driver  = (string) ($this->config->get('db.driver')  ?? 'mysql');
        $host    = (string) ($this->config->get('db.host')    ?? '127.0.0.1');
        $port    = (int)    ($this->config->get('db.port')    ?? 3306);
        $name    = (string) ($this->config->get('db.name')    ?? '');
        $charset = (string) ($this->config->get('db.charset') ?? 'utf8mb4');
        $user    = (string) ($this->config->get('db.user')    ?? ($this->config->get('db.username') ?? ''));
        $pass    = (string) ($this->config->get('db.password') ?? '');

        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $driver,
            $host,
            $port,
            $name,
            $charset,
        );

        $this->connection = new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return $this->connection;
    }

    /**
     * Discard the cached connection (e.g., after a fatal DB error).
     */
    public function reset(): void
    {
        $this->connection = null;
    }
}
