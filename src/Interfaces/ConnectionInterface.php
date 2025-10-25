<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Interfaces;

use Hibla\Promise\Interfaces\PromiseInterface;

/**
 * Interface for database connection adapters.
 * 
 * This interface defines the contract that all connection adapters must implement,
 * whether they use PDO, native extensions, or other database drivers.
 */
interface ConnectionInterface
{
    /**
     * Execute a query and return all results.
     *
     * @param string $sql SQL query
     * @param array<int|string, mixed> $bindings Query bindings
     * @return PromiseInterface<array<int, array<string, mixed>>>
     */
    public function query(string $sql, array $bindings = []): PromiseInterface;

    /**
     * Execute a query and return the first result.
     *
     * @param string $sql SQL query
     * @param array<int|string, mixed> $bindings Query bindings
     * @return PromiseInterface<array<string, mixed>|false>
     */
    public function fetchOne(string $sql, array $bindings = []): PromiseInterface;

    /**
     * Execute a query and return a single scalar value.
     *
     * @param string $sql SQL query
     * @param array<int|string, mixed> $bindings Query bindings
     * @return PromiseInterface<mixed>
     */
    public function fetchValue(string $sql, array $bindings = []): PromiseInterface;

    /**
     * Execute a statement and return affected rows count.
     *
     * @param string $sql SQL statement
     * @param array<int|string, mixed> $bindings Query bindings
     * @return PromiseInterface<int>
     */
    public function execute(string $sql, array $bindings = []): PromiseInterface;

    /**
     * Execute a callback within a transaction.
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
     * Execute a callback with the underlying connection.
     *
     * @template TResult
     * @param callable $callback
     * @return PromiseInterface<TResult>
     */
    public function run(callable $callback): PromiseInterface;

    /**
     * Get connection pool statistics.
     *
     * @return array<string, int|bool>
     */
    public function getStats(): array;

    /**
     * Reset the connection.
     *
     * @return void
     */
    public function reset(): void;

    /**
     * Get the driver name for this connection.
     *
     * @return string
     */
    public function getDriver(): string;
}