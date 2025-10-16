<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema;

use Hibla\AsyncPDO\AsyncPDO;
use Hibla\Promise\Interfaces\PromiseInterface;

class SchemaBuilder
{
    private string $driver;

    public function __construct(?string $driver = null)
    {
        $this->driver = $driver ?? $this->detectDriver();
    }

    private function detectDriver(): string
    {
        $configLoader = \Hibla\PdoQueryBuilder\Utilities\ConfigLoader::getInstance();
        $dbConfig = $configLoader->get('pdo-query-builder');

        if (!is_array($dbConfig)) {
            return 'mysql';
        }

        $defaultConnection = $dbConfig['default'] ?? 'mysql';
        $connections = $dbConfig['connections'] ?? [];
        $connectionConfig = $connections[$defaultConnection] ?? [];

        return strtolower($connectionConfig['driver'] ?? 'mysql');
    }

    public function create(string $table, callable $callback): PromiseInterface
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);

        $compiler = $this->getCompiler();
        $sql = $compiler->compileCreate($blueprint);

        return AsyncPDO::execute($sql, []);
    }

    public function dropIfExists(string $table): PromiseInterface
    {
        $compiler = $this->getCompiler();
        $sql = $compiler->compileDropIfExists($table);

        return AsyncPDO::execute($sql, []);
    }

    public function drop(string $table): PromiseInterface
    {
        $compiler = $this->getCompiler();
        $sql = $compiler->compileDrop($table);

        return AsyncPDO::execute($sql, []);
    }

    public function hasTable(string $table): PromiseInterface
    {
        $compiler = $this->getCompiler();
        $sql = $compiler->compileTableExists($table);

        // Some drivers use placeholders, others use inline values
        // For now, all our compilers use inline values
        return AsyncPDO::fetchValue($sql, []);
    }

    public function table(string $table, callable $callback): PromiseInterface
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);

        $compiler = $this->getCompiler();
        $sql = $compiler->compileAlter($blueprint);

        return AsyncPDO::execute($sql, []);
    }

    private function getCompiler(): SchemaCompiler
    {
        return match ($this->driver) {
            'mysql' => new Compilers\MySQLSchemaCompiler(),
            'pgsql' => new Compilers\PostgreSQLSchemaCompiler(),
            'sqlite' => new Compilers\SQLiteSchemaCompiler(),
            'sqlsrv' => new Compilers\SQLServerSchemaCompiler(),
            default => new Compilers\MySQLSchemaCompiler(),
        };
    }
}