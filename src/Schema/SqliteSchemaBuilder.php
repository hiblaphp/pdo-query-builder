<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema;

use function Hibla\async;

use Hibla\AsyncPDO\AsyncPDO;

use function Hibla\await;

use Hibla\Promise\Interfaces\PromiseInterface;

class SQLiteSchemaBuilder
{
    private SchemaCompiler $compiler;

    public function __construct(SchemaCompiler $compiler)
    {
        $this->compiler = $compiler;
    }

    /**
     * Handle CREATE TABLE for SQLite.
     *
     * @param string $sql
     * @return PromiseInterface<int|null>
     */
    public function handleCreate(string $sql): PromiseInterface
    {
        /** @phpstan-ignore-next-line */
        return async(function () use ($sql) {
            await(AsyncPDO::execute('PRAGMA foreign_keys = ON', []));

            return await(AsyncPDO::execute($sql, []));
        });
    }

    /**
     * Handle table modification for SQLite.
     *
     * @param string $table
     * @param Blueprint $blueprint
     * @return PromiseInterface<int|list<int>|null|bool>
     */
    public function handleTable(string $table, Blueprint $blueprint): PromiseInterface
    {
        $needsRecreation = count($blueprint->getDropColumns()) > 0 ||
            count($blueprint->getModifyColumns()) > 0 ||
            count($blueprint->getDropForeignKeys()) > 0 ||
            count($blueprint->getDropIndexes()) > 0;

        if (! $needsRecreation) {
            /** @phpstan-ignore-next-line */
            return $this->executeAlter($blueprint);
        }

        /** @phpstan-ignore-next-line */
        return $this->handleTableRecreation($table, $blueprint);
    }

    /**
     * Handle DROP COLUMN for SQLite.
     *
     * @param string $table
     * @param Blueprint $blueprint
     * @return PromiseInterface<int|list<int>|bool>
     */
    public function handleDropColumn(string $table, Blueprint $blueprint): PromiseInterface
    {
        return $this->executeTableRecreation($table, $blueprint);
    }

    /**
     * Handle DROP INDEX for SQLite.
     *
     * @param string $table
     * @param Blueprint $blueprint
     * @return PromiseInterface<int|list<int>|bool>
     */
    public function handleDropIndex(string $table, Blueprint $blueprint): PromiseInterface
    {
        return $this->executeTableRecreation($table, $blueprint);
    }

    /**
     * Handle DROP FOREIGN KEY for SQLite.
     *
     * @param string $table
     * @param Blueprint $blueprint
     * @return PromiseInterface<int|list<int>|bool>
     */
    public function handleDropForeign(string $table, Blueprint $blueprint): PromiseInterface
    {
        return $this->executeTableRecreation($table, $blueprint);
    }

    /**
     * Execute table recreation for schema modifications.
     *
     * @param string $table
     * @param Blueprint $blueprint
     * @return PromiseInterface<int|list<int>|bool>
     */
    private function handleTableRecreation(string $table, Blueprint $blueprint): PromiseInterface
    {
        /** @phpstan-ignore-next-line */
        return async(function () use ($table, $blueprint) {
            $existingColumns = await(AsyncPDO::query("PRAGMA table_info(`{$table}`)", []));

            if (method_exists($this->compiler, 'setExistingTableColumns')) {
                $this->compiler->setExistingTableColumns($existingColumns);
            }

            await(AsyncPDO::execute('PRAGMA foreign_keys = ON', []));

            $sql = $this->compiler->compileAlter($blueprint);

            if (is_array($sql)) {
                return $this->executeStatements($sql);
            }

            return await(AsyncPDO::execute($sql, []));
        });
    }

    /**
     * Execute table recreation.
     *
     * @param string $table
     * @param Blueprint $blueprint
     * @return PromiseInterface<int|list<int>|bool>
     */
    private function executeTableRecreation(string $table, Blueprint $blueprint): PromiseInterface
    {
        /** @phpstan-ignore-next-line */
        return async(function () use ($table, $blueprint) {
            $existingColumns = await(AsyncPDO::query("PRAGMA table_info(`{$table}`)", []));

            if (method_exists($this->compiler, 'setExistingTableColumns')) {
                $this->compiler->setExistingTableColumns($existingColumns);
            }

            await(AsyncPDO::execute('PRAGMA foreign_keys = ON', []));

            $sql = $this->compiler->compileAlter($blueprint);

            if (is_array($sql)) {
                return count($sql) === 0 ? true : $this->executeStatements($sql);
            }

            return await(AsyncPDO::execute($sql, []));
        });
    }

    /**
     * Execute ALTER TABLE statements.
     *
     * @param Blueprint $blueprint
     * @return PromiseInterface<int|list<int>|bool>
     */
    private function executeAlter(Blueprint $blueprint): PromiseInterface
    {
        /** @phpstan-ignore-next-line */
        return async(function () use ($blueprint) {
            $sql = $this->compiler->compileAlter($blueprint);

            if (is_array($sql)) {
                return count($sql) === 0 ? true : $this->executeMultiple($sql);
            }

            return await(AsyncPDO::execute($sql, []));
        });
    }

    /**
     * Execute a list of SQL statements.
     *
     * @param list<string> $statements
     * @return PromiseInterface<bool>
     */
    private function executeStatements(array $statements): PromiseInterface
    {
        return async(function () use ($statements) {
            try {
                foreach ($statements as $statement) {
                    await(AsyncPDO::execute($statement, []));
                }

                return true;
            } catch (\Throwable $e) {
                try {
                    await(AsyncPDO::execute('ROLLBACK', []));
                } catch (\Throwable $rollbackError) {
                }

                throw $e;
            }
        });
    }

    /**
     * Execute multiple SQL statements and return results.
     *
     * @param list<string> $statements
     * @return PromiseInterface<list<int>>
     */
    private function executeMultiple(array $statements): PromiseInterface
    {
        return async(function () use ($statements) {
            $results = [];
            foreach ($statements as $sql) {
                $results[] = await(AsyncPDO::execute($sql, []));
            }

            return $results;
        });
    }
}
