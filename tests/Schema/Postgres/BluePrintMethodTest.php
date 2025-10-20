<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForPostgres();
});

afterEach(function () {
    cleanupSchema('pgsql');
});

describe('Blueprint Methods', function () {
    it('gets blueprint properties correctly', function () {
        $blueprint = new Blueprint('test_table');

        expect($blueprint->getTable())->toBe('test_table');
        expect($blueprint->getEngine())->toBe('InnoDB');
        expect($blueprint->getCharset())->toBe('utf8mb4');
        expect($blueprint->getCollation())->toBe('utf8mb4_unicode_ci');
    });

    it('sets blueprint charset and collation correctly', function () {
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
