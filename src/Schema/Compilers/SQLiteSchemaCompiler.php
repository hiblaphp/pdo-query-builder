<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema\Compilers;

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Hibla\PdoQueryBuilder\Schema\Column;
use Hibla\PdoQueryBuilder\Schema\IndexDefinition;
use Hibla\PdoQueryBuilder\Schema\SchemaCompiler;

class SQLiteSchemaCompiler implements SchemaCompiler
{
    private array $existingTableColumns = [];

    public function setExistingTableColumns(array $columns): void
    {
        $this->existingTableColumns = $columns;
    }

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
            'BIGINT', 'INT', 'TINYINT', 'SMALLINT', 'MEDIUMINT' => 'INTEGER',
            'VARCHAR' => 'TEXT',
            'TEXT', 'MEDIUMTEXT', 'LONGTEXT' => 'TEXT',
            'DECIMAL', 'FLOAT', 'DOUBLE' => 'REAL',
            'DATETIME', 'TIMESTAMP', 'DATE' => 'TEXT',
            'JSON' => 'TEXT',
            'BOOLEAN' => 'INTEGER',
            'POINT', 'LINESTRING', 'POLYGON', 'GEOMETRY', 
            'MULTIPOINT', 'MULTILINESTRING', 'MULTIPOLYGON', 'GEOMETRYCOLLECTION' => 'TEXT',
            default => $type,
        };
    }

    public function compileAlter(Blueprint $blueprint): string|array
    {
        $table = $blueprint->getTable();
        $statements = [];

        // Check if we need table recreation (SQLite limitation)
        $needsRecreation = !empty($blueprint->getDropColumns()) ||
            !empty($blueprint->getModifyColumns()) ||
            !empty($blueprint->getDropForeignKeys()) ||
            !empty($blueprint->getDropIndexes());

        if ($needsRecreation) {
            return $this->compileTableRecreation($blueprint);
        }

        // Simple additions
        foreach ($blueprint->getColumns() as $column) {
            $statements[] = "ALTER TABLE `{$table}` ADD COLUMN " . $this->compileColumn($column);
        }

        // Rename columns
        foreach ($blueprint->getRenameColumns() as $rename) {
            $statements[] = "ALTER TABLE `{$table}` RENAME COLUMN `{$rename['from']}` TO `{$rename['to']}`";
        }

        // Table rename
        foreach ($blueprint->getCommands() as $command) {
            if ($command['type'] === 'rename') {
                $statements[] = $this->compileRename($table, $command['to']);
            }
        }

        // Add indexes
        foreach ($blueprint->getIndexDefinitions() as $indexDef) {
            if ($indexDef->getType() !== 'PRIMARY') {
                $statements = array_merge($statements, $this->compileAddIndexDefinition($table, $indexDef));
            }
        }

        return empty($statements) ? '' : $statements;
    }

    private function compileAddIndexDefinition(string $table, IndexDefinition $indexDef): array
    {
        $type = $indexDef->getType();
        $cols = implode('`, `', $indexDef->getColumns());
        $statements = [];

        if ($type === 'UNIQUE') {
            $statements[] = "CREATE UNIQUE INDEX IF NOT EXISTS `{$indexDef->getName()}` ON `{$table}` (`{$cols}`)";
        } elseif ($type === 'INDEX') {
            $statements[] = "CREATE INDEX IF NOT EXISTS `{$indexDef->getName()}` ON `{$table}` (`{$cols}`)";
        } elseif ($type === 'FULLTEXT') {
            $statements[] = "CREATE INDEX IF NOT EXISTS `{$indexDef->getName()}` ON `{$table}` (`{$cols}`)";
        } elseif ($type === 'SPATIAL') {
            $statements[] = "CREATE INDEX IF NOT EXISTS `{$indexDef->getName()}` ON `{$table}` (`{$cols}`)";
        }

        return $statements;
    }

    private function compileTableRecreation(Blueprint $blueprint): array
    {
        $table = $blueprint->getTable();
        $tempTable = "temp_{$table}_" . bin2hex(random_bytes(4));

        $statements = [];

        $statements[] = "PRAGMA foreign_keys=OFF";
        
        $newBlueprint = $this->buildNewBlueprint($blueprint, $tempTable);
        $statements[] = $this->compileCreate($newBlueprint);

        $transferInfo = $this->getTransferColumns($blueprint);
        if (!empty($transferInfo['old']) && !empty($transferInfo['new'])) {
            $oldCols = implode('`, `', $transferInfo['old']);
            $newCols = implode('`, `', $transferInfo['new']);
            $statements[] = "INSERT INTO `{$tempTable}` (`{$newCols}`) SELECT `{$oldCols}` FROM `{$table}`";
        }

        $statements[] = "DROP TABLE `{$table}`";
        $statements[] = "ALTER TABLE `{$tempTable}` RENAME TO `{$table}`";

        $dropIndexNames = array_map(fn($idx) => $idx[0], $blueprint->getDropIndexes());
        foreach ($blueprint->getIndexDefinitions() as $indexDef) {
            $indexName = $indexDef->getName();
            if ($indexDef->getType() !== 'PRIMARY' && !in_array($indexName, $dropIndexNames)) {
                $indexStatements = $this->compileAddIndexDefinition($table, $indexDef);
                $statements = array_merge($statements, $indexStatements);
            }
        }

        $statements[] = "PRAGMA foreign_key_check";
        $statements[] = "PRAGMA foreign_keys=ON";

        return $statements;
    }

    private function buildNewBlueprint(Blueprint $originalBlueprint, string $newTableName): Blueprint
    {
        $newBlueprint = new Blueprint($newTableName);

        $dropColumns = $originalBlueprint->getDropColumns();
        $renameMap = $this->getRenameMap($originalBlueprint->getRenameColumns());
        $modifyMap = $this->getModifyMap($originalBlueprint->getModifyColumns());

        foreach ($this->existingTableColumns as $existingCol) {
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
            return strpos($value, '.') !== false ? (float)$value : (int)$value;
        }
        
        if (strtoupper($value) === 'NULL') {
            return null;
        }
        
        return $value;
    }

    private function getTransferColumns(Blueprint $blueprint): array
    {
        $oldColumns = [];
        $newColumns = [];
        $dropColumns = $blueprint->getDropColumns();
        $renameMap = $this->getRenameMap($blueprint->getRenameColumns());

        foreach ($this->existingTableColumns as $existingCol) {
            $columnName = $existingCol['name'];

            if (in_array($columnName, $dropColumns)) {
                continue;
            }

            $oldColumns[] = $columnName;
            
            if (isset($renameMap[$columnName])) {
                $newColumns[] = $renameMap[$columnName];
            } else {
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
        return '';
    }

    public function compileRenameColumn(Blueprint $blueprint, string $from, string $to): string
    {
        $table = $blueprint->getTable();
        return "ALTER TABLE `{$table}` RENAME COLUMN `{$from}` TO `{$to}`";
    }
}