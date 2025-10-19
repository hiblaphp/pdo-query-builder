<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForSqlite();
});

afterEach(function () {
    cleanupSchema('sqlite');
});


describe('Table Operations', function () {
    it('drops a table', function () {
        schema('sqlite')->create('temp_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema('sqlite')->drop('temp_table')->await();

        $exists = schema('sqlite')->hasTable('temp_table')->await();
        expect($exists)->toBeFalsy();
    });

    it('drops a table if exists', function () {
        schema('sqlite')->dropIfExists('nonexistent_table')->await();

        $exists = schema('sqlite')->hasTable('nonexistent_table')->await();
        expect($exists)->toBeFalsy();
    });

    it('renames a table', function () {
        schema('sqlite')->create('old_name', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema('sqlite')->rename('old_name', 'new_name')->await();

        $oldExists = schema('sqlite')->hasTable('old_name')->await();
        $newExists = schema('sqlite')->hasTable('new_name')->await();

        expect($oldExists)->toBeFalsy();
        expect($newExists)->toBeTruthy();

        schema('sqlite')->dropIfExists('new_name')->await();
    });

    it('checks if table exists', function () {
        $exists = schema('sqlite')->hasTable('nonexistent')->await();
        expect($exists)->toBeFalsy();

        schema('sqlite')->create('users', function (Blueprint $table) {
            $table->id();
        })->await();

        $exists = schema('sqlite')->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });
});
