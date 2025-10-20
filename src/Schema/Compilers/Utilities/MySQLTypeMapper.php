<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema\Compilers\Utilities;

use Hibla\PdoQueryBuilder\Schema\Column;

/**
 * MySQL-specific type mapping
 */
class MySQLTypeMapper extends ColumnTypeMapper
{
    public function __construct()
    {
        $this->typeMap = [
            'BIGINT' => 'BIGINT',
            'INT' => 'INT',
            'MEDIUMINT' => 'MEDIUMINT',
            'TINYINT' => 'TINYINT',
            'SMALLINT' => 'SMALLINT',
            'VARCHAR' => 'VARCHAR',
            'TEXT' => 'TEXT',
            'MEDIUMTEXT' => 'MEDIUMTEXT',
            'LONGTEXT' => 'LONGTEXT',
            'DECIMAL' => 'DECIMAL',
            'FLOAT' => 'FLOAT',
            'DOUBLE' => 'DOUBLE',
            'DATETIME' => 'DATETIME',
            'TIMESTAMP' => 'TIMESTAMP',
            'DATE' => 'DATE',
            'JSON' => 'JSON',
            'BOOLEAN' => 'BOOLEAN',
        ];
    }

    public function mapType(string $type, Column $column): string
    {
        return match (true) {
            $type === 'ENUM' => 'ENUM',
            in_array($type, ['DECIMAL', 'FLOAT', 'DOUBLE']) => $this->formatPrecisionScale($type, $column),
            $column->getLength() !== null => $this->formatLength($type, $column),
            default => $this->typeMap[$type] ?? $type,
        };
    }
}
