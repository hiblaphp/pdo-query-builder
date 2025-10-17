<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema\Compilers;

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Hibla\PdoQueryBuilder\Schema\Column;
use Hibla\PdoQueryBuilder\Schema\SchemaCompiler;

class SQLiteSchemaCompiler implements SchemaCompiler
{
    public function compileCreate(Blueprint $blueprint): string
    {
        $table = $blueprint->getTable();
        $columns = $blueprint->getColumns();
        $indexes = $blueprint->getIndexes();

        $sql = "CREATE TABLE `{$table}` (\n";

        $columnDefinitions = [];
        foreach ($columns as $column) {
            $columnDefinitions[] = '  ' . $this->compileColumn($column);
        }

        foreach ($indexes as $index) {
            if ($index['type'] === 'PRIMARY') {
                $cols = implode('`, `', $index['columns']);
                $columnDefinitions[] = "  PRIMARY KEY (`{$cols}`)";
            }
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
            'BIGINT', 'INT', 'TINYINT', 'SMALLINT' => 'INTEGER',
            'VARCHAR' => 'TEXT',
            'TEXT', 'MEDIUMTEXT', 'LONGTEXT' => 'TEXT',
            'DECIMAL', 'FLOAT', 'DOUBLE' => 'REAL',
            'DATETIME', 'TIMESTAMP' => 'TEXT',
            'JSON' => 'TEXT',
            default => $type,
        };
    }

    public function compileAlter(Blueprint $blueprint): string
    {
        $table = $blueprint->getTable();
        $sql = "ALTER TABLE `{$table}`\n";

        $alterations = [];
        foreach ($blueprint->getColumns() as $column) {
            $alterations[] = "  ADD COLUMN " . $this->compileColumn($column);
        }

        return $sql . implode(",\n", $alterations);
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
}
