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
        // PostgreSQL doesn't have ENUM in the same way, use CHECK constraint
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

    public function compileAlter(Blueprint $blueprint): string
    {
        $table = $blueprint->getTable();
        $sql = "ALTER TABLE \"{$table}\"\n";
        
        $alterations = [];
        foreach ($blueprint->getColumns() as $column) {
            $alterations[] = "  ADD COLUMN " . $this->compileColumn($column);
        }

        return $sql . implode(",\n", $alterations);
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
}