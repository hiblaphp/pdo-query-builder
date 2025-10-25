<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Interfaces;

use Hibla\PdoQueryBuilder\Utilities\Builder;
use Hibla\Promise\Interfaces\PromiseInterface;

/**
 * Interface for connection proxy implementations.
 * 
 * This interface defines the contract for connection proxies that provide
 * a fluent API for database operations.
 */
interface ProxyInterface
{
    /**
     * Get the underlying connection instance.
     *
     * @return ConnectionInterface
     */
    public function getConnection(): ConnectionInterface;

    /**
     * Get the name of this connection.
     *
     * @return string|null
     */
    public function getConnectionName(): ?string;

    /**
     * Start a new query builder instance for the given table.
     *
     * @param string $table Table name
     * @return Builder
     */
    public function table(string $table): Builder;

    /**
     * Start a new query builder instance with a specific driver.
     *
     * @param string $table Table name
     * @param string $driver Driver name
     * @return Builder
     */
    public function tableWithDriver(string $table, string $driver): Builder;

    /**
     * Execute a raw query.
     *
     * @param string $sql SQL query
     * @param array<int|string, mixed> $bindings Query bindings
     * @return PromiseInterface<array<int, array<string, mixed>>>
     */
    public function raw(string $sql, array $bindings = []): PromiseInterface;

    /**
     * Execute a raw query and return the first result.
     *
     * @param string $sql SQL query
     * @param array<int|string, mixed> $bindings Query bindings
     * @return PromiseInterface<array<string, mixed>|false>
     */
    public function rawFirst(string $sql, array $bindings = []): PromiseInterface;

    /**
     * Execute a raw query and return a single scalar value.
     *
     * @param string $sql SQL query
     * @param array<int|string, mixed> $bindings Query bindings
     * @return PromiseInterface<mixed>
     */
    public function rawValue(string $sql, array $bindings = []): PromiseInterface;

    /**
     * Execute a raw statement.
     *
     * @param string $sql SQL statement
     * @param array<int|string, mixed> $bindings Query bindings
     * @return PromiseInterface<int>
     */
    public function rawExecute(string $sql, array $bindings = []): PromiseInterface;

    /**
     * Run a database transaction.
     *
     * @param callable $callback Transaction callback
     * @param int $attempts Number of retry attempts
     * @return PromiseInterface<mixed>
     */
    public function transaction(callable $callback, int $attempts = 1): PromiseInterface;

    /**
     * Register a callback to execute on transaction commit.
     *
     * @param callable $callback
     * @return void
     */
    public function onCommit(callable $callback): void;

    /**
     * Register a callback to execute on transaction rollback.
     *
     * @param callable $callback
     * @return void
     */
    public function onRollback(callable $callback): void;

    /**
     * Get connection pool statistics.
     *
     * @return array<string, int|bool>
     */
    public function getStats(): array;
}