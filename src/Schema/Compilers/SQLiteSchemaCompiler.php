<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema\Compilers;

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Hibla\PdoQueryBuilder\Schema\Column;
use Hibla\PdoQueryBuilder\Schema\Compilers\Utilities\SQLiteIndexCompiler;
use Hibla\PdoQueryBuilder\Schema\Compilers\Utilities\SQLiteTypeMapper;
use Hibla\PdoQueryBuilder\Schema\SchemaCompiler;

class SQLiteSchemaCompiler implements SchemaCompiler
{
    private array $existingTableColumns = [];
    private SQLiteTypeMapper $typeMapper;
    private SQLiteIndexCompiler $indexCompiler;

    public function __construct()
    {
        $this->typeMapper = new SQLiteTypeMapper();
        $this->indexCompiler = new SQLiteIndexCompiler();
    }

    public function setExistingTableColumns(array $columns): void
    {
        $this->existingTableColumns = $columns;
    }

    public function compileCreate(Blueprint $blueprint): string
    {
        $table = $blueprint->getTable();
        $columns = $blueprint->getColumns();
        $indexDefinitions = $blueprint->getIndexDefinitions();
        $foreignKeys = $blueprint->getForeignKeys();

        $sql = "CREATE TABLE `{$table}` (\n";

        $columnDefinitions = [];
        foreach ($columns as $column) {
            $columnDefinitions[] = '  '.$this->compileColumn($column);
        }

        foreach ($indexDefinitions as $indexDef) {
            if ($indexDef->getType() === 'PRIMARY') {
                $cols = implode('`, `', $indexDef->getColumns());
                $columnDefinitions[] = "  PRIMARY KEY (`{$cols}`)";
            }
        }

        foreach ($foreignKeys as $foreignKey) {
            $columnDefinitions[] = '  '.$this->compileForeignKey($foreignKey);
        }

        $sql .= implode(",\n", $columnDefinitions);
        $sql .= "\n)";

        return $sql;
    }

    private function compileColumn(Column $column): string
    {
        if ($column->isAutoIncrement()) {
            return "`{$column->getName()}` INTEGER PRIMARY KEY AUTOINCREMENT";
        }

        $sql = "`{$column->getName()}` ";
        $type = $this->typeMapper->mapType($column->getType(), $column);
        $sql .= $type;

        if ($column->isPrimary()) {
            $sql .= ' PRIMARY KEY';
        }

        $sql .= $column->isNullable() ? '' : ' NOT NULL';

        if ($column->hasDefault()) {
            $sql .= $this->compileDefaultValue($column->getDefault());
        } elseif ($column->shouldUseCurrent() && in_array($column->getType(), ['DATETIME', 'TIMESTAMP'])) {
            $sql .= ' DEFAULT CURRENT_TIMESTAMP';
        }

        if ($column->isUnique()) {
            $sql .= ' UNIQUE';
        }

        return $sql;
    }

    private function compileDefaultValue(mixed $default): string
    {
        if ($default === null) {
            return ' DEFAULT NULL';
        }

        if (is_bool($default)) {
            return ' DEFAULT '.($default ? '1' : '0');
        }

        if (is_numeric($default)) {
            return " DEFAULT {$default}";
        }

        $escaped = str_replace("'", "''", $default);

        return " DEFAULT '{$escaped}'";
    }

    private function compileForeignKey($foreignKey): string
    {
        $cols = implode('`, `', $foreignKey->getColumns());
        $refCols = implode('`, `', $foreignKey->getReferenceColumns());
        $refTable = $foreignKey->getReferenceTable();

        $fkDef = "FOREIGN KEY (`{$cols}`) REFERENCES `{$refTable}` (`{$refCols}`)";

        if ($foreignKey->getOnDelete() !== 'RESTRICT') {
            $fkDef .= " ON DELETE {$foreignKey->getOnDelete()}";
        }
        if ($foreignKey->getOnUpdate() !== 'RESTRICT') {
            $fkDef .= " ON UPDATE {$foreignKey->getOnUpdate()}";
        }

        return $fkDef;
    }

    public function compileAlter(Blueprint $blueprint): string|array
    {
        $table = $blueprint->getTable();
        $statements = [];

        $needsRecreation = ! empty($blueprint->getDropColumns()) ||
            ! empty($blueprint->getModifyColumns()) ||
            ! empty($blueprint->getDropForeignKeys()) ||
            ! empty($blueprint->getDropIndexes());

        if ($needsRecreation) {
            return $this->indexCompiler->compileTableRecreation($blueprint, $this->existingTableColumns, $this);
        }

        $statements = array_merge($statements, $this->compileAddColumns($table, $blueprint->getColumns()));
        $statements = array_merge($statements, $this->compileRenameColumns($table, $blueprint->getRenameColumns()));
        $statements = array_merge($statements, $this->compileRenameTable($table, $blueprint->getCommands()));
        $statements = array_merge($statements, $this->compileAddIndexes($table, $blueprint->getIndexDefinitions()));

        return empty($statements) ? [] : $statements;
    }

    private function compileAddColumns(string $table, array $columns): array
    {
        return array_map(
            fn ($col) => "ALTER TABLE `{$table}` ADD COLUMN ".$this->compileColumn($col),
            $columns
        );
    }

    private function compileRenameColumns(string $table, array $renames): array
    {
        return array_map(
            fn ($rename) => "ALTER TABLE `{$table}` RENAME COLUMN `{$rename['from']}` TO `{$rename['to']}`",
            $renames
        );
    }

    private function compileRenameTable(string $table, array $commands): array
    {
        $statements = [];
        foreach ($commands as $command) {
            if ($command['type'] === 'rename') {
                $statements[] = $this->compileRename($table, $command['to']);
            }
        }

        return $statements;
    }

    private function compileAddIndexes(string $table, array $indexes): array
    {
        return $this->indexCompiler->compileAddIndexDefinition($table, $indexes);
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

    public function compileRename(string $from, string $to): string
    {
        return "ALTER TABLE `{$from}` RENAME TO `{$to}`";
    }

    public function compileDropColumn(Blueprint $blueprint, array $columns): string
    {
        return '';
    }

    public function compileRenameColumn(Blueprint $blueprint, string $from, string $to): string
    {
        $table = $blueprint->getTable();

        return "ALTER TABLE `{$table}` RENAME COLUMN `{$from}` TO `{$to}`";
    }
}
