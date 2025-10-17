<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema\Compilers;

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Hibla\PdoQueryBuilder\Schema\Column;
use Hibla\PdoQueryBuilder\Schema\SchemaCompiler;

class SQLServerSchemaCompiler implements SchemaCompiler
{
    public function compileCreate(Blueprint $blueprint): string
    {
        $table = $blueprint->getTable();
        $columns = $blueprint->getColumns();
        $indexes = $blueprint->getIndexes();

        $sql = "CREATE TABLE [{$table}] (\n";

        $columnDefinitions = [];
        foreach ($columns as $column) {
            $columnDefinitions[] = '  ' . $this->compileColumn($column);
        }

        foreach ($indexes as $index) {
            if ($index['type'] === 'PRIMARY') {
                $cols = implode('], [', $index['columns']);
                $columnDefinitions[] = "  PRIMARY KEY ([{$cols}])";
            }
        }

        $sql .= implode(",\n", $columnDefinitions);
        $sql .= "\n)";

        return $sql;
    }

    private function compileColumn(Column $column): string
    {
        $sql = "[{$column->getName()}] ";

        $type = $this->mapType($column->getType(), $column);
        $sql .= $type;

        if ($column->isAutoIncrement()) {
            $sql .= ' IDENTITY(1,1)';
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
                $sql .= " DEFAULT '{$default}'";
            }
        }

        return $sql;
    }

    private function mapType(string $type, Column $column): string
    {
        return match ($type) {
            'BIGINT' => 'BIGINT',
            'INT' => 'INT',
            'TINYINT' => 'TINYINT',
            'SMALLINT' => 'SMALLINT',
            'VARCHAR' => "NVARCHAR({$column->getLength()})",
            'TEXT' => 'NVARCHAR(MAX)',
            'MEDIUMTEXT', 'LONGTEXT' => 'NVARCHAR(MAX)',
            'DECIMAL' => "DECIMAL({$column->getPrecision()}, {$column->getScale()})",
            'FLOAT' => 'FLOAT',
            'DOUBLE' => 'FLOAT',
            'DATETIME' => 'DATETIME2',
            'TIMESTAMP' => 'DATETIME2',
            'DATE' => 'DATE',
            'JSON' => 'NVARCHAR(MAX)',
            default => $type,
        };
    }

    public function compileAlter(Blueprint $blueprint): string|array
    {
        $table = $blueprint->getTable();
        $statements = [];

        // Drop foreign keys
        foreach ($blueprint->getDropForeignKeys() as $foreignKey) {
            $statements[] = "ALTER TABLE [{$table}] DROP CONSTRAINT [{$foreignKey}]";
        }

        // Drop indexes
        foreach ($blueprint->getDropIndexes() as $index) {
            if ($index[0] === 'PRIMARY') {
                $statements[] = "ALTER TABLE [{$table}] DROP CONSTRAINT PK_{$table}";
            } else {
                $statements[] = "DROP INDEX [{$index[0]}] ON [{$table}]";
            }
        }

        // Drop columns
        foreach ($blueprint->getDropColumns() as $column) {
            // First drop default constraint if exists
            $statements[] = "DECLARE @ConstraintName nvarchar(200); " .
                "SELECT @ConstraintName = Name FROM SYS.DEFAULT_CONSTRAINTS " .
                "WHERE PARENT_OBJECT_ID = OBJECT_ID('{$table}') " .
                "AND PARENT_COLUMN_ID = (SELECT column_id FROM sys.columns " .
                "WHERE NAME = '{$column}' AND object_id = OBJECT_ID('{$table}')); " .
                "IF @ConstraintName IS NOT NULL " .
                "EXEC('ALTER TABLE [{$table}] DROP CONSTRAINT [' + @ConstraintName + ']')";
            
            $statements[] = "ALTER TABLE [{$table}] DROP COLUMN [{$column}]";
        }

        foreach ($blueprint->getRenameColumns() as $rename) {
            $statements[] = $this->compileRenameColumn($blueprint, $rename['from'], $rename['to']);
        }

        foreach ($blueprint->getModifyColumns() as $column) {
            $statements[] = "ALTER TABLE [{$table}] ALTER COLUMN " . $this->compileColumn($column);
        }

        foreach ($blueprint->getColumns() as $column) {
            $statements[] = "ALTER TABLE [{$table}] ADD " . $this->compileColumn($column);
        }

        foreach ($blueprint->getIndexes() as $index) {
            if ($index['type'] === 'PRIMARY') {
                $cols = implode('], [', $index['columns']);
                $statements[] = "ALTER TABLE [{$table}] ADD PRIMARY KEY ([{$cols}])";
            } elseif ($index['type'] === 'UNIQUE') {
                $cols = implode('], [', $index['columns']);
                $statements[] = "CREATE UNIQUE INDEX [{$index['name']}] ON [{$table}] ([{$cols}])";
            } else {
                $cols = implode('], [', $index['columns']);
                $statements[] = "CREATE INDEX [{$index['name']}] ON [{$table}] ([{$cols}])";
            }
        }

        foreach ($blueprint->getForeignKeys() as $foreignKey) {
            $cols = implode('], [', $foreignKey->getColumns());
            $refCols = implode('], [', $foreignKey->getReferenceColumns());
            
            $statements[] = "ALTER TABLE [{$table}] ADD CONSTRAINT [{$foreignKey->getName()}] " .
                "FOREIGN KEY ([{$cols}]) REFERENCES [{$foreignKey->getReferenceTable()}] ([{$refCols}]) " .
                "ON DELETE {$foreignKey->getOnDelete()} ON UPDATE {$foreignKey->getOnUpdate()}";
        }

        return count($statements) === 1 ? $statements[0] : $statements;
    }

    public function compileDrop(string $table): string
    {
        return "DROP TABLE [{$table}]";
    }

    public function compileDropIfExists(string $table): string
    {
        return "IF OBJECT_ID('{$table}', 'U') IS NOT NULL DROP TABLE [{$table}]";
    }

    public function compileTableExists(string $table): string
    {
        return "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = '{$table}'";
    }

    public function compileRename(string $from, string $to): string
    {
        return "EXEC sp_rename '[{$from}]', '{$to}'";
    }

    public function compileDropColumn(Blueprint $blueprint, array $columns): string
    {
        $table = $blueprint->getTable();
        $drops = array_map(fn($col) => "DROP COLUMN [{$col}]", $columns);
        return "ALTER TABLE [{$table}] " . implode(', ', $drops);
    }

    public function compileRenameColumn(Blueprint $blueprint, string $from, string $to): string
    {
        $table = $blueprint->getTable();
        return "EXEC sp_rename '[{$table}].[{$from}]', '{$to}', 'COLUMN'";
    }
}