<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\AsyncPDO\AsyncPDOConnection;
use Hibla\PdoQueryBuilder\Exceptions\DatabaseConfigNotFoundException;
use Hibla\PdoQueryBuilder\Exceptions\InvalidConnectionConfigException;
use Hibla\PdoQueryBuilder\Exceptions\InvalidPoolSizeException;
use Hibla\PdoQueryBuilder\Utilities\Builder;
use Rcalicdan\ConfigLoader\Config;

/**
 * DB API - Main entry point for async database operations with multi-database support.
 */
class DB
{
    /** @var array<string, AsyncPDOConnection> Named connection instances */
    private static array $connections = [];

    /** @var string|null The default connection name */
    private static ?string $defaultConnectionName = null;

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct() {}

    /**
     * Get or create a connection proxy.
     *
     * @param  string|null  $name  Connection name from config, or null for default
     * @return ConnectionProxy Returns a ConnectionProxy bound to the specified connection
     *
     * @throws DatabaseConfigNotFoundException
     * @throws InvalidConnectionConfigException
     * @throws InvalidPoolSizeException
     */
    public static function connection(?string $name = null): ConnectionProxy
    {
        $connectionName = $name ?? self::getDefaultConnectionName();

        if (isset(self::$connections[$connectionName])) {
            return new ConnectionProxy(self::$connections[$connectionName], $connectionName);
        }

        $dbConfigAll = Config::get('pdo-query-builder');

        if (! is_array($dbConfigAll)) {
            throw new DatabaseConfigNotFoundException();
        }

        $connections = $dbConfigAll['connections'] ?? null;
        if (! is_array($connections)) {
            throw new InvalidConnectionConfigException('Database connections configuration must be an array.');
        }

        if (! isset($connections[$connectionName]) || ! is_array($connections[$connectionName])) {
            throw new InvalidConnectionConfigException("Connection '{$connectionName}' not found in configuration.");
        }

        $connectionConfig = $connections[$connectionName];

        /** @var array<string, mixed> $validatedConfig */
        $validatedConfig = [];
        $poolSize = 10;

        foreach ($connectionConfig as $key => $value) {
            if (! is_string($key)) {
                throw new InvalidConnectionConfigException('Database connection configuration must have string keys only.');
            }

            if ($key === 'pool_size') {
                if (! is_int($value) || $value < 1) {
                    throw new InvalidPoolSizeException();
                }
                $poolSize = $value;
                continue;
            }

            $validatedConfig[$key] = $value;
        }

        $connection = new AsyncPDOConnection($validatedConfig, $poolSize);
        self::$connections[$connectionName] = $connection;

        return new ConnectionProxy($connection, $connectionName);
    }

    /**
     * Initialize the default database connection manually.
     * This is useful when you want to set up the connection without a config file or for testing purposes.
     *
     * @param  array<string, mixed>  $config  Database connection configuration
     * @param  int  $poolSize  Connection pool size (default: 10)
     * @param  string  $name  Connection name (default: 'default')
     * @return ConnectionProxy
     *
     * @throws InvalidPoolSizeException
     */
    public static function init(array $config, int $poolSize = 10, string $name = 'default'): ConnectionProxy
    {
        if ($poolSize < 1) {
            throw new InvalidPoolSizeException();
        }

        $connection = new AsyncPDOConnection($config, $poolSize);
        self::$connections[$name] = $connection;

        if ($name === 'default' || self::$defaultConnectionName === null) {
            self::$defaultConnectionName = $name;
        }

        return new ConnectionProxy($connection, $name);
    }

    /**
     * Initialize multiple database connections at once.
     *
     * @param  array<string, array{config: array<string, mixed>, pool_size?: int}>  $connections
     * @param  string|null  $defaultConnection  The name of the default connection
     * @return void
     *
     * @throws InvalidPoolSizeException
     */
    public static function initMultiple(array $connections, ?string $defaultConnection = null): void
    {
        foreach ($connections as $name => $connectionData) {
            if (!is_string($name)) {
                throw new InvalidConnectionConfigException('Connection names must be strings.');
            }

            if (!isset($connectionData['config']) || !is_array($connectionData['config'])) {
                throw new InvalidConnectionConfigException("Connection '{$name}' must have a 'config' array.");
            }

            $config = $connectionData['config'];
            $poolSize = $connectionData['pool_size'] ?? 10;

            if (!is_int($poolSize) || $poolSize < 1) {
                throw new InvalidPoolSizeException();
            }

            $connection = new AsyncPDOConnection($config, $poolSize);
            self::$connections[$name] = $connection;
        }

        // Set default connection
        if ($defaultConnection !== null) {
            if (!isset(self::$connections[$defaultConnection])) {
                throw new InvalidConnectionConfigException("Default connection '{$defaultConnection}' does not exist.");
            }
            self::$defaultConnectionName = $defaultConnection;
        } elseif (self::$defaultConnectionName === null && self::$connections !== []) {
            // Set the first connection as default if no default is specified
            self::$defaultConnectionName = array_key_first(self::$connections);
        }
    }

    /**
     * Set the default connection name.
     *
     * @param  string  $name  Connection name
     * @return void
     *
     * @throws InvalidConnectionConfigException
     */
    public static function setDefaultConnection(string $name): void
    {
        if (!isset(self::$connections[$name])) {
            throw new InvalidConnectionConfigException("Connection '{$name}' does not exist.");
        }

        self::$defaultConnectionName = $name;
    }

    /**
     * Get the default connection name.
     *
     * @return string|null
     */
    public static function getDefaultConnection(): ?string
    {
        return self::$defaultConnectionName;
    }

    /**
     * Get the default connection name from config.
     *
     * @return string
     */
    private static function getDefaultConnectionName(): string
    {
        if (self::$defaultConnectionName !== null) {
            return self::$defaultConnectionName;
        }

        $dbConfigAll = Config::get('pdo-query-builder');

        if (! is_array($dbConfigAll)) {
            throw new DatabaseConfigNotFoundException();
        }

        $defaultConnection = $dbConfigAll['default'] ?? null;
        if (! is_string($defaultConnection)) {
            throw new InvalidConnectionConfigException('Default connection name must be a string in your database config.');
        }

        self::$defaultConnectionName = $defaultConnection;

        return $defaultConnection;
    }

    /**
     * Start a new query builder instance for the given table using the default connection.
     *
     * @param  string  $table  Table name
     * @return Builder
     */
    public static function table(string $table): Builder
    {
        $proxy = self::connection();
        return $proxy->table($table);
    }

    /**
     * Execute a raw query on the default connection.
     *
     * @param  string  $sql  SQL query
     * @param  array<int|string, mixed>  $bindings  Query bindings
     * @return PromiseInterface<array<int, array<string, mixed>>>
     */
    public static function raw(string $sql, array $bindings = []): PromiseInterface
    {
        $proxy = self::connection();
        return $proxy->raw($sql, $bindings);
    }

    /**
     * Execute a raw query and return the first result on the default connection.
     *
     * @param  string  $sql  SQL query
     * @param  array<int|string, mixed>  $bindings  Query bindings
     * @return PromiseInterface<array<string, mixed>|false>
     */
    public static function rawFirst(string $sql, array $bindings = []): PromiseInterface
    {
        $proxy = self::connection();
        return $proxy->rawFirst($sql, $bindings);
    }

    /**
     * Execute a raw query and return a single scalar value on the default connection.
     *
     * @param  string  $sql  SQL query
     * @param  array<int|string, mixed>  $bindings  Query bindings
     * @return PromiseInterface<mixed>
     */
    public static function rawValue(string $sql, array $bindings = []): PromiseInterface
    {
        $proxy = self::connection();
        return $proxy->rawValue($sql, $bindings);
    }

    /**
     * Execute a raw statement (INSERT, UPDATE, DELETE) on the default connection.
     *
     * @param  string  $sql  SQL statement
     * @param  array<int|string, mixed>  $bindings  Query bindings
     * @return PromiseInterface<int>
     */
    public static function rawExecute(string $sql, array $bindings = []): PromiseInterface
    {
        $proxy = self::connection();
        return $proxy->rawExecute($sql, $bindings);
    }

    /**
     * Run a database transaction on the default connection.
     *
     * @param  callable  $callback  Transaction callback
     * @param  int  $attempts  Number of times to attempt the transaction (default: 1)
     * @return PromiseInterface<mixed>
     *
     * @throws DatabaseConfigNotFoundException
     * @throws InvalidConnectionConfigException
     * @throws InvalidPoolSizeException
     */
    public static function transaction(callable $callback, int $attempts = 1): PromiseInterface
    {
        $proxy = self::connection();
        return $proxy->transaction($callback, $attempts);
    }

    /**
     * Register a callback to execute when the current transaction rolls back.
     */
    public static function onRollback(callable $callback): void
    {
        $proxy = self::connection();
        $proxy->onRollback($callback);
    }

    /**
     * Register a callback to execute when the current transaction commits.
     */
    public static function onCommit(callable $callback): void
    {
        $proxy = self::connection();
        $proxy->onCommit($callback);
    }

    /**
     * Execute a callback with a PDO connection on the default connection.
     *
     * @template TResult
     * @param  callable(\PDO): TResult  $callback  Callback that receives PDO instance
     * @return PromiseInterface<TResult>
     * @phpstan-return PromiseInterface<TResult>
     */
    public static function run(callable $callback): PromiseInterface
    {
        $proxy = self::connection();
        /** @phpstan-var PromiseInterface<TResult> */
        return $proxy->run($callback);
    }

    /**
     * Manually create a connection with custom configuration.
     *
     * @param  string  $name  Connection name
     * @param  array<string, mixed>  $connectionConfig  Database connection configuration
     * @param  int  $poolSize  Connection pool size (default: 10)
     * @return ConnectionProxy
     */
    public static function addConnection(string $name, array $connectionConfig, int $poolSize = 10): ConnectionProxy
    {
        if ($poolSize < 1) {
            throw new InvalidPoolSizeException();
        }

        $connection = new AsyncPDOConnection($connectionConfig, $poolSize);
        self::$connections[$name] = $connection;

        return new ConnectionProxy($connection, $name);
    }

    /**
     * Remove a connection by name.
     *
     * @param  string  $name  Connection name
     * @return void
     */
    public static function removeConnection(string $name): void
    {
        if (isset(self::$connections[$name])) {
            self::$connections[$name]->reset();
            unset(self::$connections[$name]);
        }

        if (self::$defaultConnectionName === $name) {
            self::$defaultConnectionName = null;
        }
    }

    /**
     * Get all registered connection names.
     *
     * @return array<string>
     */
    public static function getConnectionNames(): array
    {
        return array_keys(self::$connections);
    }

    /**
     * Check if a connection exists.
     *
     * @param  string  $name  Connection name
     * @return bool
     */
    public static function hasConnection(string $name): bool
    {
        return isset(self::$connections[$name]);
    }

    /**
     * Resets the entire database system. Crucial for isolated testing.
     */
    public static function reset(): void
    {
        foreach (self::$connections as $connection) {
            $connection->reset();
        }

        self::$connections = [];
        self::$defaultConnectionName = null;
        Config::reset();
        Builder::resetDriverCache();
    }
}
