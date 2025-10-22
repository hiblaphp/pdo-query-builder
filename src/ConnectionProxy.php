<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder;

use Hibla\AsyncPDO\AsyncPDOConnection;
use Hibla\PdoQueryBuilder\Utilities\Builder;
use Hibla\Promise\Interfaces\PromiseInterface;

/**
 * Connection Proxy - Provides a fluent API for database operations on a specific connection.
 */
class ConnectionProxy
{
    /**
     * @var AsyncPDOConnection The underlying connection instance
     */
    private AsyncPDOConnection $connection;

    /**
     * @var string|null The name of this connection
     */
    private ?string $connectionName;

    /**
     * Create a new ConnectionProxy instance.
     *
     * @param AsyncPDOConnection $connection
     * @param string|null $connectionName
     */
    public function __construct(AsyncPDOConnection $connection, ?string $connectionName = null)
    {
        $this->connection = $connection;
        $this->connectionName = $connectionName;
    }

    /**
     * Get the underlying connection instance.
     *
     * @return AsyncPDOConnection
     */
    public function getConnection(): AsyncPDOConnection
    {
        return $this->connection;
    }

    /**
     * Get the name of this connection.
     *
     * @return string|null
     */
    public function getConnectionName(): ?string
    {
        return $this->connectionName;
    }

    /**
     * Start a new query builder instance for the given table.
     *
     * @param  string  $table  Table name
     * @return Builder
     */
    public function table(string $table): Builder
    {
        return new Builder($table, $this->connection);
    }

    /**
     * Start a new query builder instance with a specific driver.
     *
     * @param  string  $table  Table name
     * @param  string  $driver  Driver name (mysql, pgsql, sqlite, sqlsrv)
     * @return Builder
     */
    public function tableWithDriver(string $table, string $driver): Builder
    {
        $builder = new Builder($table, $this->connection);

        return $builder->setDriver($driver);
    }

    /**
     * Execute a raw query.
     *
     * @param  string  $sql  SQL query
     * @param  array<int|string, mixed>  $bindings  Query bindings
     * @return PromiseInterface<array<int, array<string, mixed>>>
     */
    public function raw(string $sql, array $bindings = []): PromiseInterface
    {
        return $this->connection->query($sql, $bindings);
    }

    /**
     * Execute a raw query and return the first result.
     *
     * @param  string  $sql  SQL query
     * @param  array<int|string, mixed>  $bindings  Query bindings
     * @return PromiseInterface<array<string, mixed>|false>
     */
    public function rawFirst(string $sql, array $bindings = []): PromiseInterface
    {
        return $this->connection->fetchOne($sql, $bindings);
    }

    /**
     * Execute a raw query and return a single scalar value.
     *
     * @param  string  $sql  SQL query
     * @param  array<int|string, mixed>  $bindings  Query bindings
     * @return PromiseInterface<mixed>
     */
    public function rawValue(string $sql, array $bindings = []): PromiseInterface
    {
        return $this->connection->fetchValue($sql, $bindings);
    }

    /**
     * Execute a raw statement (INSERT, UPDATE, DELETE).
     *
     * @param  string  $sql  SQL statement
     * @param  array<int|string, mixed>  $bindings  Query bindings
     * @return PromiseInterface<int>
     */
    public function rawExecute(string $sql, array $bindings = []): PromiseInterface
    {
        return $this->connection->execute($sql, $bindings);
    }

    /**
     * Run a database transaction with automatic retry on failure.
     *
     * @param  callable  $callback  Transaction callback
     * @param  int  $attempts  Number of times to attempt the transaction (default: 1)
     * @return PromiseInterface<mixed>
     */
    public function transaction(callable $callback, int $attempts = 1): PromiseInterface
    {
        return $this->connection->transaction($callback, $attempts);
    }

    /**
     * Register a callback to be executed when a transaction is committed.
     *
     * @param  callable  $callback  Callback to execute on commit
     * @return void
     */
    public function onCommit(callable $callback): void
    {
        $this->connection->onCommit($callback);
    }

    /**
     * Register a callback to be executed when a transaction is rolled back.
     *
     * @param  callable  $callback  Callback to execute on rollback
     * @return void
     */
    public function onRollback(callable $callback): void
    {
        $this->connection->onRollback($callback);
    }

    /**
     * Execute a callback with a PDO connection.
     *
     * @template TResult
     * @param  callable(\PDO): TResult  $callback  Callback that receives PDO instance
     * @return PromiseInterface<TResult>
     * @phpstan-return PromiseInterface<TResult>
     */
    public function run(callable $callback): PromiseInterface
    {
        /** @phpstan-var PromiseInterface<TResult> */
        return $this->connection->run($callback);
    }

    /**
     * Get statistics about this connection's pool.
     *
     * @return array<string, int|bool>
     */
    public function getStats(): array
    {
        return $this->connection->getStats();
    }
}
