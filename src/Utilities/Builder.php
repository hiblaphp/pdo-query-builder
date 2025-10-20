<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Utilities;

use Hibla\AsyncPDO\AsyncPDO;
use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Promise\Promise;
use Rcalicdan\QueryBuilderPrimitives\QueryBuilderBase;

/**
 * Async Query Builder for an easy way to write asynchronous SQL queries.
 *
 * This query builder is fully immutable. Each method that modifies the query
 * returns a new instance instead of modifying the current one, ensuring a
 * predictable and safe state management.
 */
class Builder extends QueryBuilderBase
{
    /**
     * @var string|null Cached driver to avoid repeated detection
     */
    private static ?string $cachedDriver = null;

    /**
     * @var bool Whether driver detection has been attempted
     */
    private static bool $driverDetected = false;

    /**
     * @var bool Whether to return results as objects
     */
    private bool $returnAsObject = false;

    /**
     * Create a new AsyncQueryBuilder instance.
     *
     * @param  string  $table  The table name to query.
     */
    final public function __construct(string $table = '')
    {
        if ($table !== '') {
            $this->table = $table;
        }

        if (! self::$driverDetected) {
            $this->autoDetectDriver();
            self::$driverDetected = true;
        } else {
            $this->driver = self::$cachedDriver;
        }
    }

    /**
     * Auto-detect the database driver from the current AsyncPDO connection.
     * This runs only once and caches the result.
     */
    private function autoDetectDriver(): void
    {
        try {
            $driver = $this->getDriverFromConfig();
            if ($driver !== null) {
                $detectedDriver = strtolower($driver);
                $this->driver = $detectedDriver;
                self::$cachedDriver = $detectedDriver;
            } else {
                $this->driver = 'mysql';
                self::$cachedDriver = 'mysql';
            }
        } catch (\Throwable $e) {
            $this->driver = 'mysql';
            self::$cachedDriver = 'mysql';
        }
    }

    /**
     * Get the driver from the loaded configuration.
     */
    private function getDriverFromConfig(): ?string
    {
        $configLoader = ConfigLoader::getInstance();
        $dbConfig = $configLoader->get('pdo-query-builder');

        if (! is_array($dbConfig)) {
            return null;
        }

        $defaultConnection = $dbConfig['default'] ?? null;
        if (! is_string($defaultConnection)) {
            return null;
        }

        $connections = $dbConfig['connections'] ?? [];
        if (! is_array($connections)) {
            return null;
        }

        $connectionConfig = $connections[$defaultConnection] ?? null;
        if (! is_array($connectionConfig)) {
            return null;
        }

        $driver = $connectionConfig['driver'] ?? null;

        return is_string($driver) ? $driver : null;
    }

    /**
     * Reset the driver cache. Useful for testing or when switching connections.
     */
    public static function resetDriverCache(): void
    {
        self::$cachedDriver = null;
        self::$driverDetected = false;
    }

    /**
     * Set the query to return results as objects instead of arrays.
     *
     * @return static
     */
    public function toObject(): static
    {
        $clone = clone $this;
        $clone->returnAsObject = true;

        return $clone;
    }

    /**
     * Set the query to return results as arrays instead of objects.
     * Useful to override a previous toObject() call.
     *
     * @return static
     */
    public function toArray(): static
    {
        $clone = clone $this;
        $clone->returnAsObject = false;

        return $clone;
    }

    /**
     * Convert array results to objects.
     *
     * @param  array<int, array<string, mixed>>  $results
     * @return array<int, object>
     */
    private function convertToObjects(array $results): array
    {
        return array_map(static fn(array $row): object => (object) $row, $results);
    }

    /**
     * Execute the query and return all results.
     *
     * @return PromiseInterface<array<int, array<string, mixed>>|array<int, object>>
     */
    public function get(): PromiseInterface
    {
        $sql = $this->buildSelectQuery();

        return async(function () use ($sql): array {
            $results = await(AsyncPDO::query($sql, $this->getCompiledBindings()));

            return $this->returnAsObject ? $this->convertToObjects($results) : $results;
        });
    }

    /**
     * Get the first result from the query.
     *
     * @return PromiseInterface<array<string, mixed>|\stdClass|false>
     */
    public function first(): PromiseInterface
    {
        $instanceWithLimit = $this->limit(1);
        $sql = $instanceWithLimit->buildSelectQuery();

        return async(function () use ($sql, $instanceWithLimit): array|\stdClass|false {
            $result = await(AsyncPDO::fetchOne($sql, $instanceWithLimit->getCompiledBindings()));

            if ($result === false) {
                return false;
            }

            return $this->returnAsObject ? (object) $result : $result;
        });
    }

    /**
     * Find a record by ID.
     *
     * @param  mixed  $id  The ID value to search for.
     * @param  string  $column  The column name to search in.
     * @return PromiseInterface<array<string, mixed>|\stdClass|false>
     */
    public function find(mixed $id, string $column = 'id'): PromiseInterface
    {
        return $this->where($column, $id)->first();
    }

    /**
     * Find a record by ID or throw an exception if not found.
     *
     * @param  mixed  $id  The ID value to search for.
     * @param  string  $column  The column name to search in.
     * @return PromiseInterface<array<string, mixed>|\stdClass>
     *
     * @throws \RuntimeException When no record is found.
     */
    public function findOrFail(mixed $id, string $column = 'id'): PromiseInterface
    {
        return async(function () use ($id, $column): array|\stdClass {
            $result = await($this->find($id, $column));
            if ($result === null || $result === false) {
                $idString = is_scalar($id) ? (string) $id : 'complex_type';

                throw new \RuntimeException("Record not found with {$column} = {$idString}");
            }

            return $result;
        });
    }

    /**
     * Get a single value from the first result.
     *
     * @param  string  $column  The column name to retrieve.
     * @return PromiseInterface<mixed>
     */
    public function value(string $column): PromiseInterface
    {
        return async(function () use ($column): mixed {
            $result = await($this->select($column)->first());

            if ($result === false) {
                return null;
            }

            if (is_array($result)) {
                return $result[$column] ?? null;
            }

            return $result->$column ?? null;
        });
    }

    /**
     * Map the query results using a callback function.
     *
     * @param  callable(array<string, mixed>|object): mixed  $callback  The mapping function.
     * @return PromiseInterface<array<int, mixed>>
     */
    public function map(callable $callback): PromiseInterface
    {
        return async(function () use ($callback): array {
            $results = await($this->get());

            return array_map($callback, $results);
        });
    }

    /**
     * Get the first result and map it using a callback function.
     *
     * @param  callable(array<string, mixed>|object): mixed  $callback  The mapping function.
     * @return PromiseInterface<mixed|false>
     */
    public function firstMap(callable $callback): PromiseInterface
    {
        return async(function () use ($callback): mixed {
            $result = await($this->first());

            return $result !== false ? $callback($result) : false;
        });
    }

    /**
     * Count the number of records.
     *
     * @param  string  $column  The column to count.
     * @return PromiseInterface<int> A promise that resolves to the record count.
     */
    public function count(string $column = '*'): PromiseInterface
    {
        $sql = $this->buildCountQuery($column);
        /** @var PromiseInterface<int> */
        $promise = AsyncPDO::fetchValue($sql, $this->getCompiledBindings());

        return $promise;
    }

    /**
     * Check if any records exist.
     *
     * @return PromiseInterface<bool> A promise that resolves to true if records exist, false otherwise.
     */
    public function exists(): PromiseInterface
    {
        return async(function (): bool {
            $count = await($this->count());

            return $count > 0;
        });
    }

    /**
     * Insert a single record.
     *
     * @param  array<string, mixed>  $data  The data to insert as column => value pairs.
     * @return PromiseInterface<int> A promise that resolves to the number of affected rows.
     */
    public function insert(array $data): PromiseInterface
    {
        if ($data === []) {
            return Promise::resolved(0);
        }
        $sql = $this->buildInsertQuery($data);

        return AsyncPDO::execute($sql, array_values($data));
    }

    /**
     * Insert or update a record based on unique columns.
     *
     * @param  array<string, mixed>|array<array<string, mixed>>  $data  The data to insert or update as column => value pairs.
     * @param  array<string>  $uniqueColumns  The columns to check for uniqueness.
     * @param  array<string>  $updateColumns  The columns to update on conflict.
     * @return PromiseInterface<int> A promise that resolves to the number of affected rows.
     */
    public function upsert(array $data, array $uniqueColumns, array $updateColumns): PromiseInterface
    {
        if ($data === []) {
            return Promise::resolved(0);
        }

        $sql = $this->buildUpsertQuery($data, $uniqueColumns, $updateColumns);

        $params = $this->flattenBatchParameters($data);

        return AsyncPDO::execute($sql, $params);
    }

    /**
     * Flatten batch parameters from nested arrays to a single flat array.
     *
     * @param  array<string, mixed>|array<int, array<string, mixed>>  $data
     * @return array<int, mixed> Flattened parameters
     */
    protected function flattenBatchParameters(array $data): array
    {
        $firstItem = reset($data);
        $isBatch = is_array($firstItem) && !isset($firstItem[0]);

        if (!$isBatch) {
            return array_values($data);
        }

        $flattened = [];
        /** @var array<string, mixed> $row */
        foreach ($data as $row) {
            $flattened = array_merge($flattened, array_values($row));
        }

        return $flattened;
    }

    /**
     * Insert multiple records in a batch operation.
     *
     * @param  array<array<string, mixed>>  $data  An array of records to insert.
     * @return PromiseInterface<int> A promise that resolves to the number of affected rows.
     */
    public function insertBatch(array $data): PromiseInterface
    {
        if ($data === []) {
            return Promise::resolved(0);
        }

        $sql = $this->buildInsertBatchQuery($data);
        $bindings = [];
        foreach ($data as $row) {
            if (is_array($row)) {
                $bindings = array_merge($bindings, array_values($row));
            }
        }

        return AsyncPDO::execute($sql, $bindings);
    }

    /**
     * Create a new record (alias for insert).
     *
     * @param  array<string, mixed>  $data  The data to insert as column => value pairs.
     * @return PromiseInterface<int> A promise that resolves to the number of affected rows.
     */
    public function create(array $data): PromiseInterface
    {
        return $this->insert($data);
    }

    /**
     * Update records matching the query conditions.
     *
     * @param  array<string, mixed>  $data  The data to update as column => value pairs.
     * @return PromiseInterface<int> A promise that resolves to the number of affected rows.
     */
    public function update(array $data): PromiseInterface
    {
        if ($data === []) {
            return Promise::resolved(0);
        }
        $sql = $this->buildUpdateQuery($data);
        $whereBindings = $this->getCompiledBindings();
        $bindings = array_merge(array_values($data), $whereBindings);

        return AsyncPDO::execute($sql, $bindings);
    }

    /**
     * Delete records matching the query conditions.
     *
     * @return PromiseInterface<int> A promise that resolves to the number of affected rows.
     */
    public function delete(): PromiseInterface
    {
        $sql = $this->buildDeleteQuery();

        return AsyncPDO::execute($sql, $this->getCompiledBindings());
    }
}
