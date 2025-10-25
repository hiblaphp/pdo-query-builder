<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Adapters;

use Hibla\Postgres\AsyncPgSQLConnection;
use Hibla\PdoQueryBuilder\Interfaces\ConnectionInterface;
use Hibla\Promise\Interfaces\PromiseInterface;

use function Hibla\async;
use function Hibla\await;

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
        $pgConfig = [
            'host' => $config['host'],
            'port' => $config['port'],
            'database' => $config['database'],
            'username' => $config['username'],
            'password' => $config['password'] ?? '',
        ];

        if (isset($config['options']) && is_array($config['options'])) {
            $pgConfig['options'] = $config['options'];
        }

        $this->connection = new AsyncPgSQLConnection($pgConfig, $poolSize);
    }

    /**
     * {@inheritDoc}
     */
    public function query(string $sql, array $bindings = []): PromiseInterface
    {
        return async(function () use ($sql, $bindings) {
            $convertedSql = $this->convertPlaceholders($sql, $bindings);
            $indexedBindings = array_values($bindings);

            return await($this->connection->query($convertedSql, $indexedBindings));
        });
    }

    /**
     * {@inheritDoc}
     */
    public function fetchOne(string $sql, array $bindings = []): PromiseInterface
    {
        return async(function () use ($sql, $bindings) {
            $convertedSql = $this->convertPlaceholders($sql, $bindings);
            $indexedBindings = array_values($bindings);

            $result = await($this->connection->fetchOne($convertedSql, $indexedBindings));

            return $result === null ? false : $result;
        });
    }

    /**
     * {@inheritDoc}
     */
    public function fetchValue(string $sql, array $bindings = []): PromiseInterface
    {
        return async(function () use ($sql, $bindings) {
            $convertedSql = $this->convertPlaceholders($sql, $bindings);
            $indexedBindings = array_values($bindings);

            return await($this->connection->fetchValue($convertedSql, $indexedBindings));
        });
    }

    /**
     * {@inheritDoc}
     */
    public function execute(string $sql, array $bindings = []): PromiseInterface
    {
        return async(function () use ($sql, $bindings) {
            $convertedSql = $this->convertPlaceholders($sql, $bindings);
            $indexedBindings = array_values($bindings);

            return await($this->connection->execute($convertedSql, $indexedBindings));
        });
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

    /**
     * Convert ? placeholders to PostgreSQL $1, $2, $3 format.
     *
     * @param string $sql SQL with ? placeholders
     * @param array<int|string, mixed> $bindings
     * @return string SQL with $n placeholders
     */
    private function convertPlaceholders(string $sql, array &$bindings): string
    {
        if ($bindings === []) {
            return $sql;
        }

        if (array_keys($bindings) !== range(0, count($bindings) - 1)) {
            $bindings = array_values($bindings);
        }

        $count = 0;

        return (string) preg_replace_callback(
            '/\?/',
            function () use (&$count): string {
                $count++;

                return '$' . $count;
            },
            $sql
        );
    }
}
