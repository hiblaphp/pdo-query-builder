<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema\Compilers;

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Hibla\PdoQueryBuilder\Schema\Column;
use Hibla\PdoQueryBuilder\Schema\IndexDefinition;
use Hibla\PdoQueryBuilder\Schema\SchemaCompiler;

class PostgreSQLSchemaCompiler implements SchemaCompiler
{
    private bool $useConcurrentIndexes = false;
    private bool $useNotValidConstraints = false;

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
            if ($indexDef->getType() === 'PRIMARY' || $indexDef->getType() === 'UNIQUE') {
                $columnDefinitions[] = '  ' . $this->compileIndexDefinition($indexDef);
            }
        }

        foreach ($foreignKeys as $foreignKey) {
            $columnDefinitions[] = '  ' . $this->compileForeignKey($foreignKey);
        }

        $sql .= implode(",\n", $columnDefinitions);
        $sql .= "\n)";

        return $sql;
    }

    /**
     * Compile index definition for PostgreSQL
     */
    private function compileIndexDefinition(IndexDefinition $indexDef): string
    {
        $type = $indexDef->getType();
        $cols = implode('", "', $indexDef->getColumns());

        return match ($type) {
            'PRIMARY' => "PRIMARY KEY (\"{$cols}\")",
            'UNIQUE' => "CONSTRAINT \"{$indexDef->getName()}\" UNIQUE (\"{$cols}\")",
            default => '',
        };
    }

    private function compileColumn(Column $column): string
    {
        $sql = "\"{$column->getName()}\" ";

        $type = $this->mapType($column->getType(), $column);
        $sql .= $type;

        if (!$column->isNullable()) {
            $sql .= ' NOT NULL';
        }

        if ($column->isPrimary()) {
            $sql .= ' PRIMARY KEY';
        }

        if ($column->hasDefault()) {
            $sql .= ' DEFAULT ' . $this->formatDefault($column->getDefault(), $column);
        } elseif ($column->shouldUseCurrent()) {
            $sql .= ' DEFAULT CURRENT_TIMESTAMP';
        }

        return $sql;
    }

    private function formatDefault(mixed $default, ?Column $column = null): string
    {
        if ($default === null) {
            return 'NULL';
        } elseif (is_bool($default)) {
            return $default ? 'true' : 'false';
        } elseif (is_numeric($default)) {
            if ($column && $column->getType() === 'TINYINT' && $column->getLength() === 1) {
                return $default ? 'true' : 'false';
            }
            return (string) $default;
        } else {
            return "'" . addslashes((string) $default) . "'";
        }
    }

    private function mapType(string $type, Column $column): string
    {
        return match ($type) {
            'BIGINT' => $column->isAutoIncrement() ? 'BIGSERIAL' : 'BIGINT',
            'INT' => $column->isAutoIncrement() ? 'SERIAL' : 'INTEGER',
            'MEDIUMINT' => $column->isAutoIncrement() ? 'SERIAL' : 'INTEGER', 
            'TINYINT' => $column->getLength() === 1 ? 'BOOLEAN' : 'SMALLINT',
            'SMALLINT' => $column->isAutoIncrement() ? 'SMALLSERIAL' : 'SMALLINT',
            'VARCHAR' => $column->getLength() ? "VARCHAR({$column->getLength()})" : 'VARCHAR',
            'TEXT', 'MEDIUMTEXT', 'LONGTEXT' => 'TEXT',
            'DECIMAL' => "DECIMAL({$column->getPrecision()}, {$column->getScale()})",
            'FLOAT' => 'REAL',
            'DOUBLE' => 'DOUBLE PRECISION',
            'DATETIME' => 'TIMESTAMP',
            'TIMESTAMP' => 'TIMESTAMP',
            'DATE' => 'DATE',
            'JSON' => 'JSONB',
            'ENUM' => $this->compileEnum($column),
            'POINT' => 'GEOMETRY(POINT)',
            'LINESTRING' => 'GEOMETRY(LINESTRING)',
            'POLYGON' => 'GEOMETRY(POLYGON)',
            'GEOMETRY' => 'GEOMETRY',
            default => $type,
        };
    }

    private function compileEnum(Column $column): string
    {
        $values = array_map(fn($v) => "'" . addslashes($v) . "'", $column->getEnumValues());
        return "VARCHAR(255) CHECK (\"{$column->getName()}\" IN (" . implode(', ', $values) . "))";
    }

    private function compileForeignKey($foreignKey, bool $notValid = false): string
    {
        $cols = implode('", "', $foreignKey->getColumns());
        $refCols = implode('", "', $foreignKey->getReferenceColumns());

        $sql = "CONSTRAINT \"{$foreignKey->getName()}\" FOREIGN KEY (\"{$cols}\") " .
            "REFERENCES \"{$foreignKey->getReferenceTable()}\" (\"{$refCols}\")";

        if ($foreignKey->getOnDelete() !== 'RESTRICT') {
            $sql .= " ON DELETE {$foreignKey->getOnDelete()}";
        }

        if ($foreignKey->getOnUpdate() !== 'RESTRICT') {
            $sql .= " ON UPDATE {$foreignKey->getOnUpdate()}";
        }

        if ($notValid || $this->useNotValidConstraints) {
            $sql .= " NOT VALID";
        }

        return $sql;
    }

    public function compileAlter(Blueprint $blueprint): string|array
    {
        $table = $blueprint->getTable();
        $statements = [];

        foreach ($blueprint->getDropColumns() as $column) {
            $statements[] = "ALTER TABLE \"{$table}\" DROP COLUMN IF EXISTS \"{$column}\"";
        }

        foreach ($blueprint->getDropForeignKeys() as $foreignKey) {
            $statements[] = $this->compileDropForeignKey($table, $foreignKey);
        }

        foreach ($blueprint->getDropIndexes() as $index) {
            if ($index[0] === 'PRIMARY') {
                $statements[] = "ALTER TABLE \"{$table}\" DROP CONSTRAINT IF EXISTS \"{$table}_pkey\"";
            } else {
                $indexName = $index[0];
                // Try to drop as constraint first (for unique constraints), then as index
                $statements[] = "DO $$ BEGIN ALTER TABLE \"{$table}\" DROP CONSTRAINT IF EXISTS \"{$indexName}\"; EXCEPTION WHEN undefined_object THEN NULL; END $$";
                $statements[] = "DROP INDEX IF EXISTS \"{$indexName}\"";
            }
        }

        foreach ($blueprint->getRenameColumns() as $rename) {
            $statements[] = $this->compileRenameColumn($blueprint, $rename['from'], $rename['to']);
        }

        foreach ($blueprint->getModifyColumns() as $column) {
            $statements = array_merge($statements, $this->compileModifyColumn($table, $column));
        }

        foreach ($blueprint->getColumns() as $column) {
            $statements = array_merge($statements, $this->compileAddColumn($table, $column));
        }

        foreach ($blueprint->getIndexDefinitions() as $indexDef) {
            $statements = array_merge($statements, $this->compileAddIndexDefinition($table, $indexDef));
        }

        foreach ($blueprint->getForeignKeys() as $foreignKey) {
            $statements[] = "ALTER TABLE \"{$table}\" ADD " . $this->compileForeignKey($foreignKey, $this->useNotValidConstraints);

            if ($this->useNotValidConstraints) {
                $statements[] = $this->compileValidateConstraint($table, $foreignKey->getName());
            }
        }

        foreach ($blueprint->getCommands() as $command) {
            if ($command['type'] === 'rename') {
                $statements[] = $this->compileRename($table, $command['to']);
            }
        }

        return count($statements) === 1 ? $statements[0] : $statements;
    }

    private function compileAddIndexDefinition(string $table, IndexDefinition $indexDef): array
    {
        $type = $indexDef->getType();
        $statements = [];

        if ($type === 'PRIMARY') {
            $cols = implode('", "', $indexDef->getColumns());
            $statements[] = "ALTER TABLE \"{$table}\" ADD PRIMARY KEY (\"{$cols}\")";
        } elseif ($type === 'UNIQUE') {
            $cols = implode('", "', $indexDef->getColumns());
            $statements[] = "ALTER TABLE \"{$table}\" ADD CONSTRAINT \"{$indexDef->getName()}\" UNIQUE (\"{$cols}\")";
        } elseif ($type === 'FULLTEXT') {
            $statements = array_merge($statements, $this->compileFulltextIndex($table, $indexDef));
        } elseif ($type === 'SPATIAL') {
            $statements = array_merge($statements, $this->compileSpatialIndex($table, $indexDef));
        } elseif ($type === 'INDEX') {
            $cols = implode('", "', $indexDef->getColumns());
            $sql = "CREATE INDEX IF NOT EXISTS \"{$indexDef->getName()}\" ON \"{$table}\" (\"{$cols}\")";
            if ($indexDef->getAlgorithm()) {
                $algo = strtoupper($indexDef->getAlgorithm());
                if (in_array($algo, ['BTREE', 'HASH', 'GIST', 'GIN', 'BRIN'])) {
                    $sql .= " USING {$algo}";
                }
            }
            $statements[] = $sql;
        }

        return $statements;
    }

    private function compileFulltextIndex(string $table, IndexDefinition $indexDef): array
    {
        $statements = [];
        $cols = implode(" || ' ' || ", array_map(fn($c) => "\"{$c}\"", $indexDef->getColumns()));
        $name = $indexDef->getName();

        $statements[] = "CREATE INDEX IF NOT EXISTS \"{$name}\" ON \"{$table}\" USING gin(to_tsvector('english', {$cols}))";

        return $statements;
    }

    private function compileSpatialIndex(string $table, IndexDefinition $indexDef): array
    {
        $statements = [];
        $cols = implode('", "', $indexDef->getColumns());
        $name = $indexDef->getName();
        $operatorClass = $indexDef->getOperatorClass() ?? 'gist';

        $statements[] = "CREATE INDEX IF NOT EXISTS \"{$name}\" ON \"{$table}\" USING {$operatorClass} (\"{$cols}\")";

        return $statements;
    }

    private function compileAddColumn(string $table, Column $column): array
    {
        $statements = [];

        $colDef = $this->compileColumnWithoutDefault($column);
        $statements[] = "ALTER TABLE \"{$table}\" ADD COLUMN IF NOT EXISTS {$colDef}";

        if ($column->hasDefault()) {
            $default = $this->formatDefault($column->getDefault(), $column);
            $statements[] = "ALTER TABLE \"{$table}\" ALTER COLUMN \"{$column->getName()}\" SET DEFAULT {$default}";
        } elseif ($column->shouldUseCurrent()) {
            $statements[] = "ALTER TABLE \"{$table}\" ALTER COLUMN \"{$column->getName()}\" SET DEFAULT CURRENT_TIMESTAMP";
        }

        if ($column->getComment() !== null) {
            $comment = addslashes($column->getComment());
            $statements[] = "COMMENT ON COLUMN \"{$table}\".\"{$column->getName()}\" IS '{$comment}'";
        }

        return $statements;
    }

    private function compileColumnWithoutDefault(Column $column): string
    {
        $sql = "\"{$column->getName()}\" ";
        $type = $this->mapType($column->getType(), $column);
        $sql .= $type;

        if (!$column->isNullable()) {
            $sql .= ' NOT NULL';
        }

        return $sql;
    }

    /**
     * Compile column modification with USING clause support
     */
    private function compileModifyColumn(string $table, Column $column): array
    {
        $statements = [];
        $columnName = $column->getName();
        $newType = $this->mapType($column->getType(), $column);

        $statements[] = "DO $$ BEGIN ALTER TABLE \"{$table}\" ALTER COLUMN \"{$columnName}\" DROP DEFAULT; EXCEPTION WHEN undefined_column THEN NULL; END $$";

        $using = $this->getTypeConversionUsing($column, $newType);
        $statements[] = "ALTER TABLE \"{$table}\" ALTER COLUMN \"{$columnName}\" TYPE {$newType} USING {$using}";

        if (!$column->isNullable()) {
            $statements[] = "ALTER TABLE \"{$table}\" ALTER COLUMN \"{$columnName}\" SET NOT NULL";
        } else {
            $statements[] = "ALTER TABLE \"{$table}\" ALTER COLUMN \"{$columnName}\" DROP NOT NULL";
        }

        if ($column->hasDefault()) {
            $default = $this->formatDefault($column->getDefault(), $column);
            $statements[] = "ALTER TABLE \"{$table}\" ALTER COLUMN \"{$columnName}\" SET DEFAULT {$default}";
        } elseif ($column->shouldUseCurrent()) {
            $statements[] = "ALTER TABLE \"{$table}\" ALTER COLUMN \"{$columnName}\" SET DEFAULT CURRENT_TIMESTAMP";
        }

        return $statements;
    }

    /**
     * Get USING clause for type conversion
     */
    private function getTypeConversionUsing(Column $column, string $newType): string
    {
        $name = $column->getName();

        return "\"{$name}\"::{$newType}";
    }

    public function compileValidateConstraint(string $table, string $constraint): string
    {
        return "ALTER TABLE \"{$table}\" VALIDATE CONSTRAINT \"{$constraint}\"";
    }

    private function compileDropForeignKey(string $table, string $foreignKey): string
    {
        return "ALTER TABLE \"{$table}\" DROP CONSTRAINT IF EXISTS \"{$foreignKey}\"";
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
