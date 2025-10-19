<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Tests\Helpers\SchemaTestHelper;

beforeEach(function () {
    initializeSchemaForSqlserver();
});

afterEach(function () {
    cleanupSchema('sqlsrv');
});


describe('Edge Cases', function () {
    it('handles empty table creation', function () {
        schema('sqlsrv')->create('empty_table', function (Blueprint $table) {
            $table->id();
        })->await();

        $exists =   schema('sqlsrv')->hasTable('empty_table')->await();
        expect($exists)->toBeTruthy();

        schema('sqlsrv')->dropIfExists('empty_table')->await();
    });

    it('handles table with many columns', function () {
        schema('sqlsrv')->create('wide_table', function (Blueprint $table) {
            $table->id();
            for ($i = 1; $i <= 20; $i++) {
                $table->string("column_{$i}")->nullable();
            }
        })->await();

        $exists =   schema('sqlsrv')->hasTable('wide_table')->await();
        expect($exists)->toBeTruthy();

        schema('sqlsrv')->dropIfExists('wide_table')->await();
    });

    it('handles dropping non-existent table gracefully', function () {
        $result =   schema('sqlsrv')->dropIfExists('this_table_does_not_exist')->await();
        expect($result)->not->toThrow(Exception::class);
    });
});
