<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Exception;

/**
 * Thrown when .env file cannot be parsed
 */
class EnvFileLoadException extends ConfigurationException
{
    public function __construct(string $message)
    {
        parent::__construct(
            "Error loading .env file: {$message}"
        );
    }
}
