<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema;

use Hibla\AsyncPDO\AsyncPDO;
use Hibla\Promise\Interfaces\PromiseInterface;

use function Hibla\async;
use function Hibla\await;

class SQLiteSchemaBuilder
{
    private SchemaCompiler $compiler;

    public function __construct(SchemaCompiler $compiler)
    {
        $this->compiler = $compiler;
    }

    public function handleCreate(string $sql): PromiseInterface
    {
        return async(function () use ($sql) {
            await(AsyncPDO::execute('PRAGMA foreign_keys = ON', []));
            return await(AsyncPDO::execute($sql, []));
        });
    }

    public function handleTable(string $table, Blueprint $blueprint): PromiseInterface
    {
        $needsRecreation = !empty($blueprint->getDropColumns()) ||
            !empty($blueprint->getModifyColumns()) ||
            !empty($blueprint->getDropForeignKeys()) ||
            !empty($blueprint->getDropIndexes());

        if (!$needsRecreation) {
            return $this->executeAlter($blueprint);
        }

        return $this->handleTableRecreation($table, $blueprint);
    }

    public function handleDropColumn(string $table, Blueprint $blueprint): PromiseInterface
    {
        return $this->executeTableRecreation($table, $blueprint);
    }

    public function handleDropIndex(string $table, Blueprint $blueprint): PromiseInterface
    {
        return $this->executeTableRecreation($table, $blueprint);
    }

    public function handleDropForeign(string $table, Blueprint $blueprint): PromiseInterface
    {
        return $this->executeTableRecreation($table, $blueprint);
    }

    private function handleTableRecreation(string $table, Blueprint $blueprint): PromiseInterface
    {
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

    private function executeTableRecreation(string $table, Blueprint $blueprint): PromiseInterface
    {
        return async(function () use ($table, $blueprint) {
            $existingColumns = await(AsyncPDO::query("PRAGMA table_info(`{$table}`)", []));

            if (method_exists($this->compiler, 'setExistingTableColumns')) {
                $this->compiler->setExistingTableColumns($existingColumns);
            }

            await(AsyncPDO::execute('PRAGMA foreign_keys = ON', []));

            $sql = $this->compiler->compileAlter($blueprint);

            if (is_array($sql)) {
                return empty($sql) ? true : $this->executeStatements($sql);
            }

            return await(AsyncPDO::execute($sql, []));
        });
    }

    private function executeAlter(Blueprint $blueprint): PromiseInterface
    {
        return async(function () use ($blueprint) {
            $sql = $this->compiler->compileAlter($blueprint);

            if (is_array($sql)) {
                return empty($sql) ? true : $this->executeMultiple($sql);
            }

            return await(AsyncPDO::execute($sql, []));
        });
    }

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