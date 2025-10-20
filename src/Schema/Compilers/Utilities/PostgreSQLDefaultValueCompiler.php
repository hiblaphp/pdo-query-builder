<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema\Compilers\Utilities;

use Hibla\PdoQueryBuilder\Schema\Column;

class PostgreSQLDefaultValueCompiler extends DefaultValueCompiler
{
    public function __construct()
    {
        $this->expressionList = [];
    }

    public function compile(mixed $default, ?Column $column = null): string
    {
        if ($default === null) {
            return 'NULL';
        }

        if (is_bool($default)) {
            if ($column && $column->getType() === 'TINYINT' && $column->getLength() === 1) {
                return $default ? 'true' : 'false';
            }

            return $default ? 'true' : 'false';
        }

        if (is_numeric($default)) {
            if ($column && $column->getType() === 'TINYINT' && $column->getLength() === 1) {
                return $default ? 'true' : 'false';
            }

            return (string) $default;
        }

        return "'".addslashes((string) $default)."'";
    }
}
