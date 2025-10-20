<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Exception;

/**
 * Thrown when pool size configuration is invalid
 */
class InvalidPoolSizeException extends DatabaseConfigurationException
{
    public function __construct()
    {
        parent::__construct(
            'Database pool size must be a positive integer.'
        );
    }
}
