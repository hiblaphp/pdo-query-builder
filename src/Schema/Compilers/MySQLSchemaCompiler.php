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
 */
class MySQLSchemaCompiler implements SchemaCompiler
{
    private const MINIMUM_VERSION = '8.0';
    private ?PDO $connection = null;

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

        return "CONSTRAINT `{$foreignKey->getName()}` FOREIGN KEY (`{$cols}`) " .
            "REFERENCES `{$foreignKey->getReferenceTable()}` (`{$refCols}`) " .
            "ON DELETE {$foreignKey->getOnDelete()} ON UPDATE {$foreignKey->getOnUpdate()}";
    }

    public function compileAlter(Blueprint $blueprint): string|array
    {
        $table = $blueprint->getTable();
        $statements = [];

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

    /**
     * Get detailed column information from information_schema
     */
    public function getTableColumns(string $table): array
    {
        if (!$this->connection) {
            throw new \RuntimeException('Database connection required to get table columns');
        }

        $sql = "SELECT 
                    COLUMN_NAME,
                    COLUMN_TYPE,
                    IS_NULLABLE,
                    COLUMN_DEFAULT,
                    EXTRA,
                    COLUMN_KEY,
                    COLUMN_COMMENT,
                    CHARACTER_SET_NAME,
                    COLLATION_NAME,
                    GENERATION_EXPRESSION
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                ORDER BY ORDINAL_POSITION";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([$table]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get index information for a table
     */
    public function getTableIndexes(string $table): array
    {
        if (!$this->connection) {
            throw new \RuntimeException('Database connection required to get table indexes');
        }

        $sql = "SELECT 
                    INDEX_NAME,
                    COLUMN_NAME,
                    NON_UNIQUE,
                    SEQ_IN_INDEX,
                    INDEX_TYPE,
                    INDEX_COMMENT
                FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                ORDER BY INDEX_NAME, SEQ_IN_INDEX";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([$table]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get foreign key information for a table
     */
    public function getTableForeignKeys(string $table): array
    {
        if (!$this->connection) {
            throw new \RuntimeException('Database connection required to get table foreign keys');
        }

        $sql = "SELECT 
                    kcu.CONSTRAINT_NAME,
                    kcu.COLUMN_NAME,
                    kcu.REFERENCED_TABLE_NAME,
                    kcu.REFERENCED_COLUMN_NAME,
                    rc.UPDATE_RULE,
                    rc.DELETE_RULE
                FROM information_schema.KEY_COLUMN_USAGE kcu
                JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
                    ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
                    AND kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
                WHERE kcu.TABLE_SCHEMA = DATABASE()
                AND kcu.TABLE_NAME = ?
                AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
                ORDER BY kcu.CONSTRAINT_NAME, kcu.ORDINAL_POSITION";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([$table]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get table status and metadata
     */
    public function getTableStatus(string $table): ?array
    {
        if (!$this->connection) {
            throw new \RuntimeException('Database connection required to get table status');
        }

        $sql = "SELECT 
                    ENGINE,
                    VERSION,
                    ROW_FORMAT,
                    TABLE_ROWS,
                    AVG_ROW_LENGTH,
                    DATA_LENGTH,
                    MAX_DATA_LENGTH,
                    INDEX_LENGTH,
                    DATA_FREE,
                    AUTO_INCREMENT,
                    CREATE_TIME,
                    UPDATE_TIME,
                    CHECK_TIME,
                    TABLE_COLLATION,
                    CHECKSUM,
                    CREATE_OPTIONS,
                    TABLE_COMMENT
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([$table]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get the CREATE TABLE statement for a table
     */
    public function getTableCreateStatement(string $table): string
    {
        if (!$this->connection) {
            throw new \RuntimeException('Database connection required to get CREATE TABLE statement');
        }

        $sql = "SHOW CREATE TABLE `{$table}`";
        $stmt = $this->connection->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['Create Table'] ?? '';
    }

    /**
     * Check if a column exists in a table
     */
    public function columnExists(string $table, string $column): bool
    {
        if (!$this->connection) {
            throw new \RuntimeException('Database connection required to check column existence');
        }

        $sql = "SELECT COUNT(*) 
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND COLUMN_NAME = ?";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([$table, $column]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Check if an index exists in a table
     */
    public function indexExists(string $table, string $index): bool
    {
        if (!$this->connection) {
            throw new \RuntimeException('Database connection required to check index existence');
        }

        $sql = "SELECT COUNT(*) 
                FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND INDEX_NAME = ?";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([$table, $index]);

        return (bool) $stmt->fetchColumn();
    }
}
