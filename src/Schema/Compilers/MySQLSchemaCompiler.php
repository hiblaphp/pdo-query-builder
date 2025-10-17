<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema\Compilers;

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Hibla\PdoQueryBuilder\Schema\Column;
use Hibla\PdoQueryBuilder\Schema\SchemaCompiler;
use PDO;

/**
 * MySQL Schema Compiler
 * 
 * Requires MySQL 8.0+ for full feature support
 * This compiler takes advantage of modern MySQL features including:
 * - RENAME COLUMN syntax
 * - Improved JSON support
 * - Better performance optimizations
 * - Transaction support for DDL operations
 */
class MySQLSchemaCompiler implements SchemaCompiler
{
    private const MINIMUM_VERSION = '8.0';
    private ?PDO $connection = null;
    private bool $useTransactions = false;
    private ?int $lockWaitTimeout = null;

    public function setConnection(?PDO $connection): void
    {
        $this->connection = $connection;
        $this->validateMySQLVersion();
    }

    /**
     * Validate that MySQL version meets minimum requirements
     */
    private function validateMySQLVersion(): void
    {
        if (!$this->connection) {
            return;
        }

        try {
            $stmt = $this->connection->query("SELECT VERSION()");
            $version = $stmt->fetchColumn();

            if (preg_match('/^(\d+)\.(\d+)/', $version, $matches)) {
                $major = (int)$matches[1];

                if ($major < 8) {
                    throw new \RuntimeException(
                        "MySQL version {$version} is not supported. " .
                            "This library requires MySQL " . self::MINIMUM_VERSION . " or higher. " .
                            "Please upgrade your MySQL server."
                    );
                }
            }
        } catch (\PDOException $e) {
            // Connection error, let it fail naturally later
        }
    }

    public function compileCreate(Blueprint $blueprint): string
    {
        $table = $blueprint->getTable();
        $columns = $blueprint->getColumns();
        $indexes = $blueprint->getIndexes();
        $foreignKeys = $blueprint->getForeignKeys();

        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (\n";

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

        $sql .= $this->compileColumnType($column);

        if ($column->isUnsigned()) {
            $sql .= ' UNSIGNED';
        }

        if ($column->isNullable()) {
            $sql .= ' NULL';
        } else {
            $sql .= ' NOT NULL';
        }

        if ($column->isPrimary() && !$column->isAutoIncrement()) {
            $sql .= ' PRIMARY KEY';
        }

        if ($column->hasDefault()) {
            $sql .= $this->compileDefaultValue($column);
        } elseif ($column->shouldUseCurrent()) {
            $sql .= ' DEFAULT CURRENT_TIMESTAMP';
        }

        if ($column->getOnUpdate()) {
            $sql .= " ON UPDATE {$column->getOnUpdate()}";
        }

        if ($column->isAutoIncrement()) {
            $sql .= ' AUTO_INCREMENT';
        }

        if ($column->isUnique() && !$column->isPrimary()) {
            $sql .= ' UNIQUE';
        }

        if ($column->getComment()) {
            $sql .= " COMMENT " . $this->quoteValue($column->getComment());
        }

        if ($column->getAfter()) {
            $sql .= " AFTER `{$column->getAfter()}`";
        }

        return $sql;
    }

    /**
     * Compile the column type with proper length/precision
     */
    private function compileColumnType(Column $column): string
    {
        $type = $column->getType();

        return match (true) {
            $type === 'ENUM' => $this->compileEnumType($column),
            in_array($type, ['DECIMAL', 'FLOAT', 'DOUBLE']) => "{$type}({$column->getPrecision()}, {$column->getScale()})",
            $column->getLength() !== null => "{$type}({$column->getLength()})",
            default => $type,
        };
    }

    /**
     * Compile ENUM type with values
     */
    private function compileEnumType(Column $column): string
    {
        $values = array_map(fn($v) => $this->quoteValue($v), $column->getEnumValues());
        return "ENUM(" . implode(', ', $values) . ")";
    }

    /**
     * Compile default value for a column
     */
    private function compileDefaultValue(Column $column): string
    {
        $default = $column->getDefault();

        if ($default === null) {
            return ' DEFAULT NULL';
        }

        if (is_bool($default)) {
            return ' DEFAULT ' . ($default ? '1' : '0');
        }

        if (is_numeric($default)) {
            return " DEFAULT {$default}";
        }

        if ($this->isDefaultExpression($default)) {
            return " DEFAULT {$default}";
        }

        return " DEFAULT " . $this->quoteValue($default);
    }

    /**
     * Check if a default value is an expression (shouldn't be quoted)
     */
    private function isDefaultExpression(string $value): bool
    {
        $expressions = [
            'CURRENT_TIMESTAMP',
            'NOW()',
            'UUID()',
            'CURRENT_DATE',
            'CURRENT_TIME',
        ];

        return in_array(strtoupper($value), $expressions);
    }

    /**
     * Compile foreign key constraint
     */
    private function compileForeignKey($foreignKey): string
    {
        $cols = implode('`, `', $foreignKey->getColumns());
        $refCols = implode('`, `', $foreignKey->getReferenceColumns());

        $sql = "CONSTRAINT `{$foreignKey->getName()}` FOREIGN KEY (`{$cols}`) " .
            "REFERENCES `{$foreignKey->getReferenceTable()}` (`{$refCols}`)";

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

        if ($this->lockWaitTimeout !== null) {
            $statements[] = "SET SESSION lock_wait_timeout = {$this->lockWaitTimeout}";
        }

        foreach ($blueprint->getDropForeignKeys() as $foreignKey) {
            $statements[] = "ALTER TABLE `{$table}` DROP FOREIGN KEY `{$foreignKey}`";
        }

        foreach ($blueprint->getDropIndexes() as $index) {
            if ($index[0] === 'PRIMARY') {
                $statements[] = "ALTER TABLE `{$table}` DROP PRIMARY KEY";
            } else {
                $indexName = $index[0];
                $statements[] = "ALTER TABLE `{$table}` DROP INDEX `{$indexName}`";
            }
        }

        if (!empty($blueprint->getDropColumns())) {
            $drops = array_map(fn($col) => "DROP COLUMN `{$col}`", $blueprint->getDropColumns());
            $statements[] = "ALTER TABLE `{$table}` " . implode(', ', $drops);
        }

        foreach ($blueprint->getRenameColumns() as $rename) {
            $statements[] = "ALTER TABLE `{$table}` RENAME COLUMN `{$rename['from']}` TO `{$rename['to']}`";
        }

        if (!empty($blueprint->getModifyColumns())) {
            $modifications = array_map(
                fn($col) => "MODIFY COLUMN " . $this->compileColumn($col),
                $blueprint->getModifyColumns()
            );
            $statements[] = "ALTER TABLE `{$table}` " . implode(', ', $modifications);
        }

        if (!empty($blueprint->getColumns())) {
            $additions = array_map(
                fn($col) => "ADD COLUMN " . $this->compileColumn($col),
                $blueprint->getColumns()
            );
            $statements[] = "ALTER TABLE `{$table}` " . implode(', ', $additions);
        }

        foreach ($blueprint->getIndexes() as $index) {
            if ($index['type'] === 'PRIMARY') {
                $cols = implode('`, `', $index['columns']);
                $statements[] = "ALTER TABLE `{$table}` ADD PRIMARY KEY (`{$cols}`)";
            } elseif ($index['type'] === 'UNIQUE') {
                $cols = implode('`, `', $index['columns']);
                $statements[] = "ALTER TABLE `{$table}` ADD UNIQUE KEY `{$index['name']}` (`{$cols}`)";
            } else {
                $cols = implode('`, `', $index['columns']);
                $statements[] = "ALTER TABLE `{$table}` ADD KEY `{$index['name']}` (`{$cols}`)";
            }
        }

        foreach ($blueprint->getForeignKeys() as $foreignKey) {
            $statements[] = "ALTER TABLE `{$table}` ADD " . $this->compileForeignKey($foreignKey);
        }

        return empty($statements) ? '' : (count($statements) === 1 ? $statements[0] : $statements);
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
            "WHERE table_schema = DATABASE() AND table_name = " . $this->quoteValue($table);
    }

    public function compileRename(string $from, string $to): string
    {
        return "RENAME TABLE `{$from}` TO `{$to}`";
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

    /**
     * Properly escape and quote a value for SQL
     */
    private function quoteValue(string $value): string
    {
        if ($this->connection) {
            return $this->connection->quote($value);
        }

        $escaped = str_replace("'", "''", $value);
        return "'{$escaped}'";
    }
}