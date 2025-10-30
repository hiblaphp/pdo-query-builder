<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Schema\Compilers\Utilities;

use Hibla\QueryBuilder\Exceptions\SchemaCompilerException;
use Hibla\QueryBuilder\Schema\Column;

/**
 * SQLServer-specific type mapping
 */
class SQLServerTypeMapper extends ColumnTypeMapper
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
            'BIGINT' => 'BIGINT',
            'INT' => 'INT',
            'MEDIUMINT' => 'SMALLINT',
            'TINYINT' => 'TINYINT',
            'SMALLINT' => 'SMALLINT',
            'VARCHAR' => $column->getLength() !== null ? "NVARCHAR({$column->getLength()})" : 'NVARCHAR(255)',
            'TEXT', 'MEDIUMTEXT', 'LONGTEXT' => 'NVARCHAR(MAX)',
            'DECIMAL' => $this->formatPrecisionScale('DECIMAL', $column),
            'FLOAT' => 'FLOAT',
            'DOUBLE' => 'FLOAT',
            'DATETIME', 'TIMESTAMP' => 'DATETIME2',
            'DATE' => 'DATE',
            'JSON' => 'NVARCHAR(MAX)',
            'BOOLEAN' => 'BIT',
            'ENUM' => 'NVARCHAR(50)',
            'POINT' => 'geometry',
            'LINESTRING' => 'geometry',
            'POLYGON' => 'geometry',
            'GEOMETRY' => 'geometry',
            'GEOGRAPHY' => 'geography',
            default => $type,
        };
    }
}
