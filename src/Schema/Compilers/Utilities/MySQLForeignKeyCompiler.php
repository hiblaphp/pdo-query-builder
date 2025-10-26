<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Schema\Compilers\Utilities;

class MySQLForeignKeyCompiler extends ForeignKeyCompiler
{
    public function __construct()
    {
        $this->columnDelimiter = '`, `';
        $this->openQuote = '`';
        $this->closeQuote = '`';
    }
}
