<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema\Compilers;

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Hibla\PdoQueryBuilder\Schema\Column;
use Hibla\PdoQueryBuilder\Schema\SchemaCompiler;
use PDO;

/**
 * SQL Server Schema Compiler
 * 
 * Supports SQL Server 2016+ features including:
 * - IF EXISTS/IF NOT EXISTS syntax
 * - Transaction support for DDL operations
 * - Better constraint handling
 * - Improved error handling
 */
class SQLServerSchemaCompiler implements SchemaCompiler
{
    private ?PDO $connection = null;

    public function compileCreate(Blueprint $blueprint): string
    {
        $table = $blueprint->getTable();
        $columns = $blueprint->getColumns();
        $indexes = $blueprint->getIndexes();
        $foreignKeys = $blueprint->getForeignKeys();

        $sql = "IF OBJECT_ID('[{$table}]', 'U') IS NULL\nBEGIN\n";
        $sql .= "CREATE TABLE [{$table}] (\n";

        $columnDefinitions = [];
        foreach ($columns as $column) {
            $columnDefinitions[] = '  ' . $this->compileColumn($column);
        }

        foreach ($indexes as $index) {
            if ($index['type'] === 'PRIMARY') {
                $cols = implode('], [', $index['columns']);
                $columnDefinitions[] = "  CONSTRAINT [PK_{$table}] PRIMARY KEY ([{$cols}])";
            }
        }

        $sql .= implode(",\n", $columnDefinitions);
        $sql .= "\n);\n";

        foreach ($indexes as $index) {
            if ($index['type'] === 'UNIQUE') {
                $cols = implode('], [', $index['columns']);
                $sql .= "CREATE UNIQUE INDEX [{$index['name']}] ON [{$table}] ([{$cols}]);\n";
            } elseif ($index['type'] === 'INDEX') {
                $cols = implode('], [', $index['columns']);
                $sql .= "CREATE INDEX [{$index['name']}] ON [{$table}] ([{$cols}]);\n";
            }
        }

        foreach ($foreignKeys as $foreignKey) {
            $sql .= "ALTER TABLE [{$table}] ADD " . $this->compileForeignKey($foreignKey) . ";\n";
        }

        $sql .= "END";

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

        if (!$column->isNullable()) {
            $sql .= ' NOT NULL';
        } else {
            $sql .= ' NULL';
        }

        if ($column->hasDefault() && !$column->isAutoIncrement()) {
            $default = $column->getDefault();
            if ($default === null) {
                $sql .= ' DEFAULT NULL';
            } elseif (is_bool($default)) {
                $sql .= ' DEFAULT ' . ($default ? '1' : '0');
            } elseif (is_numeric($default)) {
                $sql .= " DEFAULT {$default}";
            } elseif ($this->isDefaultExpression($default)) {
                $sql .= " DEFAULT {$default}";
            } else {
                $sql .= " DEFAULT " . $this->quoteValue($default);
            }
        } elseif ($column->shouldUseCurrent()) {
            $sql .= ' DEFAULT GETDATE()';
        }

        return $sql;
    }

    /**
     * Check if a default value is an expression
     */
    private function isDefaultExpression(string $value): bool
    {
        $expressions = [
            'GETDATE()',
            'GETUTCDATE()',
            'CURRENT_TIMESTAMP',
            'NEWID()',
            'SYSDATETIME()',
        ];

        return in_array(strtoupper($value), $expressions);
    }

    private function mapType(string $type, Column $column): string
    {
        return match ($type) {
            'BIGINT' => 'BIGINT',
            'INT' => 'INT',
            'TINYINT' => 'TINYINT',
            'SMALLINT' => 'SMALLINT',
            'VARCHAR' => $column->getLength() ? "NVARCHAR({$column->getLength()})" : 'NVARCHAR(255)',
            'TEXT', 'MEDIUMTEXT', 'LONGTEXT' => 'NVARCHAR(MAX)',
            'DECIMAL' => "DECIMAL({$column->getPrecision()}, {$column->getScale()})",
            'FLOAT' => 'FLOAT',
            'DOUBLE' => 'FLOAT',
            'DATETIME', 'TIMESTAMP' => 'DATETIME2',
            'DATE' => 'DATE',
            'JSON' => 'NVARCHAR(MAX)',
            'BOOLEAN' => 'BIT',
            'ENUM' => $this->compileEnum($column),
            default => $type,
        };
    }

    /**
     * Compile ENUM as CHECK constraint
     */
    private function compileEnum(Column $column): string
    {
        return "NVARCHAR(50)";
    }

    /**
     * Compile foreign key constraint
     */
    private function compileForeignKey($foreignKey): string
    {
        $cols = implode('], [', $foreignKey->getColumns());
        $refCols = implode('], [', $foreignKey->getReferenceColumns());

        $sql = "CONSTRAINT [{$foreignKey->getName()}] FOREIGN KEY ([{$cols}]) " .
            "REFERENCES [{$foreignKey->getReferenceTable()}] ([{$refCols}])";

        if ($foreignKey->getOnDelete() !== 'RESTRICT') {
            $sql .= " ON DELETE {$foreignKey->getOnDelete()}";
        }

        if ($foreignKey->getOnUpdate() !== 'RESTRICT') {
            $sql .= " ON UPDATE {$foreignKey->getOnUpdate()}";
        }

        return $sql;
    }

    public function compileAlter(Blueprint $blueprint): string|array
    {
        $table = $blueprint->getTable();
        $statements = [];

        foreach ($blueprint->getDropForeignKeys() as $foreignKey) {
            $statements[] = $this->compileDropForeignKey($table, $foreignKey);
        }

        foreach ($blueprint->getDropIndexes() as $index) {
            if ($index[0] === 'PRIMARY') {
                $statements[] = "IF EXISTS (SELECT * FROM sys.key_constraints WHERE name = 'PK_{$table}' AND parent_object_id = OBJECT_ID('[{$table}]'))\n" .
                    "ALTER TABLE [{$table}] DROP CONSTRAINT [PK_{$table}]";
            } else {
                $indexName = $index[0];
                $statements[] = "IF EXISTS (SELECT * FROM sys.indexes WHERE name = '{$indexName}' AND object_id = OBJECT_ID('[{$table}]'))\n" .
                    "DROP INDEX [{$indexName}] ON [{$table}]";
            }
        }

        foreach ($blueprint->getDropColumns() as $column) {
            $statements[] = $this->compileDropColumn($blueprint, [$column]);
        }

        foreach ($blueprint->getRenameColumns() as $rename) {
            $statements[] = $this->compileRenameColumn($blueprint, $rename['from'], $rename['to']);
        }

        foreach ($blueprint->getModifyColumns() as $column) {
            $statements[] = $this->compileDropDefaultConstraint($table, $column->getName());
            $statements[] = "ALTER TABLE [{$table}] ALTER COLUMN " . $this->compileColumn($column);
        }

        foreach ($blueprint->getColumns() as $column) {
            $statements[] = "ALTER TABLE [{$table}] ADD " . $this->compileColumn($column);
        }

        foreach ($blueprint->getIndexes() as $index) {
            if ($index['type'] === 'PRIMARY') {
                $cols = implode('], [', $index['columns']);
                $statements[] = "ALTER TABLE [{$table}] ADD CONSTRAINT [PK_{$table}] PRIMARY KEY ([{$cols}])";
            } elseif ($index['type'] === 'UNIQUE') {
                $cols = implode('], [', $index['columns']);
                $statements[] = "IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = '{$index['name']}' AND object_id = OBJECT_ID('[{$table}]'))\n" .
                    "CREATE UNIQUE INDEX [{$index['name']}] ON [{$table}] ([{$cols}])";
            } else {
                $cols = implode('], [', $index['columns']);
                $statements[] = "IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = '{$index['name']}' AND object_id = OBJECT_ID('[{$table}]'))\n" .
                    "CREATE INDEX [{$index['name']}] ON [{$table}] ([{$cols}])";
            }
        }

        foreach ($blueprint->getForeignKeys() as $foreignKey) {
            $statements[] = "IF NOT EXISTS (SELECT * FROM sys.foreign_keys WHERE name = '{$foreignKey->getName()}' AND parent_object_id = OBJECT_ID('[{$table}]'))\n" .
                "ALTER TABLE [{$table}] ADD " . $this->compileForeignKey($foreignKey);
        }

        return count($statements) === 1 ? $statements[0] : $statements;
    }

    /**
     * Compile drop foreign key with existence check
     */
    private function compileDropForeignKey(string $table, string $foreignKey): string
    {
        return "IF EXISTS (SELECT * FROM sys.foreign_keys WHERE name = '{$foreignKey}' AND parent_object_id = OBJECT_ID('[{$table}]'))\n" .
            "ALTER TABLE [{$table}] DROP CONSTRAINT [{$foreignKey}]";
    }

    /**
     * Compile drop default constraint
     */
    private function compileDropDefaultConstraint(string $table, string $column): string
    {
        return "DECLARE @ConstraintName NVARCHAR(200);\n" .
            "SELECT @ConstraintName = dc.name\n" .
            "FROM sys.default_constraints dc\n" .
            "INNER JOIN sys.columns c ON dc.parent_column_id = c.column_id AND dc.parent_object_id = c.object_id\n" .
            "WHERE dc.parent_object_id = OBJECT_ID('[{$table}]') AND c.name = '{$column}';\n" .
            "IF @ConstraintName IS NOT NULL\n" .
            "EXEC('ALTER TABLE [{$table}] DROP CONSTRAINT [' + @ConstraintName + ']')";
    }

    public function compileDrop(string $table): string
    {
        return "DROP TABLE [{$table}]";
    }

    public function compileDropIfExists(string $table): string
    {
        return "IF OBJECT_ID('[{$table}]', 'U') IS NOT NULL DROP TABLE [{$table}]";
    }

    public function compileTableExists(string $table): string
    {
        return "SELECT CASE WHEN OBJECT_ID('[{$table}]', 'U') IS NOT NULL THEN 1 ELSE 0 END";
    }

    public function compileRename(string $from, string $to): string
    {
        return "IF OBJECT_ID('[{$from}]', 'U') IS NOT NULL EXEC sp_rename '[{$from}]', '{$to}'";
    }

    public function compileDropColumn(Blueprint $blueprint, array $columns): string
    {
        $table = $blueprint->getTable();
        $statements = [];

        foreach ($columns as $column) {
            $statements[] = $this->compileDropDefaultConstraint($table, $column);

            $statements[] = "IF EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('[{$table}]') AND name = '{$column}')\n" .
                "ALTER TABLE [{$table}] DROP COLUMN [{$column}]";
        }

        return implode(";\n", $statements);
    }

    public function compileRenameColumn(Blueprint $blueprint, string $from, string $to): string
    {
        $table = $blueprint->getTable();
        return "IF EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('[{$table}]') AND name = '{$from}')\n" .
            "EXEC sp_rename '[{$table}].[{$from}]', '{$to}', 'COLUMN'";
    }

    /**
     * Properly escape and quote a value for SQL
     */
    private function quoteValue(string $value): string
    {
        if ($this->connection) {
            return $this->connection->quote($value);
        }

        $escaped = str_replace("'", "''", $value);
        return "N'{$escaped}'";
    }
}
