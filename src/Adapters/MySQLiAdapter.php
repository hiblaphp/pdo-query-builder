<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Adapters;

use function Hibla\async;
use function Hibla\await;

use Hibla\MySQL\AsyncMySQLConnection;
use Hibla\Promise\Interfaces\PromiseInterface;

use Hibla\QueryBuilder\Interfaces\ConnectionInterface;
use mysqli;

/**
 * MySQL Native Connection Adapter.
 *
 * This adapter wraps AsyncMySQLConnection to implement the ConnectionInterface,
 * allowing native MySQL connections to work with the query builder.
 *
 * Note: This adapter returns all values as strings to preserve data integrity
 * (leading zeros, precision, etc.). Use NumericConverter utility class if you
 * need to convert specific values to native PHP types.
 */
class MySQLiAdapter implements ConnectionInterface
{
    private AsyncMySQLConnection $connection;

    /**
     * Create a new MySQL native adapter.
     *
     * @param array<string, mixed> $config Database configuration
     * @param int $poolSize Connection pool size
     */
    public function __construct(array $config, int $poolSize = 10)
    {
        $this->connection = new AsyncMySQLConnection($config, $poolSize);
    }

    /**
     * Convert bindings to positional array.
     *
     * @param array<int|string, mixed> $bindings
     * @return array<int, mixed>
     */
    private function normalizeBindings(array $bindings): array
    {
        return array_values($bindings);
    }

    /**
     * {@inheritDoc}
     */
    public function query(string $sql, array $bindings = []): PromiseInterface
    {
        return $this->connection->query($sql, $this->normalizeBindings($bindings));
    }

    /**
     * {@inheritDoc}
     */
    public function fetchOne(string $sql, array $bindings = []): PromiseInterface
    {
        return async(function () use ($sql, $bindings) {
            $result = await($this->connection->fetchOne($sql, $this->normalizeBindings($bindings)));

            return $result === null ? false : $result;
        });
    }

    /**
     * {@inheritDoc}
     */
    public function fetchValue(string $sql, array $bindings = []): PromiseInterface
    {
        return $this->connection->fetchValue($sql, $this->normalizeBindings($bindings));
    }

    /**
     * {@inheritDoc}
     */
    public function execute(string $sql, array $bindings = []): PromiseInterface
    {
        return $this->connection->execute($sql, $this->normalizeBindings($bindings));
    }

    /**
     * {@inheritDoc}
     */
    public function transaction(callable $callback, int $attempts = 1): PromiseInterface
    {
        return $this->connection->transaction($callback, $attempts);
    }

    /**
     * {@inheritDoc}
     */
    public function onCommit(callable $callback): void
    {
        $this->connection->onCommit($callback);
    }

    /**
     * {@inheritDoc}
     */
    public function onRollback(callable $callback): void
    {
        $this->connection->onRollback($callback);
    }

    /**
     * {@inheritDoc}
     */
    public function run(callable $callback): PromiseInterface
    {
        return $this->connection->run($callback);
    }

    /**
     * {@inheritDoc}
     */
    public function getStats(): array
    {
        return $this->connection->getStats();
    }

    /**
     * {@inheritDoc}
     */
    public function reset(): void
    {
        $this->connection->reset();
    }

    /**
     * {@inheritDoc}
     */
    public function getDriver(): string
    {
        return 'mysqli_native';
    }

    /**
     * Get the underlying AsyncMySQLConnection instance.
     *
     * @return AsyncMySQLConnection
     */
    public function getNativeConnection(): AsyncMySQLConnection
    {
        return $this->connection;
    }

    /**
     * Get the last used mysqli connection from the pool.
     *
     * @return mysqli|null
     */
    public function getLastConnection(): ?mysqli
    {
        return $this->connection->getLastConnection();
    }
}
