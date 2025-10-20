<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Exception;

/**
 * Thrown when the project root cannot be found
 */
class ProjectRootNotFoundException extends ConfigurationException
{
    public function __construct()
    {
        parent::__construct(
            'Project root not found. Unable to locate vendor directory within 10 parent directories.'
        );
    }
}
