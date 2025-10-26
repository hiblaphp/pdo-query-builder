<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Schema\Compilers\Utilities;

use Hibla\QueryBuilder\Schema\Column;

/**
 * SQLite-specific type mapping
 */
class SQLiteTypeMapper extends ColumnTypeMapper
{
    public function mapType(string $type, Column $column): string
    {
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
