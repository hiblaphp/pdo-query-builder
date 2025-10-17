<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema;

class Blueprint
{
    private string $table;
    private array $columns = [];
    private array $indexes = [];
    private array $foreignKeys = [];
    private string $engine = 'InnoDB';
    private string $charset = 'utf8mb4';
    private string $collation = 'utf8mb4_unicode_ci';
    private array $commands = [];
    private array $dropColumns = [];
    private array $renameColumns = [];
    private array $modifyColumns = [];
    private array $dropIndexes = [];
    private array $dropForeignKeys = [];

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getIndexes(): array
    {
        return $this->indexes;
    }

    public function getForeignKeys(): array
    {
        return $this->foreignKeys;
    }

    public function getEngine(): string
    {
        return $this->engine;
    }

    public function getCharset(): string
    {
        return $this->charset;
    }

    public function getCollation(): string
    {
        return $this->collation;
    }

    public function getCommands(): array
    {
        return $this->commands;
    }

    public function getDropColumns(): array
    {
        return $this->dropColumns;
    }

    public function getRenameColumns(): array
    {
        return $this->renameColumns;
    }

    public function getModifyColumns(): array
    {
        return $this->modifyColumns;
    }

    public function getDropIndexes(): array
    {
        return $this->dropIndexes;
    }

    public function getDropForeignKeys(): array
    {
        return $this->dropForeignKeys;
    }

    public function id(string $name = 'id'): Column
    {
        return $this->bigIncrements($name);
    }

    public function bigIncrements(string $name): Column
    {
        $column = new Column($name, 'BIGINT');
        $column->unsigned()->autoIncrement()->primary();
        $column->setBlueprint($this);
        $this->columns[] = $column;
        return $column;
    }

    public function increments(string $name): Column
    {
        $column = new Column($name, 'INT');
        $column->unsigned()->autoIncrement()->primary();
        $column->setBlueprint($this);
        $this->columns[] = $column;
        return $column;
    }

    public function string(string $name, int $length = 255): Column
    {
        $column = new Column($name, 'VARCHAR', $length);
        $column->setBlueprint($this);
        $this->columns[] = $column;
        return $column;
    }

    public function text(string $name): Column
    {
        $column = new Column($name, 'TEXT');
        $column->setBlueprint($this);
        $this->columns[] = $column;
        return $column;
    }

    public function mediumText(string $name): Column
    {
        $column = new Column($name, 'MEDIUMTEXT');
        $column->setBlueprint($this);
        $this->columns[] = $column;
        return $column;
    }

    public function longText(string $name): Column
    {
        $column = new Column($name, 'LONGTEXT');
        $column->setBlueprint($this);
        $this->columns[] = $column;
        return $column;
    }

    public function integer(string $name): Column
    {
        $column = new Column($name, 'INT');
        $column->setBlueprint($this);
        $this->columns[] = $column;
        return $column;
    }

    public function bigInteger(string $name): Column
    {
        $column = new Column($name, 'BIGINT');
        $column->setBlueprint($this);
        $this->columns[] = $column;
        return $column;
    }

    public function tinyInteger(string $name): Column
    {
        $column = new Column($name, 'TINYINT');
        $column->setBlueprint($this);
        $this->columns[] = $column;
        return $column;
    }

    public function smallInteger(string $name): Column
    {
        $column = new Column($name, 'SMALLINT');
        $column->setBlueprint($this);
        $this->columns[] = $column;
        return $column;
    }

    public function decimal(string $name, int $precision = 8, int $scale = 2): Column
    {
        $column = new Column($name, 'DECIMAL', null, $precision, $scale);
        $column->setBlueprint($this);
        $this->columns[] = $column;
        return $column;
    }

    public function float(string $name, int $precision = 8, int $scale = 2): Column
    {
        $column = new Column($name, 'FLOAT', null, $precision, $scale);
        $column->setBlueprint($this);
        $this->columns[] = $column;
        return $column;
    }

    public function double(string $name, int $precision = 8, int $scale = 2): Column
    {
        $column = new Column($name, 'DOUBLE', null, $precision, $scale);
        $column->setBlueprint($this);
        $this->columns[] = $column;
        return $column;
    }

    public function boolean(string $name): Column
    {
        $column = new Column($name, 'TINYINT', 1);
        $column->default(0);
        $column->setBlueprint($this);
        $this->columns[] = $column;
        return $column;
    }

    public function date(string $name): Column
    {
        $column = new Column($name, 'DATE');
        $column->setBlueprint($this);
        $this->columns[] = $column;
        return $column;
    }

    public function dateTime(string $name): Column
    {
        $column = new Column($name, 'DATETIME');
        $column->setBlueprint($this);
        $this->columns[] = $column;
        return $column;
    }

    public function timestamp(string $name): Column
    {
        $column = new Column($name, 'TIMESTAMP');
        $column->setBlueprint($this);
        $this->columns[] = $column;
        return $column;
    }

    public function timestamps(): void
    {
        $this->timestamp('created_at')->nullable()->useCurrent();
        $this->timestamp('updated_at')->nullable()->useCurrent()->onUpdate('CURRENT_TIMESTAMP');
    }

    public function softDeletes(string $column = 'deleted_at'): Column
    {
        return $this->timestamp($column)->nullable();
    }

    public function json(string $name): Column
    {
        $column = new Column($name, 'JSON');
        $column->setBlueprint($this);
        $this->columns[] = $column;
        return $column;
    }

    public function enum(string $name, array $values): Column
    {
        $column = new Column($name, 'ENUM');
        $column->setEnumValues($values);
        $column->setBlueprint($this);
        $this->columns[] = $column;
        return $column;
    }

    public function foreignId(string $name): Column
    {
        return $this->bigInteger($name)->unsigned();
    }

    public function index(string|array $columns, ?string $name = null): self
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $name ??= $this->table . '_' . implode('_', $columns) . '_index';
        $this->indexes[] = ['type' => 'INDEX', 'name' => $name, 'columns' => $columns];
        return $this;
    }

    public function unique(string|array $columns, ?string $name = null): self
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $name ??= $this->table . '_' . implode('_', $columns) . '_unique';
        $this->indexes[] = ['type' => 'UNIQUE', 'name' => $name, 'columns' => $columns];
        return $this;
    }

    public function primary(string|array $columns): self
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $this->indexes[] = ['type' => 'PRIMARY', 'columns' => $columns];
        return $this;
    }

    public function foreign(string|array $columns, ?string $name = null): ForeignKey
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $name ??= $this->table . '_' . implode('_', $columns) . '_foreign';
        $foreignKey = new ForeignKey($name, $columns, $this->table);
        $this->foreignKeys[] = $foreignKey;
        return $foreignKey;
    }

    // Column modification methods
    public function dropColumn(string|array $columns): self
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $this->dropColumns = array_merge($this->dropColumns, $columns);
        return $this;
    }

    public function renameColumn(string $from, string $to): self
    {
        $this->renameColumns[] = ['from' => $from, 'to' => $to];
        return $this;
    }

    public function modifyColumn(string $name): Column
    {
        $column = new Column($name, 'VARCHAR');
        $column->setBlueprint($this);
        $this->modifyColumns[] = $column;
        return $column;
    }

    public function string_modify(string $name, int $length = 255): Column
    {
        $column = new Column($name, 'VARCHAR', $length);
        $column->setBlueprint($this);
        $this->modifyColumns[] = $column;
        return $column;
    }

    public function integer_modify(string $name): Column
    {
        $column = new Column($name, 'INT');
        $column->setBlueprint($this);
        $this->modifyColumns[] = $column;
        return $column;
    }

    public function dropIndex(string|array $index): self
    {
        $this->dropIndexes[] = is_array($index) ? $index : [$index];
        return $this;
    }

    public function dropUnique(string|array $index): self
    {
        return $this->dropIndex($index);
    }

    public function dropPrimary(?string $index = null): self
    {
        $this->dropIndexes[] = $index ? [$index] : ['PRIMARY'];
        return $this;
    }

    public function dropForeign(string|array $index): self
    {
        $keys = is_array($index) ? $index : [$index];
        $this->dropForeignKeys = array_merge($this->dropForeignKeys, $keys);
        return $this;
    }

    public function rename(string $to): self
    {
        $this->commands[] = ['type' => 'rename', 'to' => $to];
        return $this;
    }

    public function engine(string $engine): self
    {
        $this->engine = $engine;
        return $this;
    }

    public function charset(string $charset): self
    {
        $this->charset = $charset;
        return $this;
    }

    public function collation(string $collation): self
    {
        $this->collation = $collation;
        return $this;
    }
}
