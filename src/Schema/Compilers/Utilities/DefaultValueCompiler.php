<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema\Compilers\Utilities;

use Hibla\PdoQueryBuilder\Schema\Column;

/**
 * Handles default value compilation for different database systems
 */
class DefaultValueCompiler
{
    protected array $expressionList = [];

    public function compile(mixed $default): string
    {
        if ($default === null) {
            return $this->formatNull();
        }

        if (is_bool($default)) {
            return $this->formatBoolean($default);
        }

        if (is_numeric($default)) {
            return $this->formatNumeric($default);
        }

        if ($this->isExpression($default)) {
            return $this->formatExpression($default);
        }

        return $this->formatString($default);
    }

    protected function isExpression(string $value): bool
    {
        return in_array(strtoupper($value), $this->expressionList);
    }

    protected function formatNull(): string
    {
        return 'NULL';
    }

    protected function formatBoolean(bool $value): string
    {
        return $value ? '1' : '0';
    }

    protected function formatNumeric(mixed $value): string
    {
        return (string)$value;
    }

    protected function formatExpression(string $value): string
    {
        return $value;
    }

    protected function formatString(string $value): string
    {
        return "'{$value}'";
    }
}