<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema\Compilers;

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Hibla\PdoQueryBuilder\Schema\Column;
use Hibla\PdoQueryBuilder\Schema\SchemaCompiler;
use Hibla\PdoQueryBuilder\Schema\Compilers\Utilities\PostgreSQLTypeMapper;
use Hibla\PdoQueryBuilder\Schema\Compilers\Utilities\PostgreSQLDefaultValueCompiler;
use Hibla\PdoQueryBuilder\Schema\Compilers\Utilities\PostgreSQLIndexCompiler;
use Hibla\PdoQueryBuilder\Schema\Compilers\Utilities\PostgreSQLForeignKeyCompiler;

class PostgreSQLSchemaCompiler implements SchemaCompiler
{
    private bool $useConcurrentIndexes = false;
    private bool $useNotValidConstraints = false;
    private PostgreSQLTypeMapper $typeMapper;
    private PostgreSQLDefaultValueCompiler $defaultCompiler;
    private PostgreSQLIndexCompiler $indexCompiler;
    private PostgreSQLForeignKeyCompiler $foreignKeyCompiler;

    public function __construct()
    {
        $this->typeMapper = new PostgreSQLTypeMapper();
        $this->defaultCompiler = new PostgreSQLDefaultValueCompiler();
        $this->indexCompiler = new PostgreSQLIndexCompiler();
        $this->foreignKeyCompiler = new PostgreSQLForeignKeyCompiler();
    }

    public function compileCreate(Blueprint $blueprint): string
    {
        $table = $blueprint->getTable();
        $columns = $blueprint->getColumns();
        $indexDefinitions = $blueprint->getIndexDefinitions();
        $foreignKeys = $blueprint->getForeignKeys();

        $sql = "CREATE TABLE IF NOT EXISTS \"{$table}\" (\n";

        $columnDefinitions = [];
        foreach ($columns as $column) {
            $columnDefinitions[] = '  ' . $this->compileColumn($column);
        }

        foreach ($indexDefinitions as $indexDef) {
            if (in_array($indexDef->getType(), ['PRIMARY', 'UNIQUE'])) {
                $columnDefinitions[] = '  ' . $this->indexCompiler->compileIndexDefinition($indexDef);
            }
        }

        foreach ($foreignKeys as $foreignKey) {
            $columnDefinitions[] = '  ' . $this->foreignKeyCompiler->compile($foreignKey);
        }

        $sql .= implode(",\n", $columnDefinitions);
        $sql .= "\n)";

        return $sql;
    }

    private function compileColumn(Column $column): string
    {
        $sql = "\"{$column->getName()}\" ";
        $type = $this->typeMapper->mapType($column->getType(), $column);
        $sql .= $type;

        if (!$column->isNullable()) {
            $sql .= ' NOT NULL';
        }

        if ($column->isPrimary()) {
            $sql .= ' PRIMARY KEY';
        }

        if ($column->hasDefault()) {
            $sql .= ' DEFAULT ' . $this->defaultCompiler->compile($column->getDefault(), $column);
        } elseif ($column->shouldUseCurrent()) {
            $sql .= ' DEFAULT CURRENT_TIMESTAMP';
        }

        return $sql;
    }

    public function compileAlter(Blueprint $blueprint): string|array
    {
        $table = $blueprint->getTable();
        $statements = [];

        $statements = array_merge($statements, $this->compileDropColumns($table, $blueprint->getDropColumns()));
        $statements = array_merge($statements, $this->compileDropForeignKeys($table, $blueprint->getDropForeignKeys()));
        $statements = array_merge($statements, $this->compileDropIndexes($table, $blueprint->getDropIndexes()));
        $statements = array_merge($statements, $this->compileRenameColumns($table, $blueprint->getRenameColumns()));
        $statements = array_merge($statements, $this->compileModifyColumns($table, $blueprint->getModifyColumns()));
        $statements = array_merge($statements, $this->compileAddColumns($table, $blueprint->getColumns()));
        $statements = array_merge($statements, $this->compileAddIndexes($table, $blueprint->getIndexDefinitions()));
        $statements = array_merge($statements, $this->compileAddForeignKeys($table, $blueprint->getForeignKeys()));
        $statements = array_merge($statements, $this->compileRenameTable($table, $blueprint->getCommands()));

        return count($statements) === 1 ? $statements[0] : $statements;
    }

    private function compileDropColumns(string $table, array $columns): array
    {
        return array_map(
            fn($col) => "ALTER TABLE \"{$table}\" DROP COLUMN IF EXISTS \"{$col}\"",
            $columns
        );
    }

    private function compileDropForeignKeys(string $table, array $foreignKeys): array
    {
        return array_map(
            fn($fk) => "ALTER TABLE \"{$table}\" DROP CONSTRAINT IF EXISTS \"{$fk}\"",
            $foreignKeys
        );
    }

    private function compileDropIndexes(string $table, array $indexes): array
    {
        $statements = [];
        foreach ($indexes as $index) {
            if ($index[0] === 'PRIMARY') {
                $statements[] = "ALTER TABLE \"{$table}\" DROP CONSTRAINT IF EXISTS \"{$table}_pkey\"";
            } else {
                $indexName = $index[0];
                $statements[] = "DO $$ BEGIN ALTER TABLE \"{$table}\" DROP CONSTRAINT IF EXISTS \"{$indexName}\"; EXCEPTION WHEN undefined_object THEN NULL; END $$";
                $statements[] = "DROP INDEX IF EXISTS \"{$indexName}\"";
            }
        }
        return $statements;
    }

    private function compileRenameColumns(string $table, array $renames): array
    {
        return array_map(
            fn($rename) => $this->compileRenameColumn(new Blueprint($table), $rename['from'], $rename['to']),
            $renames
        );
    }

    private function compileModifyColumns(string $table, array $columns): array
    {
        $statements = [];
        foreach ($columns as $column) {
            $statements = array_merge($statements, $this->indexCompiler->compileModifyColumn($table, $column));
        }
        return $statements;
    }

    private function compileAddColumns(string $table, array $columns): array
    {
        $statements = [];
        foreach ($columns as $column) {
            $statements = array_merge($statements, $this->indexCompiler->compileAddColumn($table, $column));
        }
        return $statements;
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
        $statements = [];
        foreach ($foreignKeys as $foreignKey) {
            $statements[] = "ALTER TABLE \"{$table}\" ADD " . $this->foreignKeyCompiler->compile($foreignKey, $this->useNotValidConstraints);

            if ($this->useNotValidConstraints) {
                $statements[] = $this->compileValidateConstraint($table, $foreignKey->getName());
            }
        }
        return $statements;
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

    public function compileValidateConstraint(string $table, string $constraint): string
    {
        return "ALTER TABLE \"{$table}\" VALIDATE CONSTRAINT \"{$constraint}\"";
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
        return "SELECT EXISTS (
            SELECT FROM pg_tables 
            WHERE schemaname = 'public' 
            AND tablename = '{$table}'
        )";
    }

    public function compileRename(string $from, string $to): string
    {
        return "ALTER TABLE \"{$from}\" RENAME TO \"{$to}\"";
    }

    public function compileDropColumn(Blueprint $blueprint, array $columns): string
    {
        $table = $blueprint->getTable();
        $drops = array_map(fn($col) => "DROP COLUMN IF EXISTS \"{$col}\"", $columns);
        return "ALTER TABLE \"{$table}\" " . implode(', ', $drops);
    }

    public function compileRenameColumn(Blueprint $blueprint, string $from, string $to): string
    {
        $table = $blueprint->getTable();
        return "ALTER TABLE \"{$table}\" RENAME COLUMN \"{$from}\" TO \"{$to}\"";
    }
}