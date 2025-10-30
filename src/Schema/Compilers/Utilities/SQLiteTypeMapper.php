<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Schema\Compilers\Utilities;

use Hibla\QueryBuilder\Exceptions\SchemaCompilerException;
use Hibla\QueryBuilder\Schema\Column;

/**
 * SQLite-specific type mapping
 */
class SQLiteTypeMapper extends ColumnTypeMapper
{
    public function mapType(string $type, Column $column): string
    {
        if ($type === 'VECTOR') {
            throw new SchemaCompilerException(
                'Vector columns are only supported in PostgreSQL. ' .
                    'Please use PostgreSQL with the pgvector extension for vector database functionality.'
            );
        }

        return match ($type) {
            'BIGINT', 'INT', 'TINYINT', 'SMALLINT', 'MEDIUMINT' => 'INTEGER',
            'VARCHAR' => 'TEXT',
            'TEXT', 'MEDIUMTEXT', 'LONGTEXT' => 'TEXT',
            'DECIMAL', 'FLOAT', 'DOUBLE' => 'REAL',
            'DATETIME', 'TIMESTAMP', 'DATE' => 'TEXT',
            'JSON' => 'TEXT',
            'BOOLEAN' => 'INTEGER',
            'POINT', 'LINESTRING', 'POLYGON', 'GEOMETRY',
            'MULTIPOINT', 'MULTILINESTRING', 'MULTIPOLYGON', 'GEOMETRYCOLLECTION' => 'TEXT',
            default => $type,
        };
    }
}
