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