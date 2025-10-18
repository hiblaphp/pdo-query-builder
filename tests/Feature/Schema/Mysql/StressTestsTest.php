<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Tests\Helpers\SchemaTestHelper;

beforeEach(function () {
    SchemaTestHelper::initializeDatabase();
    SchemaTestHelper::cleanupTables(schema());
});

afterEach(function () {
    SchemaTestHelper::cleanupTables(schema());
});

describe('Stress Tests', function () {
    it('handles rapid table creation and deletion', function () {
        for ($i = 1; $i <= 5; $i++) {
            $tableName = "test_table_{$i}";
            
            schema()->create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('name');
            })->await();

            $exists = schema()->hasTable($tableName)->await();
            expect($exists)->toBeTruthy();

            schema()->drop($tableName)->await();

            $exists = schema()->hasTable($tableName)->await();
            expect($exists)->toBeFalsy();
        }
    });

    it('handles multiple alterations in sequence', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema()->table('users', function (Blueprint $table) {
            $table->string('email')->nullable();
        })->await();

        schema()->table('users', function (Blueprint $table) {
            $table->index('email');
        })->await();

        schema()->table('users', function (Blueprint $table) {
            $table->renameColumn('name', 'full_name');
        })->await();

        schema()->table('users', function (Blueprint $table) {
            $table->integer('age')->default(0);
        })->await();

        $exists = schema()->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });
});