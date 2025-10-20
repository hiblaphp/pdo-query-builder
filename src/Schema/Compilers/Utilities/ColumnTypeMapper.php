<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema\Compilers\Utilities;

use Hibla\PdoQueryBuilder\Schema\Column;

/**
 * Handles common column type operations across different database systems
 */
class ColumnTypeMapper
{
    protected array $typeMap = [];

    public function mapType(string $type, Column $column): string
    {
        return $this->typeMap[$type] ?? $type;
    }

    protected function formatPrecisionScale(string $type, Column $column): string
    {
        return "{$type}({$column->getPrecision()}, {$column->getScale()})";
    }

    protected function formatLength(string $type, Column $column): string
    {
        return "{$type}({$column->getLength()})";
    }

    protected function isAutoIncrementType(string $type, Column $column): bool
    {
        return $column->isAutoIncrement() && in_array($type, ['BIGINT', 'INT', 'MEDIUMINT', 'SMALLINT']);
    }
}
