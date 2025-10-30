<?php

use Hibla\QueryBuilder\DB;
use Tests\Helpers\SchemaTestHelper;
use Tests\Helpers\ConsoleTestHelper;

pest()->extend(Tests\TestCase::class)->in('Feature');
pest()->extend(Tests\TestCase::class)->in('Stress');
pest()->extend(Tests\TestCase::class)->in('Unit');
pest()->extend(Tests\TestCase::class)->in('Integration');

function schema(?string $driver = null)
{
    return SchemaTestHelper::createSchemaBuilder($driver);
}

function initializeSchemaForSqlite()
{
    SchemaTestHelper::initializeDatabaseForDriver('sqlite');
    SchemaTestHelper::cleanupTables(schema('sqlite'));
}

function initializeSchemaForMysql()
{
    SchemaTestHelper::initializeDatabaseForDriver('mysql');
    SchemaTestHelper::cleanupTables(schema());
}

function initializeSchemaForPostgres()
{
    SchemaTestHelper::initializeDatabaseForDriver('pgsql');
    SchemaTestHelper::cleanupTables(schema('pgsql'));
}

function initializeSchemaForSqlServer()
{
    SchemaTestHelper::initializeDatabaseForDriver('sqlsrv');
    SchemaTestHelper::cleanupTables(schema('sqlsrv'));
}

function cleanupSchema(?string $driver = null)
{
    SchemaTestHelper::cleanupTables(schema($driver));
    DB::reset();
}

function skipIfPhp84OrHigher(): void
{
    if (version_compare(PHP_VERSION, '8.4.0', '>=')) {
        test()->markTestSkipped('SQL Server driver not available for PHP 8.4+');
    }
}

function skipIfWindows(): void
{
    if (DIRECTORY_SEPARATOR !== '/') {
        test()->markTestSkipped('Test only runs on Unix systems');
    }
}

function skipIfUnix(): void
{
    if (DIRECTORY_SEPARATOR === '/') {
        test()->markTestSkipped('Test only runs on Windows systems');
    }
}