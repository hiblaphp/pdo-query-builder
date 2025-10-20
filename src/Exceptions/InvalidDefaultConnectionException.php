<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Exception;

/**
 * Thrown when default connection is not properly configured
 */
class InvalidDefaultConnectionException extends DatabaseConfigurationException
{
    public function __construct(string $connectionName)
    {
        parent::__construct(
            "Default database connection '{$connectionName}' not defined in your database config."
        );
    }
}
