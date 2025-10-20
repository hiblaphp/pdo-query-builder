<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForPostgres();
});

afterEach(function () {
    cleanupSchema('mysql');
});

describe('Table Operations', function () {
    it('drops a table', function () {
        schema('pgsql')->create('temp_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema('pgsql')->drop('temp_table')->await();

        $exists = schema('pgsql')->hasTable('temp_table')->await();
        expect($exists)->toBeFalsy();
    });

    it('drops a table if exists', function () {
        schema('pgsql')->dropIfExists('nonexistent_table')->await();

        $exists = schema('pgsql')->hasTable('nonexistent_table')->await();
        expect($exists)->toBeFalsy();
    });

    it('renames a table', function () {
        schema('pgsql')->create('old_name', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema('pgsql')->rename('old_name', 'new_name')->await();

        $oldExists = schema('pgsql')->hasTable('old_name')->await();
        $newExists = schema('pgsql')->hasTable('new_name')->await();

        expect($oldExists)->toBeFalsy();
        expect($newExists)->toBeTruthy();

        schema('pgsql')->dropIfExists('new_name')->await();
    });

    it('checks if table exists', function () {
        $exists = schema('pgsql')->hasTable('nonexistent')->await();
        expect($exists)->toBeFalsy();

        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
        })->await();

        $exists = schema('pgsql')->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });
});
