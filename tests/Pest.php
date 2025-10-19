<?php

use Hibla\PdoQueryBuilder\DB;
use Tests\Helpers\SchemaTestHelper;

pest()->extend(Tests\TestCase::class)->in('Feature');
pest()->extend(Tests\TestCase::class)->in('Stress');
pest()->extend(Tests\TestCase::class)->in('Unit');
pest()->extend(Tests\TestCase::class)->in('Integration');

function schema()
{
    return SchemaTestHelper::createSchemaBuilder();
}

function initializeSchemaForSqlite()
{
    SchemaTestHelper::initializeDatabaseForDriver('sqlite');
    SchemaTestHelper::cleanupTables(schema());
}

function initializeSchemaForMysql()
{
    SchemaTestHelper::initializeDatabaseForDriver('mysql');
    SchemaTestHelper::cleanupTables(schema());
}

function initializeSchemaForPostgres()
{
    SchemaTestHelper::initializeDatabaseForDriver('pgsql');
    SchemaTestHelper::cleanupTables(schema());
}

function initializeSchemaForSqlServer()
{
    SchemaTestHelper::initializeDatabaseForDriver('sqlsrv');
    SchemaTestHelper::cleanupTables(schema());
}

function cleanupSchema()
{
    SchemaTestHelper::cleanupTables(schema());
    DB::reset();
}
