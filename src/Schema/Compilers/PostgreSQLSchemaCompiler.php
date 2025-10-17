<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema\Compilers;

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Hibla\PdoQueryBuilder\Schema\Column;
use Hibla\PdoQueryBuilder\Schema\SchemaCompiler;

class PostgreSQLSchemaCompiler implements SchemaCompiler
{
    public function compileCreate(Blueprint $blueprint): string
    {
        $table = $blueprint->getTable();
        $columns = $blueprint->getColumns();
        $indexes = $blueprint->getIndexes();
        $foreignKeys = $blueprint->getForeignKeys();

        $sql = "CREATE TABLE \"{$table}\" (\n";

        $columnDefinitions = [];
        foreach ($columns as $column) {
            $columnDefinitions[] = '  ' . $this->compileColumn($column);
        }

        foreach ($indexes as $index) {
            if ($index['type'] === 'PRIMARY') {
                $cols = implode('", "', $index['columns']);
                $columnDefinitions[] = "  PRIMARY KEY (\"{$cols}\")";
            } elseif ($index['type'] === 'UNIQUE') {
                $cols = implode('", "', $index['columns']);
                $name = $index['name'];
                $columnDefinitions[] = "  CONSTRAINT \"{$name}\" UNIQUE (\"{$cols}\")";
            }
        }

        foreach ($foreignKeys as $foreignKey) {
            $columnDefinitions[] = '  ' . $this->compileForeignKey($foreignKey);
        }

        $sql .= implode(",\n", $columnDefinitions);
        $sql .= "\n)";

        return $sql;
    }

    private function compileColumn(Column $column): string
    {
        $sql = "\"{$column->getName()}\" ";

        $type = $this->mapType($column->getType(), $column);
        $sql .= $type;

        if (!$column->isNullable()) {
            $sql .= ' NOT NULL';
        }

        if ($column->isPrimary()) {
            $sql .= ' PRIMARY KEY';
        }

        if ($column->hasDefault()) {
            $default = $column->getDefault();
            if ($default === null) {
                $sql .= ' DEFAULT NULL';
            } elseif (is_bool($default)) {
                $sql .= ' DEFAULT ' . ($default ? 'true' : 'false');
            } elseif (is_numeric($default)) {
                $sql .= " DEFAULT {$default}";
            } else {
                $sql .= " DEFAULT '{$default}'";
            }
        } elseif ($column->shouldUseCurrent()) {
            $sql .= ' DEFAULT CURRENT_TIMESTAMP';
        }

        return $sql;
    }

    private function mapType(string $type, Column $column): string
    {
        return match ($type) {
            'BIGINT' => $column->isAutoIncrement() ? 'BIGSERIAL' : 'BIGINT',
            'INT' => $column->isAutoIncrement() ? 'SERIAL' : 'INTEGER',
            'VARCHAR' => "VARCHAR({$column->getLength()})",
            'TEXT', 'MEDIUMTEXT', 'LONGTEXT' => 'TEXT',
            'TINYINT' => $column->getLength() === 1 ? 'BOOLEAN' : 'SMALLINT',
            'SMALLINT' => 'SMALLINT',
            'DECIMAL' => "DECIMAL({$column->getPrecision()}, {$column->getScale()})",
            'FLOAT' => "REAL",
            'DOUBLE' => "DOUBLE PRECISION",
            'DATETIME' => 'TIMESTAMP',
            'TIMESTAMP' => 'TIMESTAMP',
            'DATE' => 'DATE',
            'JSON' => 'JSONB',
            'ENUM' => $this->compileEnum($column),
            default => $type,
        };
    }

    private function compileEnum(Column $column): string
    {
        $values = array_map(fn($v) => "'{$v}'", $column->getEnumValues());
        return "VARCHAR(50) CHECK (\"{$column->getName()}\" IN (" . implode(', ', $values) . "))";
    }

    private function compileForeignKey($foreignKey): string
    {
        $cols = implode('", "', $foreignKey->getColumns());
        $refCols = implode('", "', $foreignKey->getReferenceColumns());

        return "CONSTRAINT \"{$foreignKey->getName()}\" FOREIGN KEY (\"{$cols}\") " .
            "REFERENCES \"{$foreignKey->getReferenceTable()}\" (\"{$refCols}\") " .
            "ON DELETE {$foreignKey->getOnDelete()} ON UPDATE {$foreignKey->getOnUpdate()}";
    }

    public function compileAlter(Blueprint $blueprint): string|array
    {
        $table = $blueprint->getTable();
        $statements = [];

        // Drop foreign keys
        foreach ($blueprint->getDropForeignKeys() as $foreignKey) {
            $statements[] = "ALTER TABLE \"{$table}\" DROP CONSTRAINT \"{$foreignKey}\"";
        }

        // Drop indexes
        foreach ($blueprint->getDropIndexes() as $index) {
            if ($index[0] === 'PRIMARY') {
                $statements[] = "ALTER TABLE \"{$table}\" DROP CONSTRAINT \"{$table}_pkey\"";
            } else {
                $statements[] = "DROP INDEX \"{$index[0]}\"";
            }
        }

        // Drop columns
        foreach ($blueprint->getDropColumns() as $column) {
            $statements[] = "ALTER TABLE \"{$table}\" DROP COLUMN \"{$column}\"";
        }

        // Rename columns
        foreach ($blueprint->getRenameColumns() as $rename) {
            $statements[] = $this->compileRenameColumn($blueprint, $rename['from'], $rename['to']);
        }

        // Modify columns
        foreach ($blueprint->getModifyColumns() as $column) {
            $type = $this->mapType($column->getType(), $column);
            $statements[] = "ALTER TABLE \"{$table}\" ALTER COLUMN \"{$column->getName()}\" TYPE {$type}";

            if (!$column->isNullable()) {
                $statements[] = "ALTER TABLE \"{$table}\" ALTER COLUMN \"{$column->getName()}\" SET NOT NULL";
            } else {
                $statements[] = "ALTER TABLE \"{$table}\" ALTER COLUMN \"{$column->getName()}\" DROP NOT NULL";
            }

            if ($column->hasDefault()) {
                $default = $column->getDefault();
                if ($default === null) {
                    $statements[] = "ALTER TABLE \"{$table}\" ALTER COLUMN \"{$column->getName()}\" SET DEFAULT NULL";
                } elseif (is_bool($default)) {
                    $statements[] = "ALTER TABLE \"{$table}\" ALTER COLUMN \"{$column->getName()}\" SET DEFAULT " . ($default ? 'true' : 'false');
                } elseif (is_numeric($default)) {
                    $statements[] = "ALTER TABLE \"{$table}\" ALTER COLUMN \"{$column->getName()}\" SET DEFAULT {$default}";
                } else {
                    $statements[] = "ALTER TABLE \"{$table}\" ALTER COLUMN \"{$column->getName()}\" SET DEFAULT '{$default}'";
                }
            }
        }

        // Add columns
        foreach ($blueprint->getColumns() as $column) {
            $statements[] = "ALTER TABLE \"{$table}\" ADD COLUMN " . $this->compileColumn($column);
        }

        // Add indexes
        foreach ($blueprint->getIndexes() as $index) {
            if ($index['type'] === 'PRIMARY') {
                $cols = implode('", "', $index['columns']);
                $statements[] = "ALTER TABLE \"{$table}\" ADD PRIMARY KEY (\"{$cols}\")";
            } elseif ($index['type'] === 'UNIQUE') {
                $cols = implode('", "', $index['columns']);
                $statements[] = "CREATE UNIQUE INDEX \"{$index['name']}\" ON \"{$table}\" (\"{$cols}\")";
            } else {
                $cols = implode('", "', $index['columns']);
                $statements[] = "CREATE INDEX \"{$index['name']}\" ON \"{$table}\" (\"{$cols}\")";
            }
        }

        // Add foreign keys
        foreach ($blueprint->getForeignKeys() as $foreignKey) {
            $statements[] = "ALTER TABLE \"{$table}\" ADD " . $this->compileForeignKey($foreignKey);
        }

        return count($statements) === 1 ? $statements[0] : $statements;
    }

    public function compileDrop(string $table): string
    {
        return "DROP TABLE \"{$table}\"";
    }

    public function compileDropIfExists(string $table): string
    {
        return "DROP TABLE IF EXISTS \"{$table}\"";
    }

    public function compileTableExists(string $table): string
    {
        return "SELECT COUNT(*) FROM information_schema.tables " .
            "WHERE table_schema = 'public' AND table_name = '{$table}'";
    }

    public function compileRename(string $from, string $to): string
    {
        return "ALTER TABLE \"{$from}\" RENAME TO \"{$to}\"";
    }

    public function compileDropColumn(Blueprint $blueprint, array $columns): string
    {
        $table = $blueprint->getTable();
        $drops = array_map(fn($col) => "DROP COLUMN \"{$col}\"", $columns);
        return "ALTER TABLE \"{$table}\" " . implode(', ', $drops);
    }

    public function compileRenameColumn(Blueprint $blueprint, string $from, string $to): string
    {
        $table = $blueprint->getTable();
        return "ALTER TABLE \"{$table}\" RENAME COLUMN \"{$from}\" TO \"{$to}\"";
    }
}
