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

        // Process column-level indexes
        $this->processColumnIndexes($blueprint);

        $compiler = $this->getCompiler();
        $sql = $compiler->compileCreate($blueprint);

        // For SQLite, enable foreign keys before creating table
        if ($this->driver === 'sqlite') {
            return Promise::resolved(null)
                ->then(function () use ($sql) {
                    await(AsyncPDO::execute('PRAGMA foreign_keys = ON', []));
                    return await(AsyncPDO::execute($sql, []));
                });
        }

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

        // Process column-level indexes
        $this->processColumnIndexes($blueprint);

        $compiler = $this->getCompiler();
        
        if ($this->driver === 'sqlite') {
            $needsRecreation = !empty($blueprint->getDropColumns()) ||
                !empty($blueprint->getModifyColumns()) ||
                !empty($blueprint->getDropForeignKeys()) ||
                !empty($blueprint->getDropIndexes());
                
            if ($needsRecreation) {
                return Promise::resolved(null)
                    ->then(function () use ($table, $blueprint, $compiler) {
                        // Fetch existing columns from database
                        $existingColumns = await(AsyncPDO::query("PRAGMA table_info(`{$table}`)", []));
                        
                        // Pass to compiler
                        if (method_exists($compiler, 'setExistingTableColumns')) {
                            $compiler->setExistingTableColumns($existingColumns);
                        }
                        
                        // Enable foreign keys
                        await(AsyncPDO::execute('PRAGMA foreign_keys = ON', []));
                        
                        // Compile with complete information
                        $sql = $compiler->compileAlter($blueprint);
                        
                        if (is_array($sql)) {
                            // Execute all statements, handle errors properly
                            try {
                                foreach ($sql as $statement) {
                                    await(AsyncPDO::execute($statement, []));
                                }
                                return true;
                            } catch (\Throwable $e) {
                                // Try to rollback if we're in a transaction
                                try {
                                    await(AsyncPDO::execute('ROLLBACK', []));
                                } catch (\Throwable $rollbackError) {
                                    // Ignore rollback errors
                                }
                                throw $e;
                            }
                        }
                        
                        return await(AsyncPDO::execute($sql, []));
                    });
            }
        }
        
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

    /**
     * Process column-level indexes and add them to blueprint
     */
    private function processColumnIndexes(Blueprint $blueprint): void
    {
        foreach ($blueprint->getColumns() as $column) {
            foreach ($column->getColumnIndexes() as $indexInfo) {
                $indexName = $indexInfo['name'] ?? $blueprint->getTable() . '_' . $column->getName() . '_' . strtolower($indexInfo['type']);

                $indexDef = new IndexDefinition($indexInfo['type'], [$column->getName()], $indexName);

                if ($indexInfo['algorithm'] ?? null) {
                    $indexDef->algorithm($indexInfo['algorithm']);
                }

                if ($indexInfo['operatorClass'] ?? null) {
                    $indexDef->operatorClass($indexInfo['operatorClass']);
                }

                $reflection = new \ReflectionClass($blueprint);
                $property = $reflection->getProperty('indexDefinitions');
                $property->setAccessible(true);
                $indexDefinitions = $property->getValue($blueprint);
                $indexDefinitions[] = $indexDef;
                $property->setValue($blueprint, $indexDefinitions);
            }
        }
    }
}