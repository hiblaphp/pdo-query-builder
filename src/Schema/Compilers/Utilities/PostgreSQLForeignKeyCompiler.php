<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Schema\Compilers\Utilities;

use Hibla\QueryBuilder\Schema\ForeignKey;

class PostgreSQLForeignKeyCompiler extends ForeignKeyCompiler
{
    public function __construct()
    {
        $this->columnDelimiter = '", "';
        $this->openQuote = '"';
        $this->closeQuote = '"';
    }

    public function compile(ForeignKey $foreignKey, bool $notValid = false): string
    {
        $sql = parent::compile($foreignKey);

        if ($notValid) {
            $sql .= ' NOT VALID';
        }

        return $sql;
    }
}
