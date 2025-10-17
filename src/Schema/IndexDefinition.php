<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema;

class IndexDefinition
{
    private string $type;
    private array $columns;
    private ?string $name;
    private ?string $algorithm = null;
    private ?string $operatorClass = null;
    private ?string $with = null;
    private array $using = [];

    public function __construct(string $type, array $columns, ?string $name = null)
    {
        $this->type = $type;
        $this->columns = is_array($columns) ? $columns : [$columns];
        $this->name = $name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getAlgorithm(): ?string
    {
        return $this->algorithm;
    }

    public function getOperatorClass(): ?string
    {
        return $this->operatorClass;
    }

    public function getWith(): ?string
    {
        return $this->with;
    }

    public function getUsing(): array
    {
        return $this->using;
    }

    /**
     * Specify the algorithm for the index
     */
    public function algorithm(string $algorithm): self
    {
        $this->algorithm = strtoupper($algorithm);
        return $this;
    }

    /**
     * Specify the operator class for spatial indexes
     */
    public function operatorClass(string $operatorClass): self
    {
        $this->operatorClass = $operatorClass;
        return $this;
    }

    /**
     * Specify WITH clause parameters for PostgreSQL
     */
    public function with(string $with): self
    {
        $this->with = $with;
        return $this;
    }

    /**
     * Add USING clause parameters
     */
    public function using(array $params): self
    {
        $this->using = array_merge($this->using, $params);
        return $this;
    }

    /**
     * Convert to array representation
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'columns' => $this->columns,
            'algorithm' => $this->algorithm,
            'operatorClass' => $this->operatorClass,
            'with' => $this->with,
            'using' => $this->using,
        ];
    }
}