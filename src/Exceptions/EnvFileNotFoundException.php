<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Exception;

/**
 * Thrown when .env file is missing or cannot be loaded
 */
class EnvFileNotFoundException extends ConfigurationException
{
    public function __construct(string $path)
    {
        parent::__construct(
            "Environment file not found at: {$path}"
        );
    }
}
