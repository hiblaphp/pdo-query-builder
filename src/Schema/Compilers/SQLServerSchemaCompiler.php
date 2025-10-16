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
            'VARCHAR' => "NVARCHAR({$column->getLength()})",
            'TEXT' => 'NVARCHAR(MAX)',
            'MEDIUMTEXT', 'LONGTEXT' => 'NVARCHAR(MAX)',
            'DATETIME' => 'DATETIME2',
            'TIMESTAMP' => 'DATETIME2',
            'JSON' => 'NVARCHAR(MAX)',
            default => $type,
        };
    }

    public function compileAlter(Blueprint $blueprint): string
    {
        $table = $blueprint->getTable();
        $sql = "ALTER TABLE [{$table}]\n";

        $alterations = [];
        foreach ($blueprint->getColumns() as $column) {
            $alterations[] = "  ADD " . $this->compileColumn($column);
        }

        return $sql . implode(",\n", $alterations);
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
}