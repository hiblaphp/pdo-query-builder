<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema\Compilers\Utilities;

/**
 * MySQL-specific default value compilation
 */
class MySQLDefaultValueCompiler extends DefaultValueCompiler
{
    public function __construct()
    {
        $this->expressionList = [
            'CURRENT_TIMESTAMP',
            'NOW()',
            'UUID()',
            'CURRENT_DATE',
            'CURRENT_TIME',
        ];
    }

    public function compileWithPrefix(mixed $default): string
    {
        return ' DEFAULT '.$this->compile($default);
    }

    public function compileCurrentTimestamp(): string
    {
        return ' DEFAULT CURRENT_TIMESTAMP';
    }
}
