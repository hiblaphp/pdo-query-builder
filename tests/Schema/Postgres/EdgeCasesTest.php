<?php

use Hibla\QueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForPostgres();
});

afterEach(function () {
    cleanupSchema('pgsql');
});

describe('Edge Cases', function () {
    it('handles empty table creation', function () {
        schema('pgsql')->create('empty_table', function (Blueprint $table) {
            $table->id();
        })->await();

        $exists = schema('pgsql')->hasTable('empty_table')->await();
        expect($exists)->toBeTruthy();

        schema('pgsql')->dropIfExists('empty_table')->await();
    });

    it('handles table with many columns', function () {
        schema('pgsql')->create('wide_table', function (Blueprint $table) {
            $table->id();
            for ($i = 1; $i <= 20; $i++) {
                $table->string("column_{$i}")->nullable();
            }
        })->await();

        $exists = schema('pgsql')->hasTable('wide_table')->await();
        expect($exists)->toBeTruthy();

        schema('pgsql')->dropIfExists('wide_table')->await();
    });

    it('handles dropping non-existent table gracefully', function () {
        $result = schema('pgsql')->dropIfExists('this_table_does_not_exist')->await();
        expect($result)->not->toThrow(Exception::class);
    });
});
