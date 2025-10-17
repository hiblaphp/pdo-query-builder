<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Tests\Helpers\SchemaTestHelper;

beforeEach(function () {
    SchemaTestHelper::initializeDatabase();
    $this->schema = SchemaTestHelper::createSchemaBuilder();
    SchemaTestHelper::cleanupTables($this->schema);
});

afterEach(function () {
    SchemaTestHelper::cleanupTables($this->schema);
});

describe('Edge Cases', function () {
    it('handles empty table creation', function () {
        $this->schema->create('empty_table', function (Blueprint $table) {
            $table->id();
        })->await();

        $exists = $this->schema->hasTable('empty_table')->await();
        expect($exists)->toBeTrue();
        
        $this->schema->dropIfExists('empty_table')->await();
    });

    it('handles table with many columns', function () {
        $this->schema->create('wide_table', function (Blueprint $table) {
            $table->id();
            for ($i = 1; $i <= 20; $i++) {
                $table->string("column_{$i}")->nullable();
            }
        })->await();

        $exists = $this->schema->hasTable('wide_table')->await();
        expect($exists)->toBeTrue();
        
        $this->schema->dropIfExists('wide_table')->await();
    });

    it('handles dropping non-existent table gracefully', function () {
        $result = $this->schema->dropIfExists('this_table_does_not_exist')->await();
        expect($result)->not->toThrow(Exception::class);
    });
});