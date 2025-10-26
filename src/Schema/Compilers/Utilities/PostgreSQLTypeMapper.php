<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Schema\Compilers\Utilities;

use Hibla\QueryBuilder\Schema\Column;

/**
 * PostgreSQL-specific type mapping
 */
class PostgreSQLTypeMapper extends ColumnTypeMapper
{
    public function mapType(string $type, Column $column): string
    {
        return match ($type) {
            'BIGINT' => $column->isAutoIncrement() ? 'BIGSERIAL' : 'BIGINT',
            'INT' => $column->isAutoIncrement() ? 'SERIAL' : 'INTEGER',
            'MEDIUMINT' => $column->isAutoIncrement() ? 'SERIAL' : 'INTEGER',
            'TINYINT' => $column->getLength() === 1 ? 'BOOLEAN' : 'SMALLINT',
            'SMALLINT' => $column->isAutoIncrement() ? 'SMALLSERIAL' : 'SMALLINT',
            'VARCHAR' => $column->getLength() !== null ? "VARCHAR({$column->getLength()})" : 'VARCHAR',
            'TEXT', 'MEDIUMTEXT', 'LONGTEXT' => 'TEXT',
            'DECIMAL' => $this->formatPrecisionScale('DECIMAL', $column),
            'FLOAT' => 'REAL',
            'DOUBLE' => 'DOUBLE PRECISION',
            'DATETIME' => 'TIMESTAMP',
            'TIMESTAMP' => 'TIMESTAMP',
            'DATE' => 'DATE',
            'JSON' => 'JSONB',
            'ENUM' => 'VARCHAR(255)',
            'POINT' => 'GEOMETRY(POINT)',
            'LINESTRING' => 'GEOMETRY(LINESTRING)',
            'POLYGON' => 'GEOMETRY(POLYGON)',
            'GEOMETRY' => 'GEOMETRY',
            default => $type,
        };
    }
}
