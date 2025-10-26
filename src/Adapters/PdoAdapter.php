<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Adapters;

use Hibla\AsyncPDO\AsyncPDOConnection;
use Hibla\PdoQueryBuilder\Interfaces\ConnectionInterface;
use Hibla\Promise\Interfaces\PromiseInterface;

/**
 * PDO Connection Adapter.
 * 
 * This adapter wraps AsyncPDOConnection to implement the ConnectionInterface,
 * maintaining backward compatibility with existing PDO-based code.
 */
class PdoAdapter implements ConnectionInterface
{
    private AsyncPDOConnection $connection;
    private string $driver;

    /**
     * Create a new PDO adapter.
     *
     * @param array<string, mixed> $config Database configuration
     * @param int $poolSize Connection pool size
     */
    public function __construct(array $config, int $poolSize = 10)
    {
        $this->connection = new AsyncPDOConnection($config, $poolSize);
        $driver = $config['driver'] ?? 'mysql';
        assert(is_string($driver));
        $this->driver = $driver;
    }

    /**
     * {@inheritDoc}
     */
    public function query(string $sql, array $bindings = []): PromiseInterface
    {
        return $this->connection->query($sql, $bindings);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchOne(string $sql, array $bindings = []): PromiseInterface
    {
        return $this->connection->fetchOne($sql, $bindings);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchValue(string $sql, array $bindings = []): PromiseInterface
    {
        return $this->connection->fetchValue($sql, $bindings);
    }

    /**
     * {@inheritDoc}
     */
    public function execute(string $sql, array $bindings = []): PromiseInterface
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
        return $this->driver;
    }

    /**
     * Get the underlying AsyncPDOConnection instance.
     *
     * @return AsyncPDOConnection
     */
    public function getPdoConnection(): AsyncPDOConnection
    {
        return $this->connection;
    }
}
