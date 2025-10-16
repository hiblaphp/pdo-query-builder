<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema;

class ForeignKey
{
    private string $name;
    private array $columns;
    private ?string $referenceTable = null;
    private array $referenceColumns = [];
    private string $onDelete = 'RESTRICT';
    private string $onUpdate = 'RESTRICT';

    public function __construct(string $name, array $columns)
    {
        $this->name = $name;
        $this->columns = $columns;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getReferenceTable(): ?string
    {
        return $this->referenceTable;
    }

    public function getReferenceColumns(): array
    {
        return $this->referenceColumns;
    }

    public function getOnDelete(): string
    {
        return $this->onDelete;
    }

    public function getOnUpdate(): string
    {
        return $this->onUpdate;
    }

    public function references(string|array $columns): self
    {
        $this->referenceColumns = is_array($columns) ? $columns : [$columns];
        return $this;
    }

    public function on(string $table): self
    {
        $this->referenceTable = $table;
        return $this;
    }

    public function onDelete(string $action): self
    {
        $this->onDelete = strtoupper($action);
        return $this;
    }

    public function onUpdate(string $action): self
    {
        $this->onUpdate = strtoupper($action);
        return $this;
    }

    public function cascadeOnDelete(): self
    {
        return $this->onDelete('CASCADE');
    }

    public function cascadeOnUpdate(): self
    {
        return $this->onUpdate('CASCADE');
    }

    public function nullOnDelete(): self
    {
        return $this->onDelete('SET NULL');
    }
}