<?php

use Hibla\QueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForPostgres();
});

afterEach(function () {
    cleanupSchema('pgsql');
});

describe('Table Configuration', function () {
    it('creates table with custom engine', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->engine('MyISAM');
        })->await();

        $exists = schema('pgsql')->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates table with custom charset and collation', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->charset('utf8');
            $table->collation('utf8_general_ci');
        })->await();

        $exists = schema('pgsql')->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });
});
