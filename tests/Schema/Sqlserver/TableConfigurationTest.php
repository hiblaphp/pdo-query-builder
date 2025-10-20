<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;

beforeEach(function () {
    skipIfPhp84OrHigher();
    initializeSchemaForSqlserver();
});

afterEach(function () {
    cleanupSchema('sqlsrv');
});

describe('Table Configuration', function () {
    it('creates table with custom engine', function () {
        schema('sqlsrv')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->engine('MyISAM');
        })->await();

        $exists = schema('sqlsrv')->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates table with custom charset and collation', function () {
        schema('sqlsrv')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->charset('utf8');
            $table->collation('utf8_general_ci');
        })->await();

        $exists = schema('sqlsrv')->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });
});
