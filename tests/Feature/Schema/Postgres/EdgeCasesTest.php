<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Tests\Helpers\SchemaTestHelper;

beforeEach(function () {
    initializeSchemaForPostgres();
});

afterEach(function () {
    cleanupSchema();
});


describe('Edge Cases', function () {
    it('handles empty table creation', function () {
        schema()->create('empty_table', function (Blueprint $table) {
            $table->id();
        })->await();

        $exists = schema()->hasTable('empty_table')->await();
        expect($exists)->toBeTruthy();
        
        schema()->dropIfExists('empty_table')->await();
    });

    it('handles table with many columns', function () {
        schema()->create('wide_table', function (Blueprint $table) {
            $table->id();
            for ($i = 1; $i <= 20; $i++) {
                $table->string("column_{$i}")->nullable();
            }
        })->await();

        $exists = schema()->hasTable('wide_table')->await();
        expect($exists)->toBeTruthy();
        
        schema()->dropIfExists('wide_table')->await();
    });

    it('handles dropping non-existent table gracefully', function () {
        $result = schema()->dropIfExists('this_table_does_not_exist')->await();
        expect($result)->not->toThrow(Exception::class);
    });
});