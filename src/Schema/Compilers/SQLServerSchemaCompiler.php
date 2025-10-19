<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema\Compilers;

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Hibla\PdoQueryBuilder\Schema\Column;
use Hibla\PdoQueryBuilder\Schema\IndexDefinition;
use Hibla\PdoQueryBuilder\Schema\SchemaCompiler;
use PDO;

class SQLServerSchemaCompiler implements SchemaCompiler
{
    private ?PDO $connection = null;
    private bool $isSystemDatabase = false;

    public function __construct(bool $isSystemDatabase = false)
    {
        $this->isSystemDatabase = $isSystemDatabase;
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
        $primaryKeyColumn = null;

        foreach ($columns as $column) {
            $columnDefinitions[] = '  ' . $this->compileColumn($column);

            if ($column->getName() === 'id' && $column->isAutoIncrement()) {
                $primaryKeyColumn = 'id';
            }
        }

        foreach ($indexDefinitions as $indexDef) {
            if ($indexDef->getType() === 'PRIMARY') {
                $columnDefinitions[] = '  ' . $this->compilePrimaryIndex($indexDef);
                $hasPrimaryKey = true;
                break;
            }
        }

        if (!$hasPrimaryKey && $primaryKeyColumn !== null) {
            $columnDefinitions[] = "  CONSTRAINT [PK_{$table}] PRIMARY KEY CLUSTERED ([{$primaryKeyColumn}])";
            $hasPrimaryKey = true;
        }

        $sql .= implode(",\n", $columnDefinitions);
        $sql .= "\n);\n";

        foreach ($indexDefinitions as $indexDef) {
            if ($indexDef->getType() !== 'PRIMARY') {
                $indexSql = $this->compileIndexDefinitionStatement($table, $indexDef);
                if (!empty($indexSql) && !str_starts_with($indexSql, '--')) {
                    $sql .= $indexSql . ";\n";
                }
            }
        }

        foreach ($foreignKeys as $foreignKey) {
            $sql .= "ALTER TABLE [{$table}] ADD " . $this->compileForeignKey($foreignKey) . ";\n";
        }

        $sql .= "END";

        return $sql;
    }

    private function compilePrimaryIndex(IndexDefinition $indexDef): string
    {
        $cols = implode('], [', $indexDef->getColumns());
        return "CONSTRAINT [PK_{$indexDef->getName()}] PRIMARY KEY CLUSTERED ([{$cols}])";
    }

    private function compileIndexDefinitionStatement(string $table, IndexDefinition $indexDef): string
    {
        $type = $indexDef->getType();
        $cols = implode('], [', $indexDef->getColumns());
        $name = $indexDef->getName();

        return match ($type) {
            'UNIQUE' => "CREATE UNIQUE INDEX [{$name}] ON [{$table}] ([{$cols}])",
            'FULLTEXT' => $this->compileFulltextIndex($table, $indexDef),
            'SPATIAL' => $this->compileSpatialIndex($table, $indexDef),
            'INDEX', 'RAW' => "CREATE INDEX [{$name}] ON [{$table}] ([{$cols}])",
            default => "CREATE INDEX [{$name}] ON [{$table}] ([{$cols}])",
        };
    }

    private function compileFulltextIndex(string $table, IndexDefinition $indexDef): string
    {
        if ($this->isSystemDatabase) {
            return "-- Full-text index skipped in system database for [{$table}]";
        }

        $cols = implode('], [', $indexDef->getColumns());
        $name = $indexDef->getName();

        return "BEGIN TRY\n" .
            "  IF NOT EXISTS (SELECT * FROM sys.fulltext_catalogs WHERE is_default = 1)\n" .
            "    CREATE FULLTEXT CATALOG ftCatalog AS DEFAULT;\n" .
            "  \n" .
            "  DECLARE @PKName_{$name} NVARCHAR(200);\n" .
            "  SELECT @PKName_{$name} = i.name\n" .
            "  FROM sys.indexes i\n" .
            "  WHERE i.object_id = OBJECT_ID('[{$table}]') AND i.is_primary_key = 1;\n" .
            "  \n" .
            "  IF @PKName_{$name} IS NOT NULL\n" .
            "    EXEC('CREATE FULLTEXT INDEX ON [{$table}] ([{$cols}]) KEY INDEX ' + @PKName_{$name} + ' WITH STOPLIST = SYSTEM');\n" .
            "END TRY\n" .
            "BEGIN CATCH\n" .
            "  -- Full-Text Search not available or other error, skip index creation\n" .
            "  -- Cannot create regular index on TEXT/NVARCHAR(MAX) columns\n" .
            "  PRINT 'Warning: Could not create full-text index [{$name}] on table [{$table}]: ' + ERROR_MESSAGE();\n" .
            "END CATCH";
    }

    private function compileSpatialIndex(string $table, IndexDefinition $indexDef): string
    {
        $cols = implode('], [', $indexDef->getColumns());
        $name = $indexDef->getName();

        return "IF EXISTS (SELECT * FROM sys.indexes WHERE object_id = OBJECT_ID('[{$table}]') AND is_primary_key = 1 AND type_desc = 'CLUSTERED')\n" .
            "  CREATE SPATIAL INDEX [{$name}] ON [{$table}] ([{$cols}]) " .
            "USING GEOMETRY_AUTO_GRID WITH (BOUNDING_BOX = (0, 0, 500, 500));\n" .
            "ELSE\n" .
            "  RAISERROR('Table [{$table}] must have a clustered primary key before creating spatial index', 16, 1)";
    }

    private function compileColumn(Column $column): string
    {
        $sql = "[{$column->getName()}] ";

        $type = $this->mapType($column->getType(), $column);
        $sql .= $type;

        if ($column->isAutoIncrement() && $column->isPrimary()) {
            $sql .= ' IDENTITY(1,1)';
        }

        if (!$column->isNullable()) {
            $sql .= ' NOT NULL';
        } else {
            $sql .= ' NULL';
        }

        if ($column->hasDefault() && !$column->isAutoIncrement()) {
            $default = $column->getDefault();
            if ($default === null) {
                $sql .= ' DEFAULT NULL';
            } elseif (is_bool($default)) {
                $sql .= ' DEFAULT ' . ($default ? '1' : '0');
            } elseif (is_numeric($default)) {
                $sql .= " DEFAULT {$default}";
            } elseif ($this->isDefaultExpression($default)) {
                $sql .= " DEFAULT {$default}";
            } else {
                $sql .= " DEFAULT " . $this->quoteValue($default);
            }
        } elseif ($column->shouldUseCurrent()) {
            $sql .= ' DEFAULT GETDATE()';
        }

        return $sql;
    }

    /**
     * Map column types to SQL Server types
     */
    private function mapType(string $type, Column $column): string
    {
        return match ($type) {
            'BIGINT' => 'BIGINT',
            'INT' => 'INT',
            'MEDIUMINT' => 'SMALLINT',
            'TINYINT' => 'TINYINT',
            'SMALLINT' => 'SMALLINT',
            'VARCHAR' => $column->getLength() ? "NVARCHAR({$column->getLength()})" : 'NVARCHAR(255)',
            'TEXT', 'MEDIUMTEXT', 'LONGTEXT' => 'NVARCHAR(MAX)',
            'DECIMAL' => "DECIMAL({$column->getPrecision()}, {$column->getScale()})",
            'FLOAT' => 'FLOAT',
            'DOUBLE' => 'FLOAT',
            'DATETIME', 'TIMESTAMP' => 'DATETIME2',
            'DATE' => 'DATE',
            'JSON' => 'NVARCHAR(MAX)',
            'BOOLEAN' => 'BIT',
            'ENUM' => $this->compileEnum($column),
            'POINT' => 'geometry',
            'LINESTRING' => 'geometry',
            'POLYGON' => 'geometry',
            'GEOMETRY' => 'geometry',
            'GEOGRAPHY' => 'geography',
            default => $type,
        };
    }

    /**
     * Compile ENUM as VARCHAR (SQL Server doesn't have native enum)
     */
    private function compileEnum(Column $column): string
    {
        return "NVARCHAR(50)";
    }

    /**
     * Check if a default value is an expression
     */
    private function isDefaultExpression(string $value): bool
    {
        $expressions = [
            'GETDATE()',
            'GETUTCDATE()',
            'CURRENT_TIMESTAMP',
            'NEWID()',
            'SYSDATETIME()',
        ];

        return in_array(strtoupper($value), $expressions);
    }

    /**
     * Compile foreign key constraint
     * Must be added AFTER table creation to ensure primary key exists
     * 
     * Note: SQL Server does not allow CASCADE on self-referencing foreign keys
     * as it may cause cycles or multiple cascade paths
     */
    private function compileForeignKey($foreignKey): string
    {
        $cols = implode('], [', $foreignKey->getColumns());
        $refCols = implode('], [', $foreignKey->getReferenceColumns());

        $sql = "CONSTRAINT [{$foreignKey->getName()}] FOREIGN KEY ([{$cols}]) " .
            "REFERENCES [{$foreignKey->getReferenceTable()}] ([{$refCols}])";

        if ($foreignKey->getOnDelete()) {
            $action = $foreignKey->getOnDelete();

            if ($action === 'NULL') {
                $action = 'SET NULL';
            }

            if ($action === 'RESTRICT') {
                $action = 'NO ACTION';
            }

            if ($action !== 'NO ACTION') {
                $sql .= " ON DELETE {$action}";
            }
        }

        if ($foreignKey->getOnUpdate()) {
            $action = $foreignKey->getOnUpdate();

            if ($action === 'NULL') {
                $action = 'SET NULL';
            }

            if ($action === 'RESTRICT') {
                $action = 'NO ACTION';
            }

            if ($action !== 'NO ACTION') {
                $sql .= " ON UPDATE {$action}";
            }
        }

        return $sql;
    }

    public function compileAlter(Blueprint $blueprint): string|array
    {
        $table = $blueprint->getTable();
        $statements = [];

        foreach ($blueprint->getDropColumns() as $column) {
            $statements[] = $this->compileDropDefaultConstraint($table, $column);
            $statements[] = "IF EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('[{$table}]') AND name = '{$column}')\n" .
                "ALTER TABLE [{$table}] DROP COLUMN [{$column}]";
        }

        foreach ($blueprint->getDropForeignKeys() as $foreignKey) {
            $statements[] = $this->compileDropForeignKey($table, $foreignKey);
        }

        foreach ($blueprint->getDropIndexes() as $index) {
            if ($index[0] === 'PRIMARY') {
                $statements[] = "IF EXISTS (SELECT * FROM sys.key_constraints WHERE name = 'PK_{$table}' AND parent_object_id = OBJECT_ID('[{$table}]'))\n" .
                    "ALTER TABLE [{$table}] DROP CONSTRAINT [PK_{$table}]";
            } else {
                $indexName = $index[0];
                $statements[] = "IF EXISTS (SELECT * FROM sys.indexes WHERE name = '{$indexName}' AND object_id = OBJECT_ID('[{$table}]'))\n" .
                    "DROP INDEX [{$indexName}] ON [{$table}]";
            }
        }

        foreach ($blueprint->getRenameColumns() as $rename) {
            $statements[] = $this->compileRenameColumn($blueprint, $rename['from'], $rename['to']);
        }

        foreach ($blueprint->getModifyColumns() as $column) {
            $statements[] = $this->compileDropDefaultConstraint($table, $column->getName());
            $statements[] = "ALTER TABLE [{$table}] ALTER COLUMN " . $this->compileColumn($column);
        }

        foreach ($blueprint->getColumns() as $column) {
            $statements[] = "ALTER TABLE [{$table}] ADD " . $this->compileColumn($column);
        }

        foreach ($blueprint->getIndexDefinitions() as $indexDef) {
            $indexStatements = $this->compileAddIndexDefinition($table, $indexDef);
            $statements = array_merge($statements, array_filter($indexStatements));
        }

        foreach ($blueprint->getForeignKeys() as $foreignKey) {
            $statements[] = "IF NOT EXISTS (SELECT * FROM sys.foreign_keys WHERE name = '{$foreignKey->getName()}' AND parent_object_id = OBJECT_ID('[{$table}]'))\n" .
                "ALTER TABLE [{$table}] ADD " . $this->compileForeignKey($foreignKey);
        }

        return count($statements) === 1 ? $statements[0] : $statements;
    }

    private function compileAddIndexDefinition(string $table, IndexDefinition $indexDef): array
    {
        $type = $indexDef->getType();
        $statements = [];

        if ($type === 'PRIMARY') {
            $cols = implode('], [', $indexDef->getColumns());
            $statements[] = "ALTER TABLE [{$table}] ADD CONSTRAINT [PK_{$indexDef->getName()}] PRIMARY KEY CLUSTERED ([{$cols}])";
        } elseif ($type === 'UNIQUE') {
            $cols = implode('], [', $indexDef->getColumns());
            $statements[] = "IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = '{$indexDef->getName()}' AND object_id = OBJECT_ID('[{$table}]'))\n" .
                "CREATE UNIQUE INDEX [{$indexDef->getName()}] ON [{$table}] ([{$cols}])";
        } elseif ($type === 'FULLTEXT') {
            $fullTextSql = $this->compileFulltextIndex($table, $indexDef);
            if (!str_starts_with($fullTextSql, '--')) {
                $statements[] = $fullTextSql;
            }
        } elseif ($type === 'SPATIAL') {
            $statements[] = $this->compileSpatialIndex($table, $indexDef);
        } elseif ($type === 'INDEX') {
            $cols = implode('], [', $indexDef->getColumns());
            $statements[] = "IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = '{$indexDef->getName()}' AND object_id = OBJECT_ID('[{$table}]'))\n" .
                "CREATE INDEX [{$indexDef->getName()}] ON [{$table}] ([{$cols}])";
        }

        return $statements;
    }

    /**
     * Compile drop foreign key with existence check
     */
    private function compileDropForeignKey(string $table, string $foreignKey): string
    {
        return "IF EXISTS (SELECT * FROM sys.foreign_keys WHERE name = '{$foreignKey}' AND parent_object_id = OBJECT_ID('[{$table}]'))\n" .
            "ALTER TABLE [{$table}] DROP CONSTRAINT [{$foreignKey}]";
    }

    /**
     * Compile drop default constraint
     */
    private function compileDropDefaultConstraint(string $table, string $column): string
    {
        return "DECLARE @ConstraintName NVARCHAR(200);\n" .
            "SELECT @ConstraintName = dc.name\n" .
            "FROM sys.default_constraints dc\n" .
            "INNER JOIN sys.columns c ON dc.parent_column_id = c.column_id AND dc.parent_object_id = c.object_id\n" .
            "WHERE dc.parent_object_id = OBJECT_ID('[{$table}]') AND c.name = '{$column}';\n" .
            "IF @ConstraintName IS NOT NULL\n" .
            "EXEC('ALTER TABLE [{$table}] DROP CONSTRAINT [' + @ConstraintName + ']')";
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
            $statements[] = $this->compileDropDefaultConstraint($table, $column);
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

    private function quoteValue(string $value): string
    {
        if ($this->connection) {
            return $this->connection->quote($value);
        }

        $escaped = str_replace("'", "''", $value);
        return "N'{$escaped}'";
    }
}
