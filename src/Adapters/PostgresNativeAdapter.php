<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Adapters;

use function Hibla\async;
use function Hibla\await;

use Hibla\Postgres\AsyncPgSQLConnection;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\QueryBuilder\Interfaces\ConnectionInterface;

/**
 * PostgreSQL Native Connection Adapter.
 *
 * This adapter wraps AsyncPgSQLConnection to implement the ConnectionInterface,
 * allowing native PostgreSQL connections to work with the query builder.
 */
class PostgresNativeAdapter implements ConnectionInterface
{
    private AsyncPgSQLConnection $connection;

    /**
     * Create a new PostgreSQL native adapter.
     *
     * @param array<string, mixed> $config Database configuration
     * @param int $poolSize Connection pool size
     */
    public function __construct(array $config, int $poolSize = 10)
    {
        $this->connection = new AsyncPgSQLConnection($config, $poolSize);
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
        return 'pgsql_native';
    }

    /**
     * Get the underlying AsyncPgSQLConnection instance.
     *
     * @return AsyncPgSQLConnection
     */
    public function getNativeConnection(): AsyncPgSQLConnection
    {
        return $this->connection;
    }
}
