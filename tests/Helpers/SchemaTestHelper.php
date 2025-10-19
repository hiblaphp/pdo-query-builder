<?php

namespace Tests\Helpers;

use Hibla\PdoQueryBuilder\Schema\SchemaBuilder;
use Hibla\PdoQueryBuilder\DB;

class SchemaTestHelper
{
    private static array $testTables = [
        'users', 'posts', 'comments', 'categories', 'tags', 'profiles',
        'articles', 'locations', 'stats', 'documents', 'financials',
        'events', 'orders', 'settings', 'temp_table', 'old_name', 'new_name',
        'counters', 'logs', 'products', 'empty_table', 'wide_table',
        'user_roles', 'user_profiles'
    ];

    /**
     * Initialize database with specific driver configuration from environment variables
     * 
     * @param string $driver The database driver (mysql, pgsql, sqlite, sqlsrv)
     * @param int $poolSize Connection pool size (default: 10)
     * @return void
     */
    public static function initializeDatabaseForDriver(string $driver, int $poolSize = 10): void
    {
        DB::reset();
        $config = self::getDriverConfig($driver);
        DB::init($config, $poolSize);
    
        DB::rawExecute("SELECT 1")->await();
    }

    /**
     * Get configuration array for specific driver from environment variables
     * 
     * @param string $driver The database driver
     * @return array<string, mixed>
     */
    private static function getDriverConfig(string $driver): array
    {
        return match ($driver) {
            'mysql' => [
                'driver' => 'mysql',
                'host' => getenv('MYSQL_HOST') ?: '127.0.0.1',
                'port' => (int) (getenv('MYSQL_PORT') ?: 3306),
                'database' => getenv('MYSQL_DATABASE') ?: 'test_db',
                'username' => getenv('MYSQL_USERNAME') ?: 'test_user',
                'password' => getenv('MYSQL_PASSWORD') ?: 'test_password',
                'charset' => 'utf8mb4',
                'options' => [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                ],
            ],
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => getenv('PGSQL_HOST') ?: '127.0.0.1',
                'port' => (int) (getenv('PGSQL_PORT') ?: 5432),
                'database' => getenv('PGSQL_DATABASE') ?: 'test_db',
                'username' => getenv('PGSQL_USERNAME') ?: 'postgres',
                'password' => getenv('PGSQL_PASSWORD') ?: 'postgres',
                'charset' => 'utf8',
                'options' => [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                ],
            ],
            'sqlite' => [
                'driver' => 'sqlite',
                'database' => getenv('SQLITE_DATABASE') ?: ':memory:',
                'options' => [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ],
            ],
            'sqlsrv' => [
                'driver' => 'sqlsrv',
                'host' => getenv('SQLSRV_HOST') ?: '127.0.0.1',
                'port' => (int) (getenv('SQLSRV_PORT') ?: 1433),
                'database' => getenv('SQLSRV_DATABASE') ?: 'test_db',
                'username' => getenv('SQLSRV_USERNAME') ?: 'sa',
                'password' => getenv('SQLSRV_PASSWORD') ?: 'Testpassword123@',
                'options' => [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ],
            ],
            default => throw new \InvalidArgumentException("Unsupported driver: {$driver}"),
        };
    }

    public static function initializeDatabase(): void
    {
        DB::rawExecute("SELECT 1")->await();
    }

    public static function createSchemaBuilder(): SchemaBuilder
    {
        return new SchemaBuilder();
    }

    public static function cleanupTables(SchemaBuilder $schema): void
    {
        foreach (self::$testTables as $table) {
            try {
                $schema->dropIfExists($table)->await();
            } catch (\Exception $e) {
                // Ignore if table doesn't exist
            }
        }
    }

    public static function getTestTables(): array
    {
        return self::$testTables;
    }
}