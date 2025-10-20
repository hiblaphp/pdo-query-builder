<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema\Compilers;

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Hibla\PdoQueryBuilder\Schema\Column;
use Hibla\PdoQueryBuilder\Schema\SchemaCompiler;
use Hibla\PdoQueryBuilder\Schema\Compilers\Utilities\MySQLTypeMapper;
use Hibla\PdoQueryBuilder\Schema\Compilers\Utilities\MySQLDefaultValueCompiler;
use Hibla\PdoQueryBuilder\Schema\Compilers\Utilities\MySQLForeignKeyCompiler;
use Hibla\PdoQueryBuilder\Schema\Compilers\Utilities\MySQLIndexCompiler;
use Hibla\PdoQueryBuilder\Schema\Compilers\Utilities\ValueQuoter;
use PDO;

class MySQLSchemaCompiler implements SchemaCompiler
{
    private const MINIMUM_VERSION = '8.0';
    private ?PDO $connection = null;
    private MySQLTypeMapper $typeMapper;
    private MySQLDefaultValueCompiler $defaultCompiler;
    private MySQLIndexCompiler $indexCompiler;
    private MySQLForeignKeyCompiler $foreignKeyCompiler;
    private ValueQuoter $quoter;

    public function __construct()
    {
        $this->typeMapper = new MySQLTypeMapper();
        $this->defaultCompiler = new MySQLDefaultValueCompiler();
        $this->indexCompiler = new MySQLIndexCompiler();
        $this->foreignKeyCompiler = new MySQLForeignKeyCompiler();
        $this->quoter = new ValueQuoter();
    }

    public function setConnection(?PDO $connection): void
    {
        $this->connection = $connection;
        $this->quoter = new ValueQuoter($connection);
        $this->validateMySQLVersion();
    }

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
        $indexDefinitions = $blueprint->getIndexDefinitions();
        $foreignKeys = $blueprint->getForeignKeys();

        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (\n";

        $columnDefinitions = [];

        foreach ($columns as $column) {
            $columnDefinitions[] = '  ' . $this->compileColumn($column);
        }

        foreach ($indexDefinitions as $indexDef) {
            $columnDefinitions[] = '  ' . $this->indexCompiler->compileIndexDefinition($indexDef);
        }

        foreach ($foreignKeys as $foreignKey) {
            $columnDefinitions[] = '  ' . $this->foreignKeyCompiler->compile($foreignKey);
        }

        $sql .= implode(",\n", $columnDefinitions);
        $sql .= "\n) ENGINE={$blueprint->getEngine()} DEFAULT CHARSET={$blueprint->getCharset()} COLLATE={$blueprint->getCollation()}";

        return $sql;
    }

    private function compileColumn(Column $column): string
    {
        $sql = "`{$column->getName()}` ";
        $sql .= $this->typeMapper->mapType($column->getType(), $column);

        if ($column->isUnsigned()) {
            $sql .= ' UNSIGNED';
        }

        $sql .= $column->isNullable() ? ' NULL' : ' NOT NULL';

        if ($column->isPrimary()) {
            $sql .= ' PRIMARY KEY';
        }

        if ($column->hasDefault()) {
            $sql .= $this->defaultCompiler->compileWithPrefix($column->getDefault());
        } elseif ($column->shouldUseCurrent()) {
            $sql .= $this->defaultCompiler->compileCurrentTimestamp();
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
            $sql .= " COMMENT " . $this->quoter->quote($column->getComment());
        }

        if ($column->getAfter()) {
            $sql .= " AFTER `{$column->getAfter()}`";
        }

        return $sql;
    }

    public function compileAlter(Blueprint $blueprint): string|array
    {
        $table = $blueprint->getTable();
        $statements = [];

        $statements = array_merge($statements, $this->compileRenameColumns($table, $blueprint->getRenameColumns()));
        $statements = array_merge($statements, $this->compileDropForeignKeys($table, $blueprint->getDropForeignKeys()));
        $statements = array_merge($statements, $this->compileDropIndexes($table, $blueprint->getDropIndexes()));
        $statements = array_merge($statements, $this->compileDropColumns($table, $blueprint->getDropColumns()));
        $statements = array_merge($statements, $this->compileModifyColumns($table, $blueprint->getModifyColumns()));
        $statements = array_merge($statements, $this->compileAddColumns($table, $blueprint->getColumns()));
        $statements = array_merge($statements, $this->compileAddIndexes($table, $blueprint->getIndexDefinitions()));
        $statements = array_merge($statements, $this->compileAddForeignKeys($table, $blueprint->getForeignKeys()));

        return empty($statements) ? '' : (count($statements) === 1 ? $statements[0] : $statements);
    }

    private function compileRenameColumns(string $table, array $renames): array
    {
        return array_map(
            fn($rename) => "ALTER TABLE `{$table}` RENAME COLUMN `{$rename['from']}` TO `{$rename['to']}`",
            $renames
        );
    }

    private function compileDropForeignKeys(string $table, array $foreignKeys): array
    {
        return array_map(
            fn($fk) => "ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fk}`",
            $foreignKeys
        );
    }

    private function compileDropIndexes(string $table, array $indexes): array
    {
        $statements = [];
        foreach ($indexes as $index) {
            if ($index[0] === 'PRIMARY') {
                $statements[] = "ALTER TABLE `{$table}` DROP PRIMARY KEY";
            } else {
                $statements[] = "ALTER TABLE `{$table}` DROP INDEX `{$index[0]}`";
            }
        }
        return $statements;
    }

    private function compileDropColumns(string $table, array $columns): array
    {
        return array_map(
            fn($col) => "ALTER TABLE `{$table}` DROP COLUMN `{$col}`",
            $columns
        );
    }

    private function compileModifyColumns(string $table, array $columns): array
    {
        if (empty($columns)) {
            return [];
        }

        $modifications = array_map(
            fn($col) => "MODIFY COLUMN " . $this->compileColumn($col),
            $columns
        );
        return ["ALTER TABLE `{$table}` " . implode(', ', $modifications)];
    }

    private function compileAddColumns(string $table, array $columns): array
    {
        if (empty($columns)) {
            return [];
        }

        $additions = array_map(
            fn($col) => "ADD COLUMN " . $this->compileColumn($col),
            $columns
        );
        return ["ALTER TABLE `{$table}` " . implode(', ', $additions)];
    }

    private function compileAddIndexes(string $table, array $indexes): array
    {
        $statements = [];
        foreach ($indexes as $indexDef) {
            $statements = array_merge($statements, $this->indexCompiler->compileAddIndexDefinition($table, $indexDef));
        }
        return $statements;
    }

    private function compileAddForeignKeys(string $table, array $foreignKeys): array
    {
        return array_map(
            fn($fk) => "ALTER TABLE `{$table}` ADD " . $this->foreignKeyCompiler->compile($fk),
            $foreignKeys
        );
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
            "WHERE table_schema = DATABASE() AND table_name = " . $this->quoter->quote($table);
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
}