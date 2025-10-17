<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema;

use Hibla\AsyncPDO\AsyncPDO;
use Hibla\PdoQueryBuilder\Utilities\ConfigLoader;
use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Promise\Promise;

use function Hibla\await;

class SchemaBuilder
{
    private string $driver;

    public function __construct(?string $driver = null)
    {
        $this->driver = $driver ?? $this->detectDriver();
    }

    private function detectDriver(): string
    {
        $configLoader = ConfigLoader::getInstance();
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

        return AsyncPDO::fetchValue($sql, []);
    }

    public function table(string $table, callable $callback): PromiseInterface
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);

        $compiler = $this->getCompiler();
        $sql = $compiler->compileAlter($blueprint);

        if (is_array($sql)) {
            return $this->executeMultiple($sql);
        }

        return AsyncPDO::execute($sql, []);
    }

    public function rename(string $from, string $to): PromiseInterface
    {
        $compiler = $this->getCompiler();
        $sql = $compiler->compileRename($from, $to);

        return AsyncPDO::execute($sql, []);
    }

    public function dropColumn(string $table, string|array $columns): PromiseInterface
    {
        $blueprint = new Blueprint($table);
        $blueprint->dropColumn($columns);

        $compiler = $this->getCompiler();
        $sql = $compiler->compileAlter($blueprint);

        if (is_array($sql)) {
            return $this->executeMultiple($sql);
        }

        return AsyncPDO::execute($sql, []);
    }

    public function renameColumn(string $table, string $from, string $to): PromiseInterface
    {
        $blueprint = new Blueprint($table);
        $blueprint->renameColumn($from, $to);

        $compiler = $this->getCompiler();
        $sql = $compiler->compileAlter($blueprint);

        if (is_array($sql)) {
            return $this->executeMultiple($sql);
        }

        return AsyncPDO::execute($sql, []);
    }

    public function dropIndex(string $table, string|array $index): PromiseInterface
    {
        $blueprint = new Blueprint($table);
        $blueprint->dropIndex($index);

        $compiler = $this->getCompiler();
        $sql = $compiler->compileAlter($blueprint);

        if (is_array($sql)) {
            return $this->executeMultiple($sql);
        }

        return AsyncPDO::execute($sql, []);
    }

    public function dropForeign(string $table, string|array $foreignKey): PromiseInterface
    {
        $blueprint = new Blueprint($table);
        $blueprint->dropForeign($foreignKey);

        $compiler = $this->getCompiler();
        $sql = $compiler->compileAlter($blueprint);

        if (is_array($sql)) {
            return $this->executeMultiple($sql);
        }

        return AsyncPDO::execute($sql, []);
    }

    private function executeMultiple(array $statements): PromiseInterface
    {
        return Promise::resolved(null)
            ->then(function () use ($statements) {
                $results = [];
                foreach ($statements as $sql) {
                    $results[] = await(AsyncPDO::execute($sql, []));
                }
                return $results;
            });
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