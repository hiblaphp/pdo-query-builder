<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema\Compilers;

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Hibla\PdoQueryBuilder\Schema\Column;
use Hibla\PdoQueryBuilder\Schema\SchemaCompiler;

class MySQLSchemaCompiler implements SchemaCompiler
{
    public function compileCreate(Blueprint $blueprint): string
    {
        $table = $blueprint->getTable();
        $columns = $blueprint->getColumns();
        $indexes = $blueprint->getIndexes();
        $foreignKeys = $blueprint->getForeignKeys();

        $sql = "CREATE TABLE `{$table}` (\n";

        $columnDefinitions = [];
        foreach ($columns as $column) {
            $columnDefinitions[] = '  ' . $this->compileColumn($column);
        }

        foreach ($indexes as $index) {
            if ($index['type'] === 'PRIMARY') {
                $cols = implode('`, `', $index['columns']);
                $columnDefinitions[] = "  PRIMARY KEY (`{$cols}`)";
            } elseif ($index['type'] === 'UNIQUE') {
                $cols = implode('`, `', $index['columns']);
                $columnDefinitions[] = "  UNIQUE KEY `{$index['name']}` (`{$cols}`)";
            } else {
                $cols = implode('`, `', $index['columns']);
                $columnDefinitions[] = "  KEY `{$index['name']}` (`{$cols}`)";
            }
        }

        foreach ($foreignKeys as $foreignKey) {
            $columnDefinitions[] = '  ' . $this->compileForeignKey($foreignKey);
        }

        $sql .= implode(",\n", $columnDefinitions);
        $sql .= "\n) ENGINE={$blueprint->getEngine()} DEFAULT CHARSET={$blueprint->getCharset()} COLLATE={$blueprint->getCollation()}";

        return $sql;
    }

    private function compileColumn(Column $column): string
    {
        $sql = "`{$column->getName()}` ";

        $type = $column->getType();
        if ($type === 'ENUM') {
            $values = array_map(fn($v) => "'{$v}'", $column->getEnumValues());
            $sql .= "ENUM(" . implode(', ', $values) . ")";
        } elseif ($type === 'DECIMAL' || $type === 'FLOAT' || $type === 'DOUBLE') {
            $sql .= "{$type}({$column->getPrecision()}, {$column->getScale()})";
        } elseif ($column->getLength() !== null) {
            $sql .= "{$type}({$column->getLength()})";
        } else {
            $sql .= $type;
        }

        if ($column->isUnsigned()) {
            $sql .= ' UNSIGNED';
        }

        if ($column->isNullable()) {
            $sql .= ' NULL';
        } else {
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
        } elseif ($column->shouldUseCurrent()) {
            $sql .= ' DEFAULT CURRENT_TIMESTAMP';
        }

        if ($column->getOnUpdate()) {
            $sql .= " ON UPDATE {$column->getOnUpdate()}";
        }

        if ($column->isAutoIncrement()) {
            $sql .= ' AUTO_INCREMENT';
        }

        if ($column->getComment()) {
            $sql .= " COMMENT '{$column->getComment()}'";
        }

        return $sql;
    }

    private function compileForeignKey($foreignKey): string
    {
        $cols = implode('`, `', $foreignKey->getColumns());
        $refCols = implode('`, `', $foreignKey->getReferenceColumns());

        return "CONSTRAINT `{$foreignKey->getName()}` FOREIGN KEY (`{$cols}`) " .
            "REFERENCES `{$foreignKey->getReferenceTable()}` (`{$refCols}`) " .
            "ON DELETE {$foreignKey->getOnDelete()} ON UPDATE {$foreignKey->getOnUpdate()}";
    }

    public function compileAlter(Blueprint $blueprint): string
    {
        // Simplified - just add columns for now
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
        return "SELECT COUNT(*) FROM information_schema.tables " .
            "WHERE table_schema = DATABASE() AND table_name = '{$table}'";
    }
}
