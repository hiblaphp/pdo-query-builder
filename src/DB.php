<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder;

use Hibla\AsyncPDO\AsyncPDO;
use Hibla\PdoQueryBuilder\Exceptions\DatabaseConfigNotFoundException;
use Hibla\PdoQueryBuilder\Exceptions\InvalidConnectionConfigException;
use Hibla\PdoQueryBuilder\Exceptions\InvalidDefaultConnectionException;
use Hibla\PdoQueryBuilder\Exceptions\InvalidPoolSizeException;
use Hibla\PdoQueryBuilder\Utilities\Builder;
use Hibla\Promise\Interfaces\PromiseInterface;
use Rcalicdan\ConfigLoader\Config;

/**
 * DB API - Main entry point for auto-configured async database operations using AsyncPDO under the hood
 * with asynchonous query builder support.
 *
 * This API automatically loads configuration from .env and config/database.php
 * the first time it is used, providing a zero-setup experience for the developer.
 */
class DB
{
    private static bool $isInitialized = false;
    private static bool $hasValidationError = false;
    private static bool $isManuallyConfigured = false;

    /**
     * Manually initialize the database with custom configuration.
     * This bypasses the auto-configuration from config files.
     *
     * @param  array<string, mixed>  $connectionConfig  Database connection configuration
     * @param  int  $poolSize  Connection pool size (default: 10)
     *
     * @throws InvalidPoolSizeException
     */
    public static function init(array $connectionConfig, int $poolSize = 10): void
    {
        if ($poolSize < 1) {
            throw new InvalidPoolSizeException();
        }

        AsyncPDO::init($connectionConfig, $poolSize);

        self::$isManuallyConfigured = true;
        self::$isInitialized = true;
        self::$hasValidationError = false;
    }

    /**
     * The core of the new design: A private, self-configuring initializer.
     * This method is called by every public method to ensure the system is ready.
     * Validates only once if successful, but re-validates if there were previous errors.
     */
    private static function initializeIfNeeded(): void
    {
        if (self::$isManuallyConfigured && self::$isInitialized) {
            return;
        }

        if (self::$isInitialized && ! self::$hasValidationError) {
            return;
        }

        self::$hasValidationError = false;

        try {
            $dbConfigAll = Config::get('pdo-query-builder');

            if (! is_array($dbConfigAll)) {
                throw new DatabaseConfigNotFoundException();
            }

            $defaultConnection = $dbConfigAll['default'] ?? null;
            if (! is_string($defaultConnection)) {
                throw new InvalidConnectionConfigException('Default connection name must be a string in your database config.');
            }

            $connections = $dbConfigAll['connections'] ?? null;
            if (! is_array($connections)) {
                throw new InvalidConnectionConfigException('Database connections configuration must be an array.');
            }

            if (! isset($connections[$defaultConnection]) || ! is_array($connections[$defaultConnection])) {
                throw new InvalidDefaultConnectionException($defaultConnection);
            }

            $connectionConfig = $connections[$defaultConnection];

            /** @var array<string, mixed> $validatedConfig */
            $validatedConfig = [];
            foreach ($connectionConfig as $key => $value) {
                if (! is_string($key)) {
                    throw new InvalidConnectionConfigException('Database connection configuration must have string keys only.');
                }
                $validatedConfig[$key] = $value;
            }

            $poolSize = 10;
            if (isset($dbConfigAll['pool_size'])) {
                if (! is_int($dbConfigAll['pool_size']) || $dbConfigAll['pool_size'] < 1) {
                    throw new InvalidPoolSizeException();
                }
                $poolSize = $dbConfigAll['pool_size'];
            }

            AsyncPDO::init($validatedConfig, $poolSize);
            self::$isInitialized = true;
        } catch (\Exception $e) {
            self::$hasValidationError = true;
            self::$isInitialized = false;

            throw $e;
        }
    }

    /**
     * Resets the entire database system. Crucial for isolated testing.
     */
    public static function reset(): void
    {
        AsyncPDO::reset();
        Config::reset();
        Builder::resetDriverCache();
        self::$isInitialized = false;
        self::$hasValidationError = false;
        self::$isManuallyConfigured = false;
    }

    /**
     * Start a new query builder instance for the given table.
     */
    public static function table(string $table): Builder
    {
        self::initializeIfNeeded();

        return new Builder($table);
    }

    /**
     * Start a new query builder instance with a specific driver.
     * Useful for testing or multi-database scenarios.
     */
    public static function tableWithDriver(string $table, string $driver): Builder
    {
        self::initializeIfNeeded();

        $builder = new Builder($table);

        return $builder->setDriver($driver);
    }

    /**
     * Execute a raw query.
     *
     * @param  array<string, mixed>  $bindings
     * @return PromiseInterface<array<int, array<string, mixed>>>
     */
    public static function raw(string $sql, array $bindings = []): PromiseInterface
    {
        self::initializeIfNeeded();

        return AsyncPDO::query($sql, $bindings);
    }

    /**
     * Execute a raw query and return the first result.
     *
     * @param  array<string, mixed>  $bindings
     * @return PromiseInterface<array<string, mixed>|false>
     */
    public static function rawFirst(string $sql, array $bindings = []): PromiseInterface
    {
        self::initializeIfNeeded();

        return AsyncPDO::fetchOne($sql, $bindings);
    }

    /**
     * Execute a raw query and return a single scalar value.
     *
     * @param  array<string, mixed>  $bindings
     * @return PromiseInterface<mixed>
     */
    public static function rawValue(string $sql, array $bindings = []): PromiseInterface
    {
        self::initializeIfNeeded();

        return AsyncPDO::fetchValue($sql, $bindings);
    }

    /**
     * Execute a raw statement (INSERT, UPDATE, DELETE).
     *
     * @param  array<string, mixed>  $bindings
     * @return PromiseInterface<int>
     */
    public static function rawExecute(string $sql, array $bindings = []): PromiseInterface
    {
        self::initializeIfNeeded();

        return AsyncPDO::execute($sql, $bindings);
    }

    /**
     * Run a database transaction with automatic retry on failure.
     *
     * @param  int  $attempts  Number of times to attempt the transaction (default: 1)
     * @return PromiseInterface<mixed>
     */
    public static function transaction(callable $callback, int $attempts = 1): PromiseInterface
    {
        self::initializeIfNeeded();

        return AsyncPDO::transaction($callback, $attempts);
    }

    /**
     * Register a callback to be executed when a transaction is committed.
     */
    public static function onCommit(callable $callback): void
    {
        self::initializeIfNeeded();

        AsyncPDO::onCommit($callback);
    }

    /**
     * Register a callback to be executed when a transaction is rolled back.
     */
    public static function onRollback(callable $callback): void
    {
        self::initializeIfNeeded();

        AsyncPDO::onRollback($callback);
    }
}
