<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema;

use Doctrine\Inflector\Inflector as DoctrineInflector;
use Doctrine\Inflector\InflectorFactory;

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
    private ?ForeignKey $foreignKey = null;
    private ?Blueprint $blueprint = null;
    private static ?DoctrineInflector $inflector = null;
    private array $columnIndexes = [];

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

    private static function getInflector(): DoctrineInflector
    {
        if (self::$inflector === null) {
            self::$inflector = InflectorFactory::create()->build();
        }

        return self::$inflector;
    }

    /**
     * Clone method to support column duplication during table recreation
     */
    public function __clone()
    {
        if ($this->foreignKey !== null) {
            $this->foreignKey = clone $this->foreignKey;
        }

        $this->blueprint = null;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getLength(): ?int
    {
        return $this->length;
    }

    public function setLength(?int $length): self
    {
        $this->length = $length;

        return $this;
    }

    public function getPrecision(): ?int
    {
        return $this->precision;
    }

    public function setPrecision(?int $precision): self
    {
        $this->precision = $precision;

        return $this;
    }

    public function getScale(): ?int
    {
        return $this->scale;
    }

    public function setScale(?int $scale): self
    {
        $this->scale = $scale;

        return $this;
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

    public function getForeignKey(): ?ForeignKey
    {
        return $this->foreignKey;
    }

    public function getBlueprint(): ?Blueprint
    {
        return $this->blueprint;
    }

    /**
     * NEW: Get column indexes
     */
    public function getColumnIndexes(): array
    {
        return $this->columnIndexes;
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
        if ($value) {
            $this->columnIndexes[] = [
                'type' => 'UNIQUE',
                'name' => null,
                'algorithm' => null,
            ];
        }

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

    public function setBlueprint(Blueprint $blueprint): self
    {
        $this->blueprint = $blueprint;

        return $this;
    }

    /**
     * Add a regular index to this column
     *
     * @param  string|null  $name  Optional index name
     * @param  string|null  $algorithm  Optional algorithm (BTREE, HASH, NGRAM, etc.)
     */
    public function index(?string $name = null, ?string $algorithm = null): self
    {
        $this->columnIndexes[] = [
            'type' => 'INDEX',
            'name' => $name,
            'algorithm' => $algorithm,
        ];

        return $this;
    }

    /**
     * Add a fulltext index to this column
     *
     * @param  string|null  $name  Optional index name
     * @param  string|null  $algorithm  Optional parser (NGRAM, etc.)
     */
    public function fullText(?string $name = null, ?string $algorithm = null): self
    {
        $this->columnIndexes[] = [
            'type' => 'FULLTEXT',
            'name' => $name,
            'algorithm' => $algorithm,
        ];

        return $this;
    }

    /**
     * Add a spatial index to this column
     * Note: MySQL requires spatial indexed columns to be NOT NULL
     *
     * @param  string|null  $name  Optional index name
     * @param  string|null  $operatorClass  Optional operator class (for PostgreSQL: gist, gin, spgist, brin)
     */
    public function spatialIndex(?string $name = null, ?string $operatorClass = null): self
    {
        $this->columnIndexes[] = [
            'type' => 'SPATIAL',
            'name' => $name,
            'operatorClass' => $operatorClass,
        ];

        return $this;
    }

    public function constrained(?string $table = null, string $column = 'id'): self
    {
        if (! $this->blueprint) {
            throw new \RuntimeException('Blueprint reference not set on column');
        }

        if ($table === null) {
            $table = $this->guessTableName();
        }

        $foreignKey = $this->blueprint->foreign($this->name);
        $foreignKey->references($column)->on($table);

        $this->foreignKey = $foreignKey;

        return $this;
    }

    private function guessTableName(): string
    {
        $name = $this->name;

        if (str_ends_with($name, '_id')) {
            $name = substr($name, 0, -3);
        }

        return self::getInflector()->pluralize($name);
    }

    public function cascadeOnDelete(): self
    {
        if ($this->foreignKey) {
            $this->foreignKey->cascadeOnDelete();
        }

        return $this;
    }

    public function cascadeOnUpdate(): self
    {
        if ($this->foreignKey) {
            $this->foreignKey->cascadeOnUpdate();
        }

        return $this;
    }

    public function nullOnDelete(): self
    {
        if ($this->foreignKey) {
            $this->foreignKey->nullOnDelete();
        }

        return $this;
    }

    public function restrictOnDelete(): self
    {
        if ($this->foreignKey) {
            $this->foreignKey->onDelete('RESTRICT');
        }

        return $this;
    }

    public function restrictOnUpdate(): self
    {
        if ($this->foreignKey) {
            $this->foreignKey->onUpdate('RESTRICT');
        }

        return $this;
    }

    public function noActionOnDelete(): self
    {
        if ($this->foreignKey) {
            $this->foreignKey->onDelete('NO ACTION');
        }

        return $this;
    }

    public function noActionOnUpdate(): self
    {
        if ($this->foreignKey) {
            $this->foreignKey->onUpdate('NO ACTION');
        }

        return $this;
    }

    /**
     * Create a new column instance from this column with a different name
     * Useful for column renaming operations
     */
    public function copyWithName(string $newName): self
    {
        $column = clone $this;
        $column->setName($newName);

        return $column;
    }

    /**
     * Create a new column instance with modified attributes
     * Useful for column modification operations
     */
    public function copyWithModifications(array $modifications): self
    {
        $column = clone $this;

        foreach ($modifications as $attribute => $value) {
            match ($attribute) {
                'type' => $column->setType($value),
                'length' => $column->setLength($value),
                'precision' => $column->setPrecision($value),
                'scale' => $column->setScale($value),
                'nullable' => $column->nullable($value),
                'default' => $column->default($value),
                'unsigned' => $column->unsigned($value),
                'unique' => $column->unique($value),
                'comment' => $column->comment($value),
                default => null,
            };
        }

        return $column;
    }

    /**
     * Convert column to array representation
     * Useful for debugging and serialization
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'length' => $this->length,
            'precision' => $this->precision,
            'scale' => $this->scale,
            'nullable' => $this->nullable,
            'default' => $this->default,
            'hasDefault' => $this->hasDefault,
            'unsigned' => $this->unsigned,
            'autoIncrement' => $this->autoIncrement,
            'primary' => $this->primary,
            'unique' => $this->unique,
            'comment' => $this->comment,
            'after' => $this->after,
            'useCurrent' => $this->useCurrent,
            'onUpdate' => $this->onUpdate,
            'enumValues' => $this->enumValues,
            'hasForeignKey' => $this->foreignKey !== null,
            'columnIndexes' => $this->columnIndexes,
        ];
    }

    /**
     * Create a column from array representation
     */
    public static function fromArray(array $data): self
    {
        $column = new self(
            $data['name'],
            $data['type'],
            $data['length'] ?? null,
            $data['precision'] ?? null,
            $data['scale'] ?? null
        );

        if ($data['nullable'] ?? false) {
            $column->nullable();
        }

        if ($data['hasDefault'] ?? false) {
            $column->default($data['default'] ?? null);
        }

        if ($data['unsigned'] ?? false) {
            $column->unsigned();
        }

        if ($data['autoIncrement'] ?? false) {
            $column->autoIncrement();
        }

        if ($data['primary'] ?? false) {
            $column->primary();
        }

        if ($data['unique'] ?? false) {
            $column->unique();
        }

        if (isset($data['comment'])) {
            $column->comment($data['comment']);
        }

        if (isset($data['after'])) {
            $column->after($data['after']);
        }

        if ($data['useCurrent'] ?? false) {
            $column->useCurrent();
        }

        if (isset($data['onUpdate'])) {
            $column->onUpdate($data['onUpdate']);
        }

        if (! empty($data['enumValues'])) {
            $column->setEnumValues($data['enumValues']);
        }

        if (! empty($data['columnIndexes'])) {
            $column->columnIndexes = $data['columnIndexes'];
        }

        return $column;
    }
}
