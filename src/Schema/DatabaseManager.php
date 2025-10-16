<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema;

use Hibla\PdoQueryBuilder\Utilities\ConfigLoader;
use PDO;

class DatabaseManager
{
    private string $driver;
    private array $config;

    public function __construct()
    {
        $configLoader = ConfigLoader::getInstance();
        $dbConfig = $configLoader->get('pdo-query-builder');

        if (!is_array($dbConfig)) {
            throw new \RuntimeException('Invalid database configuration');
        }

        $defaultConnection = $dbConfig['default'] ?? 'mysql';
        $connections = $dbConfig['connections'] ?? [];
        $this->config = $connections[$defaultConnection] ?? [];
        $this->driver = strtolower($this->config['driver'] ?? 'mysql');
    }

    public function createDatabaseIfNotExists(): bool
    {
        $database = $this->config['database'] ?? null;
        
        if (!$database) {
            throw new \RuntimeException('Database name not specified in configuration');
        }

        try {
            return match ($this->driver) {
                'mysql' => $this->createMySQLDatabase($database),
                'pgsql' => $this->createPostgreSQLDatabase($database),
                'sqlite' => $this->createSQLiteDatabase(),
                'sqlsrv' => $this->createSQLServerDatabase($database),
                default => throw new \RuntimeException("Unsupported driver: {$this->driver}"),
            };
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Failed to create database '{$database}': " . $e->getMessage(),
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

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` 
                    CHARACTER SET {$charset} 
                    COLLATE {$collation}");

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

        $stmt = $pdo->query("SELECT 1 FROM pg_database WHERE datname = '{$database}'");
        $exists = $stmt->fetchColumn();

        if (!$exists) {
            $pdo->exec("CREATE DATABASE \"{$database}\"");
        }

        return true;
    }

    private function createSQLiteDatabase(): bool
    {
        $database = $this->config['database'] ?? '';
        
        if ($database === ':memory:') {
            return true; 
        }

        $directory = dirname($database);
        
        if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
            throw new \RuntimeException("Failed to create directory: {$directory}");
        }

        if (!file_exists($database)) {
            touch($database);
        }

        return true;
    }

    private function createSQLServerDatabase(string $database): bool
    {
        $dsn = sprintf(
            'sqlsrv:Server=%s,%s',
            $this->config['host'] ?? '127.0.0.1',
            $this->config['port'] ?? 1433
        );

        $pdo = new PDO(
            $dsn,
            $this->config['username'] ?? 'sa',
            $this->config['password'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $stmt = $pdo->query("SELECT database_id FROM sys.databases WHERE name = '{$database}'");
        $exists = $stmt->fetchColumn();

        if (!$exists) {
            $pdo->exec("CREATE DATABASE [{$database}]");
        }

        return true;
    }

    public function databaseExists(): bool
    {
        $database = $this->config['database'] ?? null;
        
        if (!$database) {
            return false;
        }

        try {
            return match ($this->driver) {
                'mysql' => $this->checkMySQLDatabase($database),
                'pgsql' => $this->checkPostgreSQLDatabase($database),
                'sqlite' => $this->checkSQLiteDatabase(),
                'sqlsrv' => $this->checkSQLServerDatabase($database),
                default => false,
            };
        } catch (\Throwable $e) {
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

        $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '{$database}'");
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

        $stmt = $pdo->query("SELECT 1 FROM pg_database WHERE datname = '{$database}'");
        return (bool) $stmt->fetchColumn();
    }

    private function checkSQLiteDatabase(): bool
    {
        $database = $this->config['database'] ?? '';
        return $database === ':memory:' || file_exists($database);
    }

    private function checkSQLServerDatabase(string $database): bool
    {
        $dsn = sprintf(
            'sqlsrv:Server=%s,%s',
            $this->config['host'] ?? '127.0.0.1',
            $this->config['port'] ?? 1433
        );

        $pdo = new PDO(
            $dsn,
            $this->config['username'] ?? 'sa',
            $this->config['password'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $stmt = $pdo->query("SELECT database_id FROM sys.databases WHERE name = '{$database}'");
        return (bool) $stmt->fetchColumn();
    }
}