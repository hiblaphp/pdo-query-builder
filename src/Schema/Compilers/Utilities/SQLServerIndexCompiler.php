<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Schema\Compilers\Utilities;

use Hibla\QueryBuilder\Schema\IndexDefinition;

class SQLServerIndexCompiler extends IndexCompiler
{
    private bool $isSystemDatabase = false;

    public function __construct(bool $isSystemDatabase = false)
    {
        $this->isSystemDatabase = $isSystemDatabase;
        $this->columnDelimiter = '], [';
        $this->openQuote = '[';
        $this->closeQuote = ']';
    }

    public function compilePrimaryIndex(IndexDefinition $indexDef): string
    {
        $cols = $this->getColumnsList($indexDef);

        return "CONSTRAINT [PK_{$indexDef->getName()}] PRIMARY KEY CLUSTERED ([{$cols}])";
    }

    public function compileIndexDefinitionStatement(string $table, IndexDefinition $indexDef): string
    {
        $type = $indexDef->getType();
        $cols = $this->getColumnsList($indexDef);
        $name = $indexDef->getName();

        return match ($type) {
            'UNIQUE' => "CREATE UNIQUE INDEX [{$name}] ON [{$table}] ([{$cols}])",
            'FULLTEXT' => $this->compileFulltextIndexStatement($table, $indexDef),
            'SPATIAL' => $this->compileSpatialIndexStatement($table, $indexDef),
            'INDEX', 'RAW' => "CREATE INDEX [{$name}] ON [{$table}] ([{$cols}])",
            default => "CREATE INDEX [{$name}] ON [{$table}] ([{$cols}])",
        };
    }

    /**
     * SQLServer-specific fulltext index
     */
    private function compileFulltextIndexStatement(string $table, IndexDefinition $indexDef): string
    {
        if ($this->isSystemDatabase) {
            return "-- Full-text index skipped in system database for [{$table}]";
        }

        $cols = $this->getColumnsList($indexDef);
        $name = $indexDef->getName();

        return "BEGIN TRY\n".
            "  IF NOT EXISTS (SELECT * FROM sys.fulltext_catalogs WHERE is_default = 1)\n".
            "    CREATE FULLTEXT CATALOG ftCatalog AS DEFAULT;\n".
            "  \n".
            "  DECLARE @PKName_{$name} NVARCHAR(200);\n".
            "  SELECT @PKName_{$name} = i.name\n".
            "  FROM sys.indexes i\n".
            "  WHERE i.object_id = OBJECT_ID('[{$table}]') AND i.is_primary_key = 1;\n".
            "  \n".
            "  IF @PKName_{$name} IS NOT NULL\n".
            "    EXEC('CREATE FULLTEXT INDEX ON [{$table}] ([{$cols}]) KEY INDEX ' + @PKName_{$name} + ' WITH STOPLIST = SYSTEM');\n".
            "END TRY\n".
            "BEGIN CATCH\n".
            "  PRINT 'Warning: Could not create full-text index [{$name}] on table [{$table}]: ' + ERROR_MESSAGE();\n".
            'END CATCH';
    }

    /**
     * SQLServer-specific spatial index
     */
    private function compileSpatialIndexStatement(string $table, IndexDefinition $indexDef): string
    {
        $cols = $this->getColumnsList($indexDef);
        $name = $indexDef->getName();

        return "IF EXISTS (SELECT * FROM sys.indexes WHERE object_id = OBJECT_ID('[{$table}]') AND is_primary_key = 1 AND type_desc = 'CLUSTERED')\n".
            "  CREATE SPATIAL INDEX [{$name}] ON [{$table}] ([{$cols}]) ".
            "USING GEOMETRY_AUTO_GRID WITH (BOUNDING_BOX = (0, 0, 500, 500));\n".
            "ELSE\n".
            "  RAISERROR('Table [{$table}] must have a clustered primary key before creating spatial index', 16, 1)";
    }

    /**
     * @return array<int, string>
     */
    public function compileAddIndexDefinition(string $table, IndexDefinition $indexDef): array
    {
        $type = $indexDef->getType();
        $statements = [];

        if ($type === 'PRIMARY') {
            $cols = $this->getColumnsList($indexDef);
            $statements[] = "ALTER TABLE [{$table}] ADD CONSTRAINT [PK_{$indexDef->getName()}] PRIMARY KEY CLUSTERED ([{$cols}])";
        } elseif ($type === 'UNIQUE') {
            $cols = $this->getColumnsList($indexDef);
            $statements[] = "IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = '{$indexDef->getName()}' AND object_id = OBJECT_ID('[{$table}]'))\n".
                "CREATE UNIQUE INDEX [{$indexDef->getName()}] ON [{$table}] ([{$cols}])";
        } elseif ($type === 'FULLTEXT') {
            $fullTextSql = $this->compileFulltextIndexStatement($table, $indexDef);
            if (! str_starts_with($fullTextSql, '--')) {
                $statements[] = $fullTextSql;
            }
        } elseif ($type === 'SPATIAL') {
            $statements[] = $this->compileSpatialIndexStatement($table, $indexDef);
        } elseif ($type === 'INDEX') {
            $cols = $this->getColumnsList($indexDef);
            $statements[] = "IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = '{$indexDef->getName()}' AND object_id = OBJECT_ID('[{$table}]'))\n".
                "CREATE INDEX [{$indexDef->getName()}] ON [{$table}] ([{$cols}])";
        }

        return $statements;
    }

    /**
     * @param array<int, array<int, string>> $indexes
     * @return array<int, string>
     */
    public function compileDropIndexes(string $table, array $indexes): array
    {
        $statements = [];
        foreach ($indexes as $index) {
            $indexName = $index[0];
            if ($indexName === 'PRIMARY') {
                $statements[] = "IF EXISTS (SELECT * FROM sys.key_constraints WHERE name = 'PK_{$table}' AND parent_object_id = OBJECT_ID('[{$table}]'))\n".
                    "ALTER TABLE [{$table}] DROP CONSTRAINT [PK_{$table}]";
            } else {
                $statements[] = "IF EXISTS (SELECT * FROM sys.indexes WHERE name = '{$indexName}' AND object_id = OBJECT_ID('[{$table}]'))\n".
                    "DROP INDEX [{$indexName}] ON [{$table}]";
            }
        }

        return $statements;
    }

    public function compileDropForeignKey(string $table, string $foreignKey): string
    {
        return "IF EXISTS (SELECT * FROM sys.foreign_keys WHERE name = '{$foreignKey}' AND parent_object_id = OBJECT_ID('[{$table}]'))\n".
            "ALTER TABLE [{$table}] DROP CONSTRAINT [{$foreignKey}]";
    }

    public function compileDropDefaultConstraint(string $table, string $column): string
    {
        return "DECLARE @ConstraintName NVARCHAR(200);\n".
            "SELECT @ConstraintName = dc.name\n".
            "FROM sys.default_constraints dc\n".
            "INNER JOIN sys.columns c ON dc.parent_column_id = c.column_id AND dc.parent_object_id = c.object_id\n".
            "WHERE dc.parent_object_id = OBJECT_ID('[{$table}]') AND c.name = '{$column}';\n".
            "IF @ConstraintName IS NOT NULL\n".
            "EXEC('ALTER TABLE [{$table}] DROP CONSTRAINT [' + @ConstraintName + ']')";
    }
}
