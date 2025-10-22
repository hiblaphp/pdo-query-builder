<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Utilities;

use Hibla\AsyncPDO\AsyncPDO;
use Hibla\PdoQueryBuilder\Pagination\CursorPaginator;
use Hibla\PdoQueryBuilder\Pagination\Paginator;
use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Promise\Promise;
use Rcalicdan\ConfigLoader\Config;
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
     * @var bool Whether templates have been configured
     */
    private static bool $templatesConfigured = false;

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

        if (! self::$templatesConfigured) {
            $this->configureTemplates();
            self::$templatesConfigured = true;
        }
    }

    /**
     * Reset the driver cache. Useful for testing or when switching connections.
     */
    public static function resetDriverCache(): void
    {
        self::$cachedDriver = null;
        self::$driverDetected = false;
        self::$templatesConfigured = false;
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
        $isBatch = is_array($firstItem) && ! isset($firstItem[0]);

        if (! $isBatch) {
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

    /**
     * Paginate the results with automatic request handling.
     *
     * @param  int  $perPage  Records per page
     * @param  string|null  $path  The path for pagination links
     * @return PromiseInterface<Paginator>
     */
    public function paginate(int $perPage = 15, ?string $path = null): PromiseInterface
    {
        $pageParam = $_GET['page'] ?? 1;
        $page = max(1, is_numeric($pageParam) ? (int) $pageParam : 1);

        if ($path === null) {
            $path = $this->getCurrentPath();
        }

        return async(function () use ($perPage, $page, $path): Paginator {
            $total = await($this->count());

            $results = await($this->forPage($page, $perPage)->get());

            return new Paginator(
                items: $results,
                total: $total,
                perPage: $perPage,
                currentPage: $page,
                path: $path,
            );
        });
    }

    /**
     * Paginate with cursor-based pagination (automatic request handling).
     *
     * @param  int  $perPage  Records per page
     * @param  string  $cursorColumn  The column to use for cursor
     * @param  string|null  $path  The path for pagination links
     * @return PromiseInterface<CursorPaginator>
     */
    public function cursorPaginate(
        int $perPage = 15,
        string $cursorColumn = 'id',
        ?string $path = null,
    ): PromiseInterface {
        $cursor = $_GET['cursor'] ?? null;

        if ($path === null) {
            $path = $this->getCurrentPath();
        }

        return async(function () use ($perPage, $cursor, $cursorColumn, $path): CursorPaginator {
            /** @var string|null $cursor */
            $query = $this->applyDecodedCursor($cursor, $cursorColumn);
            $results = await($query->limit($perPage + 1)->get());

            $hasMore = count($results) > $perPage;
            if ($hasMore) {
                array_pop($results);
            }

            $nextCursor = $this->resolveNextCursor($results, $cursorColumn, $hasMore);

            return new CursorPaginator(
                items: $results,
                perPage: $perPage,
                nextCursor: $nextCursor,
                cursorColumn: $cursorColumn,
                path: $path,
            );
        });
    }

    /**
     * @param string|null $cursor
     * @param string $cursorColumn
     * @return self
     */
    private function applyDecodedCursor(
        ?string $cursor,
        string $cursorColumn,
    ): self {
        if (!is_string($cursor) || $cursor === '') {
            return $this;
        }

        $cursorValue = base64_decode($cursor, true);
        if ($cursorValue === false) {
            return $this;
        }

        return $this->where($cursorColumn, '>', $cursorValue);
    }

    /**
     * @param array<mixed> $results
     * @param string $cursorColumn
     * @param bool $hasMore
     * @return string|null
     */
    private function resolveNextCursor(
        array $results,
        string $cursorColumn,
        bool $hasMore,
    ): ?string {
        if (!$hasMore || count($results) === 0) {
            return null;
        }

        /** @var array<mixed>|object $lastItem */
        $lastItem = end($results);
        $cursorValue = $this->extractColumnValue($lastItem, $cursorColumn);

        return $this->encodeCursorValue($cursorValue);
    }

    /**
     * @param array<mixed>|object $item
     * @param string $column
     * @return mixed
     */
    private function extractColumnValue(
        array|object $item,
        string $column,
    ): mixed {
        if (is_array($item)) {
            return $item[$column] ?? null;
        }

        $vars = get_object_vars($item);
        return $vars[$column] ?? null;
    }

    /**
     * @param mixed $value
     * @return string|null
     */
    private function encodeCursorValue(
        mixed $value,
    ): ?string {
        if ($value === null) {
            return null;
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            return null;
        }

        return base64_encode((string) $value);
    }

    /**
     * Get current request path for pagination links
     */
    private function getCurrentPath(): string
    {
        $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = is_string($_SERVER['HTTP_HOST'] ?? null) ? $_SERVER['HTTP_HOST'] : 'localhost';
        $requestUri = is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/';

        $parsedPath = parse_url($requestUri, PHP_URL_PATH);
        $path = is_string($parsedPath) ? $parsedPath : '/';

        return $scheme . '://' . $host . $path;
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
        $dbConfig = Config::get('pdo-query-builder');

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
     * Configure custom pagination templates path from config
     */
    private function configureTemplates(): void
    {
        try {
            $dbConfig = Config::get('pdo-query-builder');

            if (! is_array($dbConfig)) {
                return;
            }

            $paginationConfig = $dbConfig['pagination'] ?? [];
            if (! is_array($paginationConfig)) {
                return;
            }

            $templatesPath = $paginationConfig['templates_path'] ?? null;

            if (is_string($templatesPath) && trim($templatesPath) !== '' && is_dir($templatesPath)) {
                Paginator::setTemplatesPath($templatesPath);
                CursorPaginator::setTemplatesPath($templatesPath);
            }
        } catch (\Throwable $e) {
            error_log('Failed to configure pagination templates: ' . $e->getMessage());
        }
    }
}
