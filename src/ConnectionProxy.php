<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\QueryBuilder\Interfaces\ConnectionInterface;
use Hibla\QueryBuilder\Interfaces\ProxyInterface;
use Hibla\QueryBuilder\Utilities\Builder;

/**
 * Connection Proxy - Provides a fluent API for database operations on a specific connection.
 */
class ConnectionProxy implements ProxyInterface
{
    private ConnectionInterface $connection;
    private ?string $connectionName;

    /**
     * Create a new ConnectionProxy instance.
     *
     * @param ConnectionInterface $connection
     * @param string|null $connectionName
     */
    public function __construct(ConnectionInterface $connection, ?string $connectionName = null)
    {
        $this->connection = $connection;
        $this->connectionName = $connectionName;
    }

    /**
     * {@inheritDoc}
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * {@inheritDoc}
     */
    public function getConnectionName(): ?string
    {
        return $this->connectionName;
    }

    /**
     * {@inheritDoc}
     */
    public function table(string $table): Builder
    {
        $builder = new Builder($table);
        $builder->setConnectionAdapter($this->connection);

        return $builder;
    }

    /**
     * {@inheritDoc}
     */
    public function tableWithDriver(string $table, string $driver): Builder
    {
        $builder = new Builder($table);
        $builder->setConnectionAdapter($this->connection);

        return $builder->setDriver($driver);
    }

    /**
     * {@inheritDoc}
     */
    public function raw(string $sql, array $bindings = []): PromiseInterface
    {
        return $this->connection->query($sql, $bindings);
    }

    /**
     * {@inheritDoc}
     */
    public function rawFirst(string $sql, array $bindings = []): PromiseInterface
    {
        return $this->connection->fetchOne($sql, $bindings);
    }

    /**
     * {@inheritDoc}
     */
    public function rawValue(string $sql, array $bindings = []): PromiseInterface
    {
        return $this->connection->fetchValue($sql, $bindings);
    }

    /**
     * {@inheritDoc}
     */
    public function rawExecute(string $sql, array $bindings = []): PromiseInterface
    {
        return $this->connection->execute($sql, $bindings);
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
     * Execute a callback with the underlying connection.
     *
     * @template TResult
     * @param callable(): TResult $callback Callback that receives connection instance
     * @return PromiseInterface<TResult>
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
}
