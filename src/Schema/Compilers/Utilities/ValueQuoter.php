<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Schema\Compilers\Utilities;

use PDO;

/**
 * Handles string quoting and escaping
 */
class ValueQuoter
{
    private ?PDO $connection = null;

    public function __construct(?PDO $connection = null)
    {
        $this->connection = $connection;
    }

    public function quote(string $value): string
    {
        if ($this->connection !== null) {
            return $this->connection->quote($value);
        }

        return $this->escapeAndQuote($value);
    }

    protected function escapeAndQuote(string $value): string
    {
        $escaped = str_replace("'", "''", $value);

        return "'{$escaped}'";
    }
}
