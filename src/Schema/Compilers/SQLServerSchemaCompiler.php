<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema\Compilers;

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Hibla\PdoQueryBuilder\Schema\Column;
use Hibla\PdoQueryBuilder\Schema\IndexDefinition;
use Hibla\PdoQueryBuilder\Schema\SchemaCompiler;
use Hibla\PdoQueryBuilder\Schema\Compilers\Utilities\SQLServerTypeMapper;
use Hibla\PdoQueryBuilder\Schema\Compilers\Utilities\SQLServerIndexCompiler;
use Hibla\PdoQueryBuilder\Schema\Compilers\Utilities\SQLServerForeignKeyCompiler;
use Hibla\PdoQueryBuilder\Schema\Compilers\Utilities\ValueQuoter;
use PDO;

class SQLServerSchemaCompiler implements SchemaCompiler
{
    private ?PDO $connection = null;
    private bool $isSystemDatabase = false;
    private SQLServerTypeMapper $typeMapper;
    private SQLServerIndexCompiler $indexCompiler;
    private SQLServerForeignKeyCompiler $foreignKeyCompiler;
    private ValueQuoter $quoter;

    public function __construct(bool $isSystemDatabase = false)
    {
        $this->isSystemDatabase = $isSystemDatabase;
        $this->typeMapper = new SQLServerTypeMapper();
        $this->indexCompiler = new SQLServerIndexCompiler($isSystemDatabase);
        $this->foreignKeyCompiler = new SQLServerForeignKeyCompiler();
        $this->quoter = new ValueQuoter();
    }

    public function compileCreate(Blueprint $blueprint): string
    {
        $table = $blueprint->getTable();
        $columns = $blueprint->getColumns();
        $indexDefinitions = $blueprint->getIndexDefinitions();
        $foreignKeys = $blueprint->getForeignKeys();

        $sql = "IF OBJECT_ID('[{$table}]', 'U') IS NULL\nBEGIN\n";
        $sql .= "CREATE TABLE [{$table}] (\n";

        $columnDefinitions = [];
        $hasPrimaryKey = false;

        foreach ($columns as $column) {
            $columnDefinitions[] = '  ' . $this->compileColumn($column);
        }

        $primaryKeyColumn = $this->getPrimaryKeyColumn($columns, $indexDefinitions);
        if ($primaryKeyColumn) {
            foreach ($indexDefinitions as $indexDef) {
                if ($indexDef->getType() === 'PRIMARY') {
                    $columnDefinitions[] = '  ' . $this->indexCompiler->compilePrimaryIndex($indexDef);
                    $hasPrimaryKey = true;
                    break;
                }
            }

            if (!$hasPrimaryKey) {
                $columnDefinitions[] = "  CONSTRAINT [PK_{$table}] PRIMARY KEY CLUSTERED ([{$primaryKeyColumn}])";
                $hasPrimaryKey = true;
            }
        }

        $sql .= implode(",\n", $columnDefinitions);
        $sql .= "\n);\n";

        $sql .= $this->compileCreateIndexes($table, $indexDefinitions);
        $sql .= $this->compileCreateForeignKeys($table, $foreignKeys);

        $sql .= "END";

        return $sql;
    }

    private function getPrimaryKeyColumn(array $columns, array $indexDefinitions): ?string
    {
        foreach ($columns as $column) {
            if ($column->getName() === 'id' && $column->isAutoIncrement()) {
                return 'id';
            }
        }
        return null;
    }

    private function compileCreateIndexes(string $table, array $indexDefinitions): string
    {
        $sql = '';
        foreach ($indexDefinitions as $indexDef) {
            if ($indexDef->getType() !== 'PRIMARY') {
                $indexSql = $this->indexCompiler->compileIndexDefinitionStatement($table, $indexDef);
                if (!empty($indexSql) && !str_starts_with($indexSql, '--')) {
                    $sql .= $indexSql . ";\n";
                }
            }
        }
        return $sql;
    }

    private function compileCreateForeignKeys(string $table, array $foreignKeys): string
    {
        $sql = '';
        foreach ($foreignKeys as $foreignKey) {
            $sql .= "ALTER TABLE [{$table}] ADD " . $this->foreignKeyCompiler->compile($foreignKey) . ";\n";
        }
        return $sql;
    }

    private function compileColumn(Column $column): string
    {
        $sql = "[{$column->getName()}] ";
        $type = $this->typeMapper->mapType($column->getType(), $column);
        $sql .= $type;

        if ($column->isAutoIncrement() && $column->isPrimary()) {
            $sql .= ' IDENTITY(1,1)';
        }

        $sql .= $column->isNullable() ? ' NULL' : ' NOT NULL';

        if ($column->hasDefault() && !$column->isAutoIncrement()) {
            $sql .= $this->compileDefaultValue($column->getDefault());
        } elseif ($column->shouldUseCurrent()) {
            $sql .= ' DEFAULT GETDATE()';
        }

        return $sql;
    }

    private function compileDefaultValue(mixed $default): string
    {
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

        return " DEFAULT " . $this->quoter->quote($default);
    }

    private function isDefaultExpression(string $value): bool
    {
        $expressions = ['GETDATE()', 'GETUTCDATE()', 'CURRENT_TIMESTAMP', 'NEWID()', 'SYSDATETIME()'];
        return in_array(strtoupper($value), $expressions);
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

        return count($statements) === 1 ? $statements[0] : $statements;
    }

    private function compileDropColumns(string $table, array $columns): array
    {
        $statements = [];
        foreach ($columns as $column) {
            $statements[] = $this->indexCompiler->compileDropDefaultConstraint($table, $column);
            $statements[] = "IF EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('[{$table}]') AND name = '{$column}')\n" .
                "ALTER TABLE [{$table}] DROP COLUMN [{$column}]";
        }
        return $statements;
    }

    private function compileDropForeignKeys(string $table, array $foreignKeys): array
    {
        return array_map(
            fn($fk) => $this->indexCompiler->compileDropForeignKey($table, $fk),
            $foreignKeys
        );
    }

    private function compileDropIndexes(string $table, array $indexes): array
    {
        return $this->indexCompiler->compileDropIndexes($table, $indexes);
    }

    private function compileRenameColumns(string $table, array $renames): array
    {
        $statements = [];
        foreach ($renames as $rename) {
            $statements[] = $this->compileRenameColumn(new Blueprint($table), $rename['from'], $rename['to']);
        }
        return $statements;
    }

    private function compileModifyColumns(string $table, array $columns): array
    {
        $statements = [];
        foreach ($columns as $column) {
            $statements[] = $this->indexCompiler->compileDropDefaultConstraint($table, $column->getName());
            $statements[] = "ALTER TABLE [{$table}] ALTER COLUMN " . $this->compileColumn($column);
        }
        return $statements;
    }

    private function compileAddColumns(string $table, array $columns): array
    {
        return array_map(
            fn($col) => "ALTER TABLE [{$table}] ADD " . $this->compileColumn($col),
            $columns
        );
    }

    private function compileAddIndexes(string $table, array $indexes): array
    {
        $statements = [];
        foreach ($indexes as $indexDef) {
            $indexStatements = $this->indexCompiler->compileAddIndexDefinition($table, $indexDef);
            $statements = array_merge($statements, array_filter($indexStatements));
        }
        return $statements;
    }

    private function compileAddForeignKeys(string $table, array $foreignKeys): array
    {
        return array_map(
            fn($fk) => "IF NOT EXISTS (SELECT * FROM sys.foreign_keys WHERE name = '{$fk->getName()}' AND parent_object_id = OBJECT_ID('[{$table}]'))\n" .
                "ALTER TABLE [{$table}] ADD " . $this->foreignKeyCompiler->compile($fk),
            $foreignKeys
        );
    }

    public function compileDrop(string $table): string
    {
        return "DROP TABLE [{$table}]";
    }

    public function compileDropIfExists(string $table): string
    {
        return "IF OBJECT_ID('[{$table}]', 'U') IS NOT NULL DROP TABLE [{$table}]";
    }

    public function compileTableExists(string $table): string
    {
        return "SELECT CASE WHEN OBJECT_ID('[{$table}]', 'U') IS NOT NULL THEN 1 ELSE 0 END";
    }

    public function compileRename(string $from, string $to): string
    {
        return "IF OBJECT_ID('[{$from}]', 'U') IS NOT NULL EXEC sp_rename '[{$from}]', '{$to}'";
    }

    public function compileDropColumn(Blueprint $blueprint, array $columns): string
    {
        $table = $blueprint->getTable();
        $statements = [];

        foreach ($columns as $column) {
            $statements[] = $this->indexCompiler->compileDropDefaultConstraint($table, $column);
            $statements[] = "IF EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('[{$table}]') AND name = '{$column}')\n" .
                "ALTER TABLE [{$table}] DROP COLUMN [{$column}]";
        }

        return implode(";\n", $statements);
    }

    public function compileRenameColumn(Blueprint $blueprint, string $from, string $to): string
    {
        $table = $blueprint->getTable();
        return "IF EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('[{$table}]') AND name = '{$from}')\n" .
            "EXEC sp_rename '[{$table}].[{$from}]', '{$to}', 'COLUMN'";
    }
}