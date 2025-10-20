<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForMysql();
});

afterEach(function () {
    cleanupSchema();
});

describe('Table Operations', function () {
    it('drops a table', function () {
        schema()->create('temp_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema()->drop('temp_table')->await();

        $exists = schema()->hasTable('temp_table')->await();
        expect($exists)->toBeFalsy();
    });

    it('drops a table if exists', function () {
        schema()->dropIfExists('nonexistent_table')->await();

        $exists = schema()->hasTable('nonexistent_table')->await();
        expect($exists)->toBeFalsy();
    });

    it('renames a table', function () {
        schema()->create('old_name', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema()->rename('old_name', 'new_name')->await();

        $oldExists = schema()->hasTable('old_name')->await();
        $newExists = schema()->hasTable('new_name')->await();

        expect($oldExists)->toBeFalsy();
        expect($newExists)->toBeTruthy();

        schema()->dropIfExists('new_name')->await();
    });

    it('checks if table exists', function () {
        $exists = schema()->hasTable('nonexistent')->await();
        expect($exists)->toBeFalsy();

        schema()->create('users', function (Blueprint $table) {
            $table->id();
        })->await();

        $exists = schema()->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });
});
