<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;

beforeEach(function () {
    skipIfPhp84OrHigher();
    initializeSchemaForSqlserver();
});

afterEach(function () {
    cleanupSchema('sqlsrv');
});

describe('Stress Tests', function () {
    it('handles rapid table creation and deletion', function () {
        for ($i = 1; $i <= 5; $i++) {
            $tableName = "test_table_{$i}";

            schema('sqlsrv')->create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('name');
            })->await();

            $exists = schema('sqlsrv')->hasTable($tableName)->await();
            expect($exists)->toBeTruthy();

            schema('sqlsrv')->drop($tableName)->await();

            $exists = schema('sqlsrv')->hasTable($tableName)->await();
            expect($exists)->toBeFalsy();
        }
    });

    it('handles multiple alterations in sequence', function () {
        schema('sqlsrv')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema('sqlsrv')->table('users', function (Blueprint $table) {
            $table->string('email')->nullable();
        })->await();

        schema('sqlsrv')->table('users', function (Blueprint $table) {
            $table->index('email');
        })->await();

        schema('sqlsrv')->table('users', function (Blueprint $table) {
            $table->renameColumn('name', 'full_name');
        })->await();

        schema('sqlsrv')->table('users', function (Blueprint $table) {
            $table->integer('age')->default(0);
        })->await();

        $exists = schema('sqlsrv')->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });
});
