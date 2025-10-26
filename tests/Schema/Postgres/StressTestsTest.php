<?php

use Hibla\QueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForPostgres();
});

afterEach(function () {
    cleanupSchema('pgsql');
});

describe('Stress Tests', function () {
    it('handles rapid table creation and deletion', function () {
        for ($i = 1; $i <= 5; $i++) {
            $tableName = "test_table_{$i}";

            schema('pgsql')->create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('name');
            })->await();

            $exists = schema('pgsql')->hasTable($tableName)->await();
            expect($exists)->toBeTruthy();

            schema('pgsql')->drop($tableName)->await();

            $exists = schema('pgsql')->hasTable($tableName)->await();
            expect($exists)->toBeFalsy();
        }
    });

    it('handles multiple alterations in sequence', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema('pgsql')->table('users', function (Blueprint $table) {
            $table->string('email')->nullable();
        })->await();

        schema('pgsql')->table('users', function (Blueprint $table) {
            $table->index('email');
        })->await();

        schema('pgsql')->table('users', function (Blueprint $table) {
            $table->renameColumn('name', 'full_name');
        })->await();

        schema('pgsql')->table('users', function (Blueprint $table) {
            $table->integer('age')->default(0);
        })->await();

        $exists = schema('pgsql')->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });
});
