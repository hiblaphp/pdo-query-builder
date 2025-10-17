<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema\Compilers;

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Hibla\PdoQueryBuilder\Schema\Column;
use Hibla\PdoQueryBuilder\Schema\IndexDefinition;
use Hibla\PdoQueryBuilder\Schema\SchemaCompiler;

class SQLiteSchemaCompiler implements SchemaCompiler
{
    public function compileCreate(Blueprint $blueprint): string
    {
        $table = $blueprint->getTable();
        $columns = $blueprint->getColumns();
        $indexDefinitions = $blueprint->getIndexDefinitions();
        $foreignKeys = $blueprint->getForeignKeys();

        $sql = "CREATE TABLE `{$table}` (\n";

        $columnDefinitions = [];
        foreach ($columns as $column) {
            $columnDefinitions[] = '  ' . $this->compileColumn($column);
        }

        foreach ($indexDefinitions as $indexDef) {
            if ($indexDef->getType() === 'PRIMARY') {
                $cols = implode('`, `', $indexDef->getColumns());
                $columnDefinitions[] = "  PRIMARY KEY (`{$cols}`)";
            }
        }

        foreach ($foreignKeys as $foreignKey) {
            $cols = implode('`, `', $foreignKey->getColumns());
            $refCols = implode('`, `', $foreignKey->getReferenceColumns());
            $refTable = $foreignKey->getReferenceTable();

            $fkDef = "  FOREIGN KEY (`{$cols}`) REFERENCES `{$refTable}` (`{$refCols}`)";

            if ($foreignKey->getOnDelete() !== 'RESTRICT') {
                $fkDef .= " ON DELETE {$foreignKey->getOnDelete()}";
            }
            if ($foreignKey->getOnUpdate() !== 'RESTRICT') {
                $fkDef .= " ON UPDATE {$foreignKey->getOnUpdate()}";
            }

            $columnDefinitions[] = $fkDef;
        }

        $sql .= implode(",\n", $columnDefinitions);
        $sql .= "\n)";

        return $sql;
    }

    private function compileColumn(Column $column): string
    {
        $sql = "`{$column->getName()}` ";

        $type = $this->mapType($column->getType(), $column);
        $sql .= $type;

        if ($column->isAutoIncrement()) {
            $sql = "`{$column->getName()}` INTEGER PRIMARY KEY AUTOINCREMENT";
            return $sql;
        }

        if ($column->isPrimary()) {
            $sql .= ' PRIMARY KEY';
        }

        if (!$column->isNullable()) {
            $sql .= ' NOT NULL';
        }

        if ($column->hasDefault()) {
            $default = $column->getDefault();
            if ($default === null) {
                $sql .= ' DEFAULT NULL';
            } elseif (is_bool($default)) {
                $sql .= ' DEFAULT ' . ($default ? '1' : '0');
            } elseif (is_numeric($default)) {
                $sql .= " DEFAULT {$default}";
            } else {
                $escaped = str_replace("'", "''", $default);
                $sql .= " DEFAULT '{$escaped}'";
            }
        } elseif ($column->shouldUseCurrent() && in_array($column->getType(), ['DATETIME', 'TIMESTAMP'])) {
            $sql .= " DEFAULT CURRENT_TIMESTAMP";
        }

        if ($column->isUnique()) {
            $sql .= ' UNIQUE';
        }

        return $sql;
    }

    private function mapType(string $type, Column $column): string
    {
        return match ($type) {
            'BIGINT', 'INT', 'TINYINT', 'SMALLINT' => 'INTEGER',
            'VARCHAR' => 'TEXT',
            'TEXT', 'MEDIUMTEXT', 'LONGTEXT' => 'TEXT',
            'DECIMAL', 'FLOAT', 'DOUBLE' => 'REAL',
            'DATETIME', 'TIMESTAMP', 'DATE' => 'TEXT',
            'JSON' => 'TEXT',
            'BOOLEAN' => 'INTEGER',
            default => $type,
        };
    }

    public function compileAlter(Blueprint $blueprint): string|array
    {
        $table = $blueprint->getTable();
        $statements = [];

        foreach ($blueprint->getDropColumns() as $column) {
            $statements[] = "ALTER TABLE \"{$table}\" DROP COLUMN IF EXISTS \"{$column}\"";
        }

        $needsRecreation = !empty($blueprint->getDropColumns()) ||
            !empty($blueprint->getModifyColumns()) ||
            !empty($blueprint->getDropForeignKeys());

        if ($needsRecreation) {
            return $this->compileTableRecreation($blueprint);
        }

        foreach ($blueprint->getColumns() as $column) {
            $statements[] = "ALTER TABLE `{$table}` ADD COLUMN " . $this->compileColumn($column);
        }

        foreach ($blueprint->getRenameColumns() as $rename) {
            $statements[] = "ALTER TABLE `{$table}` RENAME COLUMN `{$rename['from']}` TO `{$rename['to']}`";
        }

        foreach ($blueprint->getCommands() as $command) {
            if ($command['type'] === 'rename') {
                $statements[] = $this->compileRename($table, $command['to']);
            }
        }

        foreach ($blueprint->getDropIndexes() as $index) {
            if ($index[0] !== 'PRIMARY') {
                $statements[] = "DROP INDEX IF EXISTS `{$index[0]}`";
            }
        }

        foreach ($blueprint->getIndexDefinitions() as $indexDef) {
            if ($indexDef->getType() !== 'PRIMARY') {
                $statements = array_merge($statements, $this->compileAddIndexDefinition($table, $indexDef));
            }
        }

        return empty($statements) ? '' : (count($statements) === 1 ? $statements[0] : $statements);
    }

    /**
     * Compile add index definitions for ALTER TABLE
     */
    private function compileAddIndexDefinition(string $table, IndexDefinition $indexDef): array
    {
        $type = $indexDef->getType();
        $cols = implode('`, `', $indexDef->getColumns());
        $statements = [];

        if ($type === 'UNIQUE') {
            $statements[] = "CREATE UNIQUE INDEX `{$indexDef->getName()}` ON `{$table}` (`{$cols}`)";
        } elseif ($type === 'INDEX') {
            $statements[] = "CREATE INDEX `{$indexDef->getName()}` ON `{$table}` (`{$cols}`)";
        } elseif ($type === 'FULLTEXT') {
            // SQLite doesn't support FULLTEXT natively, use FTS5 instead
            $statements[] = "CREATE VIRTUAL TABLE IF NOT EXISTS `{$table}_fts` USING fts5(`{$cols}`)";
        } elseif ($type === 'SPATIAL') {
            // SQLite doesn't support spatial indexes natively
            $statements[] = "CREATE INDEX `{$indexDef->getName()}` ON `{$table}` (`{$cols}`)";
        }

        return $statements;
    }

    /**
     * Compile complex table recreation for operations SQLite doesn't support
     */
    private function compileTableRecreation(Blueprint $blueprint): array
    {
        $table = $blueprint->getTable();
        $tempTable = "_temp_{$table}_" . bin2hex(random_bytes(4));

        $statements = [];

        $statements[] = "PRAGMA foreign_keys=OFF";
        $statements[] = "BEGIN TRANSACTION";
        $newBlueprint = $this->buildNewBlueprint($blueprint, $tempTable);
        $statements[] = $this->compileCreate($newBlueprint);

        $transferData = $this->getColumnTransferMapping($blueprint);
        if (!empty($transferData['old']) && !empty($transferData['new'])) {
            $oldCols = implode('`, `', $transferData['old']);
            $newCols = implode('`, `', $transferData['new']);
            $statements[] = "INSERT INTO `{$tempTable}` (`{$newCols}`) SELECT `{$oldCols}` FROM `{$table}`";
        }

        $statements[] = "DROP TABLE `{$table}`";
        $statements[] = "ALTER TABLE `{$tempTable}` RENAME TO `{$table}`";

        foreach ($blueprint->getIndexDefinitions() as $indexDef) {
            if ($indexDef->getType() === 'UNIQUE') {
                $cols = implode('`, `', $indexDef->getColumns());
                $statements[] = "CREATE UNIQUE INDEX IF NOT EXISTS `{$indexDef->getName()}` ON `{$table}` (`{$cols}`)";
            } elseif ($indexDef->getType() === 'INDEX') {
                $cols = implode('`, `', $indexDef->getColumns());
                $statements[] = "CREATE INDEX IF NOT EXISTS `{$indexDef->getName()}` ON `{$table}` (`{$cols}`)";
            }
        }

        $statements[] = "PRAGMA foreign_key_check";
        $statements[] = "COMMIT";
        $statements[] = "PRAGMA foreign_keys=ON";

        return $statements;
    }

    /**
     * Build a new blueprint based on the original with modifications applied
     */
    private function buildNewBlueprint(Blueprint $originalBlueprint, string $newTableName): Blueprint
    {
        $newBlueprint = new Blueprint($newTableName);

        $dropColumns = $originalBlueprint->getDropColumns();
        $renameMap = $this->getRenameMap($originalBlueprint->getRenameColumns());
        $modifyMap = $this->getModifyMap($originalBlueprint->getModifyColumns());

        foreach ($originalBlueprint->getColumns() as $column) {
            $columnName = $column->getName();

            if (in_array($columnName, $dropColumns)) {
                continue;
            }

            if (isset($renameMap[$columnName])) {
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

            $clonedColumn = clone $column;
            $clonedColumn->setBlueprint($newBlueprint);
            $this->addColumnToBlueprint($newBlueprint, $clonedColumn);
        }

        $dropIndexNames = array_map(fn($idx) => $idx[0], $originalBlueprint->getDropIndexes());
        foreach ($originalBlueprint->getIndexDefinitions() as $indexDef) {
            $indexName = $indexDef->getName() ?? 'PRIMARY';
            if (!in_array($indexName, $dropIndexNames)) {
                $this->addIndexDefinitionToBlueprint($newBlueprint, $indexDef);
            }
        }

        $dropForeignKeys = $originalBlueprint->getDropForeignKeys();
        foreach ($originalBlueprint->getForeignKeys() as $foreignKey) {
            if (!in_array($foreignKey->getName(), $dropForeignKeys)) {
                $this->addForeignKeyToBlueprint($newBlueprint, $foreignKey);
            }
        }

        return $newBlueprint;
    }

    /**
     * Get column mapping for data transfer between old and new tables
     */
    private function getColumnTransferMapping(Blueprint $blueprint): array
    {
        $oldColumns = [];
        $newColumns = [];

        $dropColumns = $blueprint->getDropColumns();
        $renameMap = $this->getRenameMap($blueprint->getRenameColumns());

        foreach ($blueprint->getColumns() as $column) {
            $columnName = $column->getName();

            if (in_array($columnName, $dropColumns)) {
                continue;
            }

            if (isset($renameMap[$columnName])) {
                $oldColumns[] = $columnName;
                $newColumns[] = $renameMap[$columnName];
            } else {
                $oldColumns[] = $columnName;
                $newColumns[] = $columnName;
            }
        }

        return ['old' => $oldColumns, 'new' => $newColumns];
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

    public function compileDrop(string $table): string
    {
        return "DROP TABLE `{$table}`";
    }

    public function compileDropIfExists(string $table): string
    {
        return "DROP TABLE IF EXISTS `{$table}`";
    }

    public function compileTableExists(string $table): string
    {
        return "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='{$table}'";
    }

    public function compileRename(string $from, string $to): string
    {
        return "ALTER TABLE `{$from}` RENAME TO `{$to}`";
    }

    public function compileDropColumn(Blueprint $blueprint, array $columns): string
    {
        $table = $blueprint->getTable();
        $drops = array_map(fn($col) => "DROP COLUMN `{$col}`", $columns);

        return "ALTER TABLE `{$table}` " . implode(', ', $drops);
    }

    public function compileRenameColumn(Blueprint $blueprint, string $from, string $to): string
    {
        $table = $blueprint->getTable();
        return "ALTER TABLE `{$table}` RENAME COLUMN `{$from}` TO `{$to}`";
    }
}