<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Exception;

/**
 * Thrown when database configuration file is missing
 */
class DatabaseConfigNotFoundException extends DatabaseConfigurationException
{
    public function __construct()
    {
        parent::__construct(
            "Database configuration not found. Ensure 'config/pdo-query-builder.php' exists in your project root."
        );
    }
}
