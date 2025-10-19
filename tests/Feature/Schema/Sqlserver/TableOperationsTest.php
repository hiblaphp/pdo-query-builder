<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Tests\Helpers\SchemaTestHelper;

beforeEach(function () {
    skipIfPhp84OrHigher();
    initializeSchemaForSqlserver();
});

afterEach(function () {
    cleanupSchema('sqlsrv');
});


describe('Table Operations', function () {
    it('drops a table', function () {
        schema('sqlsrv')->create('temp_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema('sqlsrv')->drop('temp_table')->await();

        $exists = schema('sqlsrv')->hasTable('temp_table')->await();
        expect($exists)->toBeFalsy();
    });

    it('drops a table if exists', function () {
        schema('sqlsrv')->dropIfExists('nonexistent_table')->await();

        $exists = schema('sqlsrv')->hasTable('nonexistent_table')->await();
        expect($exists)->toBeFalsy();
    });

    it('renames a table', function () {
        schema('sqlsrv')->create('old_name', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema('sqlsrv')->rename('old_name', 'new_name')->await();

        $oldExists = schema('sqlsrv')->hasTable('old_name')->await();
        $newExists = schema('sqlsrv')->hasTable('new_name')->await();

        expect($oldExists)->toBeFalsy();
        expect($newExists)->toBeTruthy();

        schema('sqlsrv')->dropIfExists('new_name')->await();
    });

    it('checks if table exists', function () {
        $exists = schema('sqlsrv')->hasTable('nonexistent')->await();
        expect($exists)->toBeFalsy();

        schema('sqlsrv')->create('users', function (Blueprint $table) {
            $table->id();
        })->await();

        $exists = schema('sqlsrv')->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });
});
