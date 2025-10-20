<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema;

use Hibla\PdoQueryBuilder\Utilities\ConfigLoader;
use PDO;

/**
 * @phpstan-type TConnectionConfig array{
 *   driver: string,
 *   host?: string,
 *   port?: int|string,
 *   database?: string,
 *   username?: string,
 *   password?: string,
 *   charset?: string,
 *   collation?: string
 * }
 */
class DatabaseManager
{
    private string $driver;
    /** @var TConnectionConfig */
    private array $config;

    public function __construct()
    {
        $configLoader = ConfigLoader::getInstance();
        $dbConfig = $configLoader->get('pdo-query-builder');

        if (! is_array($dbConfig)) {
            throw new \RuntimeException('Invalid database configuration format');
        }

        $defaultConnection = $dbConfig['default'] ?? 'mysql';
        if (! is_string($defaultConnection)) {
            throw new \RuntimeException('Default connection name must be a string');
        }

        $connections = $dbConfig['connections'] ?? [];
        if (! is_array($connections)) {
            throw new \RuntimeException('Connections configuration must be an array');
        }

        $config = $connections[$defaultConnection] ?? [];
        if (! is_array($config)) {
            throw new \RuntimeException("Configuration for '{$defaultConnection}' connection is invalid");
        }

        /** @var TConnectionConfig $config */
        $this->config = $config;
        $this->driver = strtolower($this->config['driver'] ?? 'mysql');
    }

    /**
     * Create the configured database if it does not already exist.
     *
     * @throws \RuntimeException If the driver is unsupported or database creation fails.
     */
    public function createDatabaseIfNotExists(): bool
    {
        $database = $this->config['database'] ?? null;

        if (! is_string($database) || $database === '') {
            throw new \RuntimeException('Database name not specified or invalid in configuration');
        }

        try {
            return match ($this->driver) {
                'mysql' => $this->createMySQLDatabase($database),
                'pgsql' => $this->createPostgreSQLDatabase($database),
                'sqlite' => $this->createSQLiteDatabase($database),
                'sqlsrv' => $this->createSQLServerDatabase($database),
                default => throw new \RuntimeException("Unsupported driver: {$this->driver}"),
            };
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Failed to create database '{$database}': ".$e->getMessage(),
                0,
                $e
            );
        }
    }

    private function createMySQLDatabase(string $database): bool
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;charset=%s',
            $this->config['host'] ?? '127.0.0.1',
            $this->config['port'] ?? 3306,
            $this->config['charset'] ?? 'utf8mb4'
        );

        $pdo = new PDO(
            $dsn,
            $this->config['username'] ?? 'root',
            $this->config['password'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $charset = $this->config['charset'] ?? 'utf8mb4';
        $collation = $this->config['collation'] ?? 'utf8mb4_unicode_ci';

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET {$charset} COLLATE {$collation}");

        return true;
    }

    private function createPostgreSQLDatabase(string $database): bool
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=postgres',
            $this->config['host'] ?? '127.0.0.1',
            $this->config['port'] ?? 5432
        );

        $pdo = new PDO(
            $dsn,
            $this->config['username'] ?? 'postgres',
            $this->config['password'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $stmt = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
        $stmt->execute([$database]);
        $exists = $stmt->fetchColumn();

        if ($exists === false) {
            $pdo->exec("CREATE DATABASE \"{$database}\"");
        }

        return true;
    }

    private function createSQLiteDatabase(string $database): bool
    {
        if ($database === ':memory:') {
            return true;
        }

        $directory = dirname($database);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true)) {
            throw new \RuntimeException("Failed to create directory: {$directory}");
        }

        if (! file_exists($database)) {
            touch($database);
        }

        return true;
    }

    private function createSQLServerDatabase(string $database): bool
    {
        $dsn = sprintf(
            'sqlsrv:Server=%s,%s',
            $this->config['host'] ?? '127.0.0.1',
            (string) ($this->config['port'] ?? 1433)
        );

        $pdo = new PDO(
            $dsn,
            $this->config['username'] ?? 'sa',
            $this->config['password'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $stmt = $pdo->prepare('SELECT database_id FROM sys.databases WHERE name = ?');
        $stmt->execute([$database]);
        $exists = $stmt->fetchColumn();

        if ($exists === false) {
            $pdo->exec("CREATE DATABASE [{$database}]");
        }

        return true;
    }

    /**
     * Check if the configured database exists.
     */
    public function databaseExists(): bool
    {
        $database = $this->config['database'] ?? null;

        if (! is_string($database) || $database === '') {
            return false;
        }

        try {
            return match ($this->driver) {
                'mysql' => $this->checkMySQLDatabase($database),
                'pgsql' => $this->checkPostgreSQLDatabase($database),
                'sqlite' => $this->checkSQLiteDatabase($database),
                'sqlsrv' => $this->checkSQLServerDatabase($database),
                default => false,
            };
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkMySQLDatabase(string $database): bool
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s',
            $this->config['host'] ?? '127.0.0.1',
            $this->config['port'] ?? 3306
        );

        $pdo = new PDO(
            $dsn,
            $this->config['username'] ?? 'root',
            $this->config['password'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $stmt = $pdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
        $stmt->execute([$database]);

        return (bool) $stmt->fetchColumn();
    }

    private function checkPostgreSQLDatabase(string $database): bool
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=postgres',
            $this->config['host'] ?? '127.0.0.1',
            $this->config['port'] ?? 5432
        );

        $pdo = new PDO(
            $dsn,
            $this->config['username'] ?? 'postgres',
            $this->config['password'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $stmt = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
        $stmt->execute([$database]);

        return (bool) $stmt->fetchColumn();
    }



    private function checkSQLiteDatabase(string $database): bool
    {
        return $database === ':memory:' || file_exists($database);
    }

    private function checkSQLServerDatabase(string $database): bool
    {
        $dsn = sprintf(
            'sqlsrv:Server=%s,%s',
            $this->config['host'] ?? '127.0.0.1',
            (string) ($this->config['port'] ?? 1433)
        );

        $pdo = new PDO(
            $dsn,
            $this->config['username'] ?? 'sa',
            $this->config['password'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $stmt = $pdo->prepare('SELECT database_id FROM sys.databases WHERE name = ?');
        $stmt->execute([$database]);

        return (bool) $stmt->fetchColumn();
    }
}