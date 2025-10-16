<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema;

class Column
{
    private string $name;
    private string $type;
    private ?int $length;
    private ?int $precision;
    private ?int $scale;
    private bool $nullable = false;
    private mixed $default = null;
    private bool $hasDefault = false;
    private bool $unsigned = false;
    private bool $autoIncrement = false;
    private bool $primary = false;
    private bool $unique = false;
    private ?string $comment = null;
    private ?string $after = null;
    private bool $useCurrent = false;
    private ?string $onUpdate = null;
    private array $enumValues = [];

    public function __construct(
        string $name,
        string $type,
        ?int $length = null,
        ?int $precision = null,
        ?int $scale = null
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->length = $length;
        $this->precision = $precision;
        $this->scale = $scale;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getLength(): ?int
    {
        return $this->length;
    }

    public function getPrecision(): ?int
    {
        return $this->precision;
    }

    public function getScale(): ?int
    {
        return $this->scale;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function hasDefault(): bool
    {
        return $this->hasDefault;
    }

    public function isUnsigned(): bool
    {
        return $this->unsigned;
    }

    public function isAutoIncrement(): bool
    {
        return $this->autoIncrement;
    }

    public function isPrimary(): bool
    {
        return $this->primary;
    }

    public function isUnique(): bool
    {
        return $this->unique;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getAfter(): ?string
    {
        return $this->after;
    }

    public function shouldUseCurrent(): bool
    {
        return $this->useCurrent;
    }

    public function getOnUpdate(): ?string
    {
        return $this->onUpdate;
    }

    public function getEnumValues(): array
    {
        return $this->enumValues;
    }

    public function nullable(bool $value = true): self
    {
        $this->nullable = $value;
        return $this;
    }

    public function default(mixed $value): self
    {
        $this->default = $value;
        $this->hasDefault = true;
        return $this;
    }

    public function unsigned(bool $value = true): self
    {
        $this->unsigned = $value;
        return $this;
    }

    public function autoIncrement(bool $value = true): self
    {
        $this->autoIncrement = $value;
        return $this;
    }

    public function primary(bool $value = true): self
    {
        $this->primary = $value;
        return $this;
    }

    public function unique(bool $value = true): self
    {
        $this->unique = $value;
        return $this;
    }

    public function comment(string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    public function after(string $column): self
    {
        $this->after = $column;
        return $this;
    }

    public function useCurrent(bool $value = true): self
    {
        $this->useCurrent = $value;
        return $this;
    }

    public function onUpdate(string $value): self
    {
        $this->onUpdate = $value;
        return $this;
    }

    public function setEnumValues(array $values): self
    {
        $this->enumValues = $values;
        return $this;
    }
}