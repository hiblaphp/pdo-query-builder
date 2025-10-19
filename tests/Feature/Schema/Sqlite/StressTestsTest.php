<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForSqlite();
});

afterEach(function () {
    cleanupSchema('sqlite');
});


describe('Stress Tests', function () {
    it('handles rapid table creation and deletion', function () {
        for ($i = 1; $i <= 5; $i++) {
            $tableName = "test_table_{$i}";

            schema('sqlite')->create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('name');
            })->await();

            $exists = schema('sqlite')->hasTable($tableName)->await();
            expect($exists)->toBeTruthy();

            schema('sqlite')->drop($tableName)->await();

            $exists = schema('sqlite')->hasTable($tableName)->await();
            expect($exists)->toBeFalsy();
        }
    });

    it('handles multiple alterations in sequence', function () {
        schema('sqlite')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema('sqlite')->table('users', function (Blueprint $table) {
            $table->string('email')->nullable();
        })->await();

        schema('sqlite')->table('users', function (Blueprint $table) {
            $table->index('email');
        })->await();

        schema('sqlite')->table('users', function (Blueprint $table) {
            $table->renameColumn('name', 'full_name');
        })->await();

        schema('sqlite')->table('users', function (Blueprint $table) {
            $table->integer('age')->default(0);
        })->await();

        $exists = schema('sqlite')->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });
});
