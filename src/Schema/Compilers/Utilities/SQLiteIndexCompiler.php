<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema\Compilers\Utilities;

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Hibla\PdoQueryBuilder\Schema\Column;
use Hibla\PdoQueryBuilder\Schema\IndexDefinition;

class SQLiteIndexCompiler extends IndexCompiler
{
    public function __construct()
    {
        $this->columnDelimiter = '`, `';
        $this->openQuote = '`';
        $this->closeQuote = '`';
    }

    public function compileAddIndexDefinition(string $table, array $indexDefs): array
    {
        $statements = [];
        foreach ($indexDefs as $indexDef) {
            if ($indexDef->getType() === 'PRIMARY') {
                continue;
            }

            $type = $indexDef->getType();
            $cols = $this->getColumnsList($indexDef);

            if ($type === 'UNIQUE') {
                $statements[] = "CREATE UNIQUE INDEX IF NOT EXISTS `{$indexDef->getName()}` ON `{$table}` (`{$cols}`)";
            } elseif (in_array($type, ['INDEX', 'FULLTEXT', 'SPATIAL'])) {
                $statements[] = "CREATE INDEX IF NOT EXISTS `{$indexDef->getName()}` ON `{$table}` (`{$cols}`)";
            }
        }

        return $statements;
    }

    public function compileTableRecreation(Blueprint $blueprint, array $existingTableColumns, $compiler): array
    {
        $table = $blueprint->getTable();
        $tempTable = "temp_{$table}_".bin2hex(random_bytes(4));

        $statements = [];
        $statements[] = 'PRAGMA foreign_keys=OFF';

        $newBlueprint = $this->buildNewBlueprint($blueprint, $tempTable, $existingTableColumns);
        $statements[] = $compiler->compileCreate($newBlueprint);

        $transferInfo = $this->getTransferColumns($blueprint, $existingTableColumns);
        if (! empty($transferInfo['old']) && ! empty($transferInfo['new'])) {
            $oldCols = implode('`, `', $transferInfo['old']);
            $newCols = implode('`, `', $transferInfo['new']);
            $statements[] = "INSERT INTO `{$tempTable}` (`{$newCols}`) SELECT `{$oldCols}` FROM `{$table}`";
        }

        $statements[] = "DROP TABLE `{$table}`";
        $statements[] = "ALTER TABLE `{$tempTable}` RENAME TO `{$table}`";

        $dropIndexNames = array_map(fn ($idx) => $idx[0], $blueprint->getDropIndexes());
        foreach ($blueprint->getIndexDefinitions() as $indexDef) {
            $indexName = $indexDef->getName();
            if ($indexDef->getType() !== 'PRIMARY' && ! in_array($indexName, $dropIndexNames)) {
                $indexStatements = $this->compileAddIndexDefinition($table, [$indexDef]);
                $statements = array_merge($statements, $indexStatements);
            }
        }

        $statements[] = 'PRAGMA foreign_key_check';
        $statements[] = 'PRAGMA foreign_keys=ON';

        return $statements;
    }

    private function buildNewBlueprint(Blueprint $originalBlueprint, string $newTableName, array $existingTableColumns): Blueprint
    {
        $newBlueprint = new Blueprint($newTableName);

        $dropColumns = $originalBlueprint->getDropColumns();
        $renameMap = $this->getRenameMap($originalBlueprint->getRenameColumns());
        $modifyMap = $this->getModifyMap($originalBlueprint->getModifyColumns());

        foreach ($existingTableColumns as $existingCol) {
            $columnName = $existingCol['name'];

            if (in_array($columnName, $dropColumns)) {
                continue;
            }

            if (isset($renameMap[$columnName])) {
                $column = $this->createColumnFromPragma($existingCol);
                $newColumn = $column->copyWithName($renameMap[$columnName]);
                $newColumn->setBlueprint($newBlueprint);
                $this->addColumnToBlueprint($newBlueprint, $newColumn);

                continue;
            }

            if (isset($modifyMap[$columnName])) {
                $modifiedColumn = $modifyMap[$columnName];
                $modifiedColumn->setBlueprint($newBlueprint);
                $this->addColumnToBlueprint($newBlueprint, $modifiedColumn);

                continue;
            }

            $column = $this->createColumnFromPragma($existingCol);
            $column->setBlueprint($newBlueprint);
            $this->addColumnToBlueprint($newBlueprint, $column);
        }

        foreach ($originalBlueprint->getColumns() as $column) {
            $clonedColumn = clone $column;
            $clonedColumn->setBlueprint($newBlueprint);
            $this->addColumnToBlueprint($newBlueprint, $clonedColumn);
        }

        $dropIndexNames = array_map(fn ($idx) => $idx[0], $originalBlueprint->getDropIndexes());
        foreach ($originalBlueprint->getIndexDefinitions() as $indexDef) {
            $indexName = $indexDef->getName() ?? 'PRIMARY';
            if (! in_array($indexName, $dropIndexNames)) {
                $this->addIndexDefinitionToBlueprint($newBlueprint, $indexDef);
            }
        }

        $dropForeignKeys = $originalBlueprint->getDropForeignKeys();
        foreach ($originalBlueprint->getForeignKeys() as $foreignKey) {
            if (! in_array($foreignKey->getName(), $dropForeignKeys)) {
                $this->addForeignKeyToBlueprint($newBlueprint, $foreignKey);
            }
        }

        return $newBlueprint;
    }

    private function createColumnFromPragma(array $pragmaRow): Column
    {
        $column = new Column($pragmaRow['name'], $this->mapSqliteTypeToGeneric($pragmaRow['type']));

        if ($pragmaRow['notnull'] == 0) {
            $column->nullable();
        }

        if ($pragmaRow['dflt_value'] !== null) {
            $column->default($this->parseDefaultValue($pragmaRow['dflt_value']));
        }

        if ($pragmaRow['pk'] == 1) {
            $column->primary();
            if (stripos($pragmaRow['type'], 'INTEGER') !== false) {
                $column->autoIncrement();
            }
        }

        return $column;
    }

    private function mapSqliteTypeToGeneric(string $sqliteType): string
    {
        $sqliteType = strtoupper($sqliteType);

        if (str_contains($sqliteType, 'INT')) {
            return 'INTEGER';
        } elseif (str_contains($sqliteType, 'CHAR') || str_contains($sqliteType, 'TEXT')) {
            return 'TEXT';
        } elseif (str_contains($sqliteType, 'REAL') || str_contains($sqliteType, 'FLOA') || str_contains($sqliteType, 'DOUB')) {
            return 'REAL';
        }

        return 'TEXT';
    }

    private function parseDefaultValue(string $value): mixed
    {
        if (preg_match("/^'(.*)'$/", $value, $matches)) {
            return $matches[1];
        }

        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float) $value : (int) $value;
        }

        if (strtoupper($value) === 'NULL') {
            return null;
        }

        return $value;
    }

    private function getTransferColumns(Blueprint $blueprint, array $existingTableColumns): array
    {
        $oldColumns = [];
        $newColumns = [];
        $dropColumns = $blueprint->getDropColumns();
        $renameMap = $this->getRenameMap($blueprint->getRenameColumns());

        foreach ($existingTableColumns as $existingCol) {
            $columnName = $existingCol['name'];

            if (in_array($columnName, $dropColumns)) {
                continue;
            }

            $oldColumns[] = $columnName;
            $newColumns[] = $renameMap[$columnName] ?? $columnName;
        }

        return ['old' => $oldColumns, 'new' => $newColumns];
    }

    private function getRenameMap(array $renameColumns): array
    {
        $map = [];
        foreach ($renameColumns as $rename) {
            $map[$rename['from']] = $rename['to'];
        }

        return $map;
    }

    private function getModifyMap(array $modifyColumns): array
    {
        $map = [];
        foreach ($modifyColumns as $column) {
            $map[$column->getName()] = $column;
        }

        return $map;
    }

    private function addColumnToBlueprint(Blueprint $blueprint, Column $column): void
    {
        $reflection = new \ReflectionClass($blueprint);
        $property = $reflection->getProperty('columns');
        $property->setAccessible(true);
        $columns = $property->getValue($blueprint);
        $columns[] = $column;
        $property->setValue($blueprint, $columns);
    }

    private function addIndexDefinitionToBlueprint(Blueprint $blueprint, IndexDefinition $indexDef): void
    {
        $reflection = new \ReflectionClass($blueprint);
        $property = $reflection->getProperty('indexDefinitions');
        $property->setAccessible(true);
        $indexDefinitions = $property->getValue($blueprint);
        $indexDefinitions[] = $indexDef;
        $property->setValue($blueprint, $indexDefinitions);
    }

    private function addForeignKeyToBlueprint(Blueprint $blueprint, $foreignKey): void
    {
        $reflection = new \ReflectionClass($blueprint);
        $property = $reflection->getProperty('foreignKeys');
        $property->setAccessible(true);
        $foreignKeys = $property->getValue($blueprint);
        $foreignKeys[] = $foreignKey;
        $property->setValue($blueprint, $foreignKeys);
    }
}
