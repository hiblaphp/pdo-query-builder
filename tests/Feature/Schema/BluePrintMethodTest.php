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

describe('Blueprint Methods', function () {
    it('gets blueprint properties correctly', function () {
        $blueprint = new Blueprint('test_table');
        
        expect($blueprint->getTable())->toBe('test_table');
        expect($blueprint->getEngine())->toBe('InnoDB');
        expect($blueprint->getCharset())->toBe('utf8mb4');
        expect($blueprint->getCollation())->toBe('utf8mb4_unicode_ci');
    });

    it('sets blueprint engine correctly', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->engine('MyISAM');
        })->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });

    it('sets blueprint charset and collation correctly', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->charset('utf8');
            $table->collation('utf8_general_ci');
        })->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });
});